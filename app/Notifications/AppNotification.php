<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * One in-app (bell) notification. Stored in the `notifications` table only;
 * the existing Mailables keep handling e-mail. Sent through InAppNotifier.
 */
class AppNotification extends Notification
{
    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly ?string $body,
        public readonly string $url,
        public readonly ?string $actorName = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'actor_name' => $this->actorName,
        ];
    }
}
