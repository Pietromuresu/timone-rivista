import Sortable, { MultiDrag } from 'sortablejs';

/**
 * Registrazioni Alpine del timone (store, direttiva x-sortable, magic
 * $animatedMove). Modulo a sé, importato PER PRIMO da app.js (Fase 1,
 * bug 5, 2026-10-26 — vedi il commento lì): così il listener `alpine:init`
 * esiste sempre prima che Livewire avvii Alpine al DOMContentLoaded,
 * qualunque cosa succeda negli import successivi (Echo).
 *
 * Livewire 3 include e avvia già una propria istanza di Alpine.js
 * internamente (bundle iniettato automaticamente a fine `<body>`, senza
 * bisogno di `@livewireScripts`/`@livewireStyles` espliciti in questo
 * progetto) — importare un secondo pacchetto `alpinejs` e chiamare
 * `Alpine.start()` qui crea due istanze Alpine indipendenti: causava
 * "Detected multiple instances of Alpine running" e, peggio, `$wire is not
 * defined` sugli elementi che usano `$wire` (es. `x-sortable="$wire.swapMode"`),
 * perché il magic `$wire` è registrato solo sull'istanza Alpine di
 * Livewire — bug reale scoperto il 2026-07-29 che rompeva drag&drop e la
 * modalità scambio. Il pattern corretto per registrare store/direttive
 * custom senza importare un'altra copia di Alpine è agganciarsi
 * all'evento `alpine:init`, che Livewire/Alpine emettono sull'istanza già
 * presente prima di processare il DOM. Nessuna navigazione `wire:navigate`
 * nel progetto: la pagina viene caricata per intero, quindi gli store sono
 * registrati una volta sola per caricamento e non vanno "ripristinati".
 */

// Fase 1, bug 4 (2026-10-26): plugin MultiDrag, incluso nello stesso
// pacchetto `sortablejs` già usato (nessuna dipendenza nuova). Serve a far
// muovere VISIVAMENTE insieme tutte le pagine di un gruppo collegato o
// della selezione multipla durante il trascinamento: prima si muoveva solo
// la pagina afferrata e il resto del blocco "saltava" al posto giusto solo
// al render successivo.
Sortable.mount(new MultiDrag());

/**
 * Classe messa da MultiDrag sulle pagine che si muovono insieme a quella
 * afferrata — stilizzata in resources/css/app.css.
 */
const MOVING_TOGETHER_CLASS = 'timone-moving-together';

/**
 * Livelli di zoom delle miniature (Fase 1, bug 7). Variabili CSS lette da
 * grid.blade.php: `--timone-page-min` = larghezza minima di una colonna
 * in vista griglia, `--timone-page-w` = larghezza di una pagina in vista
 * doppia pagina. L'altezza non è qui: la dà l'aspect-ratio del formato
 * reale del numero, impostato sulla card (page-card.blade.php).
 */
const ZOOM_LEVELS = {
    s: { label: 'S', pageMin: '8.5rem', pageWidth: '10rem' },
    m: { label: 'M', pageMin: '11.5rem', pageWidth: '13rem' },
    l: { label: 'L', pageMin: '15rem', pageWidth: '17rem' },
};
const ZOOM_STORAGE_KEY = 'timone.zoom';

/** Id delle pagine nell'ordine in cui compaiono ORA nel contenitore. */
function orderedPageIds(container) {
    return [...container.querySelectorAll(':scope > [data-page-id]')].map((node) => node.dataset.pageId);
}

/**
 * Animazione FLIP (First-Last-Invert-Play) con la Web Animations API del
 * browser, nessuna libreria: misura le pagine, esegue la mutazione (una
 * chiamata Livewire che fa il morph del DOM), rimisura e anima ogni pagina
 * dalla vecchia posizione alla nuova. Usata per lo spostamento da tastiera
 * e come transizione finale dopo un drop (Fase 1, bug 4): così si vedono
 * scorrere insieme TUTTE le pagine coinvolte, gruppo compreso, anche
 * quando l'ordine finale lo decide il server (gruppo esteso, conflitto).
 */
