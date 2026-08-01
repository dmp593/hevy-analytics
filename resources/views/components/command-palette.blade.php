@php
    // The static search index: every page with its go-chord letter, plus the
    // quick actions. Built server-side so labels come from the lang files and
    // URLs from the router — the palette can never link to a page that does
    // not exist, and never shows one in the wrong language.
    $pages = [
        ['label' => __('app.nav.dashboard'), 'url' => route('dashboard'), 'key' => 'd', 'kw' => 'dashboard painel home inicio'],
        ['label' => __('app.nav.performance'), 'url' => route('performance'), 'key' => 'p', 'kw' => 'performance desempenho e1rm forca strength'],
        ['label' => __('app.nav.muscle'), 'url' => route('muscle'), 'key' => 'm', 'kw' => 'muscle musculo volume series sets mev mav'],
        ['label' => __('app.nav.levels'), 'url' => route('strength-levels'), 'key' => 'l', 'kw' => 'levels niveis forca standards percentil'],
        ['label' => __('app.nav.routines'), 'url' => route('routines'), 'key' => 'r', 'kw' => 'routines rotinas progredir progression treino'],
        ['label' => __('app.nav.body'), 'url' => route('body'), 'key' => 'b', 'kw' => 'body corpo peso weight composicao gordura'],
        ['label' => __('app.nav.photos'), 'url' => route('photos'), 'key' => 'f', 'kw' => 'photos fotografias fotos progresso'],
        ['label' => __('app.nav.compare'), 'url' => route('compare'), 'key' => 'c', 'kw' => 'compare comparar checkin check-in'],
        ['label' => __('app.nav.projections'), 'url' => route('projections'), 'key' => 'j', 'kw' => 'projections projecoes futuro forecast'],
        ['label' => __('app.nav.nutrition'), 'url' => route('nutrition'), 'key' => 'n', 'kw' => 'nutrition nutricao calorias macros tdee proteina'],
        ['label' => __('app.nav.goals'), 'url' => route('goals'), 'key' => 'o', 'kw' => 'goals objetivos alvo target'],
        ['label' => __('app.nav.ai'), 'url' => route('ai'), 'key' => 'a', 'kw' => 'ai ia analise analysis'],
        ['label' => __('app.nav.import'), 'url' => route('import'), 'key' => 'i', 'kw' => 'import importar csv strong jefit fitnotes'],
        ['label' => __('app.nav.convert'), 'url' => route('convert'), 'key' => 'v', 'kw' => 'convert converter exportar export'],
        ['label' => __('app.nav.write_operations'), 'url' => route('write.index'), 'key' => 'w', 'kw' => 'write operacoes escrita pendente hevy'],
        ['label' => __('app.nav.guide'), 'url' => route('guide'), 'key' => 'u', 'kw' => 'guide guia help ajuda documentacao'],
        ['label' => __('app.nav.profile'), 'url' => route('profile.edit'), 'key' => 'e', 'kw' => 'profile perfil definicoes settings chave api'],
    ];

    $actions = [
        ['label' => __('app.palette.action_sync'), 'action' => route('sync'), 'kw' => 'sync sincronizar hevy atualizar'],
        ['label' => __('app.palette.action_units_metric'), 'action' => route('settings.units', 'metric'), 'kw' => 'unidades units kg metrico metric'],
        ['label' => __('app.palette.action_units_imperial'), 'action' => route('settings.units', 'imperial'), 'kw' => 'unidades units lb imperial libras'],
        ['label' => __('app.palette.action_lang_pt'), 'action' => route('locale.update', 'pt'), 'kw' => 'idioma language portugues pt'],
        ['label' => __('app.palette.action_lang_en'), 'action' => route('locale.update', 'en'), 'kw' => 'idioma language english en'],
    ];
@endphp

