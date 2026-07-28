<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\Units;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Don't overwrite the stored Hevy key when the field is left blank.
        if (blank($validated['hevy_api_key'] ?? null)) {
            unset($validated['hevy_api_key']);
        }

        // The imperial form submits feet and inches; the column is metric.
        // Folding happens here, not in the model, so height_cm stays the one
        // value every calculation reads.
        if (filled($validated['height_ft'] ?? null)) {
            $validated['height_cm'] = Units::heightToCm($validated['height_ft'], $validated['height_in'] ?? null);
        }
        unset($validated['height_ft'], $validated['height_in']);

        $request->user()->fill($validated);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        // Delete photos through the model so its deleting hook removes the files
        // from disk. A database cascade would drop the rows and leave the images
        // behind — for GDPR purposes, still holding the data.
        $user->progressPhotos->each->delete();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
