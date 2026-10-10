<?php

namespace App\Notifications;

use App\Support\EmailTemplate;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One of the editable emails filled with sample values, sent from the admin
 * panel so an admin can see it in a real mailbox.
 */
class EmailTemplateSample extends Notification
{
    public function __construct(public readonly string $type, public readonly ?string $recipientName = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = EmailTemplate::sample($this->type, $this->recipientName);

        return $message->subject('[Thử] '.$message->subject);
    }
}
