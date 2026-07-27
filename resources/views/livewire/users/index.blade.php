<div>
    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Utenti
                </h2>

                <a href="{{ route('users.create') }}">
                    <x-primary-button>+ Nuovo utente</x-primary-button>
                </a>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($users as $user)
                        <li>
                            <a href="{{ route('users.edit', $user) }}" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <div>
                                    <p class="font-medium text-gray-800 dark:text-gray-100">{{ $user->name }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $user->email }}
                                        &middot; {{ $user->magazines_count }} {{ \Illuminate\Support\Str::plural('rivista', $user->magazines_count) }}
                                    </p>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                    {{ $user->role->label() }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
