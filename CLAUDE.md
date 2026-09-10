# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this app is

**AI Notula App** — an AI meeting workspace for a single company (multi-*unit*, not multi-tenant SaaS).
Upload a meeting recording / transcript / photo of notes → AI produces a transcript, an HTML
summary, structured action items, follow-up email drafts, and a per-meeting Q&A chat. Action
items become Tasks with a Kanban board, approval workflow, SLA, disposition, and escalation.
Plus a per-meeting discussion forum, activity timeline, dashboard/analytics, and an admin area
(units, users, settings, audit log, outgoing webhooks, SSO).

Stack: Laravel 12 · Inertia.js 1 + Vue 3 (`resources/js/Pages/*`) · Tailwind 3 · MySQL (prod) ·
Redis (optional) · Sanctum · `spatie/laravel-permission` v6 · Socialite (Google/Microsoft).

## Commands

```bash
# Backend
php artisan serve                      # dev server
php artisan queue:work                 # REQUIRED for AI processing, reminders, webhooks, mail
php artisan schedule:work              # REQUIRED for reminders/escalation + the stuck-meeting watchdog
php artisan migrate --seed             # DatabaseSeeder seeds roles, a superadmin, units, demo meetings

# Frontend
npm run dev                            # Vite dev server
npm run build                          # MUST run after adding/renaming any Vue page — the committed
                                       # public/build/manifest.json will 500 pages missing from it,
                                       # and there is no CI step that rebuilds it

# Tests (SQLite :memory:, config in phpunit.xml)
php artisan test                       # full suite (~2 min, ~177 tests)
php artisan test --filter=ProcessMeetingTest
php artisan test tests/Feature/Task/TaskApprovalTest.php

# Local Speech-to-Text sidecar (separate process, needed only for audio uploads)
python stt_service.py                  # Flask app on :5055, loads openai-whisper "base" model
                                       # STT_SERVICE_URL in .env must point at it

# Quality gates (all run in CI, backend job)
vendor/bin/pint                        # format (Laravel preset, pint.json)
vendor/bin/pint --test                 # CI check — fails on unformatted code
vendor/bin/phpstan analyse             # level 5; 57 pre-existing errors are in phpstan-baseline.neon
composer audit                         # fails on dependencies with known CVEs
composer test:ci                       # runs all four gates in sequence
```

The Docker Compose path (`docker compose up -d --build`, then `docker compose exec app php artisan migrate --seed`) runs app/nginx/mysql/redis/queue/scheduler together on port 8080. It has **not** been verified with a real build — treat it as unproven. It does **not** include the Whisper STT sidecar.

## Architecture

### AI provider layer (`app/Services/AI/`)
The core abstraction. Three capability contracts — `TextGenerationProvider`, `TranscriptionProvider`,
`OcrProvider` — each with swappable driver implementations under `Providers/`. `AiManager` resolves
the **active** provider per capability from `Setting::current()` (admin Settings page), falling back
to `config/ai.php` defaults (`AI_TEXT_PROVIDER` etc.). Drivers return DTOs (`AiTextResult`,
`AiTranscriptionResult`).

**To add a provider:** implement the relevant contract in `Providers/`, register a name→`{driver, model}`
entry in `config/ai.php` under `providers`. No controller/job changes needed.

Current drivers: `gemini` (default text/OCR, `gemini-2.0-flash`), `openrouter` (fallback text,
free-tier model), `whisper_local` (the Flask sidecar).

**Rate limiting:** every LLM-calling route (`meetings.chat.store`, `meetings.emails.generate|send`,
`meetings.action-items.regenerate`, `meetings.process`, `dashboard.insight`, `/stt/test`) carries
`throttle:ai`. The `ai` limiter (`AppServiceProvider::boot`, tunable via `config('ai.rate_limits')` /
`AI_RATE_*`) stacks per-minute + per-day-per-user + per-day-per-unit limits. A 429 is rendered
(`bootstrap/app.php` `withExceptions`) as a localized `{error: ...}` JSON for XHR or `back()->with('error')`
for Inertia POSTs.

