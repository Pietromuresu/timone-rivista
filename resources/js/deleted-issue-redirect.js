/**
 * Revisione 2, Fase 4.2 (2026-10-28): un admin elimina un numero mentre altri
 * lo hanno aperto. Alla loro richiesta Livewire successiva (poll di
 * checkForUpdates() o l'evento IssueDeleted via Reverb) il componente Grid
 * non si può ricostruire: il numero non esiste più e Livewire risponde 404,
 * che di suo mostrerebbe una pagina d'errore dentro una modale.
 *
 * Qui, solo sulla pagina del timone (elemento con data-deleted-redirect),
 * un 404 si verifica con una HEAD sulla pagina stessa: se anche lei è 404 il
 * numero è stato eliminato → elenco delle riviste con l'avviso
 * (?eliminato=1, layouts/app.blade.php); altrimenti era un 404 d'altro tipo
 * (es. un contenuto appena eliminato da un collega) e si ricarica la pagina.
 */
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            const marker = document.querySelector('[data-deleted-redirect]');

            if (status !== 404 || ! marker) return;

            preventDefault();

            fetch(window.location.href, { method: 'HEAD', credentials: 'same-origin' })
                .then((response) => {
                    if (response.status === 404) {
                        window.location.href = marker.dataset.deletedRedirect;
                    } else {
                        window.location.reload();
                    }
                })
                .catch(() => window.location.reload());
        });
    });
});
