<x-ui.page :title="__('app.pages.photos')" :subtitle="__('app.pages.photos_sub')" width="6xl">
    <x-flash />

    <div class="grid lg:grid-cols-3 gap-6">
        <x-panel :title="__('app.photos.add')" :subtitle="__('app.photos.add_sub')" class="lg:col-span-1 h-fit">
            <form method="POST" action="{{ route('photos.store') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <label class="text-xs text-body block">{{ __('app.photos.date') }}
                    <input type="date" name="date" value="{{ now()->toDateString() }}" class="mt-1 w-full rounded-md border-line text-sm" required>
                </label>

                {{-- One slot per pose. Filling all four is what makes the
                     comparison page able to line up like with like — but any
                     one of them is enough to save the check-in. --}}
                @foreach (\App\Http\Controllers\ProgressPhotoController::POSES as $pose)
                    <label class="text-xs text-body block">{{ __('app.photos.angle_'.$pose) }}
                        <input type="file" name="photos[{{ $pose }}]" accept="image/*" class="mt-1 w-full text-sm file:mr-2 file:min-h-11 file:cursor-pointer file:rounded-lg file:border-0 file:bg-surface-sunk file:px-3 file:py-2 file:text-xs file:font-semibold file:text-ink">
                    </label>
                @endforeach
                <p class="text-xs text-faint">{{ __('app.photos.poses_hint') }}</p>

                <label class="text-xs text-body block">{{ __('app.photos.weight', ['unit' => units()->weightUnit()]) }}
                    <input type="number" step="0.1" name="weight" class="mt-1 w-full rounded-md border-line text-sm">
                </label>
                <label class="text-xs text-body block">{{ __('app.photos.notes') }}
                    <textarea name="notes" rows="2" maxlength="500" class="mt-1 w-full rounded-md border-line text-sm"></textarea>
                </label>
                <x-input-error :messages="$errors->all()" class="mt-1" />
                <button class="w-full rounded-md bg-brand px-4 py-2 text-sm font-semibold text-on-fill hover:bg-brand-hover">{{ __('app.photos.upload') }}</button>
            </form>
            <p class="mt-3 text-xs text-faint">{{ __('app.photos.privacy') }}</p>
        </x-panel>

        <div class="lg:col-span-2 space-y-4">
            {{-- The comparison grew into its own page: 2-4 dates, poses
                 aligned, values with deltas — this is just the door to it. --}}
            <div class="flex items-center justify-end">
                <x-ui.button :href="route('compare')" variant="secondary">{{ __('app.compare.open') }}</x-ui.button>
            </div>

            {{-- Gallery --}}
            @forelse($byDate as $date => $group)
                <x-panel :title="$date">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        @foreach($group as $p)
                            <div class="relative group">
                                <img src="{{ route('photos.file', $p) }}" loading="lazy" class="w-full h-40 object-cover rounded-lg border" alt="">
                                <span class="absolute top-1 left-1 text-[11px] bg-black/60 text-white px-1.5 py-0.5 rounded-sm">{{ __('app.photos.angle_'.$p->angle) }}</span>
                                <form method="POST" action="{{ route('photos.destroy', $p) }}" @click.stop
                                      onsubmit="return confirm('{{ __('app.photos.delete_confirm') }}')"
                                      class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition">
                                    @csrf @method('DELETE')
                                    {{-- The glyph is not a label: a screen reader reads
                                         "✕" as nothing useful. --}}
                                    <button class="text-[11px] bg-bad text-on-fill px-1.5 py-0.5 rounded-sm"
                                            aria-label="{{ __('app.photos.delete') }}">✕</button>
                                </form>
                                @if($p->weight_kg)<span class="absolute bottom-1 left-1 text-[11px] bg-black/60 text-white px-1.5 py-0.5 rounded-sm">{{ units()->weight($p->weight_kg) }}{{ units()->weightUnit() }}</span>@endif
                            </div>
                        @endforeach
                    </div>
                </x-panel>
            @empty
                <x-panel><p class="text-sm text-muted">{{ __('app.photos.none') }}</p></x-panel>
            @endforelse
        </div>
    </div>
</x-ui.page>
