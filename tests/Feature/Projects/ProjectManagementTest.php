<?php

use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Projects\Actions\ArchiveProject;
use App\Domain\Projects\Actions\ChangeProjectStatus;
use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Actions\RestoreProject;
use App\Domain\Projects\Actions\UpdateProject;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Queries\ProjectIndexQuery;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('project status changes only through the explicit status action', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->planned()->create();
    $task = Task::factory()->forProject($project)->done()->create();

    expect($project->fresh()->status)->toBe(ProjectStatus::PLANNED);

    $changed = (new ChangeProjectStatus)->handle($user, $project, ProjectStatus::ACTIVE);

    expect($changed->fresh()->status)->toBe(ProjectStatus::ACTIVE)
        ->and($task->fresh()->project->status)->toBe(ProjectStatus::ACTIVE);

    (new ChangeProjectStatus)->handle($user, $project, ProjectStatus::COMPLETED);
    expect($project->fresh()->status)->toBe(ProjectStatus::COMPLETED);
});

test('archive and restore preserve project tasks and activity while changing only archive state', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->active()->create(['archived_at' => null]);
    $task = Task::factory()->forProject($project)->create();
    $activity = TaskActivity::factory()->forTask($task)->statusChanged()->create();
    $projectBeforeArchive = $project->only(['id', 'user_id', 'name', 'key', 'description', 'status', 'start_on', 'target_on', 'next_task_number']);
    $taskBeforeArchive = $task->only(['id', 'user_id', 'project_id', 'number', 'title', 'description', 'status', 'priority', 'due_on']);
    $activityBeforeArchive = $activity->only(['id', 'user_id', 'project_id', 'task_id', 'event_type', 'field', 'old_value', 'new_value', 'metadata', 'created_at']);

    $archived = (new ArchiveProject)->handle($user, $project);

    expect($archived->archived_at)->not->toBeNull()
        ->and($archived->status)->toBe(ProjectStatus::ACTIVE)
        ->and($task->fresh())->not->toBeNull()
        ->and($activity->fresh())->not->toBeNull();

    $restored = (new RestoreProject)->handle($user, $archived);

    $restoredTask = $restored->tasks()->whereKey($task)->first();
    $restoredActivity = $restored->taskActivities()->whereKey($activity)->first();

    expect($restored->id)->toBe($projectBeforeArchive['id'])
        ->and($restored->user_id)->toBe($projectBeforeArchive['user_id'])
        ->and($restored->name)->toBe($projectBeforeArchive['name'])
        ->and($restored->key)->toBe($projectBeforeArchive['key'])
        ->and($restored->description)->toBe($projectBeforeArchive['description'])
        ->and($restored->status)->toBe(ProjectStatus::ACTIVE)
        ->and($restored->start_on?->toDateString())->toBe($projectBeforeArchive['start_on']?->toDateString())
        ->and($restored->target_on?->toDateString())->toBe($projectBeforeArchive['target_on']?->toDateString())
        ->and($restored->next_task_number)->toBe($projectBeforeArchive['next_task_number'])
        ->and($restored->archived_at)->toBeNull()
        ->and($restoredTask?->id)->toBe($taskBeforeArchive['id'])
        ->and($restoredTask?->user_id)->toBe($taskBeforeArchive['user_id'])
        ->and($restoredTask?->project_id)->toBe($taskBeforeArchive['project_id'])
        ->and($restoredTask?->number)->toBe($taskBeforeArchive['number'])
        ->and($restoredTask?->title)->toBe($taskBeforeArchive['title'])
        ->and($restoredTask?->description)->toBe($taskBeforeArchive['description'])
        ->and($restoredTask?->status)->toEqual($taskBeforeArchive['status'])
        ->and($restoredTask?->priority)->toEqual($taskBeforeArchive['priority'])
        ->and($restoredTask?->due_on?->toDateString())->toBe($taskBeforeArchive['due_on']?->toDateString())
        ->and($restoredActivity?->id)->toBe($activityBeforeArchive['id'])
        ->and($restoredActivity?->user_id)->toBe($activityBeforeArchive['user_id'])
        ->and($restoredActivity?->project_id)->toBe($activityBeforeArchive['project_id'])
        ->and($restoredActivity?->task_id)->toBe($activityBeforeArchive['task_id'])
        ->and($restoredActivity?->event_type)->toEqual($activityBeforeArchive['event_type'])
        ->and($restoredActivity?->field)->toBe($activityBeforeArchive['field'])
        ->and($restoredActivity?->old_value)->toEqual($activityBeforeArchive['old_value'])
        ->and($restoredActivity?->new_value)->toEqual($activityBeforeArchive['new_value'])
        ->and($restoredActivity?->metadata)->toEqual($activityBeforeArchive['metadata'])
        ->and($restoredActivity?->created_at)->toEqual($activityBeforeArchive['created_at']);
});

