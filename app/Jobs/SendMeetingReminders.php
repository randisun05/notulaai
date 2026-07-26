<?php

namespace App\Jobs;

use App\Mail\MeetingReminder;
use App\Models\Meeting;
use App\Models\User; // <-- TAMBAHKAN IMPORT USER
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMeetingReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Menjalankan Job Pengingat Rapat (Email)...');

        // PERBAIKAN: Kita tidak perlu relasi 'creator' lagi di sini
        $todayMeetings = Meeting::whereDate('date', now()->toDateString())
            ->whereTime('date', '>', now()->toTimeString()) // Hanya rapat yang belum lewat
            ->get();

        if ($todayMeetings->isEmpty()) {
            Log::info('Tidak ada rapat terjadwal hari ini.');
            return;
        }

        foreach ($todayMeetings as $meeting) {
            // PERBAIKAN: Ambil semua user dari unit rapat
            if (!$meeting->unit_id) {
                Log::warning("Rapat ID: {$meeting->id} tidak memiliki unit_id. Dilewati.");
                continue;
            }

            $usersInUnit = User::where('unit_id', $meeting->unit_id)
                                ->whereNotNull('email') // Hanya user yang punya email
                                ->get();

            if ($usersInUnit->isEmpty()) {
                Log::warning("Tidak ada user yang ditemukan di unit ID {$meeting->unit_id} untuk Rapat ID: {$meeting->id}.");
                continue;
            }

            Log::info("Mengirim pengingat Rapat ID: {$meeting->id} ke {$usersInUnit->count()} pengguna di unit ID {$meeting->unit_id}.");

            // Kirim email ke setiap user di unit tersebut
            foreach ($usersInUnit as $user) {
                try {
                    Mail::to($user->email)->send(new MeetingReminder($meeting));

                    Log::info("Email pengingat terkirim ke {$user->email} (Rapat ID: {$meeting->id})");
                } catch (\Exception $e) {
                    Log::error("Gagal mengirim email pengingat ke {$user->email}: " . $e->getMessage());
                }
            }
        }
    }
}

