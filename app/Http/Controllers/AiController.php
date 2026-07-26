<?php

namespace App\Http\Controllers;

use App\Services\AI\DeepSeekService;
use App\Services\AI\MetricsSummary;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(private readonly DeepSeekService $deepSeek) {}

    public function index(Request $request)
    {
        $user = $request->user();

        return view('ai.index', [
            'analysis' => $this->deepSeek->latest($user, 'deep_analysis'),
            'configured' => $this->deepSeek->configured(),
        ]);
    }

    public function generate(Request $request)
    {
        $user = $request->user();

        if (! $this->deepSeek->configured()) {
            return redirect()->route('ai')->with('error', 'DEEPSEEK_API_KEY is not set in your .env file.');
        }

        $metrics = MetricsSummary::build($user);
        $analysis = $this->deepSeek->analyze(
            $user,
            'deep_analysis',
            $metrics,
            MetricsSummary::systemPrompt(),
            force: $request->boolean('force'),
        );

        if (! $analysis) {
            return redirect()->route('ai')->with('error', 'The AI analysis could not be generated. Check your API key and try again.');
        }

        return redirect()->route('ai')->with('status', 'Analysis generated.');
    }
}
