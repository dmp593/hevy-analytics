<?php

namespace App\Services\Analytics;

use App\Models\NutritionTarget;

/**
 * States what the app has actually learned about this athlete's metabolism.
 *
 * A calorie formula guesses maintenance from height, weight, age and a chosen
 * activity multiplier. It cannot know an individual's metabolism, and it is
 * routinely wrong by several hundred kcal — which is the difference between a
 * lean bulk and a fat one.
 *
 * NutritionService::adaptiveMaintenance() measures it instead, from the
 * athlete's own logged intake against their observed weight trend:
 * maintenance = average intake − (weekly change × 7700 / 7). That is the one
 * thing in this app a free calculator genuinely cannot do, and it was previously
 * surfaced as the word "adaptive" next to a number in a grey subtitle.
 *
 * Below the logging threshold the honest answer is that the targets are a
 * textbook estimate, and this says so in those words rather than presenting a
 * formula's output with the same confidence as a measurement.
 */
class NutritionVerdict
{
    /**
     * A gap smaller than this is inside the noise of food logging and scale
     * variation, so the formula and the measurement are reported as agreeing
     * rather than as differing by a number nobody should act on.
     */
    private const MEANINGFUL_GAP_KCAL = 100;

    public function __construct(
        private readonly ?NutritionTarget $target,
        private readonly int $loggedDays,
        private readonly int $requiredDays = 7,
    ) {}

    /**
     * @return array{headline: string, tone: string, body: string, measured: ?float, formula: ?float, gap: ?float}|null
     */
    public function verdict(): ?array
    {
        if (! $this->target) {
            return null;
        }

        $measured = $this->target->basis['adaptive_maintenance'] ?? null;
        $formula = $this->target->basis['formula_tdee'] ?? null;

        // Not enough logged days to measure anything. Say that plainly instead
        // of letting a formula's output pass for a measurement.
        if ($measured === null) {
            return [
                'headline' => __('app.nutrition.verdict.estimate_headline'),
                'tone' => 'info',
                'body' => trans_choice('app.nutrition.verdict.estimate_body', $this->requiredDays - $this->loggedDays, [
                    'needed' => max(0, $this->requiredDays - $this->loggedDays),
                    'logged' => $this->loggedDays,
                ]),
                'measured' => null,
                'formula' => $formula ? (float) $formula : null,
                'gap' => null,
            ];
        }

        $measured = (float) $measured;
        $gap = $formula === null ? null : round($measured - (float) $formula);

        [$headline, $tone, $body] = match (true) {
            $gap === null || abs($gap) < self::MEANINGFUL_GAP_KCAL => [
                __('app.nutrition.verdict.agrees_headline'),
                'good',
                __('app.nutrition.verdict.agrees_body', [
                    'measured' => number_format($measured),
                    'days' => $this->loggedDays,
                ]),
            ],

            // Burning more than the formula predicts: under-eating the target is
            // the risk, and on a bulk it explains a stall that looks like bad luck.
            $gap > 0 => [
                __('app.nutrition.verdict.higher_headline'),
                'info',
                __('app.nutrition.verdict.higher_body', [
                    'measured' => number_format($measured),
                    'formula' => number_format((float) $formula),
                    'gap' => number_format(abs($gap)),
                    'days' => $this->loggedDays,
                ]),
            ],

            default => [
                __('app.nutrition.verdict.lower_headline'),
                'info',
                __('app.nutrition.verdict.lower_body', [
                    'measured' => number_format($measured),
                    'formula' => number_format((float) $formula),
                    'gap' => number_format(abs($gap)),
                    'days' => $this->loggedDays,
                ]),
            ],
        };

        return [
            'headline' => $headline,
            'tone' => $tone,
            'body' => $body,
            'measured' => $measured,
            'formula' => $formula ? (float) $formula : null,
            'gap' => $gap,
        ];
    }
}
