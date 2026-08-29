<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\TaskKeyQuery;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('an owner can create a title-only task with the documented defaults and derived key', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);

    $task = (new CreateTask)->handle($owner, $project, ['title' => 'Capture the contract']);

    expect($task->user_id)->toBe($owner->id)
        ->and($task->project_id)->toBe($project->id)
        ->and($task->number)->toBe(1)
        ->and($task->status)->toBe(TaskStatus::NOT_STARTED)
        ->and($task->priority)->toBe(TaskPriority::MEDIUM)
        ->and((new TaskKeyQuery)->displayKey($task))->toBe('PLAN-1');
});

test('task creation persists optional description status priority and due date values', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);

    $task = (new CreateTask)->handle($owner, $project, [
        'title' => 'Capture optional fields',
        'description' => 'Document the intake context.',
        'status' => TaskStatus::IN_PROGRESS,
        'priority' => TaskPriority::HIGH,
        'due_on' => '2026-09-15',
    ]);

    expect($task->fresh()->user_id)->toBe($owner->id)
        ->and($task->fresh()->project_id)->toBe($project->id)
        ->and($task->fresh()->description)->toBe('Document the intake context.')
        ->and($task->fresh()->status)->toBe(TaskStatus::IN_PROGRESS)
        ->and($task->fresh()->priority)->toBe(TaskPriority::HIGH)
        ->and($task->fresh()->due_on?->toDateString())->toBe('2026-09-15');
});

test('task creation records the initial status change timestamp', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);

    $task = (new CreateTask)->handle($owner, $project, ['title' => 'Record initial status time']);

    expect($task->fresh()->status_changed_at)->not->toBeNull();
});

test('task creation records one redacted task-created activity with stable context', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = (new CreateTask)->handle($owner, $project, [
        'title' => 'Sensitive title',
        'description' => 'Sensitive description',
    ]);

    $activity = TaskActivity::query()->where('event_type', TaskActivityType::TASK_CREATED)->sole();

    expect(TaskActivity::query()->where('event_type', TaskActivityType::TASK_CREATED)->count())->toBe(1)
        ->and($activity->user_id)->toBe($owner->id)
        ->and($activity->project_id)->toBe($project->id)
        ->and($activity->task_id)->toBe($task->id)
        ->and($activity->new_value)->toBe([
            'display_key' => 'PLAN-1',
            'status' => 'NOT_STARTED',
            'priority' => 'MEDIUM',
        ])
        ->and(json_encode($activity->new_value))->not->toContain('Sensitive title')
        ->and(json_encode($activity->new_value))->not->toContain('Sensitive description');
});

test('a second task in a project receives the next number', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);

    $first = (new CreateTask)->handle($owner, $project, ['title' => 'First task']);
    $second = (new CreateTask)->handle($owner, $project, ['title' => 'Second task']);

    expect($first->user_id)->toBe($owner->id)
        ->and($first->project_id)->toBe($project->id)
        ->and($first->number)->toBe(1)
        ->and($second->user_id)->toBe($owner->id)
        ->and($second->project_id)->toBe($project->id)
        ->and($second->number)->toBe(2);
});

test('a soft-deleted task number is not reused', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $first = (new CreateTask)->handle($owner, $project, ['title' => 'Deleted first task']);
    $first->delete();

    $second = (new CreateTask)->handle($owner, $project, ['title' => 'Replacement task']);
    $deletedFirst = Task::query()->withTrashed()->findOrFail($first->id);

    expect($deletedFirst->user_id)->toBe($owner->id)
        ->and($deletedFirst->project_id)->toBe($project->id)
        ->and($deletedFirst->deleted_at)->not->toBeNull()
        ->and($second->user_id)->toBe($owner->id)
        ->and($second->project_id)->toBe($project->id)
        ->and($second->number)->toBe(2);
});

