<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-ink">Progress photos</h2></x-slot>

    <div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8"
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
            <x-panel title="Add a photo" subtitle="The mirror is the most honest metric" class="lg:col-span-1 h-fit">
                <form method="POST" action="{{ route('photos.store') }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <label class="text-xs text-body block">Date
                        <input type="date" name="date" value="{{ now()->toDateString() }}" class="mt-1 w-full rounded-md border-line text-sm" required>
                    </label>
                    <label class="text-xs text-body block">Angle
                        <select name="angle" class="mt-1 w-full rounded-md border-line text-sm">
                            <option value="front">Front</option>
                            <option value="side">Side</option>
                            <option value="back">Back</option>
                        </select>
                    </label>
                    <label class="text-xs text-body block">Photo
                        <input type="file" name="photo" accept="image/*" class="mt-1 w-full text-sm" required>
                    </label>
                    <label class="text-xs text-body block">Weight (kg, optional)
                        <input type="number" step="0.1" name="weight_kg" class="mt-1 w-full rounded-md border-line text-sm">
                    </label>
                    <x-input-error :messages="$errors->all()" class="mt-1" />
                    <button class="w-full rounded-md bg-brand px-4 py-2 text-sm font-semibold text-on-fill hover:bg-brand-hover">Upload</button>
                </form>
                <p class="mt-3 text-xs text-faint">Photos are private to your account and streamed only to you. Same pose, lighting and time of day makes comparisons meaningful.</p>
            </x-panel>

            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center justify-between">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" x-model="compare" class="rounded-sm border-line">
                        Compare mode <span class="text-xs text-faint">(pick two photos)</span>
                    </label>
                </div>

                {{-- Compare view --}}
                <div x-show="compare && a && b" x-cloak class="grid grid-cols-2 gap-3">
                    @foreach($photos as $p)
                        <template x-if="a === {{ $p->id }}">
                            <div><img src="{{ route('photos.file', $p) }}" class="w-full rounded-lg border" alt=""><div class="text-xs text-center mt-1">{{ $p->date->toDateString() }} · {{ ucfirst($p->angle) }} {{ $p->weight_kg ? '· '.$p->weight_kg.'kg' : '' }}</div></div>
                        </template>
                    @endforeach
                    @foreach($photos as $p)
                        <template x-if="b === {{ $p->id }}">
                            <div><img src="{{ route('photos.file', $p) }}" class="w-full rounded-lg border" alt=""><div class="text-xs text-center mt-1">{{ $p->date->toDateString() }} · {{ ucfirst($p->angle) }} {{ $p->weight_kg ? '· '.$p->weight_kg.'kg' : '' }}</div></div>
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
                                    <span class="absolute top-1 left-1 text-[10px] bg-black/60 text-white px-1.5 py-0.5 rounded-sm">{{ ucfirst($p->angle) }}</span>
                                    <form method="POST" action="{{ route('photos.destroy', $p) }}" @click.stop
                                          onsubmit="return confirm('Delete this photo?')"
                                          class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition">
                                        @csrf @method('DELETE')
                                        <button class="text-[10px] bg-bad text-on-fill px-1.5 py-0.5 rounded-sm">✕</button>
                                    </form>
                                    @if($p->weight_kg)<span class="absolute bottom-1 left-1 text-[10px] bg-black/60 text-white px-1.5 py-0.5 rounded-sm">{{ $p->weight_kg }}kg</span>@endif
                                </div>
                            @endforeach
                        </div>
                    </x-panel>
                @empty
                    <x-panel><p class="text-sm text-muted">No photos yet. Upload your first progress photo to start a visual timeline.</p></x-panel>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
