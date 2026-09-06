<?php

namespace App\Domain\Collaboration\Actions;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Services\TaskActivityRecorder;
use App\Domain\Collaboration\Enums\ProjectEventType;
use App\Domain\Collaboration\Models\ProjectEvent;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RemoveProjectMember
{
    public function handle(User $actor, Project $project, User $subject): void
    {
        Gate::forUser($actor)->authorize('manageMembers', $project);
        DB::transaction(function () use ($actor, $project, $subject): void {
            $tasks = Task::query()
                ->where('project_id', $project->getKey())
                ->where('assignee_id', $subject->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $membership = ProjectMembership::query()->where('project_id', $project->getKey())->where('user_id', $subject->getKey())->lockForUpdate()->first();
            if (! $membership || $membership->removed_at !== null) throw ValidationException::withMessages(['member' => 'That person is not an active project member.']);
            if ($membership->role->value === 'OWNER') throw ValidationException::withMessages(['member' => 'Transfer ownership before removing the project owner.']);
            foreach ($tasks as $task) {
                $task->forceFill(['assignee_id' => null])->save();
                app(TaskActivityRecorder::class)->record(
                    $task,
                    TaskActivityType::ASSIGNEE_CHANGED,
                    'assignee_id',
                    $subject->getKey(),
                    null,
                    ['old_assignee_id' => $subject->getKey(), 'new_assignee_id' => null],
                    $actor,
                );
            }
            $membership->forceFill(['removed_at' => now(), 'removed_by_user_id' => $actor->getKey()])->save();
            ProjectEvent::create(['project_id' => $project->getKey(), 'actor_user_id' => $actor->getKey(), 'subject_user_id' => $subject->getKey(), 'event_type' => ProjectEventType::MEMBER_REMOVED]);
        });
    }
}
