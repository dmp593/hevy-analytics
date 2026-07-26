<?php

namespace App\Console\Commands;

use App\Services\StrengthStandards\OpenPowerliftingStandards;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Downloads the OpenPowerlifting bulk CSV and precomputes percentile tables
 * for the three competition lifts (Raw), grouped by sex and bodyweight bucket.
 * Output is cached to storage for the OpenPowerlifting fallback resolver.
 *
 * Uses a memory-safe histogram (weight bins) rather than holding all rows.
 */
class BuildOplStandardsCommand extends Command
{
    protected $signature = 'strength:build-opl-standards {--keep-zip : Keep the downloaded zip}';

    protected $description = 'Build OpenPowerlifting percentile tables (squat/bench/deadlift) for the strength-level fallback';

    private const BW_BUCKETS = [50, 55, 60, 65, 70, 75, 80, 85, 90, 95, 100, 110, 120, 140];

    private const WEIGHT_BIN = 2.5; // kg per histogram bin

    private const PERCENTILES = [5, 10, 20, 30, 40, 50, 60, 70, 80, 90, 95, 99];

    public function handle(): int
    {
        $tmp = storage_path('app/opl-tmp');
        @mkdir($tmp, 0775, true);
        $zip = $tmp.'/opl.zip';

        $url = (string) config('services.openpowerlifting.csv_url');
        $this->info("Downloading OpenPowerlifting CSV from {$url} ...");
        if (! $this->download($url, $zip)) {
            $this->error('Download failed.');

            return self::FAILURE;
        }

        $this->info('Extracting ...');
        $csv = $this->extractCsv($zip, $tmp);
        if (! $csv) {
            $this->error('Could not extract CSV from zip.');

            return self::FAILURE;
        }

        $this->info('Aggregating (this can take a few minutes) ...');
        $hist = $this->buildHistogram($csv);

        $this->info('Computing percentiles ...');
        $tables = $this->computeTables($hist);

        Storage::disk('local')->put(OpenPowerliftingStandards::PATH, json_encode([
            'generated' => now()->toIso8601String(),
            'source' => 'OpenPowerlifting (CC0)',
            'equipment' => 'Raw',
            'lifts' => $tables,
        ], JSON_UNESCAPED_SLASHES));

        if (! $this->option('keep-zip')) {
            @unlink($zip);
            @unlink($csv);
        }

        $this->info('Done. Stored '.OpenPowerliftingStandards::PATH);

        return self::SUCCESS;
    }

    private function download(string $url, string $dest): bool
    {
        $in = @fopen($url, 'rb');
        if (! $in) {
            return false;
        }
        $out = fopen($dest, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        return filesize($dest) > 0;
    }

    private function extractCsv(string $zipPath, string $dir): ?string
    {
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return null;
        }
        $csvInside = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with($name, '.csv')) {
                $csvInside = $name;
                break;
            }
        }
        if (! $csvInside) {
            $zip->close();

            return null;
        }
        $zip->extractTo($dir, $csvInside);
        $zip->close();

        return $dir.'/'.$csvInside;
    }

    /**
     * @return array<string, array<string, array<int, array<int,int>>>> hist[lift][sex][bwBucketIndex][weightBin] = count
     */
    private function buildHistogram(string $csvPath): array
    {
        $fh = fopen($csvPath, 'r');
        $header = fgetcsv($fh);
        $idx = array_flip($header);

        $col = fn ($row, $name) => isset($idx[$name]) ? ($row[$idx[$name]] ?? '') : '';
        $lifts = ['squat' => 'Best3SquatKg', 'bench' => 'Best3BenchKg', 'deadlift' => 'Best3DeadliftKg'];

        $hist = [];
        $rows = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $rows++;
            if ($rows % 500000 === 0) {
                $this->line("  processed {$rows} rows...");
            }

            if ($col($row, 'Equipment') !== 'Raw') {
                continue;
            }
            $sex = $col($row, 'Sex');
            $sex = $sex === 'M' ? 'male' : ($sex === 'F' ? 'female' : null);
            if (! $sex) {
                continue;
            }
            $bw = (float) $col($row, 'BodyweightKg');
            if ($bw < 40 || $bw > 200) {
                continue;
            }
            $bucketIdx = $this->bucketIndex($bw);

            foreach ($lifts as $lift => $colName) {
                $val = (float) $col($row, $colName);
                if ($val <= 0) {
                    continue;
                }
                $bin = (int) floor($val / self::WEIGHT_BIN);
                $hist[$lift][$sex][$bucketIdx][$bin] = ($hist[$lift][$sex][$bucketIdx][$bin] ?? 0) + 1;
            }
        }
        fclose($fh);
        $this->line("  total rows: {$rows}");

        return $hist;
    }

    private function bucketIndex(float $bw): int
    {
        $best = 0;
        $bestDiff = PHP_FLOAT_MAX;
        foreach (self::BW_BUCKETS as $i => $b) {
            $d = abs($b - $bw);
            if ($d < $bestDiff) {
                $bestDiff = $d;
                $best = $i;
            }
        }

        return $best;
    }

    private function computeTables(array $hist): array
    {
        $tables = [];
        foreach ($hist as $lift => $sexes) {
            foreach ($sexes as $sex => $buckets) {
                foreach ($buckets as $bucketIdx => $bins) {
                    $n = array_sum($bins);
                    if ($n < 200) {
                        continue; // too few samples for a reliable percentile
                    }
                    ksort($bins);
                    $breakpoints = [];
                    $cum = 0;
                    $targets = [];
                    foreach (self::PERCENTILES as $p) {
                        $targets[$p] = $n * $p / 100;
                    }
                    $pIdx = 0;
                    $pKeys = self::PERCENTILES;
                    foreach ($bins as $bin => $count) {
                        $cum += $count;
                        while ($pIdx < count($pKeys) && $cum >= $targets[$pKeys[$pIdx]]) {
                            $breakpoints[] = ['p' => $pKeys[$pIdx], 'w' => round(($bin + 0.5) * self::WEIGHT_BIN, 1)];
                            $pIdx++;
                        }
                    }
                    $tables[$lift][$sex][] = [
                        'bw' => self::BW_BUCKETS[$bucketIdx],
                        'n' => $n,
                        'breakpoints' => $breakpoints,
                    ];
                }
            }
        }

        return $tables;
    }
}
