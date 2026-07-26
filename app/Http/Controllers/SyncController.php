<?php

namespace App\Http\Controllers;

use App\Services\Hevy\HevySync;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->hasHevyKey()) {
            return back()->with('error', 'Add your Hevy API key in Profile first.');
        }

        try {
            $counts = (new HevySync($user))->run((bool) $request->boolean('force'));
            $summary = collect($counts)->map(fn ($v, $k) => "{$v} {$k}")->implode(', ');

            return back()->with('status', "Synced: {$summary}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Sync failed: '.$e->getMessage());
        }
    }
}
