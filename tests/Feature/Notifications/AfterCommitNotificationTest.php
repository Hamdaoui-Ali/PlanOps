<?php

use App\Domain\Collaboration\Actions\InviteProjectMember;
use App\Domain\Collaboration\Actions\ResendProjectInvitation;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Notifications\Jobs\DeliverNotificationOutcome;
use App\Domain\Notifications\Models\PlanOpsNotification;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Actions\AssignTask;
use App\Domain\Tasks\Models\Task;
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

it('releases an invitation notification after the action-owned transaction commits', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);

    (new InviteProjectMember)->handle($owner, $project, $invitee->email, ProjectRole::MEMBER);

    Queue::assertPushed(DeliverNotificationOutcome::class);
    expect(PlanOpsNotification::query()->where('recipient_id', $invitee->id)->exists())->toBeTrue();
});

it('releases assignment notification only after the task transaction commits', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);
    $task = Task::factory()->forProject($project)->create(['assignee_id' => null]);

    DB::beginTransaction();
    (new AssignTask)->handle($owner, $task, $member);
    Queue::assertNothingPushed();
    DB::commit();

    Queue::assertPushed(DeliverNotificationOutcome::class, function (DeliverNotificationOutcome $job) use ($member, $task): bool {
        return $job->outcome->recipientId === $member->id && $job->outcome->targetId === $task->id;
    });
});

it('notifies an existing account when a pending invitation is resent', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    $invitation = (new InviteProjectMember)->handle($owner, $project, $invitee->email, ProjectRole::MEMBER);
    Queue::fake();

    (new ResendProjectInvitation)->handle($owner, $invitation);

    expect(PlanOpsNotification::query()->where('recipient_id', $invitee->id)->count())->toBe(1);
    Queue::assertPushed(DeliverNotificationOutcome::class);
});
