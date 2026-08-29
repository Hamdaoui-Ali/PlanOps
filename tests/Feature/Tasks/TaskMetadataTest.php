<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Actions\ChangeTaskDueDate;
use App\Domain\Tasks\Actions\ChangeTaskPriority;
use App\Domain\Tasks\Actions\DeleteTask;
use App\Domain\Tasks\Actions\RestoreTask;
use App\Domain\Tasks\Actions\UpdateTask;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Http\Requests\ChangeTaskDueDateRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

uses(RefreshDatabase::class);

test('one task details form saves all editable attributes together', function (): void {
    $owner = User::factory()->create();
    $task = Task::factory()->for($owner)->create();

    $this->actingAs($owner)->patch(route('tasks.details.update', $task), [
        'title' => 'Updated title',
        'description' => 'Updated description',
        'status' => TaskStatus::IN_PROGRESS->value,
        'priority' => TaskPriority::URGENT->value,
        'due_on' => '2026-10-01',
    ])->assertRedirect(route('tasks.show', $task, absolute: false));

    expect($task->fresh()->title)->toBe('Updated title')
        ->and($task->fresh()->description)->toBe('Updated description')
        ->and($task->fresh()->status)->toBe(TaskStatus::IN_PROGRESS)
        ->and($task->fresh()->priority)->toBe(TaskPriority::URGENT)
        ->and($task->fresh()->due_on?->toDateString())->toBe('2026-10-01');
});

test('task metadata HTTP actions save and return to task detail', function (): void {
    $owner = User::factory()->create();
    $task = Task::factory()->for($owner)->create();

    $this->actingAs($owner)->patch(route('tasks.update', $task), [
        'title' => 'Updated title',
        'description' => 'Updated description',
    ])->assertRedirect(route('tasks.show', $task, absolute: false));

    $this->actingAs($owner)->patch(route('tasks.priority', $task), [
        'priority' => TaskPriority::URGENT->value,
    ])->assertRedirect(route('tasks.show', $task, absolute: false));

    $this->actingAs($owner)->patch(route('tasks.due-date', $task), [
        'due_on' => '2026-10-01',
    ])->assertRedirect(route('tasks.show', $task, absolute: false));

    expect($task->fresh()->title)->toBe('Updated title')
        ->and($task->fresh()->priority)->toBe(TaskPriority::URGENT)
        ->and($task->fresh()->due_on?->toDateString())->toBe('2026-10-01');
});

test('editing a subtask changes only the subtask metadata', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $parent = Task::factory()->forProject($project)->create([
        'title' => 'Parent task',
        'priority' => TaskPriority::LOW,
        'due_on' => '2026-09-10',
    ]);
    $child = Task::factory()->forProject($project)->withParent($parent)->create([
        'title' => 'Child task',
        'priority' => TaskPriority::MEDIUM,
        'due_on' => '2026-09-11',
    ]);

    (new UpdateTask)->handle($owner, $child, ['title' => 'Updated child']);
    (new ChangeTaskPriority)->handle($owner, $child->fresh(), TaskPriority::URGENT);
    (new ChangeTaskDueDate)->handle($owner, $child->fresh(), '2026-09-12');

    expect($child->fresh()->title)->toBe('Updated child')
        ->and($child->fresh()->priority)->toBe(TaskPriority::URGENT)
        ->and($child->fresh()->due_on?->toDateString())->toBe('2026-09-12')
        ->and($parent->fresh()->title)->toBe('Parent task')
        ->and($parent->fresh()->priority)->toBe(TaskPriority::LOW)
        ->and($parent->fresh()->due_on?->toDateString())->toBe('2026-09-10');
});

test('task delete HTTP action soft deletes and returns to the project', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $task = Task::factory()->forProject($project)->create();

    $this->actingAs($owner)->delete(route('tasks.destroy', $task))
        ->assertRedirect(route('projects.show', $project, absolute: false));

    expect(Task::query()->find($task->id))->toBeNull();
});

