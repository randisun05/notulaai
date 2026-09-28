# Runbook: pindah zona waktu aplikasi ke WIB

Mulai versi ini aplikasi berjalan di **Asia/Jakarta (WIB)** (`APP_TIMEZONE`, default
`Asia/Jakarta`). Sebelumnya berjalan di **UTC**, sehingga semua timestamp buatan sistem
(`created_at`, `updated_at`, `escalated_at`, `email_verified_at`, token API, dll.) di database
tersimpan dalam jam UTC. Data lama itu harus digeser **+7 jam sekali saja**, bersamaan dengan
deploy versi ini — kalau tidak, semua data lama tampil 7 jam lebih awal (mis. komentar yang
dibuat pukul 14:00 tampil 07:00, dan perhitungan "terlambat"/SLA/eskalasi meleset).

Yang **tidak** digeser: `meetings.date` (jam rapat diinput user dan sejak awal sudah WIB),
kolom bertipe tanggal saja (`deadline`), dan tabel antrean/cache/sesi framework.

Skrip: [`shift-timestamps-utc-to-wib.sql`](shift-timestamps-utc-to-wib.sql) (MySQL/MariaDB).

## Langkah

Lakukan di jendela pemeliharaan; perkirakan beberapa detik sampai beberapa menit tergantung
jumlah baris (`ai_request_logs` dan `activities` biasanya yang terbesar).

1. **Mode pemeliharaan** — hentikan penulisan data baru selama proses:
   ```bash
   php artisan down
   ```
   Hentikan juga worker antrean dan scheduler (`queue:work`, `schedule:work` / service Windows-nya).

2. **Backup penuh database** (wajib — skrip mengubah hampir semua tabel):
   ```bash
   mysqldump --single-transaction --routines --triggers -u <user> -p <database> > backup-sebelum-wib.sql
   ```
   Pastikan file backup tidak kosong dan bisa dibaca sebelum lanjut.

3. **Deploy kode versi ini**, lalu jalankan migrasi seperti biasa:
   ```bash
   php artisan migrate --force
   ```
   Pastikan `.env` **tidak** berisi `APP_TIMEZONE` selain `Asia/Jakarta` (atau hapus barisnya
   supaya memakai default). Jalankan `php artisan config:clear` bila memakai config cache.

4. **Jalankan skrip geser timestamp — sekali saja**:
   ```bash
   mysql -u <user> -p <database> < docs/ops/shift-timestamps-utc-to-wib.sql
   ```
   Jangan memakai `--force`. Baris pertama skrip membuat tabel penanda
   `tz_shift_wib_applied`; kalau skrip tidak sengaja dijalankan dua kali, perintah itu gagal
   dan klien `mysql` berhenti sebelum ada data yang tergeser lagi.

5. **Verifikasi** — bandingkan satu baris yang waktunya Anda ketahui, mis. login terakhir Anda
   di audit log:
   ```sql
   SELECT applied_at FROM tz_shift_wib_applied;                       -- harus 1 baris
   SELECT action, created_at FROM audit_logs ORDER BY id DESC LIMIT 5; -- jam harus WIB
   ```
   Buka aplikasi dan cek timeline aktivitas sebuah rapat: jamnya harus sesuai jam dinding WIB.

6. **Keluar dari mode pemeliharaan** dan nyalakan lagi worker & scheduler:
   ```bash
   php artisan up
   ```

## Kalau ada masalah

- Skrip berjalan dalam satu transaksi (InnoDB): kalau gagal di tengah, tidak ada yang berubah
  kecuali tabel penanda. Hapus tabel penanda (`DROP TABLE tz_shift_wib_applied;`), perbaiki
  penyebabnya, jalankan ulang.
- Kalau hasilnya salah setelah COMMIT: pulihkan dari backup langkah 2
  (`mysql -u <user> -p <database> < backup-sebelum-wib.sql`).
- Instalasi **baru** (database kosong) tidak perlu menjalankan skrip ini.
