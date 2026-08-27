<?php

use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Identity\Models\UserPreference;
use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use Database\Factories\LabelFactory;
use Database\Factories\ProjectFactory;
use Database\Factories\TaskActivityFactory;
use Database\Factories\TaskFactory;
use Database\Factories\UserPreferenceFactory;

test('domain models resolve their flat database factories', function (string $model, string $factory): void {
    expect($model::factory())->toBeInstanceOf($factory);
})->with([
    'project' => [Project::class, ProjectFactory::class],
    'task' => [Task::class, TaskFactory::class],
    'label' => [Label::class, LabelFactory::class],
    'task activity' => [TaskActivity::class, TaskActivityFactory::class],
    'user preference' => [UserPreference::class, UserPreferenceFactory::class],
]);
