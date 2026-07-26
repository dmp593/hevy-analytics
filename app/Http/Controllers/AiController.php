<?php

namespace App\Http\Controllers;

use App\Services\AI\AiQuota;
use App\Services\AI\DeepSeekService;
use App\Services\AI\MetricsSummary;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(private readonly DeepSeekService $deepSeek) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $quota = new AiQuota($user);

        return view('ai.index', [
            'analysis' => $this->deepSeek->latest($user, 'deep_analysis'),
            'configured' => $this->deepSeek->configured(),
            'quotaRemaining' => $quota->remaining(),
            'quotaLimit' => $quota->monthlyLimit(),
        ]);
    }

    public function generate(Request $request)
    {
        $user = $request->user();

        if (! $this->deepSeek->configured()) {
            return redirect()->route('ai')->with('error', 'AI analysis is not configured yet.');
        }

        $quota = new AiQuota($user);
        if (! $quota->allows()) {
            return redirect()->route('ai')->with('error', $quota->denialReason());
        }

        $metrics = MetricsSummary::build($user);
        $analysis = $this->deepSeek->analyze(
            $user,
            'deep_analysis',
            $metrics,
            MetricsSummary::systemPrompt(),
            // `force` bypasses the content-hash cache and bills a fresh call, so
            // it must respect the quota rather than sit outside it.
            force: $request->boolean('force'),
        );

        if (! $analysis) {
            return redirect()->route('ai')->with('error', 'The AI analysis could not be generated. Check your API key and try again.');
        }

        return redirect()->route('ai')->with('status', 'Analysis generated.');
    }
}
