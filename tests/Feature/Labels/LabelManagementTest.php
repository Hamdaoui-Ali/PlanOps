<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Labels\Actions\AttachLabelToTask;
use App\Domain\Labels\Actions\CreateLabel;
use App\Domain\Labels\Actions\DeleteLabel;
use App\Domain\Labels\Actions\DetachLabelFromTask;
use App\Domain\Labels\Models\Label;
use App\Domain\Tasks\Actions\DeleteTask;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('CreateLabel normalizes names, rejects same-owner duplicates, and allows another owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $label = (new CreateLabel)->handle($owner, ['name' => "  Frontend\t Platform  "]);
    $otherLabel = (new CreateLabel)->handle($other, ['name' => 'FRONTEND PLATFORM']);

    expect($label->name)->toBe('Frontend Platform')
        ->and($label->normalized_name)->toBe('frontend platform')
        ->and($otherLabel->user_id)->toBe($other->id)
        ->and(fn (): Label => (new CreateLabel)->handle($owner, ['name' => ' frontend platform ']))->toThrow(ValidationException::class);
});

test('CreateLabel validates the displayed name and optional color', function (): void {
    $owner = User::factory()->create();

    expect(fn (): Label => (new CreateLabel)->handle($owner, ['name' => " \t "]))->toThrow(ValidationException::class)
        ->and(fn (): Label => (new CreateLabel)->handle($owner, ['name' => str_repeat('a', 81)]))->toThrow(ValidationException::class)
        ->and(fn (): Label => (new CreateLabel)->handle($owner, ['name' => 'Platform', 'color' => str_repeat('a', 33)]))->toThrow(ValidationException::class);
});

test('normalized duplicate label failures use the visible name error contract', function (): void {
    $owner = User::factory()->create();
    (new CreateLabel)->handle($owner, ['name' => 'Platform']);

    try {
        (new CreateLabel)->handle($owner, ['name' => ' platform ']);
        fail('Expected duplicate normalized label to be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('name')
            ->not->toHaveKey('normalized_name');
    }

    $request = file_get_contents(app_path('Http/Requests/StoreLabelRequest.php'));
    $picker = file_get_contents(resource_path('views/components/labels/label-picker.blade.php'));

    expect($request)->toContain("->errors()->add('name'")
        ->not->toMatch("/'normalized_name' => \[/")
        ->and($picker)->toContain('name="name"')
        ->toContain(':messages="$errors->get(\'name\')"');
});

test('label attach and detach are owner-scoped, idempotent, and record only pivot changes', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $task = Task::factory()->for($owner)->create();
    $foreignTask = Task::factory()->for($other)->create();
    $label = Label::factory()->forUser($owner)->create();

    (new AttachLabelToTask)->handle($owner, $task, $label);
    (new AttachLabelToTask)->handle($owner, $task->fresh(), $label);
    (new DetachLabelFromTask)->handle($owner, $task->fresh(), $label);
    (new DetachLabelFromTask)->handle($owner, $task->fresh(), $label);

    expect($task->fresh()->labels)->toHaveCount(0)
        ->and(TaskActivity::query()->where('event_type', TaskActivityType::LABEL_ADDED)->count())->toBe(1)
        ->and(TaskActivity::query()->where('event_type', TaskActivityType::LABEL_REMOVED)->count())->toBe(1)
        ->and(fn (): Task => (new AttachLabelToTask)->handle($owner, $foreignTask, $label))->toThrow(AuthorizationException::class)
        ->and(fn (): Task => (new DetachLabelFromTask)->handle($owner, $foreignTask, $label))->toThrow(AuthorizationException::class);

    expect($foreignTask->fresh()->labels)->toHaveCount(0)
        ->and(TaskActivity::query()->where('event_type', TaskActivityType::LABEL_ADDED)->count())->toBe(1)
        ->and(TaskActivity::query()->where('event_type', TaskActivityType::LABEL_REMOVED)->count())->toBe(1);
});

test('label activities contain only the label identity and reflect the pivot change', function (): void {
    $owner = User::factory()->create();
    $task = Task::factory()->for($owner)->create();
    $label = Label::factory()->forUser($owner)->create(['name' => 'Platform']);

    (new AttachLabelToTask)->handle($owner, $task, $label);
    (new DetachLabelFromTask)->handle($owner, $task, $label);

    $added = TaskActivity::query()->where('event_type', TaskActivityType::LABEL_ADDED)->sole();
    $removed = TaskActivity::query()->where('event_type', TaskActivityType::LABEL_REMOVED)->sole();

    expect($added->field)->toBe('label_id')
        ->and($added->old_value)->toBe(['label_id' => null])
        ->and($added->new_value)->toBe(['label_id' => $label->id])
        ->and($added->metadata)->toBe(['label' => ['id' => $label->id, 'name' => 'Platform']])
        ->and($removed->field)->toBe('label_id')
        ->and($removed->old_value)->toBe(['label_id' => $label->id])
        ->and($removed->new_value)->toBe(['label_id' => null])
        ->and($removed->metadata)->toBe(['label' => ['id' => $label->id, 'name' => 'Platform']]);
});

