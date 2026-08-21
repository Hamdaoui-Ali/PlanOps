<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\UserPreference;
use App\Models\User;

class UpdateUserPreferences
{
    /** @param array{timezone:string,week_start_day:string,theme:string,density:string} $attributes */
    public function execute(User $user, array $attributes): UserPreference
    {
        return $user->preference()->updateOrCreate([], $attributes);
    }
}
