<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

final class VerifyEmailChange extends Notification
{
    public function __construct(public readonly string $url, public readonly int $minutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('laranail/authkit::messages.email_change_subject'))
            ->line(__('laranail/authkit::messages.email_change_intro'))
            ->action(__('laranail/authkit::messages.email_change_action'), $this->url)
            ->line(__('laranail/authkit::messages.email_change_expiry', ['minutes' => $this->minutes]))
            ->line(__('laranail/authkit::messages.email_change_ignore'));
    }
}
