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
UI text, flash messages, and meeting status values are in **Indonesian**.

**This file is the onboarding doc — read it instead of re-surveying the repo.** The codebase map
and architecture below are kept current; if you change something they describe, update this file
in the same commit.

## Codebase map

| Where | What |
|---|---|
| `bootstrap/app.php` | L12 `Application::configure()`: routing, middleware (web group + `HandleInertiaRequests`), 429 rendering, **the schedule** |
| `bootstrap/providers.php` | registers `App`, `Ai`, `Auth` (policies + `access-admin-panel` Gate), `Event` providers (`BroadcastServiceProvider` exists but is not registered) |
| `routes/web.php` | all app routes (Inertia); `routes/auth.php` Breeze + SSO; `routes/api.php` REST API v1 (Sanctum tokens, documented in `docs/API.md`) |
| `app/Http/Controllers/` | `Meeting*` (CRUD, process, chat, emails), `Task*` (CRUD, status/approval, disposition, export), `ForumComment*`, `Dashboard`/`Analytics`, admin: `Unit`/`User`/`Setting`/`AuditLog`/`Webhook`/`ApiToken`, `SpeechController` (STT test) |
| `app/Services/AI/` | provider abstraction — `AiManager`, `Contracts/`, `Providers/`, `DTO/`, `AiRequestLogger` |
| `app/Services/Meeting/` | `MeetingProcessingService` (pipeline), `ActionItemsParser`, `EmailDraftGenerator`/`Parser`, `MeetingChatService` (+ `TranscriptRetriever`: keyword-scored, timestamp-labelled excerpts so chat on a 2-hour meeting sees the relevant part, not just the first minutes), `ActivityLogger` |
| `app/Http/Controllers/Api/V1/`, `app/Http/Resources/` | REST API v1 (meetings, transcript submission, tasks) — same policies/unit scoping as web |
| `app/Services/Task/TaskStatusService.php` | status-change rules shared by web + API (selectable statuses, Done-reopen lock, activity + webhook) |
| `app/Services/{Analytics,Audit,Forum,Webhook}/` | `DashboardInsightGenerator`, `AuditLogger`, `MentionParser`, `WebhookDispatcher` |
| `app/Jobs/` | `ProcessMeetingNotula` → `TranscribeMeetingSegment` (×N) → `FinalizeMeetingNotula`; `SendWebhookNotification`, 3 scheduled jobs (reminders, escalation) |
| `RecordingUploadController` + `Meetings/Partials/RecordingUploader.vue` | resumable chunked recording upload (see pipeline) |
| `app/Services/Audio/AudioSplitter.php` | ffmpeg wrapper: `split()` recording → fixed-length mono mp3 segments; `findSilence()` / `extract()` / `concat()` for live recording |
| `MinutesService` + `MeetingMinutesController` + `Meetings/Minutes.vue` | official minutes in the agency's NOTULA format: AI draft → edit → submit → approve/return; documentation photos; PDF (`exports/minutes-pdf.blade.php`) + Word (`MinutesWordExporter`, phpoffice/phpword) |
| `DecisionService` + `DecisionController` + `Decisions/Index.vue` | decisions register (`meeting_decisions`, `/keputusan`), see "Cross-meeting memory" |
| `CrossMeetingQaService` + `CrossMeetingQaController` + `Ask/Index.vue` | "Tanya Lintas Rapat" (`/tanya`): one question over all visible meetings, answer cites `[n]` |
| `LeadershipDashboardController` + `Leadership/Index.vue` | leadership dashboard `/pimpinan` (Gate `view-leadership-dashboard` = pimpinan/superadmin) |
| `MeetingSeriesService` + `Meetings/Partials/SeriesPanel.vue` | meeting series (`meetings.previous_meeting_id`): carried-over follow-ups & decisions |
| `AttendanceService` + `AttendanceController` + `Meetings/Partials/AttendancePanel.vue` + `Attendance/CheckIn.vue` | QR attendance (daftar hadir), public check-in at `/hadir/{token}` |
| `LiveRecordingService` + `LiveRecordingController` + `Meetings/Partials/LiveRecorder.vue` | live recording of in-person meetings with a running transcript (see "Live recording") |
| `app/Console/Commands/FailStuckMeetings.php` | `meetings:fail-stuck` watchdog |
| `app/Models/` | `Meeting`, `MeetingActionItem`, `MeetingChatMessage`, `Task` (+`TaskEvidence*`, `TaskDisposition`), `ForumComment*`, `Activity`, `AuditLog`, `AiRequestLog`, `Setting` (singleton via `Setting::current()`), `Unit`, `User`, `Webhook`; `Concerns/ScopedToUnit` |
| `app/Policies/` | `Meeting`, `Task`, `ForumComment`, `Unit`, `User` |
| `app/Support/HtmlSanitizer.php` | HTMLPurifier wrapper for every raw-rendered HTML field |
| `config/ai.php` | providers, fallbacks, rate limits; `config/gemini.php` must exist |
| `resources/js/Pages/` | `Meetings/`, `Tasks/` (Index, Kanban, Calendar, Show, Create/Edit), `Analytics/` (Heatmap, Overdue, Productivity), `Admin/` (Units, Users, Settings, AuditLogs, Webhooks), `Dashboard.vue`, `Auth/`, `Profile/` |
| `tests/Feature/` | grouped by area: `Admin`, `Analytics`, `Auth`, `Forum`, `Meeting`, `MultiTenant`, `Task`, `Webhook`, plus AI fallback / rate-limit / dashboard tests. `tests/Unit/` covers parsers + sanitizer |
| `database/seeders/` | `RolePermissionSeeder`, `MeetingSeeder`, `DatabaseSeeder` (`SourcePathSeeder` is dead) |
| `docker/`, `docker-compose.yml`, `Dockerfile` | nginx config + Whisper sidecar (`docker/whisper/`) |