test('task creation rolls back the task counter and activity when the activity write fails', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN', 'next_task_number' => 1]);
    $connection = DB::connection($project->getConnectionName());
    $driver = $connection->getDriverName();

    if ($driver === 'sqlite') {
        $connection->unprepared(<<<'SQL'
            CREATE TRIGGER fail_task_created_activity
            BEFORE INSERT ON task_activities
            WHEN NEW.event_type = 'TASK_CREATED'
            BEGIN
                SELECT RAISE(ABORT, 'task activity write failed');
            END;
        SQL);
    } elseif ($driver === 'pgsql') {
        $connection->unprepared(<<<'SQL'
            CREATE FUNCTION fail_task_created_activity() RETURNS trigger AS $$
            BEGIN
                IF NEW.event_type = 'TASK_CREATED' THEN
                    RAISE EXCEPTION 'task activity write failed';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER fail_task_created_activity
            BEFORE INSERT ON task_activities
            FOR EACH ROW EXECUTE FUNCTION fail_task_created_activity();
        SQL);
    } else {
        $this->markTestSkipped("Activity-write rollback contract requires SQLite or PostgreSQL; {$driver} is configured.");
    }

    try {
        expect(fn (): Task => (new CreateTask)->handle($owner, $project, ['title' => 'Must roll back']))
            ->toThrow(QueryException::class);
    } finally {
        if ($driver === 'sqlite') {
            $connection->unprepared('DROP TRIGGER IF EXISTS fail_task_created_activity');
        }

        if ($driver === 'pgsql') {
            $connection->unprepared('DROP TRIGGER IF EXISTS fail_task_created_activity ON task_activities');
            $connection->unprepared('DROP FUNCTION IF EXISTS fail_task_created_activity()');
        }
    }

    expect(Task::query()->count())->toBe(0)
        ->and(TaskActivity::query()->count())->toBe(0)
        ->and($project->fresh()->user_id)->toBe($owner->id)
        ->and($project->fresh()->next_task_number)->toBe(1);
});

test('task creation rejects a foreign project without creating a task', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $foreignProject = Project::factory()->for($other)->create(['key' => 'FOREIGN']);

    expect(fn (): Task => (new CreateTask)->handle($owner, $foreignProject, ['title' => 'Forbidden']))
        ->toThrow(AuthorizationException::class);
    expect(Task::query()->count())->toBe(0)
        ->and($foreignProject->fresh()->user_id)->toBe($other->id)
        ->and($foreignProject->fresh()->next_task_number)->toBe(1);
});

test('task creation rejects a parent owned by another user without creating a task', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $foreignProject = Project::factory()->for($other)->create(['key' => 'OTHER']);
    $foreignParent = Task::factory()->forProject($foreignProject)->create(['number' => 1]);

    expect(fn (): Task => (new CreateTask)->handle($owner, $project, ['title' => 'Forbidden parent', 'parent_task_id' => $foreignParent->id]))
        ->toThrow(ValidationException::class);
    expect(Task::query()->count())->toBe(1)
        ->and($foreignParent->user_id)->toBe($other->id)
        ->and($foreignParent->project_id)->toBe($foreignProject->id)
        ->and($project->fresh()->user_id)->toBe($owner->id)
        ->and($project->fresh()->next_task_number)->toBe(1);
});

test('task creation rejects a parent from another owned project without creating a task', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $otherProject = Project::factory()->for($owner)->create(['key' => 'OTHER']);
    $crossProjectParent = Task::factory()->forProject($otherProject)->create(['number' => 1]);

    expect(fn (): Task => (new CreateTask)->handle($owner, $project, ['title' => 'Cross project parent', 'parent_task_id' => $crossProjectParent->id]))
        ->toThrow(ValidationException::class);
    expect(Task::query()->count())->toBe(1)
        ->and($crossProjectParent->user_id)->toBe($owner->id)
        ->and($crossProjectParent->project_id)->toBe($otherProject->id)
        ->and($project->fresh()->user_id)->toBe($owner->id)
        ->and($project->fresh()->next_task_number)->toBe(1);
});

test('task creation rejects a nested parent without creating a task', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $topLevelParent = Task::factory()->forProject($project)->create(['number' => 1]);
    $nestedParent = Task::factory()->forProject($project)->withParent($topLevelParent)->create(['number' => 2]);

    expect(fn (): Task => (new CreateTask)->handle($owner, $project, ['title' => 'Nested parent', 'parent_task_id' => $nestedParent->id]))
        ->toThrow(ValidationException::class);
    expect(Task::query()->count())->toBe(2)
        ->and($nestedParent->user_id)->toBe($owner->id)
        ->and($nestedParent->project_id)->toBe($project->id)
        ->and($nestedParent->parent_task_id)->toBe($topLevelParent->id)
        ->and($project->fresh()->next_task_number)->toBe(1);
});

