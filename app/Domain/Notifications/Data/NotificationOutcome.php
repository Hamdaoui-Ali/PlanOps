<?php

namespace App\Domain\Notifications\Data;

use App\Domain\Notifications\Enums\NotificationEventType;

final readonly class NotificationOutcome
{
    private function __construct(
        public NotificationEventType $eventType,
        public int $recipientId,
        public int $projectId,
        public ?int $targetId,
        public ?string $targetType,
        private array $payload,
        private string $key,
    ) {}

    public static function invitationCreated(int $invitationId, int $projectId, int $recipientId, string $projectName): self
    {
        return new self(
            NotificationEventType::INVITATION_CREATED,
            $recipientId,
            $projectId,
            $invitationId,
            'project_invitation',
            ['project_id' => $projectId, 'project_name' => $projectName],
            "INVITATION_CREATED:{$invitationId}:{$recipientId}",
        );
    }

    public static function assigneeChanged(int $taskId, int $projectId, int $recipientId, ?int $oldAssigneeId, int $newAssigneeId, int $actorId, string $taskTitle): self
    {
        return new self(
            NotificationEventType::ASSIGNEE_CHANGED,
            $recipientId,
            $projectId,
            $taskId,
            'task',
            [
                'project_id' => $projectId,
                'task_id' => $taskId,
                'actor_id' => $actorId,
                'old_assignee_id' => $oldAssigneeId,
                'new_assignee_id' => $newAssigneeId,
                'task_title' => $taskTitle,
            ],
            "ASSIGNEE_CHANGED:{$taskId}:{$recipientId}",
        );
    }

    public function idempotencyKey(): string
    {
        return $this->key;
    }

    public function toPayload(): array
    {
        return $this->payload;
    }
}
