<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectBoardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskController;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    foreach (['my-work' => 'My Work', 'analytics' => 'Analytics', 'activity' => 'Activity'] as $path => $title) {
        Route::view('/'.$path, 'pages.coming-soon', ['title' => $title])->name($path);
    }

    Route::bind('project', function (string $value): Project {
        return Project::query()->ownedBy(request()->user())->findOrFail($value);
    });

    Route::bind('task', function (string $value): Task {
        return Task::query()->ownedBy(request()->user())->findOrFail($value);
    });

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/tasks/create', [TaskController::class, 'create'])
        ->name('projects.tasks.create');
    Route::post('/projects/{project}/board/reorder', [ProjectBoardController::class, 'reorder'])
        ->name('projects.board.reorder');
    Route::post('/projects/{project}/board/tasks/{task}/status', [ProjectBoardController::class, 'changeStatus'])
        ->name('projects.board.tasks.status');
    Route::get('/projects/{project}/board', [ProjectBoardController::class, 'show'])
        ->name('projects.board');
    Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])
        ->name('projects.tasks.store');
    Route::post('/tasks/{task}/status', [TaskController::class, 'changeStatus'])
        ->name('tasks.status');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('/tasks/{task}/details', [TaskController::class, 'updateDetails'])->name('tasks.details.update');
    Route::patch('/tasks/{task}/priority', [TaskController::class, 'changePriority'])->name('tasks.priority');
    Route::patch('/tasks/{task}/due-date', [TaskController::class, 'changeDueDate'])->name('tasks.due-date');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::post('/projects/{project}/status', [ProjectController::class, 'changeStatus'])->name('projects.status');
    Route::post('/projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::post('/projects/{project}/restore', [ProjectController::class, 'restore'])->name('projects.restore');
});

Route::middleware('auth')->group(function () {
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings/preferences', [SettingsController::class, 'update'])->name('settings.preferences.update');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
