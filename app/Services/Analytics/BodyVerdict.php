<?php

namespace App\Services\Analytics;

use App\Support\Units;

/**
 * Reads the corroborating body signals together and states, in words, what they
 * agree on.
 *
 * This exists because the body page used to end with a fixed sentence — "chest
 * and arms rising while waist stays flat" — printed regardless of what the
 * athlete's numbers actually said. For anyone whose waist WAS climbing, the app
 * asserted the opposite of its own data directly underneath it.
 *
 * The method is triangulation, not a better scale. A BIA reading moves several
 * points on hydration alone, so no single number is trusted. Instead we ask
 * whether independent measurements — tape, bodyweight trend and strength — point
 * the same way, and we report how strongly they agree. When they disagree, that
 * IS the answer, and saying so is more useful than picking a winner.
 *
 * Everything here is derived. Nothing is asserted that the signals do not show,
 * and the confidence label is driven by how many signals were available.
 */
class BodyVerdict
{
    /**
     * Below this, a tape trend is indistinguishable from measuring slightly
     * differently each time. A soft tape re-sited by half a centimetre is common,
     * so a month's drift under 0.2 cm is treated as "flat" rather than as change.
     */
    private const TAPE_NOISE_CM_PER_MONTH = 0.2;

    /** Bodyweight below this weekly rate is drift, not a direction. */
    private const WEIGHT_NOISE_KG_PER_WEEK = 0.05;

    private readonly Units $units;

    public function __construct(private readonly array $triangulation, ?Units $units = null)
    {
        // Judgements (noise thresholds, directions) always run on the metric
        // inputs; only the printed detail strings speak the user's unit.
        $this->units = $units ?? Units::metric();
    }

    /**
     * @return array{
     *     headline: string, tone: string, body: string, confidence: string,
     *     signals: array<int, array{label: string, direction: string, detail: string}>
     * }|null  Null when there is not enough data to say anything honest.
     */
    public function verdict(): ?array
    {
        $waist = $this->perMonth('waist');
        $chest = $this->perMonth('chest');
        $bicep = $this->perMonth('bicep');
        $weight = $this->triangulation['weight']['kg_per_week'] ?? null;
        $lift = $this->triangulation['lift']['trend'] ?? null;

        // Girth signals of muscle, counted only where a measurement exists.
        $muscleUp = 0;
        $muscleTotal = 0;
        foreach ([$chest, $bicep] as $signal) {
            if ($signal === null) {
                continue;
            }
            $muscleTotal++;
            if ($signal > self::TAPE_NOISE_CM_PER_MONTH) {
                $muscleUp++;
            }
        }

        if ($lift !== null) {
            $muscleTotal++;
            if ($lift === 'up') {
                $muscleUp++;
            }
        }

        // Nothing measured means nothing to say. An empty state beats a guess.
        if ($muscleTotal === 0 && $waist === null && $weight === null) {
            return null;
        }

        $waistRising = $waist !== null && $waist > self::TAPE_NOISE_CM_PER_MONTH;
        $waistFalling = $waist !== null && $waist < -self::TAPE_NOISE_CM_PER_MONTH;
        $waistFlat = $waist !== null && ! $waistRising && ! $waistFalling;

        $gaining = $weight !== null && $weight > self::WEIGHT_NOISE_KG_PER_WEEK;
        $losing = $weight !== null && $weight < -self::WEIGHT_NOISE_KG_PER_WEEK;

        $muscleAgrees = $muscleTotal > 0 && $muscleUp === $muscleTotal;
        $muscleMostly = $muscleTotal > 1 && $muscleUp > 0 && $muscleUp < $muscleTotal;
        $muscleFlat = $muscleTotal > 0 && $muscleUp === 0;

        // Order matters. The deficit cases are tested first because a falling
        // waist and a falling bodyweight tell a different story from a flat one,
        // and the general "gaining muscle" branch would otherwise swallow an
        // athlete who is mid-cut: chest and arms holding while the waist drops is
        // a successful cut, not a bulk.
        [$headline, $tone, $body] = match (true) {
            // Losing weight with strength going the same way is the warning sign.
            $losing && $lift === 'down' => [
                __('app.body.verdict.losing_muscle_risk'),
                'warn',
                __('app.body.verdict.losing_muscle_risk_body', [
                    'exercise' => $this->triangulation['lift']['exercise'] ?? '',
                ]),
            ],

            // Waist down while size/strength hold: the outcome a cut is for.
            $waistFalling && ($muscleUp > 0 || $lift === 'up') => [
                __('app.body.verdict.losing_fat_keeping_muscle'),
                'good',
                __('app.body.verdict.losing_fat_keeping_muscle_body', [
                    'waist' => $this->cm(abs($waist)),
                    'signals' => $this->list($this->risingLabels($chest, $bicep, $lift)),
                ]),
            ],

            // Size and strength up, waist not following.
            $muscleAgrees && ! $waistRising => [
                __('app.body.verdict.gaining_muscle'),
                'good',
                __('app.body.verdict.gaining_muscle_body', [
                    'signals' => $this->list($this->risingLabels($chest, $bicep, $lift)),
                    'waist' => $this->waistPhrase($waist),
                ]),
            ],

            // Everything is growing, waist included: still a gain, but not a clean one.
            $muscleUp > 0 && $waistRising && $gaining => [
                __('app.body.verdict.gaining_both'),
                'warn',
                __('app.body.verdict.gaining_both_body', [
                    'waist' => $this->cm($waist),
                    'signals' => $this->list($this->risingLabels($chest, $bicep, $lift)),
                ]),
            ],

            // Waist up while nothing else is: the surplus is not buying muscle.
            $muscleFlat && $waistRising => [
                __('app.body.verdict.gaining_fat'),
                'bad',
                __('app.body.verdict.gaining_fat_body', ['waist' => $this->cm($waist)]),
            ],

            $muscleMostly => [
                __('app.body.verdict.mixed'),
                'info',
                __('app.body.verdict.mixed_body', [
                    'up' => $this->list($this->risingLabels($chest, $bicep, $lift)),
                    'flat' => $this->list($this->flatLabels($chest, $bicep, $lift)),
                ]),
            ],

            $waistFlat && $muscleFlat => [
                __('app.body.verdict.holding'),
                'info',
                __('app.body.verdict.holding_body'),
            ],

            default => [
                __('app.body.verdict.unclear'),
                'info',
                __('app.body.verdict.unclear_body'),
            ],
        };

        return [
            'headline' => $headline,
            'tone' => $tone,
            'body' => $body,
            'confidence' => $this->confidence($muscleTotal, $waist !== null, $weight !== null),
            'signals' => $this->signals($chest, $bicep, $waist, $weight, $lift),
        ];
    }

