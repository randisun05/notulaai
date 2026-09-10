<?php

namespace App\Mail;

use App\Models\ForumComment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommentMentionNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $comment;

    public $mentionedUser;

    public $setting;

    public function __construct(ForumComment $comment, User $mentionedUser)
    {
        $this->comment = $comment;
        $this->mentionedUser = $mentionedUser;
        $this->setting = Setting::current();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Anda disebut dalam diskusi rapat: '.$this->comment->meeting->title,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.comment-mention');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
