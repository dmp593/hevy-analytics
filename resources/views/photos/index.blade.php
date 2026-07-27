<x-ui.page :title="__('app.pages.photos')" :subtitle="__('app.pages.photos_sub')" width="6xl"
         x-data="{
            compare: false,
            a: null,
            b: null,
            pick(id) {
                if (!this.compare) return;
                if (this.a === id) { this.a = null; return; }
                if (this.b === id) { this.b = null; return; }
                if (!this.a) { this.a = id; } else if (!this.b) { this.b = id; }
            }
         }">
    <x-flash />

    <div class="grid lg:grid-cols-3 gap-6">
        <x-panel :title="__('app.photos.add')" :subtitle="__('app.photos.add_sub')" class="lg:col-span-1 h-fit">
            <form method="POST" action="{{ route('photos.store') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <label class="text-xs text-body block">{{ __('app.photos.date') }}
                    <input type="date" name="date" value="{{ now()->toDateString() }}" class="mt-1 w-full rounded-md border-line text-sm" required>
                </label>
                <label class="text-xs text-body block">{{ __('app.photos.angle') }}
                    <select name="angle" class="mt-1 w-full rounded-md border-line text-sm">
                        @foreach (['front', 'side', 'back'] as $angle)
                            <option value="{{ $angle }}">{{ __('app.photos.angle_'.$angle) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs text-body block">{{ __('app.photos.file') }}
                    <input type="file" name="photo" accept="image/*" class="mt-1 w-full text-sm" required>
                </label>
                <label class="text-xs text-body block">{{ __('app.photos.weight') }}
                    <input type="number" step="0.1" name="weight_kg" class="mt-1 w-full rounded-md border-line text-sm">
                </label>
                <x-input-error :messages="$errors->all()" class="mt-1" />
                <button class="w-full rounded-md bg-brand px-4 py-2 text-sm font-semibold text-on-fill hover:bg-brand-hover">{{ __('app.photos.upload') }}</button>
            </form>
            <p class="mt-3 text-xs text-faint">{{ __('app.photos.privacy') }}</p>
        </x-panel>

        <div class="lg:col-span-2 space-y-4">
            <div class="flex items-center justify-between">
                <label class="inline-flex min-h-11 items-center gap-2 text-sm">
                    <input type="checkbox" x-model="compare" class="rounded-sm border-line">
                    {{ __('app.photos.compare') }} <span class="text-xs text-faint">{{ __('app.photos.compare_hint') }}</span>
                </label>
            </div>

            {{-- Compare view --}}
            <div x-show="compare && a && b" x-cloak class="grid grid-cols-2 gap-3">
                @foreach($photos as $p)
                    <template x-if="a === {{ $p->id }}">
                        <div><img src="{{ route('photos.file', $p) }}" class="w-full rounded-lg border" alt=""><div class="text-xs text-center mt-1">{{ $p->date->toDateString() }} · {{ __('app.photos.angle_'.$p->angle) }} {{ $p->weight_kg ? '· '.$p->weight_kg.'kg' : '' }}</div></div>
                    </template>
                @endforeach
                @foreach($photos as $p)
                    <template x-if="b === {{ $p->id }}">
                        <div><img src="{{ route('photos.file', $p) }}" class="w-full rounded-lg border" alt=""><div class="text-xs text-center mt-1">{{ $p->date->toDateString() }} · {{ __('app.photos.angle_'.$p->angle) }} {{ $p->weight_kg ? '· '.$p->weight_kg.'kg' : '' }}</div></div>
                    </template>
                @endforeach
            </div>

            {{-- Gallery --}}
            @forelse($byDate as $date => $group)
                <x-panel :title="$date">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        @foreach($group as $p)
                            <div class="relative group cursor-pointer"
                                 @click="pick({{ $p->id }})"
                                 :class="(a === {{ $p->id }} || b === {{ $p->id }}) ? 'ring-2 ring-brand rounded-lg' : ''">
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
                                @if($p->weight_kg)<span class="absolute bottom-1 left-1 text-[11px] bg-black/60 text-white px-1.5 py-0.5 rounded-sm">{{ $p->weight_kg }}kg</span>@endif
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