    /**
     * How much weight to put on the verdict. Driven by how many independent
     * measurements were available, because that is the whole point of
     * triangulating: one signal agreeing with itself is not corroboration.
     */
    private function confidence(int $muscleSignals, bool $hasWaist, bool $hasWeight): string
    {
        $count = $muscleSignals + ($hasWaist ? 1 : 0) + ($hasWeight ? 1 : 0);

        return match (true) {
            $count >= 4 => 'high',
            $count >= 2 => 'moderate',
            default => 'low',
        };
    }

    /** @return array<int, array{label: string, direction: string, detail: string}> */
    private function signals(?float $chest, ?float $bicep, ?float $waist, ?float $weight, ?string $lift): array
    {
        $out = [];

        foreach ([
            ['label' => __('app.series.chest'), 'value' => $chest, 'good_up' => true],
            ['label' => __('app.series.bicep'), 'value' => $bicep, 'good_up' => true],
            ['label' => __('app.series.waist'), 'value' => $waist, 'good_up' => false],
        ] as $tape) {
            if ($tape['value'] === null) {
                continue;
            }

            $rising = $tape['value'] > self::TAPE_NOISE_CM_PER_MONTH;
            $falling = $tape['value'] < -self::TAPE_NOISE_CM_PER_MONTH;

            $out[] = [
                'label' => $tape['label'],
                'direction' => match (true) {
                    $rising => $tape['good_up'] ? 'good' : 'bad',
                    $falling => $tape['good_up'] ? 'bad' : 'good',
                    default => 'flat',
                },
                'detail' => $this->cm($tape['value']),
            ];
        }

        if ($weight !== null) {
            $out[] = [
                'label' => __('app.series.weight', ['unit' => $this->units->weightUnit()]),
                'direction' => abs($weight) <= self::WEIGHT_NOISE_KG_PER_WEEK ? 'flat' : 'neutral',
                'detail' => sprintf('%+.2f %s/wk', $this->units->weight($weight, 2), $this->units->weightUnit()),
            ];
        }

        if ($lift !== null) {
            $out[] = [
                'label' => $this->triangulation['lift']['exercise'] ?? __('app.series.e1rm', ['unit' => $this->units->weightUnit()]),
                'direction' => $lift === 'up' ? 'good' : 'bad',
                'detail' => __($lift === 'up' ? 'app.body.verdict.strength_up' : 'app.body.verdict.strength_down'),
            ];
        }

        return $out;
    }

    /** @return array<int, string> */
    private function risingLabels(?float $chest, ?float $bicep, ?string $lift): array
    {
        $out = [];
        if ($chest !== null && $chest > self::TAPE_NOISE_CM_PER_MONTH) {
            $out[] = __('app.series.chest');
        }
        if ($bicep !== null && $bicep > self::TAPE_NOISE_CM_PER_MONTH) {
            $out[] = __('app.series.bicep');
        }
        if ($lift === 'up') {
            $out[] = __('app.body.verdict.your_top_lift');
        }

        return $out;
    }

    /** @return array<int, string> */
    private function flatLabels(?float $chest, ?float $bicep, ?string $lift): array
    {
        $out = [];
        if ($chest !== null && $chest <= self::TAPE_NOISE_CM_PER_MONTH) {
            $out[] = __('app.series.chest');
        }
        if ($bicep !== null && $bicep <= self::TAPE_NOISE_CM_PER_MONTH) {
            $out[] = __('app.series.bicep');
        }
        if ($lift === 'down') {
            $out[] = __('app.body.verdict.your_top_lift');
        }

        return $out;
    }

    private function perMonth(string $key): ?float
    {
        $value = $this->triangulation[$key]['per_month'] ?? null;

        return $value === null ? null : (float) $value;
    }

    private function cm(?float $value): string
    {
        return $value === null
            ? '—'
            : sprintf('%+.2f %s/%s', $this->units->girth($value, 2), $this->units->girthUnit(), __('app.body.verdict.month_abbr'));
    }

    private function waistPhrase(?float $waist): string
    {
        if ($waist === null) {
            return __('app.body.verdict.waist_unmeasured');
        }

        return $waist < -self::TAPE_NOISE_CM_PER_MONTH
            ? __('app.body.verdict.waist_falling', ['value' => $this->cm($waist)])
            : __('app.body.verdict.waist_flat', ['value' => $this->cm($waist)]);
    }

    /** @param  array<int, string>  $items */
    private function list(array $items): string
    {
        if ($items === []) {
            return '';
        }

        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items).' '.__('app.common.and').' '.$last;
    }
}
