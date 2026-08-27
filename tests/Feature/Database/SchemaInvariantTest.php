<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function indexDefinition(string $table, string $name): array
{
    return collect(Schema::getIndexes($table))
        ->firstWhere('name', $name) ?? [];
}

test('the PlanOps foundation contains the seven core tables', function () {
    foreach ([
        'users',
        'user_preferences',
        'projects',
        'tasks',
        'labels',
        'task_label',
        'task_activities',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

test('the foundation contains required columns and lifecycle fields', function () {
    foreach ([
        'projects' => [
            'user_id', 'name', 'key', 'description', 'status', 'color', 'icon',
            'start_on', 'target_on', 'next_task_number', 'archived_at',
        ],
        'tasks' => [
            'user_id', 'project_id', 'parent_task_id', 'number', 'title',
            'description', 'status', 'priority', 'due_on', 'position',
            'first_started_at', 'completed_at', 'cancelled_at',
            'status_changed_at', 'deleted_at',
        ],
        'labels' => ['user_id', 'name', 'normalized_name', 'color'],
        'task_label' => ['task_id', 'label_id', 'created_at'],
        'task_activities' => [
            'user_id', 'project_id', 'task_id', 'event_type', 'field',
            'old_value', 'new_value', 'metadata', 'created_at',
        ],
    ] as $table => $columns) {
        foreach ($columns as $column) {
            expect(Schema::hasColumn($table, $column))
                ->toBeTrue("{$table}.{$column} is required");
        }
    }
});

test('the foundation contains the documented unique keys', function () {
    expect(indexDefinition('projects', 'projects_user_id_key_unique')['unique'] ?? false)->toBeTrue();
    expect(indexDefinition('tasks', 'tasks_project_id_number_unique')['unique'] ?? false)->toBeTrue();
    expect(indexDefinition('labels', 'labels_user_id_normalized_name_unique')['unique'] ?? false)->toBeTrue();
    expect(indexDefinition('task_label', 'task_label_task_id_label_id_unique')['unique'] ?? false)->toBeTrue();
    expect(indexDefinition('user_preferences', 'user_preferences_user_id_unique')['unique'] ?? false)->toBeTrue();
});

test('the foundation contains the documented lookup indexes', function () {
    expect(indexDefinition('projects', 'projects_user_id_status_index'))->not->toBeEmpty();
    expect(indexDefinition('projects', 'projects_user_id_archived_at_index'))->not->toBeEmpty();
    expect(indexDefinition('projects', 'projects_user_id_updated_at_index'))->not->toBeEmpty();
    expect(indexDefinition('tasks', 'tasks_user_id_status_index'))->not->toBeEmpty();
    expect(indexDefinition('tasks', 'tasks_project_id_status_position_index'))->not->toBeEmpty();
    expect(indexDefinition('tasks', 'tasks_project_id_parent_task_id_index'))->not->toBeEmpty();
    expect(indexDefinition('tasks', 'tasks_user_id_priority_index'))->not->toBeEmpty();
    expect(indexDefinition('tasks', 'tasks_user_id_due_on_index'))->not->toBeEmpty();
    expect(indexDefinition('tasks', 'tasks_user_id_updated_at_index'))->not->toBeEmpty();
    expect(indexDefinition('tasks', 'tasks_parent_task_id_index'))->not->toBeEmpty();
    expect(indexDefinition('task_activities', 'task_activities_user_id_created_at_index'))->not->toBeEmpty();
    expect(indexDefinition('task_activities', 'task_activities_project_id_created_at_index'))->not->toBeEmpty();
    expect(indexDefinition('task_activities', 'task_activities_task_id_created_at_index'))->not->toBeEmpty();
    expect(indexDefinition('task_activities', 'task_activities_event_type_created_at_index'))->not->toBeEmpty();
});

test('activity payload columns are nullable JSON-capable columns', function () {
    foreach (['old_value', 'new_value', 'metadata'] as $column) {
        expect(Schema::hasColumn('task_activities', $column))->toBeTrue();
    }
});

test('tasks expose soft deletion support', function () {
    expect(Schema::hasColumn('tasks', 'deleted_at'))->toBeTrue();
});
