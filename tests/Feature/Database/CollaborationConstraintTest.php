<?php

use App\Domain\Collaboration\Enums\ProjectEventType;
use App\Domain\Collaboration\Models\ProjectEvent;
use App\Domain\Collaboration\Models\ProjectInvitation;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('token hashes are unique and event history is append-only', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $invitation = ProjectInvitation::factory()->for($project)->create(['token_hash' => str_repeat('a', 64)]);

    expect(fn (): ProjectInvitation => ProjectInvitation::factory()->for($project)->create(['token_hash' => $invitation->token_hash]))
        ->toThrow(QueryException::class);

    $event = ProjectEvent::factory()->for($project)->create([
        'actor_user_id' => $owner->id,
        'event_type' => ProjectEventType::INVITATION_CREATED,
    ]);

    expect(fn (): bool => (bool) $event->update(['metadata' => ['changed' => true]]))
        ->toThrow(LogicException::class)
        ->and(fn (): bool => (bool) $event->delete())->toThrow(LogicException::class);
});

test('assignees use a nullable user foreign key and memberships retain history', function (): void {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $task = Task::factory()->forProject($project)->create(['assignee_id' => $assignee->id]);
    $membership = ProjectMembership::factory()->for($project)->for($assignee, 'user')->create();

    expect($task->fresh()->assignee_id)->toBe($assignee->id)
        ->and($membership->user_id)->toBe($assignee->id);

    $membership->update(['removed_at' => now(), 'removed_by_user_id' => $owner->id]);

    expect(ProjectMembership::query()->whereKey($membership->id)->exists())->toBeTrue();

    if (Schema::getConnection()->getDriverName() === 'pgsql') {
        $ownerMembership = ProjectMembership::factory()->for($project)->for($owner, 'user')->owner()->create();

        expect(fn (): ProjectMembership => ProjectMembership::factory()->for($project)->owner()->create())
            ->toThrow(QueryException::class);

        $ownerMembership->delete();
    }
});
