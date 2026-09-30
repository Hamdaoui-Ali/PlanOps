<?php

namespace Database\Seeders;

use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        fake()->unique(true);
        fake()->seed(20_260_827);

        $previousTestNow = Carbon::getTestNow();
        Carbon::setTestNow(Carbon::create(2026, 8, 27, 12, 0, 0, 'UTC'));

        try {
            $owner = User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.',
                'remember_token' => 'seed-owner-token',
            ]);

            $owner->preference()->create([
                'timezone' => 'Africa/Casablanca',
                'week_start_day' => 'MONDAY',
                'theme' => 'SYSTEM',
                'density' => 'COMFORTABLE',
            ]);

            $secondUser = User::factory()->create([
                'name' => 'Second User',
                'email' => 'second@example.com',
                'password' => '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.',
                'remember_token' => 'seed-second-token',
            ]);
            $secondUser->preference()->create([
                'timezone' => 'Europe/Paris',
                'week_start_day' => 'SUNDAY',
                'theme' => 'DARK',
                'density' => 'COMPACT',
            ]);

            $activeProject = Project::factory()->for($owner)->active()->create([
                'name' => 'PlanOps Core',
                'key' => 'PLAN',
                'description' => 'Core delivery workspace.',
                'color' => '#2563EB',
            ]);
            $plannedProject = Project::factory()->for($owner)->planned()->create([
                'name' => 'Future Work',
                'key' => 'FUTURE',
                'description' => 'Planned delivery backlog.',
                'color' => '#7C3AED',
            ]);
            $onHoldProject = Project::factory()->for($owner)->onHold()->create([
                'name' => 'Discovery',
                'key' => 'HOLD',
                'description' => 'Discovery work awaiting a decision.',
                'color' => '#D97706',
            ]);
            $completedProject = Project::factory()->for($owner)->completed()->create([
                'name' => 'Foundation',
                'key' => 'FOUND',
                'description' => 'Completed foundation work.',
                'color' => '#059669',
            ]);
            $cancelledProject = Project::factory()->for($secondUser)->cancelled()->create([
                'name' => 'Retired Initiative',
                'key' => 'RETIRE',
                'description' => 'Cancelled initiative fixture.',
                'color' => '#DC2626',
            ]);

            $doneTask = Task::factory()->forProject($activeProject)->done()->create([
                'number' => 1,
                'title' => 'Model the core domain',
                'description' => 'Capture the core domain model.',
            ]);
            $subtask = Task::factory()->forProject($activeProject)->withParent($doneTask)->done()->create([
                'number' => 2,
                'title' => 'Verify the schema contract',
                'description' => 'Verify the persisted schema.',
            ]);
            $reopenedTask = Task::factory()->forProject($activeProject)->reopened()->create([
                'number' => 3,
                'title' => 'Review the persistence foundation',
                'description' => 'Review the persistence foundation.',
            ]);
            $deletedTask = Task::factory()->forProject($activeProject)->deleted()->create([
                'number' => 4,
                'title' => 'Removed exploratory task',
                'description' => 'Historical deleted task fixture.',
            ]);
            Task::factory()->forProject($plannedProject)->backlog()->create([
                'number' => 1,
                'title' => 'Prepare the next milestone',
                'description' => 'Prepare the next planned milestone.',
            ]);
            Task::factory()->forProject($onHoldProject)->blocked()->create([
                'number' => 1,
                'title' => 'Await discovery decision',
                'description' => 'Await a discovery decision.',
            ]);
            Task::factory()->forProject($completedProject)->done()->create([
                'number' => 1,
                'title' => 'Close foundation work',
                'description' => 'Close the foundation workstream.',
            ]);
            Task::factory()->forProject($cancelledProject)->cancelled()->create([
                'number' => 1,
                'title' => 'Record cancellation',
                'description' => 'Record the cancelled initiative.',
            ]);

            foreach ([$activeProject, $plannedProject, $onHoldProject, $completedProject, $cancelledProject] as $project) {
                $maximumTaskNumber = Task::withTrashed()
                    ->where('project_id', $project->id)
                    ->max('number');

                $project->update(['next_task_number' => ((int) $maximumTaskNumber) + 1]);
            }

            $urgent = Label::factory()->forUser($owner)->create(['name' => 'Urgent', 'normalized_name' => 'urgent', 'color' => '#DC2626']);
            $foundation = Label::factory()->forUser($owner)->create(['name' => 'Foundation', 'normalized_name' => 'foundation', 'color' => '#2563EB']);
            $doneTask->labels()->attach([$urgent->id, $foundation->id]);
            $subtask->labels()->attach($foundation->id);

            TaskActivity::factory()->forTask($doneTask)->statusChanged()->create([
                'old_value' => ['status' => 'IN_PROGRESS'],
                'new_value' => ['status' => 'DONE'],
            ]);
            TaskActivity::factory()->forTask($reopenedTask)->statusChanged()->create([
                'old_value' => ['status' => 'DONE'],
                'new_value' => ['status' => 'IN_PROGRESS'],
                'metadata' => ['is_reopen' => true],
            ]);
            TaskActivity::factory()->forTask($deletedTask)->taskDeleted()->create([
                'metadata' => ['fixture' => 'soft_deleted_task'],
            ]);
        } finally {
            Carbon::setTestNow($previousTestNow);
        }
    }
}
