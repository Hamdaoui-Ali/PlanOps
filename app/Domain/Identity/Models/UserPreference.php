<?php

namespace App\Domain\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
