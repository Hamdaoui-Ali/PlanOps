<?php

namespace App\Domain\Projects\Models;

use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Collaboration\Models\ProjectEvent;
use App\Domain\Collaboration\Models\ProjectInvitation;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(ProjectFactory::class)]
class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'owner_id',
        'name',
        'key',
        'description',
        'status',
        'color',
        'icon',
        'start_on',
        'target_on',
        'next_task_number',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'start_on' => 'immutable_date',
            'target_on' => 'immutable_date',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function taskActivities(): HasMany
    {
        return $this->hasMany(TaskActivity::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ProjectMembership::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ProjectInvitation::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProjectEvent::class);
    }

    public function scopeOwnedBy(Builder $query, User|int $owner): Builder
    {
        $ownerId = $owner instanceof User ? $owner->getKey() : $owner;

        return $query->where($query->getModel()->qualifyColumn('user_id'), $ownerId);
    }

    public function hasTasksEver(): bool
    {
        return $this->tasks()->withTrashed()->exists();
    }

    /**
     * @return array{eligible_task_count: int, completed_task_count: int}
     */
    public function progressCounts(): array
    {
        if (array_key_exists('eligible_task_count', $this->attributes) && array_key_exists('completed_task_count', $this->attributes)) {
            return [
                'eligible_task_count' => (int) $this->attributes['eligible_task_count'],
                'completed_task_count' => (int) $this->attributes['completed_task_count'],
            ];
        }

        $eligibleTasks = $this->tasks()
            ->whereNull('parent_task_id')
            ->where('status', '!=', \App\Domain\Tasks\Enums\TaskStatus::CANCELLED->value);

        return [
            'eligible_task_count' => (clone $eligibleTasks)->count(),
            'completed_task_count' => (clone $eligibleTasks)->where('status', \App\Domain\Tasks\Enums\TaskStatus::DONE->value)->count(),
        ];
    }

    public function progressPercent(): int|float
    {
        $counts = $this->progressCounts();
        if ($counts['eligible_task_count'] === 0) {
            return 0;
        }

        $percent = round(($counts['completed_task_count'] / $counts['eligible_task_count']) * 100, 2);

        return $percent === (float) (int) $percent ? (int) $percent : $percent;
    }

    public function hasActiveScope(): bool
    {
        return $this->progressCounts()['eligible_task_count'] > 0;
    }

    public function getEligibleTaskCountAttribute(): int
    {
        return $this->progressCounts()['eligible_task_count'];
    }

    public function getCompletedTaskCountAttribute(): int
    {
        return $this->progressCounts()['completed_task_count'];
    }

    public function getProgressPercentAttribute(): int|float
    {
        return $this->progressPercent();
    }
}
