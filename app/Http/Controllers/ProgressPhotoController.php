<?php

namespace App\Http\Controllers;

use App\Models\ProgressPhoto;
use App\Support\Units;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgressPhotoController extends Controller
{
    /** The four poses of a check-in, in the order every gallery and comparison shows them. */
    public const POSES = ['front', 'back', 'left', 'right'];

    public function index(Request $request)
    {
        $photos = $request->user()->progressPhotos()->orderByDesc('date')->get();
        $rank = array_flip(self::POSES);

        return view('photos.index', [
            'photos' => $photos,
            // Within a date, always pose order — front, back, left, right — so
            // two check-ins line up when read side by side.
            'byDate' => $photos->groupBy(fn ($p) => $p->date->toDateString())
                ->map(fn ($group) => $group->sortBy(fn ($p) => $rank[$p->angle] ?? 9)->values()),
        ]);
    }

    /**
     * Cap on how many progress photos one account may hold.
     *
     * Uploads were bounded only by a per-minute rate limit, which allowed
     * roughly 240 MB an hour per account, forever, on storage the operator pays
     * for. A ceiling is not a hardship — this is a few photos a week at most.
     */
    private const MAX_PHOTOS_PER_USER = 500;

    /**
     * A check-in, not a single upload: one date, up to four poses, one shared
     * weight and note. Photos are usually taken as a set — front, back, both
     * sides — and inserting them as a set is what lets the comparison page
     * line the same pose up across dates.
     */
    public function store(Request $request)
    {
        $units = Units::for($request->user());

        $data = $request->validate([
            'date' => ['required', 'date'],
            'photos' => ['required', 'array'],
            'photos.front' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:8192'],
            'photos.back' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:8192'],
            'photos.left' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:8192'],
            'photos.right' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:8192'],
            // Typed in the user's unit; stored metric.
            'weight' => ['nullable', 'numeric', 'min:0', 'max:'.($units->imperial() ? 1102 : 500)],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $files = collect(self::POSES)
            ->mapWithKeys(fn ($pose) => [$pose => $request->file("photos.$pose")])
            ->filter();

        if ($files->isEmpty()) {
            return back()->with('error', __('app.photos.at_least_one'));
        }

        if ($request->user()->progressPhotos()->count() + $files->count() > self::MAX_PHOTOS_PER_USER) {
            return back()->with('error', __('app.photos.limit_reached', [
                'limit' => self::MAX_PHOTOS_PER_USER,
            ]));
        }

        $weightKg = $units->weightToKg($data['weight'] ?? null);

        foreach ($files as $pose => $file) {
            // config('filesystems.photos'), never a literal: on a container
            // host the local disk is wiped on every deploy, and these are the
            // files nobody can re-create.
            $path = $file->store(
                "progress-photos/{$request->user()->id}",
                ProgressPhoto::disk(),
            );

            $request->user()->progressPhotos()->create([
                'date' => $data['date'],
                'angle' => $pose,
                'path' => $path,
                'weight_kg' => $weightKg,
                'notes' => $data['notes'] ?? null,
            ]);
        }

        return redirect()->route('photos')
            ->with('status', trans_choice('app.photos.added', $files->count(), ['count' => $files->count()]));
    }

    public function show(Request $request, ProgressPhoto $photo): StreamedResponse
    {
        $this->authorizeOwner($photo);
        $disk = Storage::disk(ProgressPhoto::disk());

        abort_unless($disk->exists($photo->path), 404);

        // Streamed through the app on purpose, even from object storage: a
        // public or signed bucket URL is a link that outlives the session
        // and can be forwarded. These are photographs of someone's body.
        return $disk->response($photo->path);
    }

    public function destroy(Request $request, ProgressPhoto $photo)
    {
        $this->authorizeOwner($photo);

        // The model's deleting hook removes the file, so deleting the row is
        // enough — and is the one path that also covers the account cascade.
        $photo->delete();

        return redirect()->route('photos')->with('status', 'Photo deleted.');
    }
}
