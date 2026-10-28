# AI Notula — REST API v1

Read-only JSON API for integrations (dashboards, reporting, chat bots, Zapier/Make, …).
For push notifications use the outgoing **webhooks** (Admin → Webhooks) instead of polling.

## Authentication

1. Open **Profile → API Tokens** and create a token. It is shown **once** — store it like a password.
2. Send it on every request:

```bash
curl -H "Authorization: Bearer <token>" https://<your-domain>/api/v1/me
```

A token acts as the user who created it: it sees exactly what that user sees in the web app
(their own unit; a superadmin sees every unit). Revoking the token on the Profile page cuts access
immediately. Requests are limited to **60 per minute** per user (`429` with `Retry-After` when exceeded).

## Endpoints

| Method | Path | Description |
|---|---|---|
| GET | `/api/v1/me` | The token owner (id, name, email, role, unit) |
| GET | `/api/v1/meetings` | Meetings, newest first, paginated |
| GET | `/api/v1/meetings/{id}` | One meeting with its summary and action items |
| GET | `/api/v1/tasks` | Tasks, nearest deadline first, paginated |
| GET | `/api/v1/tasks/{id}` | One task |

### `GET /api/v1/meetings`

| Query | Example | Notes |
|---|---|---|
| `search` | `anggaran` | Title, agenda, transcript and summary (same search as the web app) |
| `status` | `Selesai Diproses` | `Dijadwalkan`, `Memproses`, `Selesai Diproses`, `Gagal` |
| `from`, `to` | `2026-10-01` | Meeting date range (inclusive) |
| `per_page` | `50` | 1–100, default 20 |
| `page` | `2` | |

### `GET /api/v1/meetings/{id}`

Add `?include=transcript` to also get the full transcript (left out by default because it can be very long).
`summary_html` is sanitized HTML (only basic formatting tags and http/https/mailto links).

### `GET /api/v1/tasks`

| Query | Example | Notes |
|---|---|---|
| `status` | `In Progress` | `Todo`, `In Progress`, `Waiting`, `Review`, `Done`, `Cancelled` |
| `assignee_id` | `12` | User id |
| `meeting_id` | `5` | Tasks created from one meeting |
| `overdue` | `1` | Past deadline and not Done/Cancelled |
| `per_page`, `page` | | As above |

## Response format

Single resources are wrapped in `data`; lists add Laravel's `links` and `meta` pagination blocks:

```json
{
  "data": [
    {
      "id": 7,
      "title": "Kirim laporan Q4",
      "status": "In Progress",
      "priority": "High",
      "deadline": "2026-11-05",
      "is_overdue": false,
      "is_sla_breached": false,
      "assignee": { "id": 12, "name": "Budi", "email": "budi@example.com" },
      "assignee_name": "Budi",
      "meeting": { "id": 5, "title": "Rapat Kinerja Q3" },
      "unit_id": 2,
      "url": "https://<your-domain>/tasks/7",
      "created_at": "2026-10-20T09:12:00+07:00",
      "updated_at": "2026-10-21T14:03:00+07:00"
    }
  ],
  "links": { "first": "…", "last": "…", "prev": null, "next": "…" },
  "meta": { "current_page": 1, "last_page": 3, "per_page": 20, "total": 47 }
}
```

## Errors

Always JSON, with a `message` (and `errors` per field for `422`):

| Status | When |
|---|---|
| `401` | Missing, invalid or revoked token |
| `403` | The record belongs to another unit |
| `404` | No such record |
| `422` | Invalid query parameter |
| `429` | Rate limit exceeded |