test('project lifecycle actions reject direct calls from a non-owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);

    expect(fn (): Project => (new UpdateProject)->handle($other, $project, ['name' => 'Stolen', 'key' => 'STEAL']))
        ->toThrow(AuthorizationException::class);
    expect(fn (): Project => (new ChangeProjectStatus)->handle($other, $project, ProjectStatus::ACTIVE))
        ->toThrow(AuthorizationException::class);
    expect(fn (): Project => (new ArchiveProject)->handle($other, $project))
        ->toThrow(AuthorizationException::class);
    expect(fn (): Project => (new RestoreProject)->handle($other, $project))
        ->toThrow(AuthorizationException::class);
});

test('project policy denies every lifecycle capability to a different owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $policy = app(\App\Policies\ProjectPolicy::class);

    expect($policy->viewAny($owner))->toBeTrue()
        ->and($policy->viewAny($other))->toBeTrue()
        ->and($policy->view($other, $project))->toBeFalse()
        ->and($policy->create($other))->toBeTrue()
        ->and($policy->update($other, $project))->toBeFalse()
        ->and($policy->changeStatus($other, $project))->toBeFalse()
        ->and($policy->archive($other, $project))->toBeFalse()
        ->and($policy->restore($other, $project))->toBeFalse();
});

test('project index is owner scoped, active by default, and exposes derived top-level progress', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $active = Project::factory()->for($owner)->active()->create(['key' => 'ACTIVE']);
    $archived = Project::factory()->for($owner)->active()->create(['key' => 'ARCHIVE', 'archived_at' => now()]);
    $foreign = Project::factory()->for($other)->active()->create(['key' => 'FOREIGN']);
    Task::factory()->forProject($active)->done()->create();
    $inProgress = Task::factory()->forProject($active)->create(['status' => TaskStatus::IN_PROGRESS]);
    Task::factory()->forProject($active)->cancelled()->create();
    $deleted = Task::factory()->forProject($active)->create();
    $deleted->delete();
    Task::factory()->forProject($active)->withParent($inProgress)->done()->create();

    $results = (new ProjectIndexQuery)->paginate($owner);

    expect($results->getCollection()->pluck('id')->all())->toBe([$active->id])
        ->and($results->first()->getAttribute('eligible_task_count'))->toBe(2)
        ->and($results->first()->getAttribute('completed_task_count'))->toBe(1)
        ->and($results->first()->getAttribute('progress_percent'))->toBe(50)
        ->and($active->tasks()->whereKey($deleted)->exists())->toBeFalse()
        ->and($results->getCollection()->pluck('id'))->not->toContain($archived->id)
        ->and($results->getCollection()->pluck('id'))->not->toContain($foreign->id);
});

