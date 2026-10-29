/**
 * «Chiedi al manuale» (2026-10-29): il pannello dell'assistente e il
 * bottone «?» fisso nell'app. Il browser manda solo la domanda e i voti:
 * quale pagina rispondere lo decide il server (App\Support\Manual), e la
 * risposta è l'«In breve» della pagina, già convertito in HTML dal server
 * (dal markdown del manuale, con l'HTML grezzo escapato).
 *
 * Solo voti espliciti: nessun clic o tempo di lettura viene mandato.
 */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function post(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify(body),
    });

    return { status: response.status, data: await response.json().catch(() => ({})) };
}

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.data('manualAssistant', (config) => ({
        question: '',
        loading: false,
        error: '',
        result: null,
        voted: null,
        chosen: null,
        pendingVote: null,

        async ask(text = null) {
            if (text !== null) this.question = text;
            const question = this.question.trim();
            if (question === '' || this.loading) return;

            this.loading = true;
            this.error = '';
            this.result = null;
            this.voted = null;
            this.chosen = null;
            this.pendingVote = null;
            try {
                const { status, data } = await post(config.askUrl, { domanda: question, contesto: config.context });
                if (status === 429) {
                    this.error = 'Troppe domande in poco tempo: riprova tra un minuto.';
                } else if (status !== 200) {
                    this.error = 'Il manuale non risponde in questo momento. Riprova, o cerca nel manuale.';
                } else {
                    this.result = data;
                }
            } catch (e) {
                this.error = 'Il manuale non risponde in questo momento. Riprova, o cerca nel manuale.';
            } finally {
                this.loading = false;
            }
        },

        /** 👍 / 👎 sulla risposta: una volta sola. */
        async vote(value) {
            if (this.voted || !this.result?.ricevuta) return;
            this.voted = value;
            this.pendingVote = post(config.voteUrl, { ricevuta: this.result.ricevuta, voto: value }).catch(() => {});
            await this.pendingVote;
        },

        /** «👍 È questa» su un'alternativa: una sola scelta per risposta. */
        async choose(slug) {
            if (this.chosen || !this.result?.ricevuta) return;
            this.chosen = slug;
            // Il server accetta la scelta solo dopo il 👎: se il 👎 è ancora in viaggio, si aspetta.
            await this.pendingVote;
            await post(config.voteUrl, { ricevuta: this.result.ricevuta, scelta: slug }).catch(() => {});
        },
    }));

    /**
     * Bottone «?» fisso: apre il pannello dell'assistente. Esc o clic fuori
     * lo chiudono e il fuoco torna al bottone (tastiera).
     */
    Alpine.data('manualHelpButton', () => ({
        open: false,

        show() {
            this.open = true;
            this.$nextTick(() => this.$refs.panel?.querySelector('input')?.focus());
        },

        hide() {
            if (!this.open) return;
            this.open = false;
            this.$nextTick(() => this.$refs.button?.focus());
        },
    }));
});
