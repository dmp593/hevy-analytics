<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 gap-4">
            {{-- min-w-0 lets this column shrink instead of forcing the document
                 wider than the viewport. Twelve top-level links do not fit at
                 1280px, and the page was scrolling sideways as a result. The
                 link row scrolls within itself; the information architecture
                 that removes the need for that is a separate piece of work. --}}
            <div class="flex min-w-0">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="font-bold text-indigo-600 text-lg whitespace-nowrap">{{ __("app.brand") }}</a>
                </div>

                <div class="hidden min-w-0 space-x-6 overflow-x-auto sm:-my-px sm:ms-8 lg:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">{{ __("app.nav.dashboard") }}</x-nav-link>
                    <x-nav-link :href="route('performance')" :active="request()->routeIs('performance')">{{ __("app.nav.performance") }}</x-nav-link>
                    <x-nav-link :href="route('strength-levels')" :active="request()->routeIs('strength-levels')">{{ __("app.nav.levels") }}</x-nav-link>
                    <x-nav-link :href="route('muscle')" :active="request()->routeIs('muscle')">{{ __("app.nav.muscle") }}</x-nav-link>
                    <x-nav-link :href="route('body')" :active="request()->routeIs('body')">{{ __("app.nav.body") }}</x-nav-link>
                    <x-nav-link :href="route('photos')" :active="request()->routeIs('photos')">{{ __("app.nav.photos") }}</x-nav-link>
                    <x-nav-link :href="route('nutrition')" :active="request()->routeIs('nutrition')">{{ __("app.nav.nutrition") }}</x-nav-link>
                    <x-nav-link :href="route('projections')" :active="request()->routeIs('projections')">{{ __("app.nav.projections") }}</x-nav-link>
                    <x-nav-link :href="route('routines')" :active="request()->routeIs('routines*')">{{ __("app.nav.routines") }}</x-nav-link>
                    <x-nav-link :href="route('goals')" :active="request()->routeIs('goals')">{{ __("app.nav.goals") }}</x-nav-link>
                    <x-nav-link :href="route('ai')" :active="request()->routeIs('ai')">{{ __("app.nav.ai") }}</x-nav-link>
                    <x-nav-link :href="route('guide')" :active="request()->routeIs('guide')">{{ __("app.nav.guide") }}</x-nav-link>
                </div>
            </div>

            {{-- Same breakpoint as the hamburger below. These disagreed (sm vs
                 lg), so between 640px and 1024px both were on screen at once. --}}
            <div class="hidden shrink-0 lg:flex lg:items-center lg:ms-6 gap-3">
                <form method="POST" action="{{ route('sync') }}">
                    @csrf
                    <button class="inline-flex items-center whitespace-nowrap px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-md hover:bg-indigo-700 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                        {{ __("app.nav.sync") }}
                    </button>
                </form>
                <x-locale-switcher />
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center whitespace-nowrap px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-500 transition">
                            <div class="max-w-40 truncate">{{ Auth::user()->name }}</div>
                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                            </div>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">{{ __("app.nav.profile") }}</x-dropdown-link>
                        <x-dropdown-link :href="route('write.index')">{{ __("app.nav.write_operations") }}</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">{{ __("app.nav.log_out") }}</x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center lg:hidden">
                <button @click="open = ! open" :aria-expanded="open" aria-controls="mobile-menu" aria-label="{{ __('app.nav.toggle') }}"
                        class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-500">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div id="mobile-menu" :class="{'block': open, 'hidden': ! open}" class="hidden lg:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">{{ __("app.nav.dashboard") }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('performance')" :active="request()->routeIs('performance')">{{ __("app.nav.performance") }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('strength-levels')" :active="request()->routeIs('strength-levels')">{{ __("app.nav.levels") }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('muscle')" :active="request()->routeIs('muscle')">{{ __("app.nav.muscle") }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('body')" :active="request()->routeIs('body')">{{ __("app.nav.body") }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('photos')" :active="request()->routeIs('photos')">{{ __("app.nav.photos") }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('nutrition')" :active="request()->routeIs('nutrition')">{{ __("app.nav.nutrition") }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('projections')" :active="request()->routeIs('projections')">{{ __("app.nav.projections") }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('routines')" :active="request()->routeIs('routines*')">{{ __("app.nav.routines") }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('goals')" :active="request()->routeIs('goals')">{{ __("app.nav.goals") }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('ai')" :active="request()->routeIs('ai')">{{ __("app.nav.ai") }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('guide')" :active="request()->routeIs('guide')">{{ __("app.nav.guide") }}</x-responsive-nav-link>
        </div>
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>
            <div class="mt-3 space-y-1">
                <form method="POST" action="{{ route('sync') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('sync')" onclick="event.preventDefault(); this.closest('form').submit();">{{ __("app.nav.sync") }}</x-responsive-nav-link>
                </form>
                <x-responsive-nav-link :href="route('profile.edit')">{{ __("app.nav.profile") }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('write.index')">{{ __("app.nav.write_operations") }}</x-responsive-nav-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">{{ __("app.nav.log_out") }}</x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
