<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

function seededFixtureSnapshot(): array
{
    return [
        'users' => DB::table('users')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        'user_preferences' => DB::table('user_preferences')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        'projects' => DB::table('projects')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        'tasks' => DB::table('tasks')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        'labels' => DB::table('labels')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        'task_label' => DB::table('task_label')->orderBy('task_id')->orderBy('label_id')->get()->map(fn (object $row): array => (array) $row)->all(),
        'task_activities' => DB::table('task_activities')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
    ];
}

test('the database seeder produces reproducible persisted fixtures', function () {
    Artisan::call('migrate:fresh', ['--seed' => true]);

    $firstSeed = seededFixtureSnapshot();

    Artisan::call('migrate:fresh', ['--seed' => true]);

    expect(seededFixtureSnapshot())->toBe($firstSeed);
});

test('the database seeder advances every project task counter past soft-deleted tasks', function (): void {
    Artisan::call('migrate:fresh', ['--seed' => true]);

    Project::query()->each(function (Project $project): void {
        $maximumTaskNumber = Task::withTrashed()
            ->where('project_id', $project->id)
            ->max('number');

        expect($project->next_task_number)->toBeGreaterThan($maximumTaskNumber ?? 0);
    });
});
