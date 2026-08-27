<?php

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
