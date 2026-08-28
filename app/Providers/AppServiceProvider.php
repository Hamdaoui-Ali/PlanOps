<?php

namespace App\Providers;

use App\Domain\Projects\Models\Project;
use App\Domain\Labels\Models\Label;
use App\Domain\Tasks\Models\Task;
use App\Policies\LabelPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Label::class, LabelPolicy::class);
    }
}
