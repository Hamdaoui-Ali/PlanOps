<?php

use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owned scopes never return another users records', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $ownedProject = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $otherProject = Project::factory()->for($other)->create(['key' => 'OPS']);
    $ownedTask = Task::factory()->forProject($ownedProject)->create(['number' => 1]);
    $otherTask = Task::factory()->forProject($otherProject)->create(['number' => 1]);
    $ownedLabel = Label::factory()->forUser($owner)->create(['normalized_name' => 'owned']);
    $otherLabel = Label::factory()->forUser($other)->create(['normalized_name' => 'owned']);
    $ownedActivity = TaskActivity::factory()->forTask($ownedTask)->create();
    $otherActivity = TaskActivity::factory()->forTask($otherTask)->create();

    expect(Project::query()->ownedBy($owner)->pluck('id')->all())
        ->toBe([$ownedProject->id]);
    expect(Task::query()->ownedBy($owner)->pluck('id')->all())
        ->toBe([$ownedTask->id]);
    expect(Label::query()->ownedBy($owner)->pluck('id')->all())
        ->toBe([$ownedLabel->id]);
    expect(TaskActivity::query()->ownedBy($owner)->pluck('id')->all())
        ->toBe([$ownedActivity->id]);

    expect(Project::query()->ownedBy($owner->id)->pluck('id')->all())
        ->toBe([$ownedProject->id]);
    expect(Task::query()->ownedBy($owner->id)->pluck('id')->all())
        ->toBe([$ownedTask->id]);
    expect(Label::query()->ownedBy($owner->id)->pluck('id')->all())
        ->toBe([$ownedLabel->id]);
    expect(TaskActivity::query()->ownedBy($owner->id)->pluck('id')->all())
        ->toBe([$ownedActivity->id]);

    expect($owner->projects()->pluck('id')->all())->toBe([$ownedProject->id]);
    expect($owner->tasks()->pluck('id')->all())->toBe([$ownedTask->id]);
    expect($owner->labels()->pluck('id')->all())->toBe([$ownedLabel->id]);
    expect($owner->taskActivities()->pluck('id')->all())->toBe([$ownedActivity->id]);
    expect($otherProject->user_id)->toBe($other->id);
    expect($otherTask->project_id)->toBe($otherProject->id);
    expect($otherLabel->user_id)->toBe($other->id);
    expect($otherActivity->task_id)->toBe($otherTask->id);
});

test('valid activity fixtures retain task and project ownership context', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);
    $activity = TaskActivity::factory()->forTask($task)->create();

    expect($task->user_id)->toBe($owner->id);
    expect($task->project->user_id)->toBe($owner->id);
    expect($activity->user_id)->toBe($owner->id);
    expect($activity->project_id)->toBe($project->id);
    expect($activity->task_id)->toBe($task->id);
});

test('activity history retains its task relationship after soft deletion', function () {
    $owner = User::factory()->create();
    $project = Project::create([
        'user_id' => $owner->id,
        'name' => 'PlanOps',
        'key' => 'PLAN',
    ]);
    $task = Task::create([
        'user_id' => $owner->id,
        'project_id' => $project->id,
        'number' => 1,
        'title' => 'Retain task history',
    ]);
    $activity = TaskActivity::create([
        'user_id' => $owner->id,
        'project_id' => $project->id,
        'task_id' => $task->id,
        'event_type' => TaskActivityType::TASK_CREATED,
    ]);

    $task->delete();
    $reloadedActivity = TaskActivity::query()->findOrFail($activity->id);

    expect($reloadedActivity->task)->not->toBeNull();
    expect($reloadedActivity->task->getKey())->toBe($task->getKey());
    expect($reloadedActivity->task->trashed())->toBeTrue();
});
