<?php

namespace App\Http\Controllers;

use App\Services\AI\ProviderRegistry;
use App\Services\AI\UrlGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AiSettingsController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(ProviderRegistry::keys())],
            // Printable ASCII only, and no whitespace. Every provider issues
            // keys in that alphabet, and the restriction matters: a key
            // containing CR or LF is rejected by Guzzle's header validator,
            // which puts THE WHOLE KEY into its exception message and from there
            // into the application log.
            'api_key' => ['nullable', 'string', 'max:400', 'regex:/^[\x21-\x7e]+$/'],
            'model' => ['required', 'string', 'max:120'],
            'base_url' => ['nullable', 'string', 'max:400'],
        ]);

        $user = $request->user();
        $provider = $data['provider'];
        $meta = ProviderRegistry::get($provider);

        $existing = $user->aiCredentials()->where('provider', $provider)->first();

        // A blank key means "keep the one you have", so an athlete can change
        // their model without re-pasting a secret they cannot read back.
        $key = filled($data['api_key']) ? $data['api_key'] : $existing?->api_key;

        if (blank($key)) {
            throw ValidationException::withMessages([
                'api_key' => __('app.settings.ai.key_required'),
            ]);
        }

        $baseUrl = $meta['base_url_editable']
            ? (string) ($data['base_url'] ?? '')
            : $meta['base_url'];

        // Checked here as well as at call time. Validating at save gives the
        // athlete a specific error while they are looking at the field; checking
        // again at call time catches a host that was repointed afterwards.
        $check = UrlGuard::check($baseUrl);

        if (! $check['ok']) {
            throw ValidationException::withMessages([
                'base_url' => __($check['reason']),
            ]);
        }

        $user->aiCredentials()->updateOrCreate(
            ['provider' => $provider],
            [
                'api_key' => $key,
                'model' => $data['model'],
                'base_url' => $check['url'],
                'is_active' => true,
                // Cleared because this is a different key, model or endpoint
                // from whatever last succeeded or failed.
                'last_verified_at' => null,
                'last_error' => null,
            ],
        );

        // One active provider at a time. The others are kept rather than
        // deleted, so switching back does not mean pasting the key again.
        $user->aiCredentials()->where('provider', '!=', $provider)->update(['is_active' => false]);

        return redirect()->route('profile.edit')->with('status', __('app.settings.ai.saved'));
    }

    public function destroy(Request $request, string $provider)
    {
        abort_unless(ProviderRegistry::has($provider), 404);

        $request->user()->aiCredentials()->where('provider', $provider)->delete();

        return redirect()->route('profile.edit')->with('status', __('app.settings.ai.removed'));
    }
}