test('UpdateTask trims fields, persists nullable description, and redacts title and description values', function (): void {
    $owner = User::factory()->create();
    $task = Task::factory()->for($owner)->create([
        'title' => 'Old title',
        'description' => 'Old description',
    ]);

    $updated = (new UpdateTask)->handle($owner, $task, [
        'title' => '  New title  ',
        'description' => '   ',
    ]);

    expect($updated->fresh()->title)->toBe('New title')
        ->and($updated->fresh()->description)->toBeNull()
        ->and(TaskActivity::query()->where('event_type', TaskActivityType::TASK_UPDATED)->count())->toBe(2);

    foreach (TaskActivity::query()->where('event_type', TaskActivityType::TASK_UPDATED)->get() as $activity) {
        expect($activity->old_value)->toBeNull()
            ->and($activity->new_value)->toBeNull()
            ->and(json_encode([$activity->old_value, $activity->new_value]))->not->toContain('Old')
            ->and(json_encode([$activity->old_value, $activity->new_value]))->not->toContain('New');
    }
});

test('UpdateTask records only changed fields', function (): void {
    $owner = User::factory()->create();
    $task = Task::factory()->for($owner)->create(['title' => 'Keep title', 'description' => 'Keep description']);

    (new UpdateTask)->handle($owner, $task, ['title' => 'Keep title', 'description' => 'Changed description']);

    expect(TaskActivity::query()->where('event_type', TaskActivityType::TASK_UPDATED)->count())->toBe(1)
        ->and(TaskActivity::query()->where('event_type', TaskActivityType::TASK_UPDATED)->sole()->field)->toBe('description');
});

test('UpdateTaskRequest leaves an omitted description out of title-only validated updates', function (): void {
    $owner = User::factory()->create();
    $task = Task::factory()->for($owner)->create([
        'title' => 'Old title',
        'description' => 'Keep this description',
    ]);
    $request = new class extends UpdateTaskRequest
    {
        public function prepareForTest(): void
        {
            $this->prepareForValidation();
        }
    };
    $request->replace(['title' => '  New title  ']);
    $request->prepareForTest();

    $validated = Validator::make($request->all(), $request->rules())->validate();
    $updated = (new UpdateTask)->handle($owner, $task, $validated);

    expect($validated)->not->toHaveKey('description')
        ->and($updated->description)->toBe('Keep this description')
        ->and(TaskActivity::query()->where('event_type', TaskActivityType::TASK_UPDATED)->count())->toBe(1)
        ->and(TaskActivity::query()->where('event_type', TaskActivityType::TASK_UPDATED)->sole()->field)->toBe('title');
});

test('ChangeTaskPriority accepts every priority and records no event for an identical value', function (): void {
    $owner = User::factory()->create();
    $task = Task::factory()->for($owner)->create(['priority' => TaskPriority::MEDIUM]);

    (new ChangeTaskPriority)->handle($owner, $task->fresh(), TaskPriority::HIGH);
    (new ChangeTaskPriority)->handle($owner, $task->fresh(), TaskPriority::LOW->value);
    (new ChangeTaskPriority)->handle($owner, $task->fresh(), TaskPriority::MEDIUM->value);
    (new ChangeTaskPriority)->handle($owner, $task->fresh(), TaskPriority::URGENT->value);

    $beforeNoOp = TaskActivity::query()->where('event_type', TaskActivityType::PRIORITY_CHANGED)->count();
    (new ChangeTaskPriority)->handle($owner, $task->fresh(), TaskPriority::URGENT);

    expect($task->fresh()->priority)->toBe(TaskPriority::URGENT)
        ->and($beforeNoOp)->toBe(4)
        ->and(TaskActivity::query()->where('event_type', TaskActivityType::PRIORITY_CHANGED)->count())->toBe(4);
});

test('ChangeTaskDueDate persists date-only values, supports clearing, and ignores identical dates', function (): void {
    $owner = User::factory()->create();
    $task = Task::factory()->for($owner)->create(['due_on' => '2026-09-15']);

    (new ChangeTaskDueDate)->handle($owner, $task, CarbonImmutable::parse('2026-09-16 18:30:00'));
    expect($task->fresh()->due_on->toDateString())->toBe('2026-09-16')
        ->and(TaskActivity::query()->where('event_type', TaskActivityType::DUE_DATE_CHANGED)->count())->toBe(1);

    (new ChangeTaskDueDate)->handle($owner, $task->fresh(), '2026-09-16');
    expect(TaskActivity::query()->where('event_type', TaskActivityType::DUE_DATE_CHANGED)->count())->toBe(1);

    (new ChangeTaskDueDate)->handle($owner, $task->fresh(), null);
    expect($task->fresh()->due_on)->toBeNull()
        ->and(TaskActivity::query()->where('event_type', TaskActivityType::DUE_DATE_CHANGED)->count())->toBe(2);
});

