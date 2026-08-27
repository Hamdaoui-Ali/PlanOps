<?php

use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Actions\UpdateProject;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('project creation trims names, normalizes keys, and applies lifecycle defaults', function (): void {
    $user = User::factory()->create();

    $project = (new CreateProject)->handle($user, [
        'name' => '  PlanOps  ',
        'key' => ' po ',
    ]);

    expect($project->name)->toBe('PlanOps')
        ->and($project->key)->toBe('PO')
        ->and($project->status)->toBe(ProjectStatus::PLANNED)
        ->and($project->next_task_number)->toBe(1);
});

test('project creation rejects keys outside the uppercase ascii two-to-ten character contract', function (string $key): void {
    $user = User::factory()->create();

    expect(fn (): Project => (new CreateProject)->handle($user, [
        'name' => 'Invalid key project',
        'key' => $key,
    ]))->toThrow(ValidationException::class);
})->with(['P', 'PROJECT-1', 'PROJECT NAME', 'équipe', 'TOO-LONG-KEY']);

test('project keys are unique per owner but reusable by another owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    (new CreateProject)->handle($owner, ['name' => 'Owner project', 'key' => 'PLAN']);

    expect(fn (): Project => (new CreateProject)->handle($owner, [
        'name' => 'Duplicate project',
        'key' => 'plan',
    ]))->toThrow(ValidationException::class);

    $otherProject = (new CreateProject)->handle($other, ['name' => 'Other project', 'key' => 'plan']);

    expect($otherProject->user_id)->toBe($other->id)
        ->and($otherProject->key)->toBe('PLAN');
});

test('project creation rejects a target date before its start date', function (): void {
    $user = User::factory()->create();

    expect(fn (): Project => (new CreateProject)->handle($user, [
        'name' => 'Bad dates',
        'key' => 'DATES',
        'start_on' => '2026-08-20',
        'target_on' => '2026-08-19',
    ]))->toThrow(ValidationException::class);
});

test('project creation accepts a target date equal to its start date', function (): void {
    $user = User::factory()->create();

    $project = (new CreateProject)->handle($user, [
        'name' => 'Same day project',
        'key' => 'SAMEDAY',
        'start_on' => '2026-08-20',
        'target_on' => '2026-08-20',
    ]);

    expect($project->start_on?->toDateString())->toBe('2026-08-20')
        ->and($project->target_on?->toDateString())->toBe('2026-08-20');
});

test('a project key can change before the project has ever contained a task', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['key' => 'OLD']);

    $updated = (new UpdateProject)->handle($user, $project, ['name' => 'Renamed', 'key' => 'NEW']);

    expect($updated->fresh()->key)->toBe('NEW');
});

test('a project key cannot change after a task has been soft deleted', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['key' => 'LOCK']);
    Task::factory()->forProject($project)->deleted()->create();

    expect(fn (): Project => (new UpdateProject)->handle($user, $project, [
        'name' => $project->name,
        'key' => 'NEWKEY',
    ]))->toThrow(ValidationException::class);
});
