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

test('label attach and detach are owner-scoped, idempotent, and record only pivot changes', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $task = Task::factory()->for($owner)->create();
    $label = Label::factory()->forUser($owner)->create();
    $foreignLabel = Label::factory()->forUser($other)->create();

    (new AttachLabelToTask)->handle($owner, $task, $label);
    (new AttachLabelToTask)->handle($owner, $task->fresh(), $label);
    (new DetachLabelFromTask)->handle($owner, $task->fresh(), $label);
    (new DetachLabelFromTask)->handle($owner, $task->fresh(), $label);

    expect($task->fresh()->labels)->toHaveCount(0)
        ->and(TaskActivity::query()->where('event_type', TaskActivityType::LABEL_ADDED)->count())->toBe(1)
        ->and(TaskActivity::query()->where('event_type', TaskActivityType::LABEL_REMOVED)->count())->toBe(1)
        ->and(fn (): Task => (new AttachLabelToTask)->handle($owner, $task, $foreignLabel))->toThrow(AuthorizationException::class);
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
