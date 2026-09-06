<?php

use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;

function collaborationProject(User $owner, ProjectStatus $status = ProjectStatus::ACTIVE): Project
{
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'owner_id' => $owner->id,
        'status' => $status,
    ]);

    ProjectMembership::factory()->owner()->create([
        'project_id' => $project->id,
        'user_id' => $owner->id,
    ]);

    return $project;
}

function collaborationMember(Project $project, ?ProjectRole $role = null): User
{
    $user = User::factory()->create();

    ProjectMembership::factory()->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'role' => $role ?? ProjectRole::MEMBER,
    ]);

    return $user;
}

it('returns projects available through active membership', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $ownedProject = collaborationProject($owner);
    $memberProject = collaborationProject(User::factory()->create());

    ProjectMembership::factory()->create([
        'project_id' => $memberProject->id,
        'user_id' => $member->id,
    ]);
    $removedProject = collaborationProject(User::factory()->create());
    ProjectMembership::factory()->create([
        'project_id' => $removedProject->id,
        'user_id' => $member->id,
        'removed_at' => now(),
    ]);

    expect(Project::query()->accessibleBy($member)->pluck('id')->all())
        ->toBe([$memberProject->id])
        ->and(Project::query()->accessibleBy($owner)->pluck('id')->all())
        ->toBe([$ownedProject->id]);
});

it('returns tasks only from projects with active membership', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $accessible = collaborationProject($owner);
    ProjectMembership::factory()->create(['project_id' => $accessible->id, 'user_id' => $member->id]);
    $inaccessible = collaborationProject(User::factory()->create());

    $visibleTask = Task::factory()->create(['project_id' => $accessible->id, 'user_id' => $owner->id]);
    $hiddenTask = Task::factory()->create(['project_id' => $inaccessible->id, 'user_id' => $inaccessible->user_id]);

    expect(Task::query()->accessibleBy($member)->pluck('id')->all())->toBe([$visibleTask->id])
        ->and(Task::query()->accessibleBy($member)->whereKey($hiddenTask->id)->exists())->toBeFalse();
});

it('allows a member to change status only on an assigned task', function (): void {
    $owner = User::factory()->create();
    $project = collaborationProject($owner);
    $member = collaborationMember($project);
    $assigned = Task::factory()->create([
        'project_id' => $project->id,
        'user_id' => $owner->id,
        'assignee_id' => $member->id,
    ]);
    $unassigned = Task::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id]);

    expect($member->can('changeStatus', $assigned))->toBeTrue()
        ->and($member->can('changeStatus', $unassigned))->toBeFalse()
        ->and($member->can('update', $assigned))->toBeFalse()
        ->and($owner->can('changeStatus', $assigned))->toBeTrue();
});

it('makes archived projects read-only for members and admins', function (): void {
    $owner = User::factory()->create();
    $project = collaborationProject($owner, ProjectStatus::ACTIVE);
    $admin = collaborationMember($project, ProjectRole::ADMIN);
    $member = collaborationMember($project);
    $project->update(['archived_at' => now()]);

    expect($admin->can('view', $project))->toBeTrue()
        ->and($admin->can('update', $project))->toBeFalse()
        ->and($member->can('view', $project))->toBeTrue()
        ->and($member->can('changeStatus', $project))->toBeFalse();
});