test('project index supports search status archive target filters and deterministic sorting', function (): void {
    $owner = User::factory()->create();
    $ids = static fn ($paginator): array => $paginator->getCollection()->pluck('id')->all();
    $alpha = Project::factory()->for($owner)->active()->create([
        'name' => 'Operations Console', 'key' => 'ALPHA', 'target_on' => '2026-09-10',
        'updated_at' => Carbon::parse('2026-08-27 10:00:00'),
    ]);
    $beta = Project::factory()->for($owner)->onHold()->create([
        'name' => 'Zeta Planning', 'key' => 'BETA', 'target_on' => '2026-08-20',
        'updated_at' => Carbon::parse('2026-08-27 11:00:00'), 'archived_at' => now(),
    ]);

    expect($ids((new ProjectIndexQuery)->paginate($owner, ['search' => 'alpha'])))->toBe([$alpha->id]);
    expect($ids((new ProjectIndexQuery)->paginate($owner, ['status' => ProjectStatus::ON_HOLD, 'archived' => true])))->toBe([$beta->id]);
    expect($ids((new ProjectIndexQuery)->paginate($owner, ['target_date' => 'overdue', 'archived' => true])))->toContain($beta->id);
    expect($ids((new ProjectIndexQuery)->paginate($owner, ['archived' => 'all', 'sort' => 'name'])))->toBe([$alpha->id, $beta->id]);
});

test('project create and edit HTTP flows authenticate and return actionable validation errors', function (): void {
    $user = User::factory()->create();

    $this->get('/projects/create')->assertRedirect('/login');
    $invalidResponse = $this->actingAs($user)->post('/projects', [
        'name' => '', 'key' => 'bad key', 'start_on' => '2026-08-20', 'target_on' => '2026-08-19',
    ])->assertSessionHasErrors(['name', 'key', 'target_on']);
    $errors = $invalidResponse->session()->get('errors');
    expect($errors->get('name'))->not->toBeEmpty()
        ->and($errors->get('key'))->not->toBeEmpty()
        ->and($errors->get('target_on'))->not->toBeEmpty();

    $createResponse = $this->actingAs($user)->post('/projects', [
        'name' => '  PlanOps Console  ',
        'key' => 'plan',
    ]);
    $created = $user->projects()->where('key', 'PLAN')->firstOrFail();

    $createResponse->assertRedirect(route('projects.edit', $created, absolute: false));
    expect($created->name)->toBe('PlanOps Console')
        ->and($created->key)->toBe('PLAN')
        ->and($created->status)->toBe(ProjectStatus::PLANNED)
        ->and($created->next_task_number)->toBe(1);

    $project = Project::factory()->for($user)->create(['key' => 'EDIT']);
    $this->actingAs($user)->get("/projects/{$project->id}/edit")->assertOk();
    $this->actingAs($user)->patch("/projects/{$project->id}", [
        'name' => '  Updated project  ', 'key' => 'updated',
    ])->assertRedirect();
    expect($project->fresh()->name)->toBe('Updated project')
        ->and($project->fresh()->key)->toBe('UPDATED');
});

test('project lifecycle HTTP mutations require authentication and use separate status archive and restore endpoints', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['key' => 'PLAN']);

    foreach ([
        ['post', '/projects'],
        ['post', "/projects/{$project->id}/status"],
        ['post', "/projects/{$project->id}/archive"],
        ['post', "/projects/{$project->id}/restore"],
    ] as [$method, $uri]) {
        $this->{$method}($uri)->assertRedirect('/login');
    }

    $this->actingAs($user)->post("/projects/{$project->id}/status", ['status' => 'ACTIVE'])->assertRedirect();
    $this->actingAs($user)->post("/projects/{$project->id}/archive")->assertRedirect();
    $this->actingAs($user)->post("/projects/{$project->id}/restore")->assertRedirect();
    expect($project->fresh()->archived_at)->toBeNull();
});

test('cross-user project identifiers resolve as not found for HTTP reads and mutations', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);

    $this->actingAs($other)->get("/projects/{$project->id}/edit")->assertNotFound();
    $this->actingAs($other)->patch("/projects/{$project->id}", ['name' => 'Stolen', 'key' => 'STEAL'])->assertNotFound();
    $this->actingAs($other)->post("/projects/{$project->id}/status", ['status' => 'ACTIVE'])->assertNotFound();
    $this->actingAs($other)->post("/projects/{$project->id}/archive")->assertNotFound();
    $this->actingAs($other)->post("/projects/{$project->id}/restore")->assertNotFound();
});