async function flipMove(container, mutate) {
    if (! container) {
        return mutate();
    }

    const before = new Map();
    container.querySelectorAll(':scope > [data-page-id]').forEach((node) => {
        before.set(node.dataset.pageId, node.getBoundingClientRect());
    });

    const result = await mutate();

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return result;
    }

    await new Promise((resolve) => requestAnimationFrame(resolve));

    container.querySelectorAll(':scope > [data-page-id]').forEach((node) => {
        const first = before.get(node.dataset.pageId);

        if (! first) return;

        const last = node.getBoundingClientRect();
        const dx = first.left - last.left;
        const dy = first.top - last.top;

        if (Math.abs(dx) < 1 && Math.abs(dy) < 1) return;

        node.animate(
            [{ transform: `translate(${dx}px, ${dy}px)` }, { transform: 'none' }],
            { duration: 280, easing: 'cubic-bezier(0.2, 0, 0, 1)' },
        );
    });

    return result;
}

document.addEventListener('alpine:init', () => {
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

        // Vero mentre QUESTO client sta trascinando una pagina (Sortable
        // onStart/onEnd, sotto) — usato dal polling di fallback in
        // grid.blade.php per mettere in pausa wire:poll durante un drag: un
        // poll a metà trascinamento potrebbe far ripartire il morph di
        // Livewire sull'elemento che Sortable sta ancora manipolando a
        // mano (2026-09-01).
        dragging: false,

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
     * Fallback quando i websocket (Reverb) non sono raggiungibili: espone
     * solo `connected` (letto da grid.blade.php per montare/smontare
     * l'elemento `wire:poll.1s="checkForUpdates"` — vedi lì per il
     * perché di `x-if` invece di un polling gestito qui in JS). Prima del
     * 2026-09-01 questo store gestiva anche direttamente un
     * `setInterval`/`$wire.call` (vedi HANDOFF.md per la versione
     * precedente) — sostituito da `wire:poll`, nativo di Livewire, che si
     * occupa da solo di schedulare/fermare la richiesta periodica; questo
     * store resta responsabile SOLO di rilevare lo stato della connessione
     * Reverb, non di far girare il timer.
     * Attivo solo se la connessione non è "connected" (dopo un breve periodo
     * di grazia iniziale, per non scattare durante il normale handshake) o
     * se Echo/Reverb non è configurato affatto.
     */
    Alpine.store('realtimeFallback', {
        connected: true,
        graceTimer: null,

        watch() {
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
            this.connected = false;
        },

        exitFallback() {
            clearTimeout(this.graceTimer);
            this.connected = true;
        },
    });

    /**
     * Zoom delle miniature del timone S/M/L (Fase 1, bug 7): preferenza
     * personale del singolo utente su questo browser, quindi localStorage
     * e non Livewire/database — non deve propagarsi agli altri utenti né
     * sopravvivere a un cambio di computer. try/catch su ogni accesso:
     * in navigazione privata o con i dati del sito bloccati localStorage
     * può lanciare, e lo zoom deve comunque funzionare (solo senza essere
     * ricordato). Le variabili CSS vanno su <html>, fuori dall'albero che
     * Livewire ri-renderizza: un morph non può mai cancellarle.
     */
    /**
     * Campo di upload di un file con barra di avanzamento e controllo della
     * dimensione PRIMA dell'invio (revisione 2, Fase 1, bug 5, 2026-10-27).
     * Era scritto dentro content-create.blade.php (Fase 1, bug 9); estratto
     * qui per usarlo uguale anche nella modale di modifica (content-edit),
     * che prima mostrava solo "Caricamento…" e lasciava partire file oltre
     * il limite. maxBytes/maxLabel: limite effettivo (UploadLimits). Gli
     * eventi livewire-upload-* arrivano dall'<input wire:model> interno.
     */
    Alpine.data('fileUpload', ({ maxBytes = null, maxLabel = null, property = 'file' } = {}) => ({
        dragging: false,
        uploading: false,
        progress: 0,
        clientError: null,

        tooLarge(file) {
            if (! maxBytes || ! file || file.size <= maxBytes) return false;

            this.clientError = `Il file pesa ${(file.size / 1024 / 1024).toFixed(1).replace('.', ',')} MB: il massimo accettato è ${maxLabel}. Riduci il PDF (es. esportalo ottimizzato per la stampa a risoluzione più bassa) e riprova.`;

            return true;
        },

        /** File trascinati sulla drop zone. */
        send(files) {
            this.dragging = false;
            this.clientError = null;

            if (! files || files.length === 0) return;

            if (files.length > 1) {
                this.clientError = 'Trascina un solo file alla volta: ogni contenuto ha un solo PDF o immagine.';

                return;
            }

            if (this.tooLarge(files[0])) return;

            this.uploading = true;
            this.progress = 0;
            this.$wire.upload(property, files[0],
                () => { this.uploading = false; },
                () => { this.uploading = false; },
                (event) => { this.progress = event.detail.progress; },
            );
        },

        /**
         * Selettore classico: in fase di cattura sul contenitore, quindi
         * PRIMA che wire:model avvii l'upload; stopPropagation() lo ferma.
         */
        checkPicked(event) {
            if (event.target.type !== 'file') return;

            this.clientError = null;

            if (this.tooLarge(event.target.files[0])) {
                event.stopPropagation();
                event.target.value = '';
            }
        },
    }));

    /**
     * Caricamento massivo (revisione 2, Fase 3.2, 2026-10-28), usato da
     * livewire/timone/bulk-upload.blade.php. La coda vive qui e i file
     * partono UNO ALLA VOLTA: $wire.upload('upload') e poi
     * $wire.importUploaded(tipo), che crea il contenuto e risponde con
     * l'esito; solo allora si passa al file successivo. Un lotto in una
     * sola richiesta supererebbe i limiti del server con pochi PDF di
     * stampa, e un file scartato non deve fermare gli altri.
     *
     * Gli oggetti File restano FUORI dallo stato reattivo (mappa `files`):
     * Alpine li avvolgerebbe in un Proxy, che FormData (usato da
     * $wire.upload) non accetta come Blob.
     */
    Alpine.data('bulkUpload', ({ maxBytes = null, maxLabel = null, type = 'articolo' } = {}) => {
        const files = new Map();
        const accepted = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

        return {
            open: false,
            // Tipo iniziale dal server: 'pubblicita' per chi carica solo pubblicità (Fase 4.1).
            type,
            dragging: false,
            busy: false,
            items: [],
            nextId: 1,

            add(fileList) {
                this.dragging = false;

                for (const file of Array.from(fileList || [])) {
                    const id = this.nextId++;

                    files.set(id, file);
                    // Il tipo è quello scelto quando il file entra in coda:
                    // cambiarlo dopo vale per i file aggiunti dopo.
                    this.items.push({ id, name: file.name, type: this.type, status: 'queued', progress: 0, message: null });
                }

                this.run();
            },

            pick(event) {
                this.add(event.target.files);
                event.target.value = '';
            },

            /** Controlli prima dell'invio: estensione e dimensione (il server ricontrolla tutto). */
            clientProblem(file) {
                const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';

                if (! accepted.includes(extension)) {
                    return 'Formato non accettato: carica un PDF o un\'immagine JPG, PNG o WebP.';
                }

                if (maxBytes && file.size > maxBytes) {
                    return `Il file pesa ${(file.size / 1024 / 1024).toFixed(1).replace('.', ',')} MB: il massimo accettato è ${maxLabel}.`;
                }

                return null;
            },

            async run() {
                if (this.busy) return;

                this.busy = true;

                try {
                    let item;

                    while ((item = this.items.find((candidate) => candidate.status === 'queued'))) {
                        await this.process(item);
                    }
                } finally {
                    this.busy = false;
                }
            },

            async process(item) {
                const file = files.get(item.id);
                const problem = this.clientProblem(file);

                if (problem) {
                    item.status = 'error';
                    item.message = problem;

                    return;
                }

                item.status = 'uploading';
                item.progress = 0;

                const uploaded = await new Promise((resolve) => {
                    this.$wire.upload('upload', file,
                        () => resolve(true),
                        () => resolve(false),
                        (event) => { item.progress = event.detail.progress; },
                        () => resolve(false),
                    );
                });

                if (! uploaded) {
                    item.status = 'error';
                    item.message = this.$wire.uploadError || 'Caricamento non riuscito: riprova.';

                    return;
                }

                item.status = 'importing';

                try {
                    const result = await this.$wire.importUploaded(item.type);

                    if (result && result.ok) {
                        item.status = 'ok';
                        item.message = result.duplicateOf
                            ? `Esisteva già «${result.duplicateOf}»: creato come «${result.title}».`
                            : null;
                        item.createdTitle = result.title;
                        item.occupancy = result.occupancy;
                        files.delete(item.id);
                    } else {
                        item.status = 'error';
                        item.message = result?.message || 'File non accettato.';
                    }
                } catch (error) {
                    item.status = 'error';
                    item.message = 'Errore del server durante la creazione: riprova.';
                }
            },

            retryFailed() {
                for (const item of this.items) {
                    if (item.status === 'error') {
                        item.status = 'queued';
                        item.message = null;
                        item.progress = 0;
                    }
                }

                this.run();
            },

            clear() {
                this.items = [];
                files.clear();
            },

            get failedCount() {
                return this.items.filter((item) => item.status === 'error').length;
            },

            /** «12 caricati, 1 scartato: motivo». */
            get summary() {
                const done = this.items.filter((item) => item.status === 'ok').length;
                const failed = this.items.filter((item) => item.status === 'error');
                let text = `${done} ${done === 1 ? 'caricato' : 'caricati'}`;

                if (failed.length > 0) {
                    text += `, ${failed.length} ${failed.length === 1 ? 'scartato' : 'scartati'}: `
                        + failed.map((item) => `${item.name} (${item.message})`).join('; ');
                }

                return text;
            },

            statusLabel(item) {
                return {
                    queued: 'in coda',
                    uploading: `caricamento ${item.progress}%`,
                    importing: 'creazione…',
                    ok: `✓ creato${item.occupancy ? ' — ' + item.occupancy : ''}`,
                    error: '✕ errore',
                }[item.status];
            },

            statusClass(item) {
                return {
                    queued: 'text-gray-400',
                    uploading: 'text-indigo-600 dark:text-indigo-400',
                    importing: 'text-indigo-600 dark:text-indigo-400',
                    ok: 'text-emerald-700 dark:text-emerald-400',
                    error: 'text-red-600 dark:text-red-400',
                }[item.status];
            },
        };
    });

    /**
     * Pannello contenuti del timone (2026-10-28, «contenuti sempre
     * raggiungibili»): aperto/chiuso e larghezza sono preferenze del singolo
     * browser (localStorage, con try/catch: in navigazione privata può
     * mancare), nessuno stato Livewire. Da 1440 px il pannello è una colonna
     * a destra e il contenuto della pagina si restringe (CSS su
     * html[data-content-panel="docked"], resources/css/app.css); sotto è una
     * tendina sotto la fascia sticky. Il markup è in grid.blade.php
     * (`data-testid="content-panel"`): una sola istanza.
     */
    const CONTENT_PANEL_KEY = 'timone.contentPanel';
    const CONTENT_PANEL_MIN = 280;
    const CONTENT_PANEL_MAX = 520;

    Alpine.store('contentPanel', {
        open: false,
        width: 360,
        docked: false,

        init() {
            try {
                const saved = JSON.parse(window.localStorage.getItem(CONTENT_PANEL_KEY) || '{}');

                this.open = saved.open === true;
                if (Number.isFinite(saved.width)) this.width = saved.width;
            } catch (error) {
                // Preferenze non leggibili: pannello chiuso, larghezza predefinita.
            }

            const media = window.matchMedia('(min-width: 1440px)');
            this.docked = media.matches;
            media.addEventListener('change', (event) => { this.docked = event.matches; this.apply(); });
            this.apply();
        },

        toggle(force = null) {
            this.open = force === null ? ! this.open : force;
            this.save();
            this.apply();
        },

        setWidth(width) {
            this.width = Math.round(Math.min(CONTENT_PANEL_MAX, Math.max(CONTENT_PANEL_MIN, width)));
            this.save();
            this.apply();
        },

        save() {
            try {
                window.localStorage.setItem(CONTENT_PANEL_KEY, JSON.stringify({ open: this.open, width: this.width }));
            } catch (error) {
                // Non ricordato, ma applicato per questa sessione.
            }
        },

        /** Su <html>, fuori dall'albero di Livewire: un morph non lo cancella. */
        apply() {
            const html = document.documentElement;

            html.dataset.contentPanel = this.open ? (this.docked ? 'docked' : 'dropdown') : 'closed';
            html.style.setProperty('--content-panel-width', `${this.width}px`);
        },
    });

    /**
     * Commenti per pagina (2026-10-29): contatori delle icone 💬 e del
     * contatore «non letti» nella testata. Li manda Grid (evento
     * comment-counts, e l'elemento «seme» a ogni render): così un commento
     * aggiorna le icone senza ridisegnare la griglia (Grid::checkForUpdates()
     * fa skipRender quando cambiano solo i commenti).
     */
    Alpine.store('pageComments', {
        pages: {},
        unreadTotal: 0,

        replace(counts) {
            this.pages = counts?.pages ?? {};
            this.unreadTotal = counts?.unread ?? 0;
        },

        total(pageId) {
            return this.pages[pageId]?.total ?? 0;
        },

        unread(pageId) {
            return this.pages[pageId]?.unread ?? 0;
        },
    });

    window.addEventListener('comment-counts', (event) => Alpine.store('pageComments').replace(event.detail?.counts));

    // Dal pannello commenti alla pagina nel timone (link «pagina 12 →», mail).
    window.addEventListener('scroll-to-page', (event) => {
        document.querySelector(`[data-page-id="${event.detail?.pageId}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    /**
     * Opzioni di vista del singolo utente (2026-10-29): per ora i confini
     * delle segnature. Su <html> (fuori dall'albero di Livewire, un morph
     * non lo tocca) come contentPanel; il disegno è tutto in app.css.
     */
    const VIEW_STORAGE_KEY = 'timone.view';

    Alpine.store('timoneView', {
        signatures: true,

        init() {
            try {
                const saved = JSON.parse(window.localStorage.getItem(VIEW_STORAGE_KEY) || '{}');

                if (typeof saved.signatures === 'boolean') this.signatures = saved.signatures;
            } catch (error) {
                // Preferenza non leggibile: confini visibili.
            }

            this.apply();
        },

        toggleSignatures() {
            this.signatures = ! this.signatures;

            try {
                window.localStorage.setItem(VIEW_STORAGE_KEY, JSON.stringify({ signatures: this.signatures }));
            } catch (error) {
                // Non ricordata, ma applicata per questa sessione.
            }

            this.apply();
        },

        apply() {
            document.documentElement.dataset.signatures = this.signatures ? 'on' : 'off';
        },
    });

    Alpine.store('timoneZoom', {
        levels: ZOOM_LEVELS,
        level: 'm',

        init() {
            try {
                const saved = window.localStorage.getItem(ZOOM_STORAGE_KEY);

                if (saved && ZOOM_LEVELS[saved]) this.level = saved;
            } catch (error) {
                // Preferenza non leggibile: si resta sul livello predefinito.
            }

            this.apply();
        },

        set(level) {
            if (! ZOOM_LEVELS[level]) return;

            this.level = level;
            this.apply();

            try {
                window.localStorage.setItem(ZOOM_STORAGE_KEY, level);
            } catch (error) {
                // Non ricordata, ma applicata comunque per questa sessione.
            }
        },

        apply() {
            const { pageMin, pageWidth } = ZOOM_LEVELS[this.level];
            document.documentElement.style.setProperty('--timone-page-min', pageMin);
            document.documentElement.style.setProperty('--timone-page-w', pageWidth);
        },
    });

    /**
     * `$animatedMove('nudgePage', id, -1)` — spostamento da tastiera
     * animato (Fase 1, bug 4): chiama il metodo Livewire indicato dentro un
     * FLIP sul contenitore x-sortable della pagina, poi il focus torna
     * sulla pagina spostata, così le frecce si possono premere più volte di
     * fila. Nome del metodo + argomenti invece di una closure: niente `=>`
     * negli attributi HTML (alcuni test leggono il markup con regex).
     */
    Alpine.magic('animatedMove', (el) => (method, ...args) => {
        const pageId = el.closest('[data-page-id]')?.dataset.pageId;
        const wire = Alpine.evaluate(el, '$wire');

        return flipMove(el.closest('[x-sortable]'), () => wire.call(method, ...args)).then((result) => {
            if (pageId) {
                el.closest('[x-sortable]')?.querySelector(`:scope > [data-page-id="${pageId}"]`)?.focus();
            }

            return result;
        });
    });

    // L'espressione opzionale (es. x-sortable="$wire.swapMode") disabilita il
    // drag&drop reattivamente mentre resta vera — usata per la modalità
    // scambio, che sostituisce il drag con una selezione a click: tenere
    // Sortable attivo in contemporanea creerebbe due modi conflittuali di
    // riordinare (un mousedown sul drag-handle avvierebbe comunque un drag
    // invece della sola selezione).
    Alpine.directive('sortable', (el, { expression }, { effect, evaluateLater, evaluate, cleanup }) => {
        let orderBeforeDrag = [];

        /**
         * Pagine che devono muoversi insieme a quella afferrata (Fase 1,
         * bug 4): tutta la selezione multipla se la pagina ne fa parte
         * (stessa condizione di prima per il "blocco"), altrimenti il suo
         * gruppo collegato (data-group-id, page-card/page-row). Il server
         * resta l'unica fonte di verità sullo spostamento vero
         * (Grid::movePage()/moveSelectedBlock() estendono comunque la
         * selezione al gruppo intero): questa è solo l'anteprima visiva.
         */
        function companionsOf(item) {
            const pageId = item.dataset.pageId;
            const selectionMode = evaluate('$wire.selectionMode');
            const selectedPageIds = (evaluate('$wire.selectedPageIds') || []).map(String);

            if (selectionMode && selectedPageIds.length > 1 && selectedPageIds.includes(pageId)) {
                return [...el.querySelectorAll(':scope > [data-page-id]')].filter((node) => selectedPageIds.includes(node.dataset.pageId));
            }

            const groupId = item.dataset.groupId;

            if (groupId) {
                return [...el.querySelectorAll(`:scope > [data-page-id][data-group-id="${groupId}"]`)];
            }

            return [];
        }

        function clearCompanions() {
            el.querySelectorAll(`:scope > .${MOVING_TOGETHER_CLASS}`).forEach((node) => Sortable.utils.deselect(node));
        }

        // Fase di cattura sul contenitore: gira PRIMA del pointerdown di
        // Sortable (in bubbling sullo stesso elemento), quindi quando il
        // drag parte MultiDrag trova già selezionate le pagine compagne.
        const onPointerDown = (event) => {
            const handle = event.target.closest('.drag-handle');
            const item = handle?.closest('[data-page-id]');

            clearCompanions();

            if (! item || item.parentNode !== el) return;

            const companions = companionsOf(item);

            if (companions.length > 1) {
                companions.forEach((node) => Sortable.utils.select(node));
            }
        };

        // Un click sulla maniglia senza trascinare non deve lasciare le
        // compagne evidenziate (MultiDrag le considererebbe una selezione).
        const onPointerUp = () => setTimeout(() => {
            if (! Alpine.store('pagePresence').dragging) clearCompanions();
        }, 0);

        el.addEventListener('pointerdown', onPointerDown, true);
        document.addEventListener('pointerup', onPointerUp);

        const sortable = Sortable.create(el, {
            animation: 150,
            handle: '.drag-handle',
            // Limita gli elementi trascinabili/ordinabili a quelli con
            // data-page-id: necessario per la vista "doppia pagina", che
            // usa elementi "spacer" senza data-page-id (flex-basis:100%)
            // per andare a capo visivamente tra un'apertura e la
            // successiva pur restando tutte le pagine figlie dirette dello
            // stesso contenitore Sortable (vedi grid.blade.php).
            draggable: '[data-page-id]',
            multiDrag: true,
            selectedClass: MOVING_TOGETHER_CLASS,
            // La "selezione" di MultiDrag la gestiamo noi (onPointerDown):
            // niente deselezione implicita al click altrove.
            avoidImplicitDeselect: true,
            // Revisione 2, Fase 1, bug 4 (2026-10-27): niente auto-scroll di
            // Sortable. Con il drag nativo lasciava la finestra allo scroll
            // del browser, che sotto la fascia sticky non partiva: ora un
            // unico gestore per tutti i drag (resources/js/drag-autoscroll.js).
            scroll: false,
            /**
             * Immagine "fantasma" del trascinamento nativo: il browser
             * fotografa solo l'elemento afferrato. Per un gruppo/blocco
             * componiamo al volo un'immagine con TUTTE le pagine che si
             * muovono, affiancate (Fase 1, bug 4). Deve stare nel DOM nel
             * momento di setDragImage(), poi si rimuove subito.
             */
            setData(dataTransfer, dragEl) {
                dataTransfer.setData('Text', '');

                const companions = [...el.querySelectorAll(`:scope > .${MOVING_TOGETHER_CLASS}`)];

                if (companions.length < 2 || typeof dataTransfer.setDragImage !== 'function') return;

                const ghost = document.createElement('div');
                ghost.style.cssText = 'position:fixed;top:-10000px;left:-10000px;display:flex;gap:4px;padding:4px;pointer-events:none;';

                companions.forEach((node) => {
                    const rect = node.getBoundingClientRect();
                    const copy = node.cloneNode(true);
                    copy.classList.remove(MOVING_TOGETHER_CLASS);
                    copy.style.width = `${rect.width}px`;
                    copy.style.height = `${rect.height}px`;
                    copy.style.flex = 'none';
                    ghost.appendChild(copy);
                });

                document.body.appendChild(ghost);
                dataTransfer.setDragImage(ghost, 20, 20);
                setTimeout(() => ghost.remove(), 0);
            },
            onStart(evt) {
                orderBeforeDrag = orderedPageIds(el);
                Alpine.store('pagePresence').dragging = true;
                Alpine.store('pagePresence').startEditing(evt.item.dataset.pageId);
            },
            onEnd(evt) {
                Alpine.store('pagePresence').dragging = false;

                const pageId = evt.item.dataset.pageId;
                Alpine.store('pagePresence').stopEditing(pageId);

                const movedItems = evt.items?.length ? evt.items : [evt.item];
                const orderAfterDrag = orderedPageIds(el);

                setTimeout(clearCompanions, 0);

                if (orderAfterDrag.join(',') === orderBeforeDrag.join(',')) return;

                // Posizione calcolata sull'ordine REALE delle pagine nel
                // DOM, non su evt.newIndex: quest'ultimo conta anche gli
                // "spacer" senza data-page-id della vista doppia pagina
                // (Sortable.index() senza selettore), e con MultiDrag
                // sarebbe l'indice della sola pagina afferrata. Il primo
                // elemento del blocco = posizione di partenza del blocco,
                // esattamente come la interpreta PageReorderer::moveBlock().
                const newPosition = Math.min(...movedItems.map((node) => orderAfterDrag.indexOf(node.dataset.pageId))) + 1;

                // Selezione multipla: l'intera selezione si sposta come
                // blocco unico (Fase 5, 2026-07-31) — stessa condizione di
                // prima. Un gruppo collegato passa invece da movePage(),
                // che lato server riconosce il gruppo e lo sposta intero.
                const selectionMode = evaluate('$wire.selectionMode');
                const selectedPageIds = (evaluate('$wire.selectedPageIds') || []).map(String);
                const isBlockDrag = selectionMode && selectedPageIds.includes(String(pageId)) && selectedPageIds.length > 1;

                el.dispatchEvent(new CustomEvent(isBlockDrag ? 'block-dropped' : 'page-dropped', {
                    detail: isBlockDrag
                        ? { anchorPageId: pageId, newPosition }
                        : { pageId, newPosition },
                }));
            },
        });

        if (expression) {
            const getDisabled = evaluateLater(expression);
            effect(() => getDisabled((disabled) => sortable.option('disabled', !!disabled)));
        }

        cleanup(() => {
            el.removeEventListener('pointerdown', onPointerDown, true);
            document.removeEventListener('pointerup', onPointerUp);
            sortable.destroy();
        });
    });

    /**
     * Transizione finale dopo un drop: `$flipAfterDrop('movePage', id, posizione)`
     * in grid.blade.php. Sortable ha già messo il DOM nell'ordine scelto
     * dall'utente; il server può comunque decidere un ordine diverso
     * (gruppo esteso, conflitto, pagina bloccata) e il FLIP fa scorrere le
     * pagine dalla posizione mostrata a quella vera invece di farle saltare.
     */
    Alpine.magic('flipAfterDrop', (el) => (method, ...args) => {
        const wire = Alpine.evaluate(el, '$wire');

        return flipMove(el.closest('[x-sortable]') ?? el, () => wire.call(method, ...args));
    });
});
