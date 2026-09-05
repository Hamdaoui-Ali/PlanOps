<?php

namespace App\Models;

use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Collaboration\Models\ProjectEvent;
use App\Domain\Collaboration\Models\ProjectInvitation;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Identity\Models\UserPreference;
use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deactivated_at' => 'immutable_datetime',
        ];
    }

    /**
     * The display and timezone preferences owned by the user.
     *
     * @return HasOne<UserPreference, $this>
     */
    public function preference(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    public function taskActivities(): HasMany
    {
        return $this->hasMany(TaskActivity::class);
    }

    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMembership::class);
    }

    public function sentInvitations(): HasMany
    {
        return $this->hasMany(ProjectInvitation::class, 'invited_by_user_id');
    }

    public function projectEvents(): HasMany
    {
        return $this->hasMany(ProjectEvent::class, 'actor_user_id');
    }
}