## Setup from a fresh clone

```bash
composer install
cp .env.example .env && php artisan key:generate
npm ci && npm run build        # REQUIRED before tests: public/build is gitignored, and every
                               # Inertia page test 500s ("Vite manifest not found") without it
php artisan test               # should be all green
```

**Claude Code cloud sandbox:** the egress proxy returns 403 for GitHub dist zips (`api.github.com/.../zipball`),
but `git clone` works. Use `COMPOSER_ALLOW_SUPERUSER=1 composer install --prefer-source`. The one
package that only ships as a dist, `phpstan/phpstan`, still fails; work around it by cloning
`https://github.com/phpstan/phpstan.git` at the locked tag into the scratchpad, zipping it, temporarily
pointing its `dist.url` in `composer.lock` at `file://<zip>` (empty `shasum`), installing, then
`git checkout composer.lock`. Never commit the modified lock.

## Commands

```bash
# Backend
php artisan serve                      # dev server
php artisan queue:work                 # REQUIRED for AI processing, reminders, webhooks, mail
php artisan schedule:work              # REQUIRED for reminders/escalation + the stuck-meeting watchdog
php artisan migrate --seed             # DatabaseSeeder seeds roles, a superadmin, units, demo meetings

# Frontend
npm run dev                            # Vite dev server
npm run build                          # builds public/build (gitignored, NOT committed). Needed before
                                       # tests and after adding/renaming any Vue page, or Inertia 500s

# Tests (SQLite :memory:, config in phpunit.xml)
php artisan test                       # full suite (~20 s, ~300 tests)
php artisan test --filter=ProcessMeetingTest
php artisan test tests/Feature/Task/TaskApprovalTest.php

# Local Speech-to-Text sidecar (separate process, needed only for audio uploads)
pip install -r docker/whisper/requirements.txt
python docker/whisper/stt_service.py   # Flask app on :5055 (GET /health, POST /transcribe),
                                       # model from WHISPER_MODEL (default "base");
                                       # STT_SERVICE_URL in .env must point at it

# Quality gates — there is NO CI (the GitHub Actions workflow was removed), so run
# `composer test:ci` yourself before every push
vendor/bin/pint                        # format (Laravel preset, pint.json)
vendor/bin/pint --test                 # fails on unformatted code
vendor/bin/phpstan analyse             # level 5; pre-existing errors are in phpstan-baseline.neon
composer audit                         # fails on dependencies with known CVEs
composer test:ci                       # runs all four gates in sequence
```

The Docker Compose path (`docker compose up -d --build`, then `docker compose exec app php artisan migrate --seed`) runs app/nginx/mysql/redis/queue/scheduler/whisper together on port 8080 (Vite assets and `vendor/` are baked into the image). It has **not** been verified with a real build — treat it as unproven.

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
free-tier model), `gemini_stt` (default transcription — audio sent inline, so only for ~10-min segments,
≤14 MB), `whisper_local` (the Flask sidecar, slow on CPU). The active provider stored in the Settings
row wins over `AI_TRANSCRIPTION_PROVIDER`, so existing installs switch STT in Admin → Settings.

