<?php

namespace App\Domain\Activity\Services;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Tasks\Models\Task;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use LogicException;

final class TaskActivityRecorder
{
    private const REDACTED_FIELDS = ['title', 'description'];

    public function record(
        Task $task,
        TaskActivityType $type,
        ?string $field,
        mixed $oldValue,
        mixed $newValue,
        array $metadata = [],
    ): TaskActivity {
        if (! $task->exists || ! $task->getKey()) {
            throw new LogicException('Task activity requires a persisted task.');
        }

        $redactValues = $type === TaskActivityType::TASK_UPDATED
            && in_array(strtolower((string) $field), self::REDACTED_FIELDS, true);

        return TaskActivity::query()->create([
            'user_id' => $task->user_id,
            'project_id' => $task->project_id,
            'task_id' => $task->getKey(),
            'event_type' => $type,
            'field' => $field,
            'old_value' => $redactValues ? null : $this->normalizePayload($oldValue, $field),
            'new_value' => $redactValues ? null : $this->normalizePayload($newValue, $field),
            'metadata' => $this->normalizeMetadata($metadata),
        ])->refresh();
    }

    private function normalizePayload(mixed $value, ?string $field): mixed
    {
        $normalized = $this->normalizeValue($value);

        return $field !== null && ! is_array($normalized)
            ? [$field => $normalized]
            : $normalized;
    }

    private function normalizeMetadata(array $metadata): array
    {
        return $this->normalizeMetadataValue($metadata);
    }

    private function normalizeMetadataValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $this->normalizeValue($value);
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (in_array(strtolower((string) $key), self::REDACTED_FIELDS, true)) {
                continue;
            }

            $normalized[$key] = $this->normalizeMetadataValue($item);
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalizeValue($item);
        }

        return $normalized;
    }
}
