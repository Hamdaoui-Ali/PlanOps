<?php

namespace App\Domain\Collaboration\Models;

use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Database\Factories\ProjectInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(ProjectInvitationFactory::class)]
class ProjectInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'email', 'normalized_email', 'role', 'invited_by_user_id',
        'token_hash', 'expires_at', 'accepted_at', 'revoked_at', 'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => ProjectRole::class,
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'last_sent_at' => 'immutable_datetime',
        ];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }

    public function invitedBy(): BelongsTo { return $this->belongsTo(User::class, 'invited_by_user_id'); }

    public function isAccepted(): bool { return $this->accepted_at !== null; }

    public function isRevoked(): bool { return $this->revoked_at !== null; }

    public function isExpired(): bool { return $this->expires_at->isPast(); }

    public function isPending(): bool { return ! $this->isAccepted() && ! $this->isRevoked() && ! $this->isExpired(); }
}
