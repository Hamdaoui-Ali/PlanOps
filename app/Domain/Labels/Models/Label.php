<?php

namespace App\Domain\Labels\Models;

use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Database\Factories\LabelFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[UseFactory(LabelFactory::class)]
class Label extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'normalized_name',
        'color',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_label')->withPivot('created_at');
    }

    public function scopeOwnedBy(Builder $query, User|int $owner): Builder
    {
        $ownerId = $owner instanceof User ? $owner->getKey() : $owner;

        return $query->where($query->getModel()->qualifyColumn('user_id'), $ownerId);
    }
}
