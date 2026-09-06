<?php

use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('enforces the collaboration task policy matrix', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    $admin = User::factory()->create();
    $member = User::factory()->create();
    ProjectMembership::factory()->admin()->create(['project_id' => $project->id, 'user_id' => $admin->id]);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id, 'role' => ProjectRole::MEMBER]);
    $task = Task::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id, 'assignee_id' => $member->id]);

    expect($owner->can('update', $task))->toBeTrue()
        ->and($admin->can('update', $task))->toBeTrue()
        ->and($member->can('update', $task))->toBeFalse()
        ->and($member->can('changeStatus', $task))->toBeTrue()
        ->and($member->can('assign', $task))->toBeFalse()
        ->and($owner->can('assign', $task))->toBeTrue();
});

it('keeps ownership permanent and gives admins owner-level operational access', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    $admin = User::factory()->create();
    $member = User::factory()->create();
    ProjectMembership::factory()->admin()->create(['project_id' => $project->id, 'user_id' => $admin->id]);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);
    $task = Task::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id, 'assignee_id' => $member->id]);

    expect($admin->can('update', $project))->toBeTrue()
        ->and($admin->can('delete', $task))->toBeTrue()
        ->and($admin->can('assign', $task))->toBeTrue()
        ->and($member->can('update', $project))->toBeFalse()
        ->and($member->can('delete', $task))->toBeFalse()
        ->and($member->can('changePriority', $task))->toBeFalse()
        ->and($project->fresh()->owner_id)->toBe($owner->id)
        ->and(Route::has('projects.team.members.transfer'))->toBeFalse();
});

it('shows only permitted project and task controls to each collaborator role', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    $admin = User::factory()->create();
    $member = User::factory()->create();
    ProjectMembership::factory()->admin()->create(['project_id' => $project->id, 'user_id' => $admin->id]);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);
    $task = Task::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id, 'assignee_id' => $member->id]);
    $unassigned = Task::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id]);

    $this->actingAs($member)->get(route('projects.show', $project))
        ->assertOk()
        ->assertDontSee('Edit project')
        ->assertDontSee('New task');
    $this->actingAs($admin)->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Edit project')
        ->assertSee('New task');

    $this->actingAs($member)->get(route('tasks.show', $task))
        ->assertOk()
        ->assertDontSee('name="title"', false)
        ->assertDontSee('Delete task')
        ->assertSee('Change status');
    $this->actingAs($admin)->get(route('tasks.show', $task))
        ->assertOk()
        ->assertSee('name="title"', false)
        ->assertSee('Delete task');
    $this->actingAs($member)->get(route('tasks.show', $unassigned))
        ->assertOk()
        ->assertDontSee('Change status');
});
