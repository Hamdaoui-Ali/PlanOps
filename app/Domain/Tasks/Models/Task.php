<?php

namespace App\Domain\Tasks\Models;

use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Models\User;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(TaskFactory::class)]
class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'project_id',
        'parent_task_id',
        'number',
        'title',
        'description',
        'status',
        'priority',
        'due_on',
        'position',
        'first_started_at',
        'completed_at',
        'cancelled_at',
        'status_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_on' => 'immutable_date',
            'first_started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'status_changed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'task_label')->withPivot('created_at');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class);
    }

    public function scopeOwnedBy(Builder $query, User|int $owner): Builder
    {
        $ownerId = $owner instanceof User ? $owner->getKey() : $owner;

        return $query->where($query->getModel()->qualifyColumn('user_id'), $ownerId);
    }
}
