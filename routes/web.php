<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Collaboration\ProjectInvitationController;
use App\Http\Controllers\Collaboration\ProjectMemberController;
use App\Http\Controllers\Collaboration\ProjectTeamController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\MyWorkController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectAnalyticsController;
use App\Http\Controllers\ProjectBoardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTaskListController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/invitations/{token}', [ProjectInvitationController::class, 'show'])->name('invitations.show');

Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity');
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/exports/projects.csv', [ExportController::class, 'projects'])->name('exports.projects');
    Route::get('/exports/tasks.csv', [ExportController::class, 'tasks'])->name('exports.tasks');
    Route::get('/exports/activity.{format?}', [ExportController::class, 'activity'])->whereIn('format', ['csv', 'json'])->name('exports.activity');

    Route::bind('project', function (string $value): Project {
        return Project::query()->accessibleBy(request()->user())->findOrFail($value);
    });

    Route::bind('task', function (string $value): Task {
        return Task::query()->accessibleBy(request()->user())->findOrFail($value);
    });

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/my-work', [MyWorkController::class, 'index'])->name('my-work');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/{notification}/accept-invitation', [NotificationController::class, 'acceptInvitation'])->name('notifications.accept-invitation');
    Route::post('/notifications/{notification}/decline-invitation', [NotificationController::class, 'declineInvitation'])->name('notifications.decline-invitation');
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::get('/projects/{project}/tasks', [ProjectTaskListController::class, 'index'])
        ->name('projects.tasks.index');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/team', [ProjectTeamController::class, 'show'])->name('projects.team');
    Route::post('/projects/{project}/team/invitations', [ProjectInvitationController::class, 'store'])->name('projects.team.invitations.store');
    Route::delete('/invitations/{invitation}', [ProjectInvitationController::class, 'revoke'])->name('invitations.revoke');
    Route::post('/invitations/{invitation}/resend', [ProjectInvitationController::class, 'resend'])->name('invitations.resend');
    Route::post('/invitations/{token}/accept', [ProjectInvitationController::class, 'accept'])->name('invitations.accept');
    Route::patch('/projects/{project}/team/members/{membership}', [ProjectMemberController::class, 'update'])->name('projects.team.members.update');
    Route::delete('/projects/{project}/team/members/{membership}', [ProjectMemberController::class, 'destroy'])->name('projects.team.members.destroy');
    Route::post('/projects/{project}/team/members/{membership}/transfer', [ProjectMemberController::class, 'transfer'])->name('projects.team.members.transfer');
    Route::get('/projects/{project}/analytics', [ProjectAnalyticsController::class, 'index'])->name('projects.analytics');
    Route::get('/projects/{project}/tasks/create', [TaskController::class, 'create'])
        ->name('projects.tasks.create');
    Route::post('/projects/{project}/board/reorder', [ProjectBoardController::class, 'reorder'])
        ->name('projects.board.reorder');
    Route::post('/projects/{project}/board/tasks/{task}/status', [ProjectBoardController::class, 'changeStatus'])
        ->scopeBindings()
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
    Route::patch('/tasks/{task}/assignee', [TaskController::class, 'assign'])->name('tasks.assignee');
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
