<?php

namespace App\Http\Controllers;

use App\Models\ProgressPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgressPhotoController extends Controller
{
    public function index(Request $request)
    {
        $photos = $request->user()->progressPhotos()->orderByDesc('date')->get();

        return view('photos.index', [
            'photos' => $photos,
            'byDate' => $photos->groupBy(fn ($p) => $p->date->toDateString()),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'angle' => ['required', 'in:front,side,back'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:8192'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $path = $request->file('photo')->store("progress-photos/{$request->user()->id}", 'local');

        $request->user()->progressPhotos()->create([
            'date' => $data['date'],
            'angle' => $data['angle'],
            'path' => $path,
            'weight_kg' => $data['weight_kg'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('photos')->with('status', 'Photo added.');
    }

    public function show(Request $request, ProgressPhoto $photo): StreamedResponse
    {
        $this->authorizeOwner($photo);
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path);
    }

    public function destroy(Request $request, ProgressPhoto $photo)
    {
        $this->authorizeOwner($photo);

        Storage::disk('local')->delete($photo->path);
        $photo->delete();

        return redirect()->route('photos')->with('status', 'Photo deleted.');
    }
}
