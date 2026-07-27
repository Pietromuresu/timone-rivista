import './bootstrap';

import Alpine from 'alpinejs';
import Sortable from 'sortablejs';

window.Alpine = Alpine;

/**
 * Indicatore "chi sta modificando cosa": stato puramente effimero,
 * mai persistito né passato da Livewire, trasmesso via whisper (client
 * event) sul canale presence `issue.{issueId}` già usato per la barra
 * "utenti online" e per i broadcast server-side (PageMoved, ecc.).
 */
Alpine.store('pagePresence', {
    channel: null,
    user: null,
    editing: {},

    join(issueId, user) {
        this.user = user;

        if (! window.Echo || this.channel) return;

        this.channel = window.Echo.join(`issue.${issueId}`)
            .listenForWhisper('PageEditingStarted', ({ pageId, user }) => {
                this.editing[pageId] = user;
            })
            .listenForWhisper('PageEditingStopped', ({ pageId, userId }) => {
                if (this.editing[pageId]?.id === userId) {
                    delete this.editing[pageId];
                }
            })
            .leaving((leavingUser) => {
                for (const pageId of Object.keys(this.editing)) {
                    if (this.editing[pageId].id === leavingUser.id) {
                        delete this.editing[pageId];
                    }
                }
            });
    },

    startEditing(pageId) {
        if (! this.channel || ! this.user) return;

        this.editing[pageId] = this.user;
        this.channel.whisper('PageEditingStarted', { pageId, user: this.user });
    },

    stopEditing(pageId) {
        if (! this.channel || ! this.user) return;

        if (this.editing[pageId]?.id === this.user.id) {
            delete this.editing[pageId];
        }
        this.channel.whisper('PageEditingStopped', { pageId, userId: this.user.id });
    },

    /**
     * Solo l'editor altrui va mostrato: non ha senso segnalare a un
     * utente che è lui stesso a modificare la pagina che sta guardando.
     */
    editorFor(pageId) {
        const editor = this.editing[pageId];

        return editor && editor.id !== this.user?.id ? editor : null;
    },
});

/**
 * Fallback quando i websocket (Reverb) non sono raggiungibili: invece di
 * restare silenziosamente non aggiornati, si passa a un polling
 * periodico che richiama un metodo Livewire (in pratica un $wire.call
 * mirato, non $wire.$refresh, perché deve anche riallineare la versione
 * del lock ottimistico di riordino — vedi Grid::pollRefresh()).
 * Attivo solo se la connessione non è "connected" (dopo un breve periodo
 * di grazia iniziale, per non scattare durante il normale handshake) o
 * se Echo/Reverb non è configurato affatto.
 */
Alpine.store('realtimeFallback', {
    connected: true,
    pollTimer: null,
    graceTimer: null,
    pollFn: null,

    watch(pollFn) {
        this.pollFn = pollFn;

        const connection = window.Echo?.connector?.pusher?.connection;

        if (! connection) {
            this.enterFallback();

            return;
        }

        this.graceTimer = setTimeout(() => {
            if (connection.state !== 'connected') this.enterFallback();
        }, 5000);

        connection.bind('state_change', ({ current }) => {
            if (current === 'connected') {
                this.exitFallback();
            } else if (['unavailable', 'failed', 'disconnected'].includes(current)) {
                this.enterFallback();
            }
        });
    },

    enterFallback() {
        if (! this.connected) return;

        this.connected = false;
        this.pollTimer = setInterval(() => this.pollFn?.(), 8000);
    },

    exitFallback() {
        clearTimeout(this.graceTimer);

        if (this.connected) return;

        this.connected = true;
        clearInterval(this.pollTimer);
        this.pollTimer = null;
    },
});

Alpine.directive('sortable', (el) => {
    Sortable.create(el, {
        animation: 150,
        handle: '.drag-handle',
        onStart(evt) {
            Alpine.store('pagePresence').startEditing(evt.item.dataset.pageId);
        },
        onEnd(evt) {
            Alpine.store('pagePresence').stopEditing(evt.item.dataset.pageId);

            if (evt.oldIndex === evt.newIndex) return;

            el.dispatchEvent(new CustomEvent('page-dropped', {
                detail: {
                    pageId: evt.item.dataset.pageId,
                    newPosition: evt.newIndex + 1,
                },
            }));
        },
    });
});

Alpine.start();
