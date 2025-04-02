                    <!-- User Management -->
                    <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                        {{ __('Users') }}
                    </x-nav-link>

                    <!-- Content Management -->
                    <x-nav-link :href="route('admin.spans.index')" :active="request()->routeIs('admin.spans.*')">
                        {{ __('Spans') }}
                    </x-nav-link>
                    <x-nav-link :href="route('admin.connections.index')" :active="request()->routeIs('admin.connections.*')">
                        {{ __('Connections') }}
                    </x-nav-link>
                    <x-nav-link :href="route('admin.span-types.index')" :active="request()->routeIs('admin.span-types.*')">
                        {{ __('Span Types') }}
                    </x-nav-link>
                    <x-nav-link :href="route('admin.connection-types.index')" :active="request()->routeIs('admin.connection-types.*')">
                        {{ __('Connection Types') }}
                    </x-nav-link>

                    <!-- System Management -->
                    <x-nav-link :href="route('admin.import.index')" :active="request()->routeIs('admin.import.*')">
                        {{ __('Import') }}
                    </x-nav-link>
                    <x-nav-link :href="route('admin.visualizer.index')" :active="request()->routeIs('admin.visualizer.*')">
                        {{ __('Visualizer') }}
                    </x-nav-link>
                    <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link> 