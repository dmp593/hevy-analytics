<?php

namespace App\Science\Goals;

class GoalType
{
    /** @return array<string, array{label: string, description: string}> */
    public static function all(): array
    {
        return [
            'lean_bulk' => ['label' => 'Lean Bulk', 'description' => 'Slow surplus to add muscle while minimizing fat gain (+0.25-0.5%BW/wk).'],
            'hypertrophy' => ['label' => 'Hypertrophy / Maingain', 'description' => 'Maintenance to tiny surplus, maximal training volume.'],
            'aggressive_bulk' => ['label' => 'Aggressive Bulk', 'description' => 'Larger surplus for faster mass gain, accepts more fat.'],
            'recomposition' => ['label' => 'Recomposition', 'description' => 'Maintenance calories, high protein — build muscle & lose fat simultaneously.'],
            'cut' => ['label' => 'Cut / Fat Loss', 'description' => 'Deficit with very high protein to preserve muscle (-0.5-1%BW/wk).'],
            'strength' => ['label' => 'Strength', 'description' => 'Low reps, heavy loads, slight surplus for max force output.'],
        ];
    }

    public static function label(string $type): string
    {
        return self::all()[$type]['label'] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
