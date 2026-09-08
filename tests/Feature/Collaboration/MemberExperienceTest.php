<?php

use App\Domain\Dashboard\Queries\DashboardQueryService;
use App\Domain\Export\Queries\ExportQueryService;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Identity\ValueObjects\ReportPeriod;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('excludes member-only projects from complete task exports', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $shared = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    $foreign = Project::factory()->create();
    ProjectMembership::factory()->owner()->create(['project_id' => $shared->id, 'user_id' => $owner->id]);
    ProjectMembership::factory()->create(['project_id' => $shared->id, 'user_id' => $member->id]);
    Task::factory()->forProject($shared)->create(['title' => 'Member only task']);
    Task::factory()->forProject($foreign)->create(['title' => 'Hidden foreign task']);

    $titles = (new ExportQueryService)->tasks($member)->collect()->pluck('title')->all();

    expect($titles)->toBe([]);
});

it('scopes member dashboard counts to accessible projects', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $shared = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    $foreign = Project::factory()->create();
    ProjectMembership::factory()->owner()->create(['project_id' => $shared->id, 'user_id' => $owner->id]);
    ProjectMembership::factory()->create(['project_id' => $shared->id, 'user_id' => $member->id]);
    Task::factory()->forProject($shared)->create(['status' => TaskStatus::IN_PROGRESS]);
    Task::factory()->forProject($foreign)->create(['status' => TaskStatus::IN_PROGRESS]);

    $snapshot = (new DashboardQueryService)->for($member, new ReportPeriod(
        'Today',
        CarbonImmutable::now()->startOfDay(),
        CarbonImmutable::now()->addDay()->startOfDay(),
        'day',
    ));

    expect($snapshot->activeProjects)->toBe(1)
        ->and($snapshot->statusCounts[TaskStatus::IN_PROGRESS->value])->toBe(1);
});