test('ChangeTaskDueDateRequest accepts only nullable Y-m-d due dates', function (): void {
    $request = new ChangeTaskDueDateRequest;

    expect($request->rules()['due_on'])->toBe(['nullable', 'date_format:Y-m-d']);
});

test('ChangeTaskDueDate turns malformed due-date strings into due_on validation errors', function (string $dueOn): void {
    $owner = User::factory()->create();
    $task = Task::factory()->for($owner)->create(['due_on' => '2026-09-15']);

    try {
        (new ChangeTaskDueDate)->handle($owner, $task, $dueOn);
        fail('Expected malformed due date to be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('due_on')
            ->and($exception->errors()['due_on'])->toContain('Enter a valid due date.');
    }

    expect($task->fresh()->due_on?->toDateString())->toBe('2026-09-15')
        ->and(TaskActivity::query()->where('event_type', TaskActivityType::DUE_DATE_CHANGED)->count())->toBe(0);
})->with([
    'calendar-invalid date' => ['2026-02-29'],
    'date-time input' => ['2026-09-16 18:30:00'],
    'non-date input' => ['next Tuesday'],
]);

test('metadata actions reject a task owned by another user without mutation or activity', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $task = Task::factory()->for($other)->create(['title' => 'Foreign title', 'priority' => TaskPriority::LOW, 'due_on' => '2026-09-15']);

    expect(fn (): Task => (new UpdateTask)->handle($owner, $task, ['title' => 'Changed']))->toThrow(AuthorizationException::class)
        ->and(fn (): Task => (new ChangeTaskPriority)->handle($owner, $task, TaskPriority::HIGH))->toThrow(AuthorizationException::class)
        ->and(fn (): Task => (new ChangeTaskDueDate)->handle($owner, $task, '2026-09-16'))->toThrow(AuthorizationException::class);

    expect($task->fresh()->title)->toBe('Foreign title')
        ->and($task->fresh()->priority)->toBe(TaskPriority::LOW)
        ->and($task->fresh()->due_on->toDateString())->toBe('2026-09-15')
        ->and(TaskActivity::query()->count())->toBe(0);
});

test('DeleteTask soft-deletes a task while retaining its number and activity history', function (): void {
    $owner = User::factory()->create();
    $task = Task::factory()->for($owner)->create(['number' => 7]);

    $deleted = (new DeleteTask)->handle($owner, $task);

    expect($deleted->trashed())->toBeTrue()
        ->and(Task::query()->find($task->id))->toBeNull()
        ->and(Task::query()->withTrashed()->findOrFail($task->id)->number)->toBe(7)
        ->and(TaskActivity::query()->where('task_id', $task->id)->where('event_type', TaskActivityType::TASK_DELETED)->count())->toBe(1);

    (new DeleteTask)->handle($owner, $deleted);

    expect(TaskActivity::query()->where('task_id', $task->id)->where('event_type', TaskActivityType::TASK_DELETED)->count())->toBe(1);
});

test('RestoreTask restores only an explicitly trashed task and preserves identity, history, and labels', function (): void {
    $owner = User::factory()->create();
    $task = Task::factory()->for($owner)->create();
    $label = Label::factory()->forUser($owner)->create();
    $task->labels()->attach($label);
    $history = TaskActivity::factory()->forTask($task)->create([
        'event_type' => TaskActivityType::TASK_UPDATED,
    ]);
    $task->delete();

    $restored = (new RestoreTask)->handle($owner, $task);

    expect($restored->getKey())->toBe($task->getKey())
        ->and($restored->trashed())->toBeFalse()
        ->and($restored->fresh()->labels->pluck('id')->all())->toBe([$label->id])
        ->and(TaskActivity::query()->find($history->id))->not->toBeNull()
        ->and(TaskActivity::query()->where('task_id', $task->id)->where('event_type', TaskActivityType::TASK_RESTORED)->count())->toBe(1);

    expect(fn (): Task => (new RestoreTask)->handle($owner, $restored))->toThrow(LogicException::class);

    expect(TaskActivity::query()->where('task_id', $task->id)->where('event_type', TaskActivityType::TASK_RESTORED)->count())->toBe(1);
});

