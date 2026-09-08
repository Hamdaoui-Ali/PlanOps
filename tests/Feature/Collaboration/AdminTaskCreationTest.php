<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('a member cannot create tasks directly or change task activity and numbering', function (): void {
    $member = User::factory()->create();
    $project = Project::factory()->create(['next_task_number' => 7]);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);

    expect(fn () => (new CreateTask)->handle($member, $project, ['title' => 'Denied task']))
        ->toThrow(AuthorizationException::class);
    expect(Task::query()->count())->toBe(0)
        ->and(TaskActivity::query()->count())->toBe(0)
        ->and($project->fresh()->next_task_number)->toBe(7);
});

test('task creation rechecks the admin role inside its transaction before allocating or writing', function (): void {
    $admin = User::factory()->create();
    $project = Project::factory()->create(['next_task_number' => 7]);
    $membership = ProjectMembership::factory()->admin()->create([
        'project_id' => $project->id,
        'user_id' => $admin->id,
    ]);
    $demoted = false;
    // Change real persisted authorization state after the preliminary gate passes.
    Event::listen(TransactionBeginning::class, function () use ($membership, &$demoted): void {
        if (! $demoted) {
            $demoted = true;
            $membership->forceFill(['role' => ProjectRole::MEMBER])->save();
        }
    });

    expect(fn () => (new CreateTask)->handle($admin, $project, ['title' => 'Stale admin task']))
        ->toThrow(AuthorizationException::class);
    expect($demoted)->toBeTrue()
        ->and(Task::query()->count())->toBe(0)
        ->and(TaskActivity::query()->count())->toBe(0)
        ->and($project->fresh()->next_task_number)->toBe(7);
});

test('an active project admin can create a task with explicit creator and actor attribution', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $project = Project::factory()->for($owner)->create([
        'key' => 'SHARED',
        'next_task_number' => 7,
    ]);
    ProjectMembership::factory()->admin()->create([
        'project_id' => $project->id,
        'user_id' => $admin->id,
    ]);

    $task = (new CreateTask)->handle($admin, $project, ['title' => 'Created by an admin']);
    $activity = TaskActivity::query()->where('task_id', $task->id)->sole();

    expect($task->project_id)->toBe($project->id)
        ->and($task->number)->toBe(7)
        ->and($task->created_by_user_id)->toBe($admin->id)
        ->and($task->user_id)->toBe($admin->id)
        ->and($project->fresh()->next_task_number)->toBe(8)
        ->and($activity->event_type)->toBe(TaskActivityType::TASK_CREATED)
        ->and($activity->actor_user_id)->toBe($admin->id)
        ->and($activity->user_id)->toBe($admin->id);
});

test('an active project admin can use an owner-created top-level task as a parent', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'SHARED']);
    ProjectMembership::factory()->admin()->create([
        'project_id' => $project->id,
        'user_id' => $admin->id,
    ]);
    $parent = Task::factory()->forProject($project)->create(['number' => 20]);

    $task = (new CreateTask)->handle($admin, $project, [
        'title' => 'Admin child task',
        'parent_task_id' => $parent->id,
    ]);

    expect($task->parent_task_id)->toBe($parent->id)
        ->and($task->project_id)->toBe($project->id)
        ->and($task->created_by_user_id)->toBe($admin->id)
        ->and($task->user_id)->toBe($admin->id);
});

test('an active project admin cannot use a parent from another accessible project', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'SHARED']);
    $otherProject = Project::factory()->for($owner)->create(['key' => 'OTHER']);
    ProjectMembership::factory()->admin()->create([
        'project_id' => $project->id,
        'user_id' => $admin->id,
    ]);
    ProjectMembership::factory()->admin()->create([
        'project_id' => $otherProject->id,
        'user_id' => $admin->id,
    ]);
    $otherParent = Task::factory()->forProject($otherProject)->create(['number' => 20]);

    expect(fn (): Task => (new CreateTask)->handle($admin, $project, [
        'title' => 'Cross-project child task',
        'parent_task_id' => $otherParent->id,
    ]))->toThrow(ValidationException::class);
    expect(Task::query()->count())->toBe(1)
        ->and($project->fresh()->next_task_number)->toBe(1);
});

test('the task creation form lists all top-level parents from the admins project only', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'SHARED']);
    $otherProject = Project::factory()->for($owner)->create(['key' => 'OTHER']);
    ProjectMembership::factory()->admin()->create([
        'project_id' => $project->id,
        'user_id' => $admin->id,
    ]);
    ProjectMembership::factory()->admin()->create([
        'project_id' => $otherProject->id,
        'user_id' => $admin->id,
    ]);
    $ownerParent = Task::factory()->forProject($project)->create([
        'number' => 10,
        'title' => 'Owner-created parent',
    ]);
    $adminParent = Task::factory()->forProject($project)->create([
        'user_id' => $admin->id,
        'number' => 20,
        'title' => 'Admin-created parent',
    ]);
    Task::factory()->forProject($otherProject)->create([
        'number' => 10,
        'title' => 'Other-project parent',
    ]);

    $this->actingAs($admin)
        ->get(route('projects.tasks.create', $project))
        ->assertOk()
        ->assertViewHas('parentOptions', function ($parentOptions) use ($ownerParent, $adminParent): bool {
            return $parentOptions->all() === [
                [
                    'id' => $ownerParent->id,
                    'display_key' => 'SHARED-10',
                    'title' => 'Owner-created parent',
                ],
                [
                    'id' => $adminParent->id,
                    'display_key' => 'SHARED-20',
                    'title' => 'Admin-created parent',
                ],
            ];
        });
});
