<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Activity\Queries\TaskActivityFeedQuery;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('global activity feed scopes rows before applying filters', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ownerProject = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $ownerOtherProject = Project::factory()->for($owner)->create(['key' => 'BACK']);
    $otherProject = Project::factory()->for($other)->create(['key' => 'OPS']);
    $ownerTask = Task::factory()->forProject($ownerProject)->create(['number' => 1]);
    $ownerOtherTask = Task::factory()->forProject($ownerOtherProject)->create(['number' => 1]);
    $otherTask = Task::factory()->forProject($otherProject)->create(['number' => 1]);
    $ownerActivity = TaskActivity::factory()->forTask($ownerTask)->statusChanged()->create([
        'created_at' => Carbon::parse('2026-08-27 12:00:00 UTC'),
    ]);
    $ownerProjectActivity = TaskActivity::factory()->forTask($ownerOtherTask)->statusChanged()->create([
        'created_at' => Carbon::parse('2026-08-27 11:00:00 UTC'),
    ]);
    $ownerEventActivity = TaskActivity::factory()->forTask($ownerTask)->create([
        'created_at' => Carbon::parse('2026-08-27 10:00:00 UTC'),
    ]);
    TaskActivity::factory()->forTask($otherTask)->statusChanged()->create([
        'created_at' => Carbon::parse('2026-08-27 13:00:00 UTC'),
    ]);

    $feed = (new TaskActivityFeedQuery)->paginate($owner, [
        'project_id' => $otherProject->id,
        'task_id' => $otherTask->id,
        'event_type' => TaskActivityType::STATUS_CHANGED,
    ]);

    expect($feed->total())->toBe(0);
    expect((new TaskActivityFeedQuery)->paginate($owner, ['project_id' => $ownerProject->id])->getCollection()->pluck('id')->all())
        ->toBe([$ownerActivity->id, $ownerEventActivity->id]);
    expect((new TaskActivityFeedQuery)->paginate($owner, ['task_id' => $ownerTask->id])->getCollection()->pluck('id')->all())
        ->toBe([$ownerActivity->id, $ownerEventActivity->id]);
    expect((new TaskActivityFeedQuery)->paginate($owner, ['event_type' => TaskActivityType::STATUS_CHANGED])->getCollection()->pluck('id')->all())
        ->toBe([$ownerActivity->id, $ownerProjectActivity->id]);
});

test('global activity feed filters UTC bounds and orders newest first', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);
    TaskActivity::factory()->forTask($task)->create([
        'created_at' => Carbon::parse('2026-08-27 08:59:59 UTC'),
    ]);
    $fromBoundary = TaskActivity::factory()->forTask($task)->create([
        'created_at' => Carbon::parse('2026-08-27 09:00:00 UTC'),
    ]);
    $firstTie = TaskActivity::factory()->forTask($task)->create([
        'created_at' => Carbon::parse('2026-08-27 10:00:00 UTC'),
    ]);
    $secondTie = TaskActivity::factory()->forTask($task)->create([
        'created_at' => Carbon::parse('2026-08-27 10:00:00 UTC'),
    ]);
    TaskActivity::factory()->forTask($task)->create([
        'created_at' => Carbon::parse('2026-08-27 12:00:00 UTC'),
    ]);

    $feed = (new TaskActivityFeedQuery)->paginate($owner, [
        'from' => Carbon::parse('2026-08-27 09:00:00 UTC'),
        'until' => Carbon::parse('2026-08-27 12:00:00 UTC'),
    ]);

    expect($feed->getCollection()->pluck('id')->all())
        ->toBe([$secondTie->id, $firstTie->id, $fromBoundary->id]);
});

test('global activity pagination defaults to fifty rows and keeps deleted task context', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);
    TaskActivity::factory()->count(51)->forTask($task)->create();
    $task->delete();

    $feed = (new TaskActivityFeedQuery)->paginate($owner);

    expect($feed->perPage())->toBe(50);
    expect($feed->first()->task)->not->toBeNull();
    expect($feed->first()->task->trashed())->toBeTrue();
});

test('task activity timeline is owner-scoped and oldest first', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $otherProject = Project::factory()->for($other)->create(['key' => 'OPS']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);
    $otherTask = Task::factory()->forProject($otherProject)->create(['number' => 1]);
    $older = TaskActivity::factory()->forTask($task)->create([
        'created_at' => Carbon::parse('2026-08-27 09:00:00 UTC'),
    ]);
    $newer = TaskActivity::factory()->forTask($task)->create([
        'created_at' => Carbon::parse('2026-08-27 10:00:00 UTC'),
    ]);
    TaskActivity::factory()->forTask($otherTask)->create();

    $timeline = (new TaskActivityFeedQuery)->forTask($owner, $task);

    expect($timeline->pluck('id')->all())->toBe([$older->id, $newer->id]);
    expect((new TaskActivityFeedQuery)->forTask($owner, $otherTask))->toHaveCount(0);
});
