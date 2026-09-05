<?php

namespace App\Domain\Collaboration\Models;

use App\Domain\Collaboration\Enums\ProjectEventType;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Database\Factories\ProjectEventFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[UseFactory(ProjectEventFactory::class)]
class ProjectEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['project_id', 'actor_user_id', 'subject_user_id', 'event_type', 'metadata', 'created_at'];

    protected static function booted(): void
    {
        static::updating(static function (): never { throw new LogicException('Project events are append-only.'); });
        static::deleting(static function (): never { throw new LogicException('Project events are append-only.'); });
    }

    protected function casts(): array
    {
        return ['event_type' => ProjectEventType::class, 'metadata' => 'array', 'created_at' => 'immutable_datetime'];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }

    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }

    public function subject(): BelongsTo { return $this->belongsTo(User::class, 'subject_user_id'); }
}
