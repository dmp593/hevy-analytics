<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportController extends Controller
{
    /**
     * Everything the app holds about the athlete, as one JSON file.
     *
     * GDPR Article 20 asks for a "structured, commonly used, machine-readable
     * format", which JSON is. Streamed rather than assembled in memory because a
     * few years of training is tens of thousands of set rows, and building that
     * as one string is how an export works in development and dies in production.
     *
     * Secrets are deliberately absent. An export is a file people email to
     * themselves and leave in a downloads folder; a Hevy key or an AI provider
     * key in it is a credential leak with a helpful filename. The athlete
     * already has those keys — they issued them.
     */
    public function download(Request $request): StreamedResponse
    {
        $user = $request->user();
        $filename = 'hevy-analytics-export-'.now()->format('Y-m-d').'.json';

        return response()->streamJson([
            'exported_at' => now()->toIso8601String(),
            'format_note' => 'API keys are deliberately excluded from this file.',

            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
                'sex' => $user->sex,
                'age' => $user->age,
                'height_cm' => $user->height_cm,
                'activity_level' => $user->activity_level,
                'body_fat_source' => $user->body_fat_source,
                'timezone' => $user->timezone,
                'locale' => $user->locale,
            ],

            // Lazily streamed: each of these can be large on its own, and all of
            // them together certainly are.
            'goals' => $user->goals()->orderBy('started_at')->lazy(),
            'body_measurements' => $user->bodyMeasurements()->orderBy('date')->lazy(),
            'intake_logs' => $user->intakeLogs()->orderBy('date')->lazy(),
            'nutrition_targets' => $user->nutritionTargets()->orderBy('date')->lazy(),
            'routines' => $user->routines()->with('exercises')->lazy(),
            'workouts' => $user->workouts()->with('exercises.sets')->orderBy('start_time')->lazy(),
            'progress_photos' => $user->progressPhotos()
                ->orderBy('date')
                ->lazy()
                ->map(fn ($photo) => [
                    'date' => $photo->date?->toDateString(),
                    'angle' => $photo->angle,
                    'weight_kg' => $photo->weight_kg,
                    'notes' => $photo->notes,
                    // The image itself is not embedded: base64 would multiply the
                    // file size for something the athlete can download directly.
                    'download_url' => route('photos.file', $photo),
                ]),

            'ai_analyses' => $user->aiAnalyses()->orderBy('created_at')->lazy(),
            'ai_usage' => $user->aiUsageEvents()->orderBy('created_at')->lazy(),

            // Which providers are configured, never the keys themselves.
            'ai_providers' => $user->aiCredentials()->lazy()->map(fn ($credential) => [
                'provider' => $credential->provider,
                'model' => $credential->model,
                'base_url' => $credential->base_url,
                'is_active' => $credential->is_active,
                'last_verified_at' => $credential->last_verified_at?->toIso8601String(),
            ]),
        ], headers: [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ], encodingOptions: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