**Fallback chain:** `AiManager::text()/transcription()/ocr()` (called with no argument) return a
`Fallback*Provider` wrapper that walks `[active provider, ...config('ai.fallbacks.<capability>')]`
in order, returning the first success. `text` defaults to falling back to `openrouter`
(`AI_TEXT_FALLBACKS`); STT/OCR have none configured. Only a chain with 2+ providers raises the
aggregate `AiProviderChainException` — a single-provider chain rethrows the driver's own exception
untouched. Result DTOs carry `provider`/`model`, so `ai_request_logs` records whichever provider
actually served the request. Passing an explicit name (`->text('gemini')`) skips the chain.

### Meeting processing pipeline
`MeetingController::process()` → dispatches `ProcessMeetingNotula` job → `MeetingProcessingService::process()`:
1. `extractTranscript()` branches on file extension: audio → transcription provider, `txt`/`md` → read as-is, image → OCR provider.
2. `summarize()` — one text-provider call; the prompt asks the model to return **HTML**. It is passed through `App\Support\HtmlSanitizer::clean()` (HTMLPurifier `ai_html` profile) before being stored in `meetings.summary`, then rendered with `v-html` / `{!! !!}`. Same sanitizer guards the free-text `meetings.agenda` (`MeetingController`), the AI email draft body (`EmailDraftGenerator`), and the user-edited send body (`MeetingEmailController`). Forum comment bodies are safe a different way — `renderBody()` in Vue HTML-escapes then only wraps `@Mention` spans.
3. `generateActionItems()` — second text call returning a JSON array, parsed by `ActionItemsParser` (unit-tested against code-fence / prose-wrapped / missing-title LLM quirks). **Best-effort**: a failure here never fails the meeting.
4. Logs an Activity, dispatches the `meeting.processed` webhook, emails everyone in the unit.

Every AI call is recorded via `AiRequestLogger` into `ai_request_logs` (the `cost` column is always null — no pricing data wired).

**Reliability notes (see also memory `project-ai-reliability-fixes`):** the job's `$timeout` relies on
`pcntl`, which does not exist on Windows (the dev/deploy OS), so it is a no-op there. The real safety
net is `php artisan meetings:fail-stuck` (`FailStuckMeetings` command, scheduled every 5 min) which
force-fails any meeting stuck in `Memproses` past a threshold — this needs `schedule:work` running.
HTTP clients in `OpenRouterTextProvider` / Gemini providers have explicit timeouts; `config/gemini.php`
must exist (a config-cache clear that drops it silently breaks Gemini).

Meeting status values (Indonesian, stored as strings): `Dijadwalkan` → `Memproses` → `Selesai Diproses` / `Gagal`. `Meetings/Show.vue` shows a static spinner during `Memproses` — there is no polling or broadcasting, the user must refresh manually.

### Auth & multi-unit scoping
Three roles only: `user`, `admin`, `superadmin` (`spatie/laravel-permission`, seeded by
`RolePermissionSeeder` — call `$this->seed(RolePermissionSeeder::class)` before `assignRole()`/`syncRoles()`
in tests or roles won't exist). Admin panel routes are gated by the `access-admin-panel` Gate, which
currently allows **superadmin only** (defined in `AuthServiceProvider::boot`).

Unit scoping is enforced by the `ScopedToUnit` trait's local scope `Model::visibleTo($user)` (superadmin
sees all, everyone else sees their `unit_id`) — used on `Meeting`, `Task`, `User` in every list query.
Deliberately a **local** scope, not a global one: a global scope would turn cross-unit single-record
access into a 404 before the Policy's 403 runs. Single-record authorization goes through Policies
(`app/Policies/`).

