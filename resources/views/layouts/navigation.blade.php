<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="font-bold text-indigo-600 text-lg">Hevy&nbsp;Analytics</a>
                </div>

                <div class="hidden space-x-6 sm:-my-px sm:ms-8 lg:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-nav-link>
                    <x-nav-link :href="route('performance')" :active="request()->routeIs('performance')">Performance</x-nav-link>
                    <x-nav-link :href="route('strength-levels')" :active="request()->routeIs('strength-levels')">Levels</x-nav-link>
                    <x-nav-link :href="route('muscle')" :active="request()->routeIs('muscle')">Muscle</x-nav-link>
                    <x-nav-link :href="route('body')" :active="request()->routeIs('body')">Body</x-nav-link>
                    <x-nav-link :href="route('photos')" :active="request()->routeIs('photos')">Photos</x-nav-link>
                    <x-nav-link :href="route('nutrition')" :active="request()->routeIs('nutrition')">Nutrition</x-nav-link>
                    <x-nav-link :href="route('projections')" :active="request()->routeIs('projections')">Projections</x-nav-link>
                    <x-nav-link :href="route('routines')" :active="request()->routeIs('routines*')">Routines</x-nav-link>
                    <x-nav-link :href="route('goals')" :active="request()->routeIs('goals')">Goals</x-nav-link>
                    <x-nav-link :href="route('ai')" :active="request()->routeIs('ai')">AI</x-nav-link>
                    <x-nav-link :href="route('guide')" :active="request()->routeIs('guide')">Guide</x-nav-link>
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-3">
                <form method="POST" action="{{ route('sync') }}">
                    @csrf
                    <button class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-md hover:bg-indigo-700">
                        Sync Hevy
                    </button>
                </form>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition">
                            <div>{{ Auth::user()->name }}</div>
                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                            </div>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">Profile &amp; Settings</x-dropdown-link>
                        <x-dropdown-link :href="route('write.index')">Write Operations</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Log Out</x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center lg:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden lg:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('performance')" :active="request()->routeIs('performance')">Performance</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('strength-levels')" :active="request()->routeIs('strength-levels')">Levels</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('muscle')" :active="request()->routeIs('muscle')">Muscle</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('body')" :active="request()->routeIs('body')">Body</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('photos')" :active="request()->routeIs('photos')">Photos</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('nutrition')" :active="request()->routeIs('nutrition')">Nutrition</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('projections')" :active="request()->routeIs('projections')">Projections</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('routines')" :active="request()->routeIs('routines*')">Routines</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('goals')" :active="request()->routeIs('goals')">Goals</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('ai')" :active="request()->routeIs('ai')">AI</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('guide')" :active="request()->routeIs('guide')">Guide</x-responsive-nav-link>
        </div>
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>
            <div class="mt-3 space-y-1">
                <form method="POST" action="{{ route('sync') }}"><x-responsive-nav-link :href="route('sync')" onclick="event.preventDefault(); this.closest('form').submit();">@csrf Sync Hevy</x-responsive-nav-link></form>
                <x-responsive-nav-link :href="route('profile.edit')">Profile &amp; Settings</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('write.index')">Write Operations</x-responsive-nav-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Log Out</x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
