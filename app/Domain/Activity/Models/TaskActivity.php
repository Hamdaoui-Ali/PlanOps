<?php

namespace App\Domain\Activity\Models;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Database\Factories\TaskActivityFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[UseFactory(TaskActivityFactory::class)]
class TaskActivity extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'project_id',
        'task_id',
        'event_type',
        'field',
        'old_value',
        'new_value',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Task activity records are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Task activity records are append-only.');
        });
    }

    protected function casts(): array
    {
        return [
            'event_type' => TaskActivityType::class,
            'old_value' => 'array',
            'new_value' => 'array',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class)->withTrashed();
    }

    public function scopeOwnedBy(Builder $query, User|int $owner): Builder
    {
        $ownerId = $owner instanceof User ? $owner->getKey() : $owner;

        return $query->where($query->getModel()->qualifyColumn('user_id'), $ownerId);
    }
}
