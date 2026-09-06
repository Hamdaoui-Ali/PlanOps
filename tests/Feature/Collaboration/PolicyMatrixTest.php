<?php

use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;

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
