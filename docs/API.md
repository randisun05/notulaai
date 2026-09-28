# API Notula AI — v1

API REST untuk integrasi sistem lain (mis. mengirim transkrip Zoom/Meet/Teams secara otomatis,
atau menarik daftar tugas ke aplikasi internal).

## Autentikasi

1. Login ke aplikasi → **Profil** → bagian **API Token** → buat token baru.
2. Salin token (hanya tampil sekali).
3. Kirim di setiap request:

```
Authorization: Bearer <token>
Accept: application/json
```

Token mewakili user yang membuatnya: data yang terlihat dan tindakan yang boleh dilakukan **sama
persis** dengan user itu di aplikasi web (dibatasi unitnya; superadmin melihat semua unit).

Batas: 60 request/menit per user. Endpoint yang memicu AI (`POST .../transcript`) juga ikut kuota AI
harian per user dan per unit. Melebihi batas → `429`.

## Format respons

- Data tunggal: `{"data": {...}}`. Daftar: `{"data": [...], "links": {...}, "meta": {...}}` (paginasi,
  parameter `page` dan `per_page` maks. 100).
- Error: `401` token tidak valid · `403` tidak berhak · `404` tidak ada · `409` status tidak
  memungkinkan · `422` validasi gagal (`{"message": ..., "errors": {field: [...]}}`) · `429` batas request.
- Tanggal rapat (`date`) adalah jam lokal WIB `YYYY-MM-DD HH:MM:SS`; `created_at`/`updated_at` ISO 8601.

## Endpoint

### `GET /api/v1/user`
Profil pemilik token: `id`, `name`, `email`, `role` (`user`/`admin`/`superadmin`), `unit`.

### `GET /api/v1/meetings`
Daftar rapat, terbaru dulu. Filter: `search` (judul/agenda), `status`
(`Dijadwalkan`, `Memproses`, `Selesai Diproses`, `Gagal`), `per_page`.

### `POST /api/v1/meetings`
Menjadwalkan rapat baru di unit pemilik token.

| Field | Wajib | Keterangan |
|---|---|---|
| `title` | ya | maks. 255 karakter |
| `date` | ya | `YYYY-MM-DD HH:MM` (WIB) |
| `agenda` | tidak | teks/HTML sederhana |
| `attendees` | tidak | maks. 255 karakter |

Respons `201` berisi rapat yang dibuat.

### `GET /api/v1/meetings/{id}`
Detail rapat termasuk `summary` (HTML), `transcript`, dan `action_items`.

### `POST /api/v1/meetings/{id}/transcript`
Mengirim transkrip jadi (mis. ekspor transkrip Zoom) untuk dirangkum AI. Hanya untuk rapat
berstatus `Dijadwalkan` atau `Gagal` — selain itu `409`.

```json
{ "transcript": "Budi: Selamat pagi, kita mulai rapat..." }
```

Respons `202` dengan `status: "Memproses"`. Pemrosesan berjalan di latar belakang; cek hasilnya
lewat `GET /api/v1/meetings/{id}` sampai `status` menjadi `Selesai Diproses` (atau `Gagal`), atau
pasang webhook `meeting.processed` di panel admin.

### `GET /api/v1/tasks`
Daftar tugas, urut deadline. Filter: `status`, `assigned_to_me=1`, `meeting_id`, `per_page`.

### `GET /api/v1/tasks/{id}`
Detail tugas: status, prioritas, deadline, SLA, `is_overdue`, `is_sla_breached`, assignee.

### `PATCH /api/v1/tasks/{id}/status`
Mengubah status kerja tugas. Aturannya sama dengan aplikasi web:

- Nilai yang diterima: `Todo`, `In Progress`, `Waiting`, `Cancelled`.
- `Done` hanya lewat persetujuan admin, `Review` hanya lewat pengajuan dengan bukti — keduanya
  tidak bisa di-set lewat endpoint ini (`422`).
- Tugas yang sudah `Done` hanya bisa dibuka kembali oleh admin unit tersebut (`403` untuk yang lain).

```json
{ "status": "In Progress" }
```

## Contoh

```bash
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  https://notula.example.go.id/api/v1/tasks?assigned_to_me=1

curl -X POST -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"transcript": "..."}' \
  https://notula.example.go.id/api/v1/meetings/42/transcript
```
