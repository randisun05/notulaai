<?php

namespace App\Services\Meeting;

use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Daftar hadir rapat: lewat QR (pegawai yang login, atau tamu tanpa akun) atau
 * ditambah manual oleh notulen.
 */
class AttendanceService
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * Pegawai yang login memindai QR. Memindai ulang tidak membuat baris baru.
     */
    public function checkInUser(Meeting $meeting, User $user): MeetingAttendance
    {
        $existing = $meeting->attendances()->where('user_id', $user->id)->first();
        if ($existing) {
            return $existing;
        }
        $this->assertOpen($meeting);

        return $meeting->attendances()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'organization' => $user->unit?->name,
            'method' => MeetingAttendance::METHOD_QR,
            'checked_in_at' => now(),
        ]);
    }

    /**
     * Tamu tanpa akun (mis. undangan dari instansi lain). Nama yang sama tidak
     * dicatat dua kali; data jabatan/instansi terbaru yang dipakai.
     *
     * @param  array{name: string, position?: ?string, organization?: ?string}  $data
     */
    public function checkInGuest(Meeting $meeting, array $data, string $method = MeetingAttendance::METHOD_QR): MeetingAttendance
    {
        if ($method === MeetingAttendance::METHOD_QR) {
            $this->assertOpen($meeting);
        }

        $name = Str::squish($data['name']);
        $attributes = [
            'position' => Str::squish($data['position'] ?? '') ?: null,
            'organization' => Str::squish($data['organization'] ?? '') ?: null,
        ];

        $existing = $meeting->attendances()->whereNull('user_id')->get()
            ->first(fn (MeetingAttendance $a) => mb_strtolower($a->name) === mb_strtolower($name));

        if ($existing) {
            $existing->update(array_filter($attributes));

            return $existing;
        }

        return $meeting->attendances()->create([
            'name' => $name,
            ...$attributes,
            'method' => $method,
            'checked_in_at' => now(),
        ]);
    }

    public function addUser(Meeting $meeting, User $user): MeetingAttendance
    {
        return $meeting->attendances()->firstOrCreate(['user_id' => $user->id], [
            'name' => $user->name,
            'organization' => $user->unit?->name,
            'method' => MeetingAttendance::METHOD_MANUAL,
            'checked_in_at' => now(),
        ]);
    }

    public function setOpen(Meeting $meeting, bool $open, User $by): void
    {
        $meeting->update(['attendance_closed_at' => $open ? null : now()]);

        $this->activityLogger->log($meeting, $by, $open ? 'attendance.opened' : 'attendance.closed',
            $by->name.($open ? ' membuka kembali daftar hadir.' : ' menutup daftar hadir.'));
    }

    /** QR lama tidak berlaku lagi (mis. fotonya tersebar keluar ruangan). */
    public function regenerateToken(Meeting $meeting): void
    {
        $meeting->forceFill(['attendance_token' => Str::random(40)])->save();
    }

    /**
     * Nama peserta yang hadir, untuk konteks AI (pengenalan pembicara, notula).
     */
    public function attendeeSummary(Meeting $meeting): ?string
    {
        $list = $meeting->attendances()->get()->map->describe();

        return $list->isEmpty() ? null : $list->implode('; ');
    }

    private function assertOpen(Meeting $meeting): void
    {
        if (! $meeting->isAttendanceOpen()) {
            throw new RuntimeException('Daftar hadir rapat ini sudah ditutup. Hubungi notulen rapat.');
        }
    }
}
