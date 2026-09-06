<?php

use App\Domain\Collaboration\Actions\InviteProjectMember;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Notifications\Data\NotificationOutcome;
use App\Domain\Notifications\Jobs\DeliverNotificationOutcome;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('releases invitation notification only after the transaction commits', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);

    DB::beginTransaction();
    (new InviteProjectMember)->handle($owner, $project, $invitee->email, ProjectRole::MEMBER);
    Queue::assertNothingPushed();
    DB::commit();

    Queue::assertPushed(DeliverNotificationOutcome::class, function (DeliverNotificationOutcome $job) use ($invitee, $project): bool {
        return $job->outcome->recipientId === $invitee->id && $job->outcome->projectId === $project->id;
    });
});
