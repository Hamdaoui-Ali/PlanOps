<?php

use App\Domain\Collaboration\Enums\ProjectEventType;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectEvent;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Queries\ProjectActivityFeedQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('project role-change activity is scoped, ordered, and keeps historical identities', function (): void {
    $owner = User::factory()->create(['name' => 'Project Owner']);
    $viewer = User::factory()->create(['name' => 'Active Viewer']);
    $subject = User::factory()->create(['name' => 'Historical Member']);
    $foreignOwner = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'owner_id' => $owner->id,
    ]);
    $foreignProject = Project::factory()->create([
        'user_id' => $foreignOwner->id,
        'owner_id' => $foreignOwner->id,
    ]);

    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $viewer->id]);
    $subjectMembership = ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $subject->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $foreignProject->id, 'user_id' => $foreignOwner->id]);

    $older = ProjectEvent::factory()->create([
        'project_id' => $project->id,
        'actor_user_id' => $owner->id,
        'subject_user_id' => $subject->id,
        'event_type' => ProjectEventType::MEMBER_ROLE_CHANGED,
        'metadata' => ['old_role' => ProjectRole::MEMBER->value, 'new_role' => ProjectRole::ADMIN->value],
        'created_at' => Carbon::parse('2026-09-29 09:00:00 UTC'),
    ]);
    $newer = ProjectEvent::factory()->create([
        'project_id' => $project->id,
        'actor_user_id' => $owner->id,
        'subject_user_id' => $subject->id,
        'event_type' => ProjectEventType::MEMBER_ROLE_CHANGED,
        'metadata' => ['old_role' => ProjectRole::ADMIN->value, 'new_role' => ProjectRole::MEMBER->value],
        'created_at' => Carbon::parse('2026-09-29 10:00:00 UTC'),
    ]);
    ProjectEvent::factory()->create([
        'project_id' => $project->id,
        'event_type' => ProjectEventType::INVITATION_CREATED,
    ]);
    ProjectEvent::factory()->create([
        'project_id' => $foreignProject->id,
        'event_type' => ProjectEventType::MEMBER_ROLE_CHANGED,
    ]);

    $subjectMembership->forceFill(['removed_at' => Carbon::parse('2026-09-29 11:00:00 UTC')])->save();

    $events = (new ProjectActivityFeedQuery)->for($viewer, $project);

    expect($events->modelKeys())->toBe([$newer->id, $older->id])
        ->and($events->first()->relationLoaded('actor'))->toBeTrue()
        ->and($events->first()->relationLoaded('subject'))->toBeTrue()
        ->and($events->first()->actor->name)->toBe('Project Owner')
        ->and($events->first()->subject->name)->toBe('Historical Member')
        ->and($events->first()->metadata)->toBe([
            'old_role' => ProjectRole::ADMIN->value,
            'new_role' => ProjectRole::MEMBER->value,
        ]);
});

test('active project members can read role changes on the project overview', function (): void {
    $owner = User::factory()->create(['name' => 'Project Owner']);
    $viewer = User::factory()->create(['name' => 'Active Viewer']);
    $subject = User::factory()->create(['name' => 'Historical Member']);
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'owner_id' => $owner->id,
        'name' => 'PlanOps rollout',
        'key' => 'PLAN',
    ]);

    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $viewer->id]);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $subject->id]);
    $event = ProjectEvent::factory()->create([
        'project_id' => $project->id,
        'actor_user_id' => $owner->id,
        'subject_user_id' => $subject->id,
        'event_type' => ProjectEventType::MEMBER_ROLE_CHANGED,
        'metadata' => ['old_role' => ProjectRole::MEMBER->value, 'new_role' => ProjectRole::ADMIN->value],
        'created_at' => Carbon::parse('2026-09-29 10:00:00 UTC'),
    ]);

    $this->actingAs($viewer)->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Project activity')
        ->assertSee('Project Owner changed the role of Historical Member')
        ->assertSee('From Member to Admin')
        ->assertSee('2026-09-29T10:00:00+00:00', false)
        ->assertSee('Sep 29, 2026')
        ->assertDontSee(json_encode($event->metadata), false);
});

test('removed project members cannot open the project activity surface', function (): void {
    $owner = User::factory()->create();
    $removed = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'owner_id' => $owner->id,
    ]);

    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    ProjectMembership::factory()->create([
        'project_id' => $project->id,
        'user_id' => $removed->id,
        'removed_at' => Carbon::parse('2026-09-29 11:00:00 UTC'),
    ]);

    $this->actingAs($removed)->get(route('projects.show', $project))->assertNotFound();
});
