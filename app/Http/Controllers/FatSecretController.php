<?php

namespace App\Http\Controllers;

use App\Services\FatSecret\FatSecretClient;
use App\Services\FatSecret\FatSecretSync;
use Illuminate\Http\Request;

/**
 * Linking a fatsecret.com account: the classic OAuth 1.0 three-legged dance.
 * The request-token secret lives in the session between the redirect out and
 * the callback in — it is worthless after the exchange and stored nowhere.
 */
class FatSecretController extends Controller
{
    public function connect(Request $request)
    {
        abort_unless(FatSecretClient::configured(), 404);

        try {
            $pair = (new FatSecretClient)->requestToken(route('fatsecret.callback'));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('app.fatsecret.failed'));
        }

        $request->session()->put('fatsecret.request_secret', $pair['secret']);

        return redirect()->away((new FatSecretClient)->authorizeUrl($pair['token']));
    }

    public function callback(Request $request)
    {
        abort_unless(FatSecretClient::configured(), 404);

        $token = (string) $request->query('oauth_token');
        $verifier = (string) $request->query('oauth_verifier');
        $secret = (string) $request->session()->pull('fatsecret.request_secret');

        if ($token === '' || $verifier === '' || $secret === '') {
            return redirect()->route('profile.edit')->with('error', __('app.fatsecret.failed'));
        }

        try {
            $pair = (new FatSecretClient)->accessToken($token, $secret, $verifier);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('profile.edit')->with('error', __('app.fatsecret.failed'));
        }

        $request->user()->forceFill([
            'fatsecret_token' => $pair['token'],
            'fatsecret_secret' => $pair['secret'],
            'fatsecret_linked_at' => now(),
        ])->save();

        return redirect()->route('profile.edit')->with('status', __('app.fatsecret.linked'));
    }

    public function sync(Request $request)
    {
        abort_unless($request->user()->fatsecret_linked_at !== null, 404);

        try {
            $days = (new FatSecretSync)->run($request->user());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('app.fatsecret.sync_failed'));
        }

        return back()->with('status', trans_choice('app.fatsecret.synced', $days, ['days' => $days]));
    }

    public function disconnect(Request $request)
    {
        $request->user()->forceFill([
            'fatsecret_token' => null,
            'fatsecret_secret' => null,
            'fatsecret_linked_at' => null,
            'fatsecret_synced_at' => null,
        ])->save();

        return back()->with('status', __('app.fatsecret.unlinked'));
    }
}
