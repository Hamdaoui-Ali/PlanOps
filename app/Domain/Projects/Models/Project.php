<?php

namespace App\Domain\Projects\Models;

use App\Domain\Activity\Models\TaskActivity;
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

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function taskActivities(): HasMany
    {
        return $this->hasMany(TaskActivity::class);
    }

    public function scopeOwnedBy(Builder $query, User|int $owner): Builder
    {
        $ownerId = $owner instanceof User ? $owner->getKey() : $owner;

        return $query->where($query->getModel()->qualifyColumn('user_id'), $ownerId);
    }
}