<div x-data="{
        open: false,
        query: '',
        active: 0,
        showHelp: false,
        pendingG: false,
        gTimer: null,
        loaded: false,
        returnFocus: null,
        pages: @js($pages),
        actions: @js($actions),
        dynamic: { routines: [], exercises: [] },

        toggle() { this.open ? this.close() : this.show() },
        show() {
            this.returnFocus = document.activeElement;
            this.open = true;
            this.query = '';
            this.active = 0;
            this.showHelp = false;
            this.$nextTick(() => this.$refs.input?.focus());
            if (! this.loaded) {
                this.loaded = true;
                fetch('{{ route('palette.data') }}', { headers: { Accept: 'application/json' } })
                    .then(r => r.ok ? r.json() : { routines: [], exercises: [] })
                    .then(d => { this.dynamic = d })
                    .catch(() => {});
            }
        },
        close() {
            this.open = false;
            if (this.returnFocus && this.returnFocus.focus) this.returnFocus.focus();
        },

        score(text, q) {
            const t = text.toLowerCase();
            if (t.startsWith(q)) return 3;
            if (t.includes(q)) return 2;
            let i = 0;
            for (const ch of t) if (ch === q[i]) i++;
            return i >= q.length ? 1 : 0;
        },
        {{-- Results are recomputed on demand; the x-for below caches this
             array in a local so group headers and rows always read the SAME
             list (recomputing per row let indexes disagree). --}}
        results() {
            const q = this.query.trim().toLowerCase();
            if (! q) {
                return [
                    ...this.pages.map(i => ({ ...i, group: 'pages' })),
                    ...this.actions.map(i => ({ ...i, group: 'actions' })),
                ];
            }
            const groups = [
                ['pages', this.pages],
                ['actions', this.actions],
                ['routines', this.dynamic.routines || []],
                ['exercises', this.dynamic.exercises || []],
            ];
            const out = [];
            for (const [group, items] of groups) {
                for (const item of items) {
                    const hay = (item.label || item.title || '') + ' ' + (item.kw || '') + ' ' + (item.context || '');
                    const s = this.score(hay, q);
                    if (s > 0) out.push({ ...item, group, s });
                }
            }
            {{-- Sort by match quality, but keep each group's rows together so
                 the headers stay truthful. --}}
            const order = { pages: 0, actions: 1, routines: 2, exercises: 3 };
            out.sort((a, b) => (order[a.group] - order[b.group]) || (b.s - a.s));
            return out.slice(0, 12);
        },
        activate(item) {
            if (! item) return;
            if (item.url) { window.location.assign(item.url); return }
            if (item.action) {
                this.$refs.actionForm.setAttribute('action', item.action);
                this.$refs.actionForm.submit();
            }
        },
        {{-- Inside the box, typing is ALWAYS searching: an earlier design ran
             the chords here too, and searching for 'gemeos' navigated away at
             the second letter. --}}
        onKeydown(e) {
            const list = this.results();
            if (e.key === 'ArrowDown') { e.preventDefault(); this.active = Math.min(this.active + 1, list.length - 1); return }
            if (e.key === 'ArrowUp') { e.preventDefault(); this.active = Math.max(this.active - 1, 0); return }
            if (e.key === 'Enter') { e.preventDefault(); this.activate(list[this.active]); return }
            if (e.key === 'Tab') { e.preventDefault() }
        },
        {{-- Global keys. Cmd/Ctrl+K toggles anywhere. The g-chords and ? work
             on any page but never inside a form field, and never while a
             modifier is held, so they cannot eat someone's typing. --}}
        onGlobalKeydown(e) {
            if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); this.toggle(); return }
            if (this.open || e.metaKey || e.ctrlKey || e.altKey) return;
            const t = e.target;
            if (t && t.matches && t.matches('input, textarea, select, [contenteditable=\'true\']')) return;
            if (this.pendingG) {
                this.pendingG = false;
                clearTimeout(this.gTimer);
                const hit = this.pages.find(p => p.key === e.key.toLowerCase());
                if (hit) { e.preventDefault(); window.location.assign(hit.url); return }
            }
            if (e.key === 'g') {
                this.pendingG = true;
                clearTimeout(this.gTimer);
                this.gTimer = setTimeout(() => { this.pendingG = false }, 1500);
                return;
            }
            if (e.key === '?') { e.preventDefault(); this.show(); this.showHelp = true }
        },
    }"
    @keydown.window="onGlobalKeydown($event)"
    @keydown.escape.window="if (open) close()"
    @open-palette.window="show()"
    data-command-palette>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="{{ __('app.palette.open_aria') }}">
            <div class="absolute inset-0 bg-ink/40" @click="close()"></div>

            <div class="relative mx-auto mt-[10vh] w-[min(38rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-line bg-surface shadow-xl">
                <div class="flex items-center gap-2 border-b border-line px-4">
                    <svg class="h-4 w-4 shrink-0 text-muted" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.4l3.07 3.08a1 1 0 0 1-1.41 1.41l-3.08-3.07A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
                    </svg>
                    <input x-ref="input" x-model="query" @input="active = 0" @keydown="onKeydown($event)"
                           type="text" role="combobox" aria-expanded="true" aria-controls="palette-results"
                           :aria-activedescendant="'palette-item-' + active"
                           autocomplete="off" spellcheck="false"
                           placeholder="{{ __('app.palette.placeholder') }}"
                           class="w-full border-0 bg-transparent py-3.5 text-sm text-ink placeholder:text-faint focus:outline-hidden focus:ring-0">
                    <button type="button" @click="showHelp = ! showHelp"
                            class="shrink-0 rounded-md border border-line px-2 py-0.5 text-xs text-muted hover:text-ink"
                            aria-label="{{ __('app.palette.help_title') }}">?</button>
                </div>

                <div x-show="showHelp" x-cloak class="border-b border-line bg-surface-sunk px-4 py-3 text-xs leading-relaxed text-body">
                    <p class="font-semibold text-ink">{{ __('app.palette.help_title') }}</p>
                    <ul class="mt-1.5 space-y-1">
                        @foreach (['open', 'g', 'arrows', 'q'] as $line)
                            <li>{{ __('app.palette.help_'.$line) }}</li>
                        @endforeach
                    </ul>
                </div>

                <div id="palette-results" role="listbox" class="max-h-[50vh] overflow-y-auto py-2">
                    <template x-for="(item, i) in results()" :key="item.group + ':' + i">
                        <div>
                            {{-- The group header sits OUTSIDE the option, so a
                                 screen reader never reads a heading as a choice. --}}
                            <p x-show="i === 0 || results()[i - 1].group !== item.group"
                               class="px-4 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-faint"
                               x-text="({pages: @js(__('app.palette.group_pages')), actions: @js(__('app.palette.group_actions')), routines: @js(__('app.palette.group_routines')), exercises: @js(__('app.palette.group_exercises'))})[item.group]"></p>
                            <button type="button" role="option" :id="'palette-item-' + i" :aria-selected="i === active"
                                    class="flex w-full items-center justify-between gap-3 px-4 py-2 text-left text-sm"
                                    :class="i === active ? 'bg-brand-soft text-brand-ink' : 'text-body hover:bg-surface-sunk'"
                                    @click="activate(item)" @mousemove="active = i">
                                <span class="min-w-0">
                                    <span class="block truncate" x-text="item.label || item.title"></span>
                                    <span x-show="item.context" class="block truncate text-xs text-muted" x-text="item.context"></span>
                                </span>
                                <kbd x-show="item.key && ! query" class="shrink-0 rounded-sm border border-line bg-surface-sunk px-1.5 py-0.5 font-mono text-[11px] text-muted" x-text="'g ' + item.key"></kbd>
                            </button>
                        </div>
                    </template>
                </div>

                <p x-show="query !== '' && results().length === 0" class="px-4 pb-4 pt-1 text-sm text-muted">
                    {{ __('app.palette.no_results') }}
                </p>
            </div>

            {{-- One POST vehicle for the quick actions (sync, units, locale):
                 the palette points it at the chosen route and submits. --}}
            <form x-ref="actionForm" method="POST" class="hidden">
                @csrf
            </form>
        </div>
    </template>
</div>
