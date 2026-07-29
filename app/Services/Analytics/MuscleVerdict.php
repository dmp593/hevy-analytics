<?php

namespace App\Services\Analytics;

use App\Support\Labels;

/**
 * Turns the weekly-sets table into the sentence the page is actually for.
 *
 * The muscle page promises to say which muscles are under-trained and by how
 * much, then answers it with ten bars and four abbreviations per bar
 * (MV/MEV/MAV/MRV). The information is all there; the conclusion is not. An
 * athlete has to decode the legend and do the subtraction themselves to reach
 * the one thing they came for: which muscles to add sets to, and how many.
 *
 * So this does the subtraction. "Chest needs 5 more sets a week" is a thing you
 * can act on tomorrow; "Chest 5.4/wk, MEV 10" is homework.
 */
class MuscleVerdict
{
    /**
     * A ratio this far from 1.0 is treated as a genuine imbalance rather than
     * ordinary variation between sessions.
     */
    private const RATIO_SKEW = 0.35;

    /** Muscles named individually before the rest are summarised as a count. */
    private const NAMED_LIMIT = 3;

    /**
     * @param  array<int, array{muscle: string, per_week: float, status: string, landmarks: array{mv: float, mev: float, mav: float, mrv: float}}>  $weeklySets
     * @param  array<string, array{ratio: float|null, label_a: string, label_b: string, balanced: bool, enough_data: bool}>  $balance
     */
    public function __construct(
        private readonly array $weeklySets,
        private readonly array $balance = [],
    ) {}

    /**
     * @return array{headline: string, tone: string, body: string, gaps: array<int, array{muscle: string, need: float, current: float, target: float}>, excess: array<int, string>, imbalance: ?string}|null
     */
    public function verdict(): ?array
    {
        if ($this->weeklySets === []) {
            return null;
        }

        $gaps = [];
        $excess = [];

        foreach ($this->weeklySets as $row) {
            $mev = (float) ($row['landmarks']['mev'] ?? 0);
            $mrv = (float) ($row['landmarks']['mrv'] ?? 0);
            $perWeek = (float) $row['per_week'];

            if ($mev > 0 && $perWeek < $mev) {
                $gaps[] = [
                    'muscle' => Labels::muscle($row['muscle']),
                    'current' => $perWeek,
                    'target' => $mev,
                    // Rounded up: a partial set is not a thing you can do, and
                    // rounding down would leave the muscle still short of MEV.
                    'need' => (float) ceil($mev - $perWeek),
                ];
            } elseif ($mrv > 0 && $perWeek > $mrv) {
                $excess[] = Labels::muscle($row['muscle']);
            }
        }

        // Largest shortfall first: that is where added sets buy the most.
        usort($gaps, fn ($a, $b) => $b['need'] <=> $a['need']);

        $imbalance = $this->imbalance();
        $total = count($this->weeklySets);

        [$headline, $tone, $body] = match (true) {
            $gaps !== [] => [
                trans_choice('app.muscle.verdict.under_headline', count($gaps), [
                    'count' => count($gaps),
                    'total' => $total,
                ]),
                count($gaps) > $total / 2 ? 'bad' : 'warn',
                __('app.muscle.verdict.under_body', [
                    'fixes' => $this->fixList($gaps),
                ]),
            ],

            $excess !== [] => [
                __('app.muscle.verdict.over_headline'),
                'warn',
                __('app.muscle.verdict.over_body', ['muscles' => implode(', ', $excess)]),
            ],

            default => [
                __('app.muscle.verdict.all_good_headline'),
                'good',
                __('app.muscle.verdict.all_good_body'),
            ],
        };

        return [
            'headline' => $headline,
            'tone' => $tone,
            'body' => $body,
            'gaps' => $gaps,
            'excess' => $excess,
            'imbalance' => $imbalance,
        ];
    }

    /**
     * The most skewed of the three balance ratios, phrased as a sentence.
     * Only one is reported: three imbalance warnings at once reads as noise and
     * they are usually the same underlying problem seen from three angles.
     */
    private function imbalance(): ?string
    {
        $worst = null;
        $worstDistance = self::RATIO_SKEW;

        foreach ($this->balance as $key => $row) {
            $ratio = $row['ratio'] ?? null;
            if ($ratio === null || $ratio <= 0) {
                continue;
            }

            // Compare in log space so 2.0 and 0.5 are equally far from balanced;
            // on a linear scale "twice as much" and "half as much" would score
            // 1.0 and 0.5, and the under-trained side would always look milder.
            $distance = abs(log((float) $ratio));

            if ($distance > $worstDistance) {
                $worstDistance = $distance;
                $worst = [$key, (float) $ratio];
            }
        }

        if ($worst === null) {
            return null;
        }

        [$key, $ratio] = $worst;

        // The balance row already names its own sides — a local table here
        // would need manual re-syncing every time a group is added.
        $row = $this->balance[$key] ?? null;
        $sides = ($row['label_a'] ?? null) !== null ? [$row['label_a'], $row['label_b']] : null;

        if ($sides === null) {
            return null;
        }

        // The ratio is A/B, so above 1 means the first side dominates.
        [$heavy, $light] = $ratio > 1 ? $sides : [$sides[1], $sides[0]];

        return __('app.muscle.verdict.imbalance', [
            'heavy' => __('app.balance_groups.'.$heavy),
            'light' => __('app.balance_groups.'.$light),
            'ratio' => number_format($ratio > 1 ? $ratio : 1 / $ratio, 1),
        ]);
    }

    /** @param  array<int, array{muscle: string, need: float}>  $gaps */
    private function fixList(array $gaps): string
    {
        $named = array_slice($gaps, 0, self::NAMED_LIMIT);

        $parts = array_map(
            fn ($g) => __('app.muscle.verdict.fix', [
                'muscle' => $g['muscle'],
                'sets' => (int) $g['need'],
            ]),
            $named,
        );

        $sentence = implode('; ', $parts);

        $remaining = count($gaps) - count($named);
        if ($remaining > 0) {
            $sentence .= '. '.trans_choice('app.muscle.verdict.and_more', $remaining, ['count' => $remaining]);
        }

        return $sentence;
    }
}