test('task creation rejects a self-parented task without creating a task', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $selfParent = Task::factory()->forProject($project)->create(['number' => 1]);
    $selfParent->forceFill(['parent_task_id' => $selfParent->id])->save();

    expect(fn (): Task => (new CreateTask)->handle($owner, $project, ['title' => 'Self parent', 'parent_task_id' => $selfParent->id]))
        ->toThrow(ValidationException::class);
    expect(Task::query()->count())->toBe(1)
        ->and($selfParent->user_id)->toBe($owner->id)
        ->and($selfParent->project_id)->toBe($project->id)
        ->and($selfParent->parent_task_id)->toBe($selfParent->id)
        ->and($project->fresh()->next_task_number)->toBe(1);
});

test('the task creation route renders the required labels', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);

    $this->actingAs($owner)
        ->get(route('projects.tasks.create', $project))
        ->assertOk()
        ->assertSee('Project')
        ->assertSee('Title')
        ->assertSee('More task details')
        ->assertSee('Status')
        ->assertSee('Priority')
        ->assertSee('Due date');
});

test('the task creation route exposes only owner-scoped top-level non-deleted parent options', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $otherProject = Project::factory()->for($owner)->create(['key' => 'OTHER']);
    $visibleParent = Task::factory()->forProject($project)->create(['number' => 2, 'title' => 'Visible parent']);
    Task::factory()->forProject($otherProject)->create(['number' => 1, 'title' => 'Cross-project parent']);
    Task::factory()->forProject($project)->create(['user_id' => $other->id, 'number' => 3, 'title' => 'Foreign-owner parent']);
    Task::factory()->forProject($project)->withParent($visibleParent)->create(['number' => 4, 'title' => 'Nested parent']);
    Task::factory()->forProject($project)->deleted()->create(['number' => 5, 'title' => 'Deleted parent']);

    $this->actingAs($owner)
        ->get(route('projects.tasks.create', $project))
        ->assertOk()
        ->assertViewHas('parentOptions', function ($parentOptions) use ($visibleParent): bool {
            return $parentOptions->all() === [[
                'id' => $visibleParent->getKey(),
                'display_key' => 'PLAN-2',
                'title' => 'Visible parent',
            ]];
        });
});

test('a valid task creation post redirects to the projects interface with the derived key message', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);

    $response = $this->actingAs($owner)->post(route('projects.tasks.store', $project), ['title' => 'Create from the form']);

    $response->assertRedirect(route('projects.index', absolute: false))
        ->assertSessionHas('status', 'PLAN-1 created.');
});

test('an invalid task creation post preserves the title and returns a field-level error', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);

    $response = $this->actingAs($owner)->from(route('projects.tasks.create', $project))->post(route('projects.tasks.store', $project), [
        'title' => 'Keep this title',
        'status' => 'INVALID',
    ]);

    $response->assertRedirect(route('projects.tasks.create', $project, absolute: false))
        ->assertSessionHasInput('title', 'Keep this title')
        ->assertSessionHasErrors(['status']);
});

test('a forged unavailable parent post returns a field error without creating a task or advancing the counter', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $foreignProject = Project::factory()->for($other)->create(['key' => 'OTHER']);
    $foreignParent = Task::factory()->forProject($foreignProject)->create(['number' => 1]);

    $response = $this->actingAs($owner)
        ->from(route('projects.tasks.create', $project))
        ->post(route('projects.tasks.store', $project), [
            'title' => 'Forged parent',
            'parent_task_id' => $foreignParent->id,
        ]);

    $response->assertRedirect(route('projects.tasks.create', $project, absolute: false))
        ->assertSessionHasErrors(['parent_task_id']);
    expect(Task::query()->count())->toBe(1)
        ->and($project->fresh()->next_task_number)->toBe(1);
});

test('a foreign task creation URL returns not found without rendering its project or parent options', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $foreignProject = Project::factory()->for($other)->create(['name' => 'Foreign Project', 'key' => 'OTHER']);
    $foreignParent = Task::factory()->forProject($foreignProject)->create(['number' => 1, 'title' => 'Foreign parent option']);

    $this->actingAs($owner)
        ->get("/projects/{$foreignProject->id}/tasks/create")
        ->assertNotFound()
        ->assertDontSee($foreignProject->name)
        ->assertDontSee($foreignParent->title);
});
