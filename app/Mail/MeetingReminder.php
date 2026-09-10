<?php

namespace App\Mail;

use App\Models\Meeting;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MeetingReminder extends Mailable
{
    use Queueable, SerializesModels;

    public $meeting;

    public $setting;

    /**
     * Create a new message instance.
     */
    public function __construct(Meeting $meeting)
    {
        $this->meeting = $meeting;
        $this->setting = Setting::current();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pengingat Rapat: '.$this->meeting->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            // Nama file blade template
            view: 'emails.meeting-reminder',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
