<?php

namespace App\Services\Analytics;

/**
 * Answers the question the performance page asks in its own subtitle: is the
 * weight on the bar actually going up?
 *
 * The page used to answer it with tonnage, working sets and total reps. Those
 * are volume — how much work was done — and volume can rise while every lift
 * stalls, simply by adding sets. The only honest answer is per-lift e1RM over
 * time, which is what this reads.
 *
 * Lifts with too little history, or with a trend too scattered to trust, are
 * excluded from the count rather than filed under "flat". Counting an unknown as
 * a stall would manufacture bad news.
 */
class StrengthVerdict
{
    /** @param  array<string, array{direction: string, per_week: float, sessions: int, r2: float, reliable: bool}>  $trends */
    public function __construct(
        private readonly array $trends,
        private readonly array $prs = [],
    ) {}

    /**
     * @return array{headline: string, tone: string, body: string, rising: int, stalled: int, falling: int, unknown: int, movers: array<int, array{exercise: string, per_week: float, direction: string}>}|null
     */
    public function verdict(): ?array
    {
        $confident = array_filter($this->trends, fn ($t) => $t['reliable']);

        if ($confident === []) {
            return null;
        }

        $rising = $this->count($confident, 'up');
        $falling = $this->count($confident, 'down');
        $stalled = $this->count($confident, 'flat');
        $total = count($confident);
        $unknown = count($this->trends) - $total;

        $names = $this->names();

        // Ranked by weekly change so the page leads with the lift that has moved
        // most, in whichever direction.
        $movers = [];
        foreach ($confident as $key => $trend) {
            $movers[] = [
                'exercise' => $names[$key] ?? (string) $key,
                'per_week' => $trend['per_week'],
                'direction' => $trend['direction'],
            ];
        }
        usort($movers, fn ($a, $b) => abs($b['per_week']) <=> abs($a['per_week']));

        $best = $this->firstWith($movers, 'up');
        $worst = $this->firstWith($movers, 'down');

        [$headline, $tone, $body] = match (true) {
            $falling > 0 && $falling >= $rising => [
                trans_choice('app.performance.verdict.falling_headline', $falling, ['count' => $falling]),
                'bad',
                __('app.performance.verdict.falling_body', [
                    'exercise' => $worst['exercise'] ?? '',
                    'rate' => $this->yearly($worst['per_week'] ?? 0),
                ]),
            ],

            $rising === $total => [
                trans_choice('app.performance.verdict.all_rising_headline', $total, ['count' => $total]),
                'good',
                __('app.performance.verdict.all_rising_body', [
                    'exercise' => $best['exercise'] ?? '',
                    'rate' => $this->yearly($best['per_week'] ?? 0),
                ]),
            ],

            $rising > 0 => [
                __('app.performance.verdict.mixed_headline', ['rising' => $rising, 'total' => $total]),
                $stalled > $rising ? 'warn' : 'info',
                __('app.performance.verdict.mixed_body', [
                    'exercise' => $best['exercise'] ?? '',
                    'rate' => $this->yearly($best['per_week'] ?? 0),
                    'stalled' => $stalled,
                ]),
            ],

            default => [
                trans_choice('app.performance.verdict.stalled_headline', $total, ['count' => $total]),
                'warn',
                __('app.performance.verdict.stalled_body'),
            ],
        };

        return [
            'headline' => $headline,
            'tone' => $tone,
            'body' => $body,
            'rising' => $rising,
            'stalled' => $stalled,
            'falling' => $falling,
            'unknown' => $unknown,
            'movers' => $movers,
        ];
    }

    /** @param  array<int, array{direction: string}>  $movers */
    private function firstWith(array $movers, string $direction): ?array
    {
        foreach ($movers as $mover) {
            if ($mover['direction'] === $direction) {
                return $mover;
            }
        }

        return null;
    }

    /** Weekly slope restated per year, which is the horizon lifters think in. */
    private function yearly(float $perWeek): string
    {
        return sprintf('%+.1f kg', $perWeek * 52);
    }

    private function count(array $trends, string $direction): int
    {
        return count(array_filter($trends, fn ($t) => $t['direction'] === $direction));
    }

    /** @return array<string, string> template id (or title) => display title */
    private function names(): array
    {
        $out = [];
        foreach ($this->prs as $pr) {
            $out[$pr['template_id'] ?? $pr['exercise']] = $pr['exercise'];
        }

        return $out;
    }
}
