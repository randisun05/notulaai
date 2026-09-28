<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\User;
use App\Services\Meeting\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;

/**
 * Daftar hadir (lihat AttendanceService). Halaman /hadir/{token} bisa dibuka
 * tanpa login — tamu undangan dari instansi lain tidak punya akun.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function checkInForm(string $token)
    {
        $meeting = $this->meetingFor($token);
        $user = Auth::user();

        // "Masuk dulu" di halaman ini → setelah login kembali ke sini, bukan ke dashboard.
        if (! $user) {
            session()->put('url.intended', route('attendance.check-in', $token));
        }

        return Inertia::render('Attendance/CheckIn', [
            'token' => $token,
            'meeting' => [
                'title' => $meeting->title,
                'date' => $meeting->date,
                'unit' => $meeting->unit?->name,
            ],
            'open' => $meeting->isAttendanceOpen(),
            'user' => $user ? ['name' => $user->name, 'unit' => $user->unit?->name] : null,
            'checkedInAt' => $user ? $meeting->attendances()->where('user_id', $user->id)->value('checked_in_at') : null,
        ]);
    }

    public function checkIn(Request $request, string $token)
    {
        $meeting = $this->meetingFor($token);

        try {
            if ($user = $request->user()) {
                $attendance = $this->attendance->checkInUser($meeting, $user);
            } else {
                $attendance = $this->attendance->checkInGuest($meeting, $request->validate([
                    'name' => 'required|string|max:255',
                    'position' => 'nullable|string|max:255',
                    'organization' => 'nullable|string|max:255',
                ]));
            }
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Terima kasih, {$attendance->name}. Kehadiran tercatat pukul "
            .$attendance->checked_in_at->format('H.i').' WIB.');
    }

    public function store(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);

        if ($request->filled('user_id')) {
            $validated = $request->validate(['user_id' => ['integer', Rule::exists('users', 'id')]]);
            $this->attendance->addUser($meeting, User::findOrFail($validated['user_id']));
        } else {
            $this->attendance->checkInGuest($meeting, $request->validate([
                'name' => 'required|string|max:255',
                'position' => 'nullable|string|max:255',
                'organization' => 'nullable|string|max:255',
            ]), MeetingAttendance::METHOD_MANUAL);
        }

        return back()->with('success', 'Peserta ditambahkan ke daftar hadir.');
    }

    public function destroy(Meeting $meeting, MeetingAttendance $attendance)
    {
        $this->authorize('update', $meeting);
        abort_unless($attendance->meeting_id === $meeting->id, 404);

        $attendance->delete();

        return back()->with('success', 'Peserta dihapus dari daftar hadir.');
    }

    public function toggle(Meeting $meeting)
    {
        $this->authorize('update', $meeting);

        $this->attendance->setOpen($meeting, ! $meeting->isAttendanceOpen(), Auth::user());

        return back();
    }

    public function regenerate(Meeting $meeting)
    {
        $this->authorize('update', $meeting);

        $this->attendance->regenerateToken($meeting);

        return back()->with('success', 'QR baru dibuat; QR lama tidak berlaku lagi.');
    }

    private function meetingFor(string $token): Meeting
    {
        return Meeting::where('attendance_token', $token)->firstOrFail();
    }
}
