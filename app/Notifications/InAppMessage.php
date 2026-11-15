<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * One message in someone's FoodLoop inbox (the bell). There is no email,
 * push or SMS in the prototype: it is saved to the `notifications` table only.
 *
 * The texts all live in App\Services\Notifier.
 */
class InAppMessage extends Notification
{
    /**
     * @param  string  $kind  short machine name, e.g. "reservation.booked" (used by tests and for the icon)
     * @param  string|null  $url  where tapping the message goes (a path, not a full address)
     */
    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public ?string $url = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{kind: string, title: string, body: string, url: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