test('DeleteLabel detaches every owned task, retains tasks, and records each removal', function (): void {
    $owner = User::factory()->create();
    $label = Label::factory()->forUser($owner)->create();
    $first = Task::factory()->for($owner)->create();
    $second = Task::factory()->for($owner)->create();
    $first->labels()->attach($label);
    $second->labels()->attach($label);

    (new DeleteLabel)->handle($owner, $label);

    expect(Label::query()->find($label->id))->toBeNull()
        ->and($first->fresh()->exists)->toBeTrue()
        ->and($second->fresh()->exists)->toBeTrue()
        ->and($first->fresh()->labels)->toHaveCount(0)
        ->and($second->fresh()->labels)->toHaveCount(0)
        ->and(TaskActivity::query()->where('event_type', TaskActivityType::LABEL_REMOVED)->count())->toBe(2);

    (new DeleteLabel)->handle($owner, $label);

    expect(TaskActivity::query()->where('event_type', TaskActivityType::LABEL_REMOVED)->count())->toBe(2);
});

test('DeleteLabel detaches a soft-deleted owned task and records its retained history', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $label = Label::factory()->forUser($owner)->create();
    $deletedTask = Task::factory()->for($owner)->create();
    $otherTask = Task::factory()->for($other)->create();
    $deletedTask->labels()->attach($label);
    $deletedTask->delete();

    (new DeleteLabel)->handle($owner, $label);

    $restoredTask = Task::query()->withTrashed()->findOrFail($deletedTask->id);
    $restoredTask->restore();

    expect($restoredTask->fresh()->labels)->toHaveCount(0)
        ->and($otherTask->fresh()->labels)->toHaveCount(0)
        ->and(TaskActivity::query()
            ->where('task_id', $deletedTask->id)
            ->where('event_type', TaskActivityType::LABEL_REMOVED)
            ->count())->toBe(1)
        ->and(TaskActivity::query()
            ->where('task_id', $otherTask->id)
            ->where('event_type', TaskActivityType::LABEL_REMOVED)
            ->count())->toBe(0);
});

test('DeleteLabel removes only its pivot when owner tasks share another label', function (): void {
    $owner = User::factory()->create();
    $removedLabel = Label::factory()->forUser($owner)->create(['name' => 'Removed']);
    $retainedLabel = Label::factory()->forUser($owner)->create(['name' => 'Retained']);
    $first = Task::factory()->for($owner)->create();
    $second = Task::factory()->for($owner)->create();
    $first->labels()->attach([$removedLabel->id, $retainedLabel->id]);
    $second->labels()->attach([$retainedLabel->id, $removedLabel->id]);

    (new DeleteLabel)->handle($owner, $removedLabel);

    $removals = TaskActivity::query()->where('event_type', TaskActivityType::LABEL_REMOVED)->get();

    expect($first->fresh()->labels->pluck('id')->all())->toBe([$retainedLabel->id])
        ->and($second->fresh()->labels->pluck('id')->all())->toBe([$retainedLabel->id])
        ->and($removals)->toHaveCount(2)
        ->and($removals->every(
            fn (TaskActivity $activity): bool => $activity->old_value === ['label_id' => $removedLabel->id]
                && $activity->new_value === ['label_id' => null],
        ))->toBeTrue();
});

test('DeleteLabel orders its owner-scoped task locks by primary key before locking', function (): void {
    $source = file_get_contents(app_path('Domain/Labels/Actions/DeleteLabel.php'));

    expect($source)->toMatch('/tasks\(\)\s*->withTrashed\(\)\s*->ownedBy\(\$user\)\s*->orderBy\(\(new Task\)->qualifyColumn\(\(new Task\)->getKeyName\(\)\)\)\s*->lockForUpdate\(\)\s*->get\(\)/s');
});

test('label deletion by another user is rejected without detaching the label', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $task = Task::factory()->for($owner)->create();
    $label = Label::factory()->forUser($owner)->create();
    $task->labels()->attach($label);

    expect(fn (): mixed => (new DeleteLabel)->handle($other, $label))->toThrow(AuthorizationException::class);

    expect($task->fresh()->labels->pluck('id')->all())->toBe([$label->id])
        ->and(TaskActivity::query()->count())->toBe(0);
});
