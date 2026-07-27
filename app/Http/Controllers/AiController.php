<?php

namespace App\Http\Controllers;

use App\Services\AI\AiQuota;
use App\Services\AI\AnalysisService;
use App\Services\AI\CredentialResolver;
use App\Services\AI\MetricsSummary;
use App\Services\AI\ProviderException;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(private readonly AnalysisService $ai) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $quota = new AiQuota($user);
        $resolver = new CredentialResolver($user);

        return view('ai.index', [
            'analysis' => $this->ai->latest($user, 'deep_analysis'),
            'configured' => $resolver->configured(),
            // An athlete on their own key is not rationed, so showing them an
            // allowance would be inventing a limit that does not exist.
            'usesOwnKey' => $resolver->usesOwnKey(),
            'quotaRemaining' => $quota->remaining(),
            'quotaLimit' => $quota->monthlyLimit(),
        ]);
    }

    public function generate(Request $request)
    {
        $user = $request->user();
        $resolver = new CredentialResolver($user);

        if (! $resolver->configured()) {
            return redirect()->route('ai')->with('error', __('app.ai.unavailable'));
        }

        $metrics = MetricsSummary::build($user);

        // A cached analysis costs nothing to return, so the allowance only gates
        // calls that would actually go out to the provider.
        if (! $request->boolean('force') && $this->ai->cached($user, 'deep_analysis', $metrics)) {
            return redirect()->route('ai')->with('status', __('app.ai.unchanged'));
        }

        // The allowance exists to bound the operator's API bill. An athlete
        // spending their own key is not spending it, so the check does not apply.
        if (! $resolver->usesOwnKey()) {
            $quota = new AiQuota($user);

            if (! $quota->allows()) {
                return redirect()->route('ai')->with('error', $quota->denialReason());
            }
        }

        try {
            $analysis = $this->ai->analyze(
                $user,
                'deep_analysis',
                $metrics,
                MetricsSummary::systemPrompt(),
                // `force` bypasses the content-hash cache and bills a fresh
                // call, so it must respect the quota rather than sit outside it.
                force: $request->boolean('force'),
            );
        } catch (ProviderException $e) {
            // Distinguishes "the key was rejected" from "the endpoint is
            // unreachable" from "you are being rate limited", because each needs
            // something different from the athlete.
            return redirect()->route('ai')->with('error', $e->userMessage());
        }

        if (! $analysis) {
            return redirect()->route('ai')->with('error', __('app.ai.unavailable'));
        }

        return redirect()->route('ai')->with('status', __('app.ai.generated'));
    }
}
