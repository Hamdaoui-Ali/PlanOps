<?php

use App\Domain\Collaboration\Actions\InviteProjectMember;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Models\User;

it('does not expose invitation tokens through persisted attributes', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);

    $invitation = (new InviteProjectMember)->handle($owner, $project, 'member@example.com', ProjectRole::MEMBER);
    $persisted = $invitation->fresh()->getAttributes();

    expect($persisted)->not->toHaveKey('plain_token')
        ->and($persisted['token_hash'])->toBe(hash('sha256', $invitation->plain_token));
});
