# 12 — Glossario

[← Indice](README.md) · Vedi anche: [03-dominio-e-flussi.md](03-dominio-e-flussi.md), `MANUALE_UTENTE.md`

## Termini di dominio

| Termine | Significato | Nel codice |
|---|---|---|
| **Timone** (timone elettronico) | Il flat plan di un numero: la mappa di tutte le pagine con cosa contengono. Sostituisce il timone cartaceo (`timone_cartaceo_riferimento.jpg`) | `App\Livewire\Timone\*`, rotta `issues.show` |
| **Rivista** | Testata periodica | `Magazine`, tabella `magazines` |
| **Numero** | Una singola uscita di una rivista | `Issue`, tabella `issues` |
| **Pagina** | Pagina fisica del numero, identificata dalla posizione | `Page`, `pages.position` |
| **Apertura / doppia pagina** (spread) | Coppia di pagine affiancate (pari a sinistra, dispari a destra) | `App\Support\PageSpreadBuilder` |
| **Contenuto** | Articolo o pubblicità da collocare nel numero | `Content` (`type`) |
| **Rubrica** | Sezione ricorrente della rivista | `Section` |
| **Occupazione (di pagina)** | Quanto spazio prende un contenuto: frazione di pagina (¼, ⅓, ½, ⅔) o numero intero di pagine. Obbligatoria dal 2026-10-12 | `contents.page_occupancy`, `App\Support\PageOccupancy` |
| **Percentuale occupata** | Quota di **una** pagina presa da un contenuto (0–100, somma ≤ 100 per pagina) | `page_content.occupied_percentage`, `PagePercentageAllocator` |
| **Pagina mista** | Pagina con contenuti editoriali e pubblicitari insieme | `PageContentType::Mista` |
| **Carico pubblicitario** | Percentuale del numero occupata da pubblicità, comprese le prenotazioni | `App\Support\AdLoadCalculator` |
| **Soglia di allarme** | Carico pubblicitario oltre il quale il cruscotto diventa rosso; impostata per rivista | `magazines.ad_threshold_percentage` |
| **Prenotazione** (pubblicità prenotata) | Pubblicità registrata senza pagina né materiale | `AdMaterialStatus::Prenotato` |
| **Materiale** | Il file (PDF o immagine) di un contenuto | `ContentFile` |
| **Listino ADV / formato** | Formati pubblicitari vendibili con le loro misure | `App\Enums\AdFormat` |
| **Abbondanza** (bleed) | Margine di stampa oltre il formato: +3 mm per lato | `PdfFormatChecker::BLEED_MM` |
| **Controllo formato** | Confronto tra le misure reali del PDF e il formato ADV + abbondanza + tolleranza | `FormatCheckStatus`, `TIMONE_PDF_FORMAT_TOLERANCE_MM` |
| **Override formato** | Conferma manuale che un PDF fuori formato va bene comunque | `format_override_confirmed_by/_at`, `Grid::confirmFormatOverride` |
| **Copertina I/II/III/IV** | Prima di copertina, seconda (retro della prima), terza, quarta (retro di copertina) | `PageRole::Copertina1..4` |
| **Battente** | Aletta pieghevole della copertina; può contare o no nel totale pagine | `PageRole::Battente`, `magazines.battente_counted_in_total` |
| **Pagine collegate / gruppo** | Pagine che si spostano sempre insieme | `PageGroup`, `pages.page_group_id` |
| **Blocco pagina** | Pagina "congelata": non si sposta né si modifica | `pages.locked_at/locked_by` |
| **Modalità scambio** | Scambio di posizione tra due pagine | `Grid::toggleSwapMode/selectForSwap`, evento `PagesSwapped` |
| **Ok stampa** | Stato finale di una pagina; richiede un PDF | `PageStatus::OkStampa` |
| **Scadenza stampa** | Data entro cui il numero va in tipografia | `issues.print_deadline_at` |
| **Redattore di riferimento** | Utente che riceve il sollecito della scadenza | `magazines.redattore_id` |
| **Sollecito** | Mail automatica di avviso prima della scadenza | `IssueDeadlineReminder`, `timone:send-deadline-reminders` |
| **Chiusura del numero** | Stato `chiuso`, consentito solo con tutta la pubblicità completa | `AdReservations::closeIssue` |
| **Cronologia / storico** | Log delle azioni e degli spostamenti | `activity_logs`, `page_reorder_logs` |
| **Avvisi automatici** | Controlli non bloccanti di coerenza | `App\Support\AutomaticChecks` |
| **PDF legacy / PageFile** | PDF caricato direttamente su una pagina con il vecchio flusso (prima del 2026-09-02) | `PageFile`, `page_files` |
| **Presence** | Chi è online sullo stesso numero | canale `presence-issue.{id}` |
| **Whisper** | Messaggio effimero client-to-client via Reverb ("X sta modificando pagina N") | `resources/js/app.js`, store `pagePresence` |

## Abbreviazioni e termini tecnici

| Sigla | Significato |
|---|---|
| ADV | Advertising, pubblicità |
| DX / SX | Pagina destra / sinistra (indicatori sulle card) |
| HMR | Hot Module Replacement (Vite in dev) |
| FPM | PHP FastCGI Process Manager (container `app`) |
| WS / WSS | WebSocket in chiaro / su TLS |
| Fase N, Punto N, Bug N | Riferimenti alle fasi di sviluppo documentate in `HANDOFF.md` e ai prompt in `CLAUDE_PROMPTS/` |
| §N | Riferimento a una sezione dello spec originale o del manuale |

## Valori degli enum (`app/Enums`)

| Enum | Valori (salvati in DB) |
|---|---|
| `UserRole` | `admin`, `redattore`, `commerciale`, `sola_lettura` |
| `Periodicity` | `mensile`, `settimanale`, `altro` |
| `IssueStatus` | `bozza`, `in_lavorazione`, `chiuso` |
| `PageStatus` | `da_assegnare`, `assegnata`, `in_bozza`, `revisionata`, `ok_stampa` |
| `PageContentType` | `editoriale`, `pubblicita`, `mista`, `bianca` |
| `PageRole` | `copertina1`, `copertina2`, `copertina3`, `copertina4`, `battente`, `interna` |
| `ContentType` | `articolo`, `pubblicita` |
| `EditorialStatus` | `da_scrivere`, `in_scrittura`, `in_revisione`, `pronto` |
| `AdFormat` | `pagina_intera`, `mezza_pagina_orizzontale`, `mezza_pagina_verticale`, `un_terzo_pagina_orizzontale`, `un_terzo_pagina_verticale`, `un_quarto_pagina`, `battente_copertina`, `doppia_pagina`, `copertina_seconda_terza_quarta`, `piedino`, `due_terzi_pagina`, `prima_romana`, `controsommario`, `elenco_inserzionisti`, `controeditoriale`, `pubbliredazionale` |
| `AdConfirmationStatus` | `in_trattativa`, `confermata`, `annullata` |
| `AdMaterialStatus` (calcolato, non salvato) | `prenotato`, `assegnato`, `completo` |
| `ThumbnailStatus` | `pending`, `processing`, `ready`, `failed` |
| `FormatCheckStatus` | `not_applicable`, `unverifiable`, `matching`, `mismatch` |
