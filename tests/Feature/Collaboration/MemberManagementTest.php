<?php

use App\Domain\Collaboration\Actions\ChangeProjectMemberRole;
use App\Domain\Collaboration\Actions\RemoveProjectMember;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

function memberManagementProject(User $owner): Project
{
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);

    return $project;
}

it('removes members and unassigns their tasks while retaining the membership row', function (): void {
    $owner = User::factory()->create();
    $project = memberManagementProject($owner);
    $member = User::factory()->create();
    $membership = ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);
    $task = Task::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id, 'assignee_id' => $member->id]);

    (new RemoveProjectMember)->handle($owner, $project, $member);

    expect($task->fresh()->assignee_id)->toBeNull()
        ->and($membership->fresh()->removed_at)->not->toBeNull()
        ->and($member->can('view', $project))->toBeFalse();
});

it('allows only the owner to change collaborator roles while keeping ownership permanent', function (): void {
    $owner = User::factory()->create();
    $project = memberManagementProject($owner);
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $adminMembership = ProjectMembership::factory()->admin()->create(['project_id' => $project->id, 'user_id' => $admin->id]);
    $memberMembership = ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);

    expect(fn () => (new ChangeProjectMemberRole)->handle($admin, $memberMembership, ProjectRole::ADMIN))
        ->toThrow(AuthorizationException::class);

    (new ChangeProjectMemberRole)->handle($owner, $memberMembership, ProjectRole::ADMIN);
    expect($memberMembership->fresh()->role)->toBe(ProjectRole::ADMIN)
        ->and($adminMembership->fresh()->role)->toBe(ProjectRole::ADMIN)
        ->and($project->fresh()->owner_id)->toBe($owner->id);
});
