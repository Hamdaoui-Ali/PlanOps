<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Actions\UpdateUserPreferences;
use App\Http\Requests\UpdateUserPreferencesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        $preference = Auth::user()->preference()->firstOrCreate([]);

        $timezones = \DateTimeZone::listIdentifiers();

        return view('pages.settings.index', compact('preference', 'timezones'));
    }

    public function update(UpdateUserPreferencesRequest $request, UpdateUserPreferences $update): RedirectResponse
    {
        $update->execute($request->user(), $request->validated());

        return to_route('settings.edit')->with('status', 'Preferences saved.');
    }
}
