-- ============================================================================
-- Geser timestamp sistem dari UTC ke WIB (+7 jam) — JALANKAN SEKALI SAJA
-- ============================================================================
-- Konteks: sampai versi ini aplikasi berjalan di timezone UTC, jadi Laravel
-- menyimpan created_at/updated_at/dsb. dalam jam UTC. Mulai versi ini
-- APP_TIMEZONE=Asia/Jakarta, sehingga data lama harus digeser +7 jam agar tetap
-- menunjuk momen yang sama.
--
-- TIDAK digeser:
--   * meetings.date  — jam rapat yang diinput user, sejak awal sudah WIB.
--   * kolom bertipe DATE (tasks.deadline, meeting_action_items.deadline).
--   * tabel antrean/cache/sesi framework (jobs, failed_jobs, cache, sessions).
--
-- Cara pakai & langkah backup: lihat docs/ops/MIGRASI-ZONA-WAKTU-WIB.md
--
-- Pengaman: baris pertama membuat tabel penanda. Kalau skrip ini tidak sengaja
-- dijalankan lagi, CREATE TABLE gagal ("already exists") dan klien mysql
-- berhenti sebelum ada data yang tergeser dua kali (JANGAN pakai --force).
-- ============================================================================

CREATE TABLE `tz_shift_wib_applied` (`applied_at` DATETIME NOT NULL);

START TRANSACTION;

UPDATE `activities` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `ai_request_logs` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `audit_logs` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `forum_comment_attachments` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `forum_comment_mentions` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `forum_comment_reactions` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `forum_comments` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `meeting_action_items` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `meeting_chat_messages` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `meeting_segments` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `meetings` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR,
    `processing_heartbeat_at` = `processing_heartbeat_at` + INTERVAL 7 HOUR,
    `live_started_at` = `live_started_at` + INTERVAL 7 HOUR;

UPDATE `meeting_attendances` SET
    `checked_in_at` = `checked_in_at` + INTERVAL 7 HOUR,
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `meetings` SET
    `attendance_closed_at` = `attendance_closed_at` + INTERVAL 7 HOUR;

UPDATE `meeting_minutes` SET
    `submitted_at` = `submitted_at` + INTERVAL 7 HOUR,
    `approved_at` = `approved_at` + INTERVAL 7 HOUR,
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `meeting_markers` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `meeting_decisions` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `password_reset_tokens` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR;

UPDATE `permissions` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `personal_access_tokens` SET
    `last_used_at` = `last_used_at` + INTERVAL 7 HOUR,
    `expires_at` = `expires_at` + INTERVAL 7 HOUR,
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `recording_uploads` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `roles` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `settings` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `source_paths` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `task_dispositions` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `task_evidence_attachments` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `task_evidences` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `tasks` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR,
    `escalated_at` = `escalated_at` + INTERVAL 7 HOUR;

UPDATE `units` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `users` SET
    `email_verified_at` = `email_verified_at` + INTERVAL 7 HOUR,
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

UPDATE `webhooks` SET
    `created_at` = `created_at` + INTERVAL 7 HOUR,
    `updated_at` = `updated_at` + INTERVAL 7 HOUR;

INSERT INTO `tz_shift_wib_applied` (`applied_at`) VALUES (NOW());

COMMIT;
