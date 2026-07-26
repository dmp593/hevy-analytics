<?php

namespace App\Science\Stats;

/**
 * Ordinary least squares linear regression with R² and standard error,
 * used for trend-based projections. x is typically a day offset.
 */
class LinearRegression
{
    public float $slope = 0.0;

    public float $intercept = 0.0;

    public float $r2 = 0.0;

    public float $stdError = 0.0;

    public int $n = 0;

    /**
     * @param  array<int, float>  $x
     * @param  array<int, float>  $y
     */
    public static function fit(array $x, array $y): self
    {
        $model = new self;
        $n = min(count($x), count($y));
        $model->n = $n;

        if ($n < 2) {
            if ($n === 1) {
                $model->intercept = $y[0];
            }

            return $model;
        }

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $meanX = $sumX / $n;
        $meanY = $sumY / $n;

        $sxx = 0.0;
        $sxy = 0.0;
        $syy = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $meanX;
            $dy = $y[$i] - $meanY;
            $sxx += $dx * $dx;
            $sxy += $dx * $dy;
            $syy += $dy * $dy;
        }

        if ($sxx == 0.0) {
            $model->intercept = $meanY;

            return $model;
        }

        $model->slope = $sxy / $sxx;
        $model->intercept = $meanY - $model->slope * $meanX;
        $model->r2 = $syy == 0.0 ? 1.0 : ($sxy * $sxy) / ($sxx * $syy);

        // residual standard error
        $ssRes = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $pred = $model->slope * $x[$i] + $model->intercept;
            $ssRes += ($y[$i] - $pred) ** 2;
        }
        $model->stdError = $n > 2 ? sqrt($ssRes / ($n - 2)) : 0.0;

        return $model;
    }

    public function predict(float $x): float
    {
        return $this->slope * $x + $this->intercept;
    }

    /** ~95% confidence half-width for a prediction (approx: 1.96·SE). */
    public function confidenceHalfWidth(): float
    {
        return round(1.96 * $this->stdError, 3);
    }
}
