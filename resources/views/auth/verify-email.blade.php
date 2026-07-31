<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        Grazie per esserti registrato! Prima di iniziare, conferma il tuo indirizzo email cliccando sul link che ti abbiamo appena inviato. Se non hai ricevuto l'email, possiamo inviartene un'altra volentieri.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
            Un nuovo link di verifica è stato inviato all'indirizzo email indicato in fase di registrazione.
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    Invia di nuovo l'email di verifica
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                Esci
            </button>
        </form>
    </div>
</x-guest-layout>
