<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;

it('streams an owned task csv with stable fields and serialized labels', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'PlanOps', 'key' => 'PLAN']);
    $parent = Task::factory()->forProject($project)->create([
        'number' => 1,
        'title' => 'Ship export',
        'status' => TaskStatus::IN_PROGRESS,
        'priority' => TaskPriority::HIGH,
        'due_on' => '2026-09-05',
        'description' => 'This must not be exported.',
    ]);
    $child = Task::factory()->forProject($project)->withParent($parent)->create(['number' => 2, 'title' => 'Add tests']);
    $label = Label::factory()->forUser($user)->create(['name' => 'Important', 'normalized_name' => 'important']);
    $parent->labels()->attach($label);

    $response = $this->actingAs($user)->get(route('exports.tasks'));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('content-disposition', 'attachment; filename=planops-tasks.csv');
    $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', $response->streamedContent())));

    expect(str_getcsv($lines[0]))->toBe(['key', 'project', 'parent', 'title', 'status', 'priority', 'due_on', 'labels', 'created_at', 'updated_at'])
        ->and($lines[1])->toContain('PLAN-1', 'PlanOps', 'Ship export', 'IN_PROGRESS', 'HIGH', 'Important')
        ->and($lines[2])->toContain('PLAN-2', 'PLAN-1', 'Add tests')
        ->and($response->streamedContent())->not->toContain('This must not be exported.');
});

it('exports only the current users projects and activity records', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($user)->create(['key' => 'OWN']);
    $foreignProject = Project::factory()->for($other)->create(['key' => 'OTHER']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);
    $foreignTask = Task::factory()->forProject($foreignProject)->create(['number' => 1]);
    TaskActivity::factory()->forTask($task)->create([
        'event_type' => TaskActivityType::STATUS_CHANGED,
        'field' => 'status',
        'old_value' => ['status' => 'NOT_STARTED'],
        'new_value' => ['status' => 'IN_PROGRESS'],
    ]);
    TaskActivity::factory()->forTask($foreignTask)->create(['event_type' => TaskActivityType::TASK_CREATED]);

    $projectsResponse = $this->actingAs($user)->get(route('exports.projects'));
    $projectsCsv = $projectsResponse->streamedContent();
    expect($projectsResponse->getStatusCode())->toBe(200)
        ->and($projectsCsv)->toContain('OWN')
        ->and($projectsCsv)->not->toContain('OTHER');
    $this->actingAs($user)->get(route('exports.activity', ['format' => 'json']))
        ->assertOk()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonCount(1)
        ->assertJsonPath('0.task_key', 'OWN-'.$task->number)
        ->assertJsonPath('0.event_type', 'STATUS_CHANGED');
});

it('forbids complete exports for a viewer who is only a project member', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'owner_id' => $owner->id,
    ]);
    ProjectMembership::factory()->owner()->create([
        'project_id' => $project->id,
        'user_id' => $owner->id,
    ]);
    ProjectMembership::factory()->create([
        'project_id' => $project->id,
        'user_id' => $member->id,
    ]);

    $this->actingAs($member)->get(route('exports.projects'))->assertForbidden();
    $this->actingAs($member)->get(route('exports.tasks'))->assertForbidden();
    $this->actingAs($member)->get(route('exports.activity', ['format' => 'json']))->assertForbidden();
});

it('limits mixed-role complete exports to projects where the viewer is an admin', function (): void {
    $adminProjectOwner = User::factory()->create();
    $memberProjectOwner = User::factory()->create();
    $viewer = User::factory()->create();
    $adminProject = Project::factory()->create([
        'user_id' => $adminProjectOwner->id,
        'owner_id' => $adminProjectOwner->id,
        'key' => 'ADMIN',
        'name' => 'Admin export project',
    ]);
    $memberProject = Project::factory()->create([
        'user_id' => $memberProjectOwner->id,
        'owner_id' => $memberProjectOwner->id,
        'key' => 'MEMBER',
        'name' => 'Member only project',
    ]);
    ProjectMembership::factory()->owner()->create([
        'project_id' => $adminProject->id,
        'user_id' => $adminProjectOwner->id,
    ]);
    ProjectMembership::factory()->admin()->create([
        'project_id' => $adminProject->id,
        'user_id' => $viewer->id,
    ]);
    ProjectMembership::factory()->owner()->create([
        'project_id' => $memberProject->id,
        'user_id' => $memberProjectOwner->id,
    ]);
    ProjectMembership::factory()->create([
        'project_id' => $memberProject->id,
        'user_id' => $viewer->id,
    ]);
    $adminTask = Task::factory()->forProject($adminProject)->create([
        'number' => 1,
        'title' => 'Admin export task',
    ]);
    $memberTask = Task::factory()->forProject($memberProject)->create([
        'number' => 1,
        'title' => 'Member only task',
    ]);
    TaskActivity::factory()->forTask($adminTask)->create(['event_type' => TaskActivityType::TASK_CREATED]);
    TaskActivity::factory()->forTask($memberTask)->create(['event_type' => TaskActivityType::STATUS_CHANGED]);

    $projectsResponse = $this->actingAs($viewer)->get(route('exports.projects'));
    expect($projectsResponse->getStatusCode())->toBe(200)
        ->and($projectsResponse->streamedContent())->toContain('ADMIN')
        ->and($projectsResponse->streamedContent())->not->toContain('MEMBER');

    $tasksResponse = $this->actingAs($viewer)->get(route('exports.tasks'));
    expect($tasksResponse->getStatusCode())->toBe(200)
        ->and($tasksResponse->streamedContent())->toContain('Admin export task')
        ->and($tasksResponse->streamedContent())->not->toContain('Member only task');

    $this->actingAs($viewer)->get(route('exports.activity', ['format' => 'json']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.task_key', 'ADMIN-1')
        ->assertJsonMissing(['task_key' => 'MEMBER-1']);
});
