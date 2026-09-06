<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Actions\AssignTask;
use App\Domain\Tasks\Actions\ChangeTaskStatus;
use App\Domain\Collaboration\Actions\RemoveProjectMember;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function assignmentProject(User $owner): Project
{
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    return $project;
}

it('assigns, reassigns, and unassigns only active members of the task project', function (): void {
    $owner = User::factory()->create();
    $project = assignmentProject($owner);
    $first = User::factory()->create();
    $second = User::factory()->create();
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $first->id]);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $second->id]);
    $task = Task::factory()->forProject($project)->create(['user_id' => $owner->id, 'assignee_id' => null]);

    (new AssignTask)->handle($owner, $task, $first);
    (new AssignTask)->handle($owner, $task->fresh(), $second);
    (new AssignTask)->handle($owner, $task->fresh(), null);

    expect($task->fresh()->assignee_id)->toBeNull()
        ->and($task->activities()->where('event_type', TaskActivityType::ASSIGNEE_CHANGED->value)->count())->toBe(3)
        ->and($task->activities()->latest('id')->first()->actor_user_id)->toBe($owner->id)
        ->and($task->activities()->latest('id')->first()->metadata)->toMatchArray(['old_assignee_id' => $second->id, 'new_assignee_id' => null]);
});

it('rejects non-members, removed members, members assigning, and archived projects', function (): void {
    $owner = User::factory()->create();
    $project = assignmentProject($owner);
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $membership = ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);
    $task = Task::factory()->forProject($project)->create(['user_id' => $owner->id]);

    expect(fn () => (new AssignTask)->handle($owner, $task, $outsider))->toThrow(ValidationException::class);
    $membership->update(['removed_at' => now()]);
    expect(fn () => (new AssignTask)->handle($owner, $task, $member))->toThrow(ValidationException::class);
    expect(fn () => (new AssignTask)->handle($member, $task, $owner))->toThrow(AuthorizationException::class);
    $project->update(['archived_at' => now()]);
    expect(fn () => (new AssignTask)->handle($owner, $task->fresh(), $owner))->toThrow(AuthorizationException::class);
});

it('does not record a no-op assignment event', function (): void {
    $owner = User::factory()->create();
    $project = assignmentProject($owner);
    $member = User::factory()->create();
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);
    $task = Task::factory()->forProject($project)->create(['user_id' => $owner->id, 'assignee_id' => $member->id]);

    (new AssignTask)->handle($owner, $task, $member);

    expect($task->activities()->where('event_type', TaskActivityType::ASSIGNEE_CHANGED->value)->count())->toBe(0);
});

it('records the authenticated actor for task mutations', function (): void {
    $owner = User::factory()->create();
    $project = assignmentProject($owner);
    $member = User::factory()->create();
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);
    $task = Task::factory()->forProject($project)->create(['user_id' => $owner->id, 'assignee_id' => $member->id]);

    (new ChangeTaskStatus)->handle($member, $task, TaskStatus::IN_PROGRESS);

    expect($task->activities()->latest('id')->first()->actor_user_id)->toBe($member->id);
});

it('shows an assignee selector to managers and a read-only assignee to members', function (): void {
    $owner = User::factory()->create();
    $project = assignmentProject($owner);
    $member = User::factory()->create();
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);
    $task = Task::factory()->forProject($project)->create(['user_id' => $owner->id, 'assignee_id' => $member->id]);

    $ownerResponse = $this->actingAs($owner)->get(route('tasks.show', $task));
    $ownerResponse->assertOk()->assertSee('Assign task to')->assertSee($member->name);

    $memberResponse = $this->actingAs($member)->get(route('tasks.show', $task));
    $memberResponse->assertOk()->assertSee('Assigned to')->assertDontSee('name="assignee_id"', false);

    $this->actingAs($owner)->get(route('projects.tasks.index', $project))->assertOk()->assertSee($member->name);
    $this->actingAs($owner)->get(route('projects.board', $project))->assertOk()->assertSee($member->name);
});

it('shows members only their assigned work in My Work', function (): void {
    $owner = User::factory()->create();
    $project = assignmentProject($owner);
    $member = User::factory()->create();
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);
    $assigned = Task::factory()->forProject($project)->create(['user_id' => $owner->id, 'assignee_id' => $member->id, 'title' => 'Assigned item']);
    Task::factory()->forProject($project)->create(['user_id' => $owner->id, 'assignee_id' => null, 'title' => 'Unassigned item']);

    $response = $this->actingAs($member)->get(route('my-work'));

    $response->assertOk()->assertSee($assigned->title)->assertDontSee('Unassigned item');
});

it('records actor-aware unassignment when a member is removed', function (): void {
    $owner = User::factory()->create();
    $project = assignmentProject($owner);
    $member = User::factory()->create();
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);
    $task = Task::factory()->forProject($project)->create(['user_id' => $owner->id, 'assignee_id' => $member->id]);

    (new RemoveProjectMember)->handle($owner, $project, $member);

    $activity = $task->activities()->latest('id')->first();
    expect($task->fresh()->assignee_id)->toBeNull()
        ->and($activity->event_type)->toBe(TaskActivityType::ASSIGNEE_CHANGED)
        ->and($activity->actor_user_id)->toBe($owner->id)
        ->and($activity->metadata)->toMatchArray(['old_assignee_id' => $member->id, 'new_assignee_id' => null]);
});
