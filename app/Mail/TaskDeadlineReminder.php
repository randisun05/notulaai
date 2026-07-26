<?php

namespace App\Mail;

use App\Models\Setting;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskDeadlineReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $task;
    public $setting;

    public function __construct(Task $task)
    {
        $this->task = $task;
        $this->setting = Setting::current();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pengingat Deadline Task: ' . $this->task->title,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.task-deadline-reminder');
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
