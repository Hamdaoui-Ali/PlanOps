<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Database\Factories\UserFactory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function seedPlanOpsFixtures(): void
{
    $previousHashingConfig = config('hashing');
    $factoryPassword = new ReflectionProperty(UserFactory::class, 'password');
    $previousFactoryPassword = $factoryPassword->isInitialized()
        ? $factoryPassword->getValue()
        : null;

    try {
        config(['hashing.bcrypt.rounds' => 12]);
        Hash::forgetDrivers();

        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]);
    } finally {
        config(['hashing' => $previousHashingConfig]);
        Hash::forgetDrivers();
        $factoryPassword->setValue($previousFactoryPassword);
    }
}

function resetPlanOpsFixtures(): void
{
    foreach ([
        'task_activities',
        'task_label',
        'tasks',
        'labels',
        'projects',
        'user_preferences',
        'users',
    ] as $table) {
        DB::table($table)->truncate();
    }
}

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
    seedPlanOpsFixtures();

    $firstSeed = seededFixtureSnapshot();

    resetPlanOpsFixtures();
    seedPlanOpsFixtures();

    expect(seededFixtureSnapshot())->toBe($firstSeed);
});

test('the database seeder restores hashing state for later user factories', function (): void {
    $previousHashingConfig = config('hashing');

    seedPlanOpsFixtures();

    expect(config('hashing'))->toBe($previousHashingConfig);
    expect(User::factory()->make())->toBeInstanceOf(User::class);
});

test('the database seeder advances every project task counter past soft-deleted tasks', function (): void {
    seedPlanOpsFixtures();

    Project::query()->each(function (Project $project): void {
        $maximumTaskNumber = Task::withTrashed()
            ->where('project_id', $project->id)
            ->max('number');

        expect($project->next_task_number)->toBeGreaterThan($maximumTaskNumber ?? 0);
    });
});
