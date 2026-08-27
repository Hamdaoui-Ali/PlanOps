<?php

use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('task label attach and sync only require the documented creation timestamp', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $task = Task::factory()->forProject($project)->create();
    $label = Label::factory()->forUser($owner)->create();

    $task->labels()->attach($label);

    expect(DB::table('task_label')->where([
        'task_id' => $task->id,
        'label_id' => $label->id,
    ])->value('created_at'))->not->toBeNull();

    $task->labels()->detach($label);
    $task->labels()->sync([$label->id]);

    expect(DB::table('task_label')->where([
        'task_id' => $task->id,
        'label_id' => $label->id,
    ])->value('created_at'))->not->toBeNull();

    $task->labels()->detach($label);
    $label->tasks()->attach($task);

    expect(DB::table('task_label')->where([
        'task_id' => $task->id,
        'label_id' => $label->id,
    ])->value('created_at'))->not->toBeNull();

    $label->tasks()->detach($task);
    $label->tasks()->sync([$task->id]);

    expect(DB::table('task_label')->where([
        'task_id' => $task->id,
        'label_id' => $label->id,
    ])->value('created_at'))->not->toBeNull();
});
