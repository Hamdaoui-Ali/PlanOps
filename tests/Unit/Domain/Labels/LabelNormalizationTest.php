<?php

use App\Domain\Labels\Rules\NormalizedLabelName;
use App\Domain\Labels\Models\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('label display names are trimmed and repeated whitespace is squished', function (): void {
    $rule = new NormalizedLabelName;

    expect($rule->displayName("  Frontend\t  Platform  "))->toBe('Frontend Platform');
});

test('label normalized names are lowercase display names', function (): void {
    $rule = new NormalizedLabelName;

    expect($rule->normalize('  Frontend\t  Platform  '))->toBe('frontend platform');
});

test('normalized label names collide for one owner but not another owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    Label::factory()->forUser($owner)->create([
        'name' => 'Frontend',
        'normalized_name' => 'frontend',
    ]);
    Label::factory()->forUser($other)->create([
        'name' => ' frontend ',
        'normalized_name' => 'frontend',
    ]);

    expect(Label::query()->ownedBy($owner)->where('normalized_name', 'frontend')->count())->toBe(1)
        ->and(Label::query()->ownedBy($other)->where('normalized_name', 'frontend')->count())->toBe(1)
        ->and(Label::query()->where('normalized_name', 'frontend')->count())->toBe(2);
});
