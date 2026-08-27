<?php

namespace Database\Seeders;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $owner->preference()->create([
            'timezone' => 'Africa/Casablanca',
            'week_start_day' => 'MONDAY',
            'theme' => 'SYSTEM',
            'density' => 'COMFORTABLE',
        ]);

        $secondUser = User::factory()->create(['name' => 'Second User', 'email' => 'second@example.com']);
        $secondUser->preference()->create([
            'timezone' => 'Europe/Paris',
            'week_start_day' => 'SUNDAY',
            'theme' => 'DARK',
            'density' => 'COMPACT',
        ]);

        $activeProject = Project::factory()->for($owner)->active()->create(['name' => 'PlanOps Core', 'key' => 'PLAN']);
        $plannedProject = Project::factory()->for($owner)->planned()->create(['name' => 'Future Work', 'key' => 'FUTURE']);
        $onHoldProject = Project::factory()->for($owner)->onHold()->create(['name' => 'Discovery', 'key' => 'HOLD']);
        $completedProject = Project::factory()->for($owner)->completed()->create(['name' => 'Foundation', 'key' => 'FOUND']);
        $cancelledProject = Project::factory()->for($secondUser)->cancelled()->create(['name' => 'Retired Initiative', 'key' => 'RETIRE']);

        $doneTask = Task::factory()->forProject($activeProject)->done()->create(['number' => 1, 'title' => 'Model the core domain']);
        $subtask = Task::factory()->forProject($activeProject)->withParent($doneTask)->done()->create(['number' => 2, 'title' => 'Verify the schema contract']);
        $reopenedTask = Task::factory()->forProject($activeProject)->reopened()->create(['number' => 3, 'title' => 'Review the persistence foundation']);
        $deletedTask = Task::factory()->forProject($activeProject)->deleted()->create(['number' => 4, 'title' => 'Removed exploratory task']);
        Task::factory()->forProject($plannedProject)->backlog()->create(['number' => 1, 'title' => 'Prepare the next milestone']);
        Task::factory()->forProject($onHoldProject)->blocked()->create(['number' => 1, 'title' => 'Await discovery decision']);
        Task::factory()->forProject($completedProject)->done()->create(['number' => 1, 'title' => 'Close foundation work']);
        Task::factory()->forProject($cancelledProject)->cancelled()->create(['number' => 1, 'title' => 'Record cancellation']);

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
        TaskActivity::factory()->forTask($deletedTask)->create([
            'event_type' => TaskActivityType::TASK_DELETED,
            'metadata' => ['fixture' => 'soft_deleted_task'],
        ]);
    }
}
