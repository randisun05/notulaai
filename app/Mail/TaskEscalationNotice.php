<?php

namespace App\Mail;

use App\Models\Setting;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskEscalationNotice extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $task;

    public $reason;

    public $setting;

    public function __construct(Task $task, string $reason)
    {
        $this->task = $task;
        $this->reason = $reason;
        $this->setting = Setting::current();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Eskalasi Task: '.$this->task->title,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.task-escalation-notice');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
