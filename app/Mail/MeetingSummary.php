<?php

namespace App\Mail;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MeetingSummary extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Properti publik akan otomatis tersedia di dalam view.
     */
    public $meeting;
    public $user;

    /**
     * Buat instance pesan baru.
     */
    public function __construct(Meeting $meeting)
    {
        $this->meeting = $meeting;
        // Kita load relasi user agar bisa digunakan di template email
        $this->user = $meeting->user;
    }

    /**
     * Dapatkan amplop pesan (subjek, pengirim, dll).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ringkasan Notula Rapat: ' . $this->meeting->title,
        );
    }

    /**
     * Dapatkan konten pesan (template view).
     */
    public function content(): Content
    {
        return new Content(
            // Mengarahkan ke template Blade yang kita buat
            view: 'emails.meeting-summary',
        );
    }

    /**
     * Dapatkan lampiran untuk pesan.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

