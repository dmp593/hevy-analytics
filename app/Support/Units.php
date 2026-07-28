<?php

namespace App\Support;

use App\Models\User;

/**
 * The single place metric becomes imperial and back.
 *
 * Everything the app stores is metric — kilograms and centimetres — and every
 * calculation runs on metric values. This class exists only at the edges: a
 * form field on its way in, a number or a chart series on its way out. With
 * the conversion confined here, no calculation can accidentally run on a
 * pounds value that merely looks like kilograms.
 *
 * Scope: body data (height, bodyweight, tape measurements). Training loads
 * stay in kg everywhere for now — converting every chart, tooltip and
 * strength standard is its own project.
 */
final class Units
{
    public const METRIC = 'metric';

    public const IMPERIAL = 'imperial';

    public const SYSTEMS = [self::METRIC, self::IMPERIAL];

    private const LB_PER_KG = 2.2046226218;

    private const CM_PER_IN = 2.54;

    private function __construct(private readonly string $system) {}

    public static function for(?User $user): self
    {
        return new self($user?->unit_system === self::IMPERIAL ? self::IMPERIAL : self::METRIC);
    }

    public static function metric(): self
    {
        return new self(self::METRIC);
    }

    public function imperial(): bool
    {
        return $this->system === self::IMPERIAL;
    }

    /* ---------------------------------------------------------------------
     | Display: canonical metric value -> number in the user's unit.
     */

    public function weightUnit(): string
    {
        return $this->imperial() ? 'lb' : 'kg';
    }

    public function girthUnit(): string
    {
        return $this->imperial() ? 'in' : 'cm';
    }

    public function weight(null|int|float $kg, int $decimals = 1): ?float
    {
        if ($kg === null) {
            return null;
        }

        return round($this->imperial() ? $kg * self::LB_PER_KG : (float) $kg, $decimals);
    }

    public function girth(null|int|float $cm, int $decimals = 1): ?float
    {
        if ($cm === null) {
            return null;
        }

        return round($this->imperial() ? $cm / self::CM_PER_IN : (float) $cm, $decimals);
    }

    /** @param array<int, array{label: string, value: float}> $series */
    public function weightSeries(array $series): array
    {
        return array_map(fn ($p) => ['label' => $p['label'], 'value' => $this->weight($p['value'])], $series);
    }

    /** @param array<int, array{label: string, value: float}> $series */
    public function girthSeries(array $series): array
    {
        return array_map(fn ($p) => ['label' => $p['label'], 'value' => $this->girth($p['value'])], $series);
    }

    /**
     * cm -> [feet, inches] for the two-field imperial height input.
     * Rounding inches to one decimal can produce 12.0, which must carry.
     *
     * @return array{int, float}
     */
    public static function heightParts(float $cm): array
    {
        $totalInches = $cm / self::CM_PER_IN;
        $feet = (int) floor($totalInches / 12);
        $inches = round($totalInches - $feet * 12, 1);

        if ($inches >= 12) {
            $feet++;
            $inches = 0.0;
        }

        return [$feet, $inches];
    }

    /* ---------------------------------------------------------------------
     | Input: number typed in the user's unit -> canonical metric.
     */

    public function weightToKg(null|int|float|string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $v = (float) $value;

        return round($this->imperial() ? $v / self::LB_PER_KG : $v, 2);
    }

    public function girthToCm(null|int|float|string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $v = (float) $value;

        return round($this->imperial() ? $v * self::CM_PER_IN : $v, 1);
    }

    public static function heightToCm(null|int|float|string $feet, null|int|float|string $inches = null): ?float
    {
        if ($feet === null || $feet === '') {
            return null;
        }

        $total = ((float) $feet * 12 + (float) ($inches ?: 0)) * self::CM_PER_IN;

        return round($total, 1);
    }
}
