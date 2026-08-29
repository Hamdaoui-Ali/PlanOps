<?php

use App\Http\Requests\ReportPeriodRequest;
use Illuminate\Support\Facades\Validator;

test('report period request accepts standard periods and defaults to today', function (): void {
    $request = new ReportPeriodRequest;

    expect($request->rules())->toBeArray();
    expect((new ReportPeriodRequest)->period())->toBe('today');
    expect(Validator::make(['period' => 'week'], $request->rules())->passes())->toBeTrue();
});

test('report period request rejects invalid custom ranges', function (): void {
    $request = new ReportPeriodRequest;

    expect(Validator::make([
        'period' => 'custom',
        'from' => '2026-09-10',
        'until' => '2026-09-01',
    ], $request->rules())->fails())->toBeTrue();
});
