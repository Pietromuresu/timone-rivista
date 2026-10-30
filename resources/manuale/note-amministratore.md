---
titolo: Note per l'amministratore
slug: note-amministratore
categoria: Riferimento
ruoli: [admin]
parole_chiave: [amministratore, stato pagine, riallineamento, installazione, server, limite upload, websocket, aggiornamento, migrazione, configurazione, voti, assistente]
sinonimi: [tecnico, admin di sistema, deploy, readme, impostazioni tecniche]
ordine: 760
---

:::in-breve
Dettagli tecnici e di installazione, per chi gestisce il timone. Per avviare l'applicazione la prima volta vedi il file README.md del progetto.
:::

## Limiti e impostazioni

- **Limite dei file**: il massimo indicato nel riquadro di caricamento è il limite **reale** del server, che può essere più basso di 100 MB se il server è configurato così.
- **«Il file non è arrivato al server…»**: nel registro del server c'è la causa; chiedi all'utente l'ora del tentativo.
- **Preavviso della scadenza stampa**: di default 3 giorni prima; è un'impostazione tecnica dell'installazione, non modificabile dall'interfaccia.
- **Manuale non disponibile**: se compare l'avviso giallo «Il manuale utente non è disponibile», il manuale non è stato trovato sul server (vedi il README).

## Assistente «Chiedi al manuale»

- **Cosa chiedono gli utenti**: pagina per gli amministratori (link in fondo al riquadro «Chiedi al manuale» nella pagina del Manuale). Mostra le domande frequenti senza risposta, le domande imparate dai voti, le **risposte probabilmente sbagliate** (almeno 5 persone, quasi tutte «Non è questo»), l'ultimo ricalcolo e l'ultimo blocco. L'unica azione è **«Disattiva»**, su una domanda imparata o una correzione: vale subito.
- **Correggere una risposta**: si modifica la pagina del manuale (parole chiave, sinonimi, «In breve»), non i voti. Le domande iniziali (`resources/manuale/domande.yml`) restano come sono: le domande nuove le impara l'assistente.
- **Ricalcolo notturno** alle 3:15 (`php artisan manuale:apprendi`, si può lanciare a mano): ricalcola tutto da zero dai voti e pubblica solo se tutte le domande di prova trovano ancora la loro pagina tra le prime tre; altrimenti tiene i pesi di prima e lo scrive nel registro e nella pagina «Cosa chiedono gli utenti». Richiede che lo scheduler dell'installazione giri (servizio `scheduler`).
- **Dati**: i voti sono salvati senza il nome di chi vota (un'impronta non reversibile) e cancellati dopo 12 mesi (`php artisan manuale:pulisci`, ogni notte).

## Aggiornamenti in tempo reale

Le modifiche arrivano agli altri utenti in tempo reale. Se per qualche minuto non arrivano (la rete taglia fuori il collegamento in tempo reale, i websocket), compare l'avviso giallo: la pagina controlla da sola se c'è qualcosa di nuovo circa una volta al secondo e si aggiorna nello stesso modo, avviso «qualcosa è cambiato» compreso. L'unica differenza è un ritardo massimo di un secondo invece dell'istante.

## Controlli e calcoli

- I permessi valgono anche lato server: un tentativo diretto di fare un'azione non permessa viene rifiutato.
- Lo spazio si conta in **dodicesimi di pagina** (1/4 = 3, 1/3 = 4, 1/2 = 6, 2/3 = 8, 3/4 = 9, intera = 12), così le somme sono sempre esatte.

## Dati convertiti

- **Contenuti creati prima di questa versione** (occupazione obbligatoria): lo spazio che avevano sulle pagine è stato convertito alla frazione più vicina (50% → 1/2, 33% → 1/3, 99,7% → intera). Se su una pagina la somma risulta oltre la capienza, la pagina mostra «⚠ Oltre capienza». Un contenuto vecchio ancora senza occupazione non viene mai piazzato «a caso» su una pagina: un messaggio lo segnala.
- **Numeri creati prima del 2026-10-26**: convertiti automaticamente. Le pagine che erano copertina sono diventate le copertine I–IV (con i loro contenuti), le pagine interne sono state rinumerate da 1, e le copertine che mancavano (spesso la II e la III) sono state create vuote.
- **Pagine caricate prima del 2026-09-02**: se una pagina ha ancora un PDF caricato con il vecchio sistema (direttamente sulla pagina), continua a funzionare come prima, con l'anteprima e lo storico 🕐, finché non verrà migrata. La migrazione è un'operazione futura, non ancora effettuata: nessun dato esistente è stato toccato.
- **Stato delle pagine già piene**: lo [stato automatico](manuale:stato-pagina#stato-automatico) scatta solo quando cambia lo spazio, quindi le pagine riempite prima restano come sono. `php artisan timone:sync-page-status --dry-run` elenca le pagine piene ancora «Da assegnare» e quelle «Assegnata» non piene; lanciato senza `--dry-run` porta ad «Assegnata» **solo** le prime (mai le bloccate) e lo scrive in cronologia. Le «Assegnata» non piene restano un elenco da controllare a mano. Si può limitare a un numero con `--issue=ID`.
- Le voci vecchie della cronologia sui cambi della soglia di allarme pubblicitario restano leggibili, ma la soglia non esiste più.

## Vedi anche

- [Problemi frequenti](manuale:problemi-frequenti)
- [Novità](manuale:novita)
- [Gestire gli utenti](manuale:utenti)
- [Chiedi al manuale](manuale:chiedi-al-manuale)
