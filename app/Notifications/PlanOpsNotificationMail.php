<?php

namespace App\Notifications;

use App\Domain\Notifications\Data\NotificationOutcome;
use App\Domain\Notifications\Enums\NotificationEventType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanOpsNotificationMail extends Notification
{
    use Queueable;

    public function __construct(public readonly NotificationOutcome $outcome) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->greeting('PlanOps notification');

        if ($this->outcome->eventType === NotificationEventType::INVITATION_CREATED) {
            return $mail->subject('You have been invited to a PlanOps project')
                ->line('You have a new project invitation for '.$this->outcome->toPayload()['project_name'].'.');
        }

        return $mail->subject('A PlanOps task was assigned to you')
            ->line('You are now assigned to “'.$this->outcome->toPayload()['task_title'].'”.')
            ->action('Open task', route('tasks.show', $this->outcome->targetId));
    }
}
