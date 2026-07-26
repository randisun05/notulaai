<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AiGeneratedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $bodyHtml;
    public $setting;

    public function __construct(public string $emailSubject, string $bodyHtml)
    {
        $this->bodyHtml = $bodyHtml;
        $this->setting = Setting::current();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.ai-generated');
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