### Task approval workflow
`Done` status is unreachable through the normal status dropdown — `TaskController::updateStatus()`
rejects it. Path: assignee submits for review (requires evidence) → `approval_requested` → an
admin/superadmin **in the same unit** approves (`approved`, self-approval blocked by policy) or rejects
back to `In Progress` with a reason. `is_overdue` / `is_sla_breached` are Eloquent accessors
(`$appends`), not columns — never true for Done/Cancelled.

### Collaboration
Forum (`ForumComment`, one level of nesting) lives on `Meetings/Show.vue` **outside** the
"processing done" conditional — discussion is allowed on scheduled/failed meetings too. Comment
bodies are stored HTML-escaped then rendered with `v-html` so `@Mention` spans (matched server-side
against the unit roster by `MentionParser`) can be wrapped. Deleting a comment also deletes its
attachments' physical files (and its replies' files).

`ActivityLogger` (`app/Services/Meeting/`) is a thin direct-call logger (no events/listeners) writing
the user-facing `activities` feed. Separate from `AuditLogger` (`app/Services/Audit/`) which writes the
admin-only security `audit_logs` (CRUD on User/Unit/Setting/ApiToken/Webhook; login/logout via an
`AuditAuthEvents` listener so SSO is covered for free).

### Scheduled work (`app/Console/Kernel.php`)
`SendMeetingReminders` (daily 07:00), `SendTaskDeadlineReminders` (daily 07:30), `EscalateOverdueTasks`
(hourly — emails the task *creator*, there is no manager role), `meetings:fail-stuck` (every 5 min).
Timezone comes from `Setting::current()->timezone`.

### Webhooks
Per-unit outgoing webhooks (`WebhookDispatcher::dispatch()` called from the same sites as
`ActivityLogger`), events `task.created` / `task.status_changed` / `task.approved` /
`meeting.processed`. Delivered async by `SendWebhookNotification`, HMAC-SHA256 signed in
`X-Notula-Signature`.

## Conventions & gotchas

- **Route ordering:** literal task routes (`/tasks/kanban`, `/tasks/calendar`, `/tasks/export/*`, `/tasks/create`) must stay registered before `/tasks/{task}` or the wildcard swallows them. Same for meetings.
- **Inertia flash:** `HandleInertiaRequests` shares a `flash` prop; use `redirect()->...->with('success'|'error', ...)` and read `$page.props.flash` in Vue.
- **Legacy skeleton:** this app was bumped 10→11→12 via `composer.json` only. It still uses `app/Http/Kernel.php`, `app/Console/Kernel.php`, the old `bootstrap/app.php`, the legacy `AuthServiceProvider` `$policies` array, and old `.env` keys (`CACHE_DRIVER`, `BROADCAST_DRIVER`, `QUEUE_CONNECTION`). Works via backcompat; new L11/12 skeleton features are not available.
- **`openai-php/laravel`** is used only to talk to **OpenRouter** (`OPENAI_BASE_URI`), not OpenAI. `OpenRouterTextProvider` has a Guzzle middleware that backfills missing `completion_tokens_details` fields OpenRouter omits, which would otherwise `TypeError` in the client.
- **Excel export tests:** assert on the collection via `Excel::fake()` + `Excel::assertDownloaded(...)`, not on raw bytes.
- **Tests run on SQLite**, prod is MySQL — watch for engine differences (`whereJsonContains`, fulltext, etc.).
- **Dead stubs:** `SourcePath` model/migration/seeder are unused. `whisper-api/` and `venv/` are empty local dirs (gitignored). `routes/api.php` is effectively empty despite API tokens being a feature.
- Meeting search (`MeetingController::index`) uses `LIKE %...%` on `transcript`/`summary` TEXT columns — won't scale.

## Following the project's task workflow

This repo follows a strict loop (see memory `feedback-workflow-and-decisions`): analyze → propose a
plan naming the files to touch → implement one coherent unit → **git commit as a checkpoint** →
summarize → wait for approval before the next task. Prefer removing a broken flow that duplicates an
already-correct path over patching it. For broad feature terms (multi-tenant, SSO, RBAC…), ask which
reading applies before building the generic textbook version.