**Rate limiting:** every LLM-calling route (`ask.store`, `meetings.chat.store`, `meetings.emails.generate|send`,
`meetings.action-items.regenerate`, `meetings.process`, `dashboard.insight`, `/stt/test`) carries
`throttle:ai`. `/stt/test` (STT smoke test, not used by the UI) is superadmin-only and deletes its upload afterwards. The `ai` limiter (`AppServiceProvider::boot`, tunable via `config('ai.rate_limits')` /
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
**Recordings arrive via resumable chunked upload**, not the `process` form: `RecordingUploader.vue` →
`POST /meetings/{id}/recording-uploads` (init; same meeting+user+name+size returns the existing
upload, so re-picking the file resumes) → `PUT /recording-uploads/{uuid}` with a raw 5 MB body and
`X-Upload-Offset` (a wrong offset gets 409 + the server position; bytes past `received_bytes` are
truncated first, so a half-written chunk never corrupts the file; serialized with a cache lock) →
`POST .../complete` (size check, finfo must say `audio/*`/`video/*`) → moved to
`recordings/{meeting}/{uuid}.ext` on the **private `local` disk** (`meetings.source_disk = local`) →
`MeetingProcessingService::start()`. Raw bodies dodge `upload_max_filesize`; PHP `post_max_size`
must stay above the chunk size (8 MB default is fine). The player streams via the authorized
`meetings.recording` route (Range-capable), never `/storage`. `recordings:prune-uploads` (daily)
removes abandoned partial uploads. Older meetings keep `source_disk = public`.

`MeetingController::process()` / API `POST /api/v1/meetings/{id}/transcript` → `MeetingProcessingService::start()` (only from `Dijadwalkan`/`Gagal`, see `canStart()` — re-processing would duplicate the job, wipe converted action items, and re-email the unit) → dispatches `ProcessMeetingNotula` job → `MeetingProcessingService::process()`:
1. **Audio/video** (`AUDIO_EXTENSIONS`, incl. Zoom `.mp4`): `AudioSplitter` (ffmpeg, `config('ai.audio')`) cuts the recording into ~10-min mono 16 kHz mp3 segments on the private `local` disk (`meeting_segments/{id}/`), one `meeting_segments` row each, and dispatches one `TranscribeMeetingSegment` job per segment. Each segment job transcribes, deletes its audio, touches `processing_heartbeat_at`, and the one that finishes last atomically claims `processing_stage` `transcribing → summarizing` and dispatches `FinalizeMeetingNotula`, which joins segments as `[HH:MM:SS]`-labelled blocks and calls `finalize()`. A segment failing all tries → `markFailed()` (atomic, one activity). Re-processing after `Gagal` deletes old segments and starts over.
   **Text/image:** `extractTranscript()` → `txt`/`md` read as-is, image → OCR provider, then `finalize()` in the same job.
2. `summarize()` — transcripts ≤ `ai.summary.chunk_chars` (40k chars ≈ 1 h) get one text call; longer ones are **map-reduced**: `transcriptChunks()` splits at paragraph/segment boundaries, each chunk becomes plain-text notes, then one call turns the notes into the summary (heartbeat between calls, so no single AI call sees the whole transcript). All AI calls here go through `callText()` (logging). The final prompt asks the model to return **HTML**. It is passed through `App\Support\HtmlSanitizer::clean()` (HTMLPurifier `ai_html` profile) before being stored in `meetings.summary`, then rendered with `v-html` / `{!! !!}`. Same sanitizer guards the free-text `meetings.agenda` (`MeetingController`), the AI email draft body (`EmailDraftGenerator`), and the user-edited send body (`MeetingEmailController`). Forum comment bodies are safe a different way — `renderBody()` in Vue HTML-escapes then only wraps `@Mention` spans.
3. `generateActionItems()` — one text call per transcript chunk returning a JSON array, merged and de-duplicated by title, parsed by `ActionItemsParser` (unit-tested against code-fence / prose-wrapped / missing-title LLM quirks). **Best-effort**: a failure here never fails the meeting.
4. Logs an Activity, dispatches the `meeting.processed` webhook, emails everyone in the unit.

Every AI call is recorded via `AiRequestLogger` into `ai_request_logs` (the `cost` column is always null — no pricing data wired).

**Reliability notes (see also memory `project-ai-reliability-fixes`):** job `$timeout`s (Process 900 s,
segment/finalize 600 s) must stay below the queue's `retry_after` (960 s, `config/queue.php`) or a running
job gets picked up twice. `$timeout` relies on `pcntl`, which does not exist on Windows (the dev/deploy OS),
so it is a no-op there. The real safety net is `php artisan meetings:fail-stuck --minutes=20` (scheduled
every 5 min) which fails a `Memproses` meeting whose **last progress** (`processing_heartbeat_at`, else
`updated_at`) is older than the threshold — a 3-hour recording that keeps advancing is never killed.
Needs `schedule:work` running. **ffmpeg must be installed** on the app/queue host (`FFMPEG_BINARY`;
the Docker image installs it). Tests needing real ffmpeg skip when it's missing — run them with
`FFMPEG_BINARY=/path/to/ffmpeg php artisan test`.
HTTP clients in `OpenRouterTextProvider` / Gemini providers have explicit timeouts; `config/gemini.php`
must exist (a config-cache clear that drops it silently breaks Gemini).

Meeting status values (Indonesian, stored as strings): `Dijadwalkan` → (`Berlangsung`, live only) → `Memproses` → `Selesai Diproses` / `Gagal`. During `Memproses`/`Berlangsung`, `Meetings/Show.vue` polls the lazy `progress` + `live` props (`Meeting::processingProgress()`, `Meeting::liveState()`) every 5 s via partial reload and does a full reload once the status changes.

### Live recording (in-person meetings)
One recorder device per meeting (`live_user_id`) runs `LiveRecorder.vue`: MediaRecorder (webm/opus, mp4 on Safari)
emits a blob every 5 s, sent in order to `PUT /meetings/{id}/live/audio` with `X-Upload-Offset` + `X-Recording-Seconds`
(recorder clock for the current part) and appended to `recordings/{id}/live-{part}.{ext}` (same offset/409/truncate
rules as chunked upload; queued in memory and retried forever while offline). When ≥ `ai.live.window_seconds` (60) of
new audio exist, `CutLiveWindow` (unique per meeting) asks `AudioSplitter::findSilence()` for the first pause between
second 60 and 90 of the window — **so cuts land between utterances, not mid-sentence**; no pause yet → wait, still none
at 90 s → forced cut. Each window becomes a `meeting_segments` row transcribed by the normal `TranscribeMeetingSegment`,
which passes the STT driver a `context` (meeting attendees + last `ai.live.context_lines` lines of the previous segment)
so speaker labels stay consistent. All times are meeting-absolute: `live_part_offset_seconds` maps part-local file time.
- **Stop** → status `Memproses`, stage `closing` (NOT claimable, so a finishing segment can't trigger the summary before
  the tail exists) → `FinishLiveRecording` cuts the tail, sets stage `transcribing`, `claimFinalization()`.
- **Tab closed / reload** → "Lanjutkan Rekaman" (`/live/resume`): tail of the old part is cut, a new part file starts
  (a new MediaRecorder stream can't be appended to the old container). Parts are concatenated into
  `rekaman-lengkap.mp3` at finalize (best-effort). Another member can take over the same way (deliberately low-key UI).
- **Markers** (`meeting_markers`, "Keputusan"/"Tindak Lanjut", any member, timestamped with the server's recorded
  seconds) are injected into the summary and action-item prompts as MUST-include items.
- **Speaker names** (`meetings.speaker_names` JSON, label → name, any member): applied on display and when segments are
  joined; renaming a displayed name updates the mapping; after finalize it rewrites `meetings.transcript` (not the summary).
- **Watchdog:** a `Berlangsung` meeting with no audio for `ai.live.stale_minutes` (15) is auto-stopped and processed as is.
- Microphone access requires HTTPS (or localhost). Browser e2e was verified with Chromium's fake audio device.

### Auth & multi-unit scoping
Four roles (`User::ROLES`): `user`, `admin`, `pimpinan`, `superadmin` (`spatie/laravel-permission`, seeded by
`RolePermissionSeeder`; existing installs get `pimpinan` from migration `2026_09_29_000000` — call
`$this->seed(RolePermissionSeeder::class)` before `assignRole()`/`syncRoles()`
in tests or roles won't exist). Admin panel routes are gated by the `access-admin-panel` Gate, which
currently allows **superadmin only** (defined in `AuthServiceProvider::boot`).

**Pimpinan** (agency leadership) = a normal user in their own unit + **read-only view of every unit**:
`User::seesAllUnits()` (superadmin|pimpinan) drives `visibleTo()` and the `view` policies, while
`update`/`process`/`delete` stay own-unit (or superadmin). Everything that *writes* on a meeting — forum
posts, reactions, per-meeting chat, live markers, attendance, minutes — authorizes `update`, never `view`;
keep it that way or pimpinan gains write access to other units. `Meetings/Show.vue` gets a `can`
prop (`update`/`delete`) and renders read-only (`readonly` on LiveRecorder/ForumSection, `canManage` on
AttendancePanel); task lists use `resources/js/utils/access.js`. A pimpinan may be picked as a minutes
chairperson for any unit and then approves those minutes (`MeetingMinutesController::chairCandidates()`).

Unit scoping is enforced by the `ScopedToUnit` trait's local scope `Model::visibleTo($user)` (superadmin and
pimpinan see all, everyone else sees their `unit_id`) — used on `Meeting`, `Task`, `User` in every list query.
Deliberately a **local** scope, not a global one: a global scope would turn cross-unit single-record
access into a 404 before the Policy's 403 runs. Single-record authorization goes through Policies
(`app/Policies/`).

### Task approval workflow
`Done` status is unreachable through the normal status dropdown — `TaskController::updateStatus()`
rejects it. Path: assignee submits for review (requires evidence) → `approval_requested` → an
admin/superadmin **in the same unit** approves (`approved`; `TaskPolicy::approve` refuses the task's own assignee, so an admin can't approve their own work) or rejects
back to `In Progress` with a reason. Reopening a `Done` task (status dropdown / Kanban drag) is allowed only for `TaskPolicy::manage` — admin/superadmin of that unit. `is_overdue` / `is_sla_breached` are Eloquent accessors
(`$appends`), not columns — never true for Done/Cancelled.

### Official minutes (notula resmi)
Format follows the agency's own NOTULA template (a real sample was provided; it is **not** in the repo — it contains
names and NIPs): centered letterhead (Settings logo + name + multi-line address, thick rule), "NOTULA" + the minutes
title in caps, optional Nomor, identity rows **Hari/Tanggal** ("Senin/28 September 2026"), **Pukul**, **Tempat**,
**Pemimpin Rapat** (prints the chair's *jabatan* when set — `MeetingMinutes::chairpersonLine()`), **Peserta Rapat**,
optional Acara, then **Resume**: ordered bullet points `resume = [{speaker, text, response}]` (speaker line, their
input, `➔` answer), optional "Kesimpulan rapat" (`decisions`) and "Tindak lanjut" (the meeting's **action items** — not
stored in the minutes, so they never diverge from Tasks), the closing sentence ("Demikian yang dapat disampaikan, terima
kasih."), a right-aligned **Notulen** signature with name + `users.nip`, and a **DOKUMENTASI** page with the uploaded
photos (`documentation`, private `local` disk under `minutes/{meeting}/`, max 12). Sans font (DejaVu Sans in PDF, Arial
in Word). `MinutesService::draft()` asks the text AI for `{"resume": [{pembicara, isi, tanggapan}], "kesimpulan": [...]}`
(parsed by `MinutesDraftParser`; empty resume = error) from summary + decision markers + action items + transcript if
≤ 30k chars; redrafting replaces resume/decisions only, never identity fields; Pukul is prefilled from the recording
length when known. Workflow: `draf` → `diajukan` (locked) → `disahkan` (UI says "Disetujui"; locked for good) or
`dikembalikan` (with note). `MeetingPolicy::approveMinutes`: the chair user if set, else a unit admin (external chair);
superadmin always; never the submitter. The template has only the minute-taker's signature, so approval prints as a
small "disetujui melalui aplikasi oleh … pada …" line — an approval record, **not** a certified e-signature (BSrE).
Unapproved exports carry a DRAF watermark/header.

### Attendance (daftar hadir)
`meeting_attendances` (name, position/jabatan, organization/unit-instansi, `method` qr|manual, `checked_in_at`; unique per
user). The meeting page shows a QR (client-side `qrcode` npm, fullscreen for the room's projector, count refreshes every
10 s) encoding `/hadir/{meetings.attendance_token}` — a **public, login-free** route (`throttle:attendance`, 120/min/IP)
because invited guests from other agencies have no account: logged-in employees are recorded once with their unit (even
from another unit — attending grants no access to the meeting), guests type name/jabatan/instansi (same name,
case-insensitive, updates instead of duplicating; the form remembers last input in localStorage). A guest who taps
"Masuk dulu" is sent back to the check-in page after login (`url.intended`). Unit members add/remove entries manually,
close/reopen check-in (manual additions still work when closed), and regenerate the token (old QR → 404). Approving the
minutes closes check-in. Attendees feed the STT speaker `context` and the minutes prompt; the minutes prefill "Peserta
Rapat" with "Sebagaimana daftar hadir terlampir" and PDF/Word append a **DAFTAR HADIR** table page (before DOKUMENTASI).
No signature capture (physical signatures stay on paper if required).

### Cross-meeting memory
- **Series:** a meeting may continue another one in the same unit (`previous_meeting_id`, picked on Create/Edit, API
  field too; "+ Rapat lanjutan" on the meeting page links to `meetings.create?previous=ID`). `MeetingSeriesService`
  walks the chain back (max 10, cycle-safe; validation rejects self/descendant/other-unit). The meeting page shows
  previous/next meetings, **open follow-ups** (action items of earlier meetings whose Task is not Done/Cancelled, or
  not converted yet) and earlier decisions. `promptContext()` feeds the same into the summary prompt (asks for a
  "Perkembangan Tindak Lanjut Rapat Sebelumnya" section) and the minutes draft prompt.
- **Decisions register:** `meeting_decisions` (unit-scoped via `ScopedToUnit`). `DecisionService::extract()` runs one
  best-effort text call after action items at finalize (and after action-item regeneration, which deletes the linked
  items): summary + "Keputusan" markers + numbered action items → `[{keputusan, tindak_lanjut: n|null}]`, so each
  decision links to the action item that carries it out; its `follow_up` accessor derives the status from that item's
  Task (selesai / berjalan / belum jadi Task / tanpa). Approving the official minutes replaces them with the minutes'
  "Kesimpulan rapat" (`source = notula`, links inherited from the most similar AI decision, Jaccard ≥ 0.4); AI never
  overwrites `notula` rows. `/keputusan` searches/filters (text or meeting title, unit for pimpinan/superadmin, meeting
  date range, follow-up status). Old meetings: `php artisan meetings:extract-decisions --limit=50` (one AI call each).
- **Cross-meeting Q&A** (`POST /tanya`, `throttle:ai`, not stored): keyword retrieval, no embeddings. Terms come from
  `TranscriptRetriever::terms()`; SQL `LIKE` prefilter on title/summary/transcript/decision text within
  `visibleTo()` + `Selesai Diproses` (+ optional unit for pimpinan/superadmin, meeting date range), newest 300, scored
  in PHP (title ×3, summary & decisions ×2, +5 if the transcript mentions a term — transcripts are not loaded for
  scoring), top 6 become numbered sources: summary (2.5k chars), decisions with follow-up status, action items with
  Task status, and a `relevantExcerpt()` of the transcript. The model must cite `[n]`; the page escapes the answer
  and only turns `[n]` into links. A question with no usable terms falls back to the 6 newest meetings. Logged as
  `ai_request_logs.type = cross_meeting_qa` (no meeting_id). Won't scale past a few thousand meetings (LIKE scans).
- **Leadership dashboard** (`/pimpinan`): per-unit table for a 30/90/365-day window (meetings by `meetings.date`,
  processed, minutes approved, decisions; open & overdue Tasks are all-time; completion % = Done / non-cancelled Tasks
  *created* in the window), plus the 10 most overdue Tasks, minutes awaiting approval, and latest decisions. Pure
  grouped counts, no AI. Nav: superadmin's admin links live in an "Admin" dropdown (the bar overflowed at 1024 px).

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

### Scheduled work (`bootstrap/app.php` → `withSchedule()`)
`SendMeetingReminders` (daily 07:00), `SendTaskDeadlineReminders` (daily 07:30), `EscalateOverdueTasks`
(hourly — emails the task *creator*, there is no manager role), `meetings:fail-stuck --minutes=20` (every 5 min),
`recordings:prune-uploads` (daily 02:00).
Timezone comes from `Setting::current()->timezone`, wrapped in `rescue()` (falls back to `Asia/Jakarta`)
because `withSchedule()` runs on every artisan call, including ones with no DB.

**App timezone is WIB** (`APP_TIMEZONE`, default `Asia/Jakarta`, `config/app.php`) — timestamps are
stored as WIB wall-clock strings. Until 2026-09 it was UTC; existing installs shift old data once with
`docs/ops/shift-timestamps-utc-to-wib.sql` following `docs/ops/MIGRASI-ZONA-WAKTU-WIB.md` (run by the
server team, never automatically — a mass rewrite of production data). If you add a datetime/timestamp
column before that script has run everywhere, add it to the script too.

### Webhooks
Per-unit outgoing webhooks (`WebhookDispatcher::dispatch()` called from the same sites as
`ActivityLogger`), events `task.created` / `task.status_changed` / `task.approved` /
`meeting.processed`. Delivered async by `SendWebhookNotification`, HMAC-SHA256 signed in
`X-Notula-Signature`.

## Conventions & gotchas

- **Route ordering:** literal task routes (`/tasks/kanban`, `/tasks/calendar`, `/tasks/export/*`, `/tasks/create`) must stay registered before `/tasks/{task}` or the wildcard swallows them. Same for meetings.
- **Inertia flash:** `HandleInertiaRequests` shares a `flash` prop; use `redirect()->...->with('success'|'error', ...)` and read `$page.props.flash` in Vue.
- **Skeleton:** migrated to the L11/12 structure — there is no `app/Http/Kernel.php`, `app/Console/Kernel.php`, `app/Exceptions/Handler.php`, or `RouteServiceProvider`. Middleware, exception rendering, and the schedule live in `bootstrap/app.php`; the `api` RateLimiter is in `AppServiceProvider::boot()`; post-login redirect is the literal `'/dashboard'`. Still legacy: the `AuthServiceProvider` `$policies` array and old `.env` keys (`CACHE_DRIVER`, `BROADCAST_DRIVER`).
- **`openai-php/laravel`** is used only to talk to **OpenRouter** (`OPENAI_BASE_URI`), not OpenAI. `OpenRouterTextProvider` has a Guzzle middleware that backfills missing `completion_tokens_details` fields OpenRouter omits, which would otherwise `TypeError` in the client.
- **Excel export tests:** assert on the collection via `Excel::fake()` + `Excel::assertDownloaded(...)`, not on raw bytes.
- **Tests run on SQLite**, prod is MySQL — watch for engine differences (`whereJsonContains`, fulltext, etc.).
- **Dead stubs:** `SourcePath` model/migration/seeder are unused. `whisper-api/` and `venv/` are empty local dirs (gitignored).
- **API:** every `/api/*` request renders errors as JSON (`shouldRenderJsonWhen` in `bootstrap/app.php`). Web and API must share rules via services (`MeetingProcessingService::canStart()/start()`, `TaskStatusService`) — don't reimplement them in `Api\V1` controllers. Update `docs/API.md` with any endpoint change.
- **Meeting details** are editable only while `Dijadwalkan` (enforced in both `edit()` and `update()`). `meetings.date` has no Eloquent cast — it round-trips as the raw `datetime-local` string.
- **Last superadmin** can't be demoted (`UserController::update`) or self-deleted (`ProfileController::destroy`) — `User::isLastSuperadmin()`; the admin panel is superadmin-only, so losing the last one locks everyone out.
- **Date-only columns** (`deadline` on Task / MeetingActionItem) are cast `date:Y-m-d`. A bare `date` cast serializes as
  midnight-WIB-in-UTC (`2026-10-19T17:00:00Z`), which shows the previous day in browsers outside WIB.
- **Forum replies** are one level deep: a reply to a reply is re-parented to the top-level comment.
- Meeting search (`MeetingController::index`) uses `LIKE %...%` on `transcript`/`summary` TEXT columns — won't scale.

## Following the project's task workflow

This repo follows a strict loop (see memory `feedback-workflow-and-decisions`): analyze → propose a
plan naming the files to touch → implement one coherent unit → **git commit as a checkpoint** →
summarize → wait for approval before the next task. Prefer removing a broken flow that duplicates an
already-correct path over patching it. For broad feature terms (multi-tenant, SSO, RBAC…), ask which
reading applies before building the generic textbook version.
