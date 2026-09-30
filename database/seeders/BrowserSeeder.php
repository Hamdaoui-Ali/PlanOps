<?php

namespace Database\Seeders;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Collaboration\Enums\ProjectEventType;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectEvent;
use App\Domain\Collaboration\Models\ProjectInvitation;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Notifications\Data\NotificationOutcome;
use App\Domain\Notifications\Enums\NotificationEventType;
use App\Domain\Notifications\Models\PlanOpsNotification;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

final class BrowserSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Browser Owner',
            'email' => 'browser-owner@example.test',
        ]);
        $admin = User::factory()->create([
            'name' => 'Browser Admin',
            'email' => 'browser-admin@example.test',
        ]);
        $member = User::factory()->create([
            'name' => 'Browser Member',
            'email' => 'browser-member@example.test',
        ]);
        $invitee = User::factory()->create([
            'name' => 'Browser Invitee',
            'email' => 'browser-invitee@example.test',
        ]);
        $project = Project::factory()->create([
            'user_id' => $owner->id,
            'owner_id' => $owner->id,
            'name' => 'Browser Collaboration',
            'key' => 'BROW',
            'status' => ProjectStatus::ACTIVE,
        ]);

        ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
        ProjectMembership::factory()->admin()->create(['project_id' => $project->id, 'user_id' => $admin->id]);
        ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);
        ProjectEvent::create([
            'project_id' => $project->id,
            'actor_user_id' => $owner->id,
            'subject_user_id' => $admin->id,
            'event_type' => ProjectEventType::MEMBER_ROLE_CHANGED,
            'metadata' => ['old_role' => ProjectRole::MEMBER->value, 'new_role' => ProjectRole::ADMIN->value],
            'created_at' => now()->subMinutes(15),
        ]);

        Task::factory()->forProject($project)->active()->create([
            'title' => 'Review launch checklist',
            'assignee_id' => $admin->id,
        ]);
        Task::factory()->forProject($project)->blocked()->create([
            'title' => 'Resolve release blocker',
            'assignee_id' => $owner->id,
            'due_on' => now()->subDay()->toDateString(),
        ]);
        Task::factory()->forProject($project)->create([
            'title' => 'Assign documentation owner',
            'status' => TaskStatus::NOT_STARTED,
        ]);
        $completed = Task::factory()->forProject($project)->done()->create([
            'title' => 'Publish release notes',
            'assignee_id' => $admin->id,
        ]);
        TaskActivity::factory()->forTask($completed)->create([
            'event_type' => TaskActivityType::TASK_CREATED,
            'created_at' => now()->subHour(),
        ]);
        TaskActivity::factory()->forTask($completed)->create([
            'event_type' => TaskActivityType::STATUS_CHANGED,
            'field' => 'status',
            'old_value' => ['status' => TaskStatus::IN_REVIEW->value],
            'new_value' => ['status' => TaskStatus::DONE->value],
            'created_at' => now()->subMinutes(30),
        ]);

        PlanOpsNotification::query()->create([
            'recipient_id' => $owner->id,
            'event_type' => NotificationEventType::ASSIGNEE_CHANGED,
            'idempotency_key' => 'BROWSER:ASSIGNEE_CHANGED:OWNER',
            'project_id' => $project->id,
            'target_type' => 'task',
            'target_id' => $completed->id,
            'data' => ['message' => 'Release review needs your attention.'],
        ]);

        $invitationProject = Project::factory()->create([
            'user_id' => $owner->id,
            'owner_id' => $owner->id,
            'name' => 'Browser Invitations',
            'key' => 'INVITE',
            'status' => ProjectStatus::ACTIVE,
        ]);
        ProjectMembership::factory()->owner()->create([
            'project_id' => $invitationProject->id,
            'user_id' => $owner->id,
        ]);
        $invitation = ProjectInvitation::factory()->create([
            'project_id' => $invitationProject->id,
            'email' => $invitee->email,
            'normalized_email' => strtolower($invitee->email),
            'invited_by_user_id' => $owner->id,
        ]);
        PlanOpsNotification::query()->create(
            PlanOpsNotification::fromOutcome(NotificationOutcome::invitationCreated(
                $invitation->id,
                $invitationProject->id,
                $invitee->id,
                $invitationProject->name,
            )),
        );
    }
}
