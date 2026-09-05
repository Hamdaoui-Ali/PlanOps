<?php

namespace App\Domain\Collaboration\Models;

use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Database\Factories\ProjectMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(ProjectMembershipFactory::class)]
class ProjectMembership extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'user_id', 'role', 'joined_at', 'removed_at', 'removed_by_user_id'];

    protected function casts(): array
    {
        return [
            'role' => ProjectRole::class,
            'joined_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
        ];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function removedBy(): BelongsTo { return $this->belongsTo(User::class, 'removed_by_user_id'); }
}
