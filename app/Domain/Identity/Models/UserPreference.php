<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\DensityPreference;
use App\Domain\Identity\Enums\ThemePreference;
use App\Domain\Identity\Enums\WeekStartDay;
use App\Models\User;
use Database\Factories\UserPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(UserPreferenceFactory::class)]
class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'timezone',
        'week_start_day',
        'theme',
        'density',
    ];

    protected $attributes = [
        'timezone' => 'Africa/Casablanca',
        'week_start_day' => 'MONDAY',
        'theme' => 'SYSTEM',
        'density' => 'COMFORTABLE',
    ];

    protected function casts(): array
    {
        return [
            'timezone' => 'string',
            'week_start_day' => WeekStartDay::class,
            'theme' => ThemePreference::class,
            'density' => DensityPreference::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOwnedBy(Builder $query, User|int $owner): Builder
    {
        $ownerId = $owner instanceof User ? $owner->getKey() : $owner;

        return $query->where($query->getModel()->qualifyColumn('user_id'), $ownerId);
    }
}