test('deletion and restoration by another user are rejected without mutation', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $task = Task::factory()->for($owner)->create();

    expect(fn (): Task => (new DeleteTask)->handle($other, $task))->toThrow(AuthorizationException::class)
        ->and($task->fresh()->trashed())->toBeFalse();

    $task->delete();

    expect(fn (): Task => (new RestoreTask)->handle($other, $task))->toThrow(AuthorizationException::class);

    expect($task->fresh()->trashed())->toBeTrue()
        ->and(TaskActivity::query()->count())->toBe(0);
});

test('metadata and label components retain their route-agnostic Blade form contracts', function (): void {
    $metadataComponent = file_get_contents(resource_path('views/components/tasks/metadata-form.blade.php'));
    $labelPickerComponent = file_get_contents(resource_path('views/components/labels/label-picker.blade.php'));

    expect($metadataComponent)->toContain('$saveAction')
        ->toContain('$deleteAction')
        ->toContain("'task'")
        ->toContain("'statuses'")
        ->toContain("'priorities'")
        ->toContain('>Title<')
        ->toContain('>Description<')
        ->toContain('>Priority<')
        ->toContain('>Due date<')
        ->toContain('name="title"')
        ->toContain('name="description"')
        ->toContain('name="priority"')
        ->toContain('name="due_on"')
        ->toContain("old('title', \$task->title)")
        ->toContain("old('description', \$task->description)")
        ->toContain("old('priority', \$task->priority->value)")
        ->toContain("old('due_on', \$task->due_on?->format('Y-m-d'))")
        ->toContain('@csrf')
        ->toContain("@method('PATCH')")
        ->toContain("@method('DELETE')")
        ->toContain(':messages="$errors->get(\'title\')"')
        ->toContain(':messages="$errors->get(\'description\')"')
        ->toContain(':messages="$errors->get(\'priority\')"')
        ->toContain(':messages="$errors->get(\'due_on\')"')
        ->toContain('aria-hidden="true"')
        ->toContain('Confirm task deletion')
        ->toContain("window.confirm('Delete this task?')")
        ->toContain('Save changes')
        ->toContain('task-metadata-actions')
        ->not->toContain('Update priority')
        ->not->toContain('Update due date')
        ->toContain('@if ($deleteAction !== null)')
        ->toContain('action="{{ $deleteAction }}"')
        ->not->toContain('route(');

    expect($metadataComponent)->toMatch('/@if \(\$deleteAction !== null\)\s*<form\\b[^>]*action="\\{\\{ \$deleteAction \\}\\}"[^>]*>.*?<\\/form>\s*@endif/s');

    expect($labelPickerComponent)->toContain('$attachAction')
        ->toContain('$detachAction')
        ->toContain('$createAction')
        ->toContain("'labels'")
        ->toContain("'selectedLabelIds'")
        ->toContain('>Add label<')
        ->toContain('>Attached labels<')
        ->toContain('No labels attached.')
        ->toContain('name="label_id"')
        ->toContain("old('label_id')")
        ->toContain('name="name"')
        ->toContain("old('name')")
        ->toContain('@csrf')
        ->toContain("@method('DELETE')")
        ->toContain(':messages="$errors->get(\'label_id\')"')
        ->toContain(':messages="$errors->get(\'name\')"')
        ->toContain('aria-hidden="true"')
        ->toContain('@if ($createAction !== null)')
        ->toContain('action="{{ $createAction }}"')
        ->not->toContain('route(')
        ->not->toContain('display_key');

    expect($labelPickerComponent)->toMatch('/@if \(\$createAction !== null\)\s*<form\\b[^>]*action="\\{\\{ \$createAction \\}\\}"[^>]*>.*?<\\/form>\s*@endif/s');
});
