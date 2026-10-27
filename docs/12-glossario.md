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
| **Occupazione (di pagina)** | Quanto spazio prende un contenuto, in **dodicesimi di pagina**: pagine intere + una frazione (¼, ⅓, ½, ⅔, ¾), es. 2 e ½ pagine = 30/12. Obbligatoria dal 2026-10-12, in dodicesimi dal 2026-10-27 | `contents.occupancy_twelfths`, `App\Support\PageOccupancy` |
| **Dodicesimo (di pagina)** | Unità con cui il sistema conta lo spazio: 12 = pagina intera, ¼ = 3, ⅓ = 4, ½ = 6, ⅔ = 8, ¾ = 9. Sempre un intero, così le somme sono esatte | `App\Support\Twelfths` |
| **Spazio occupato su una pagina** | Quota di **una** pagina presa da un contenuto, da 1/12 a intera; somma ≤ 12 per pagina (capienza). Prima del 2026-10-27 era una percentuale | `page_content.occupied_twelfths`, `App\Support\PageCapacity` |
| **Capienza (della pagina)** | 12 dodicesimi: più contenuti possono stare sulla stessa pagina finché la somma non supera la pagina intera | `PageCapacity::fits()` |
| **Riempimento (della pagina)** | Stato della pagina rispetto alla capienza: vuota (○), parziale («Libero: 1/3», ◐), piena (●), oltre capienza (⚠, solo da dati convertiti) | `App\Enums\PageFillStatus`, `partials/page-fill.blade.php` |
| **Pagina bianca** | Pagina interna senza alcun contenuto (occupazione 0); sulla card «○ Vuota» | `PageFillStatus::Empty`, `PageContentType::Bianca` |
| **Anteprima (di un contenuto)** | Immagine del PDF o dell'immagine di stampa allegati, generata in automatico: è la miniatura del contenuto nel timone (non esiste un'immagine separata) | `content_files.thumbnail_path`, `GenerateContentFileThumbnail` |
| **Vetrina** | Pubblicità da ¼ di pagina (formato «Un quarto pagina») | `AdFormat::UnQuartoPagina` (3/12) |
| **Piedino** | Pubblicità a fascia in basso, ⅓ di pagina | `AdFormat::Piedino` (4/12) |
| **Pagina mista** | Pagina con contenuti editoriali e pubblicitari insieme | `PageContentType::Mista` |
| **Carico pubblicitario** | Percentuale delle pagine interne occupata da pubblicità, comprese le prenotazioni; calcolata in dodicesimi (esatta) | `App\Support\AdLoadCalculator` |
| **Gruppo collegato** | Pagine legate tra loro (🔗) che si spostano sempre insieme, con il mouse o con le frecce | `PageGroup`, `pages.page_group_id`, `Grid::nudgePage()` |
| **Zoom delle pagine (S/M/L)** | Dimensione delle miniature nel timone, scelta da ciascun utente e ricordata dal suo browser | store Alpine `timoneZoom` (`resources/js/timone-alpine.js`) |
| **Soglia di allarme** | Carico pubblicitario oltre il quale il cruscotto diventa rosso; impostata per rivista | `magazines.ad_threshold_percentage` |
| **Prenotazione** (pubblicità prenotata) | Pubblicità registrata senza pagina né materiale | `AdMaterialStatus::Prenotato` |
| **Materiale** | Il file (PDF o immagine) di un contenuto | `ContentFile` |
| **Listino ADV / formato** | Formati pubblicitari vendibili con le loro misure | `App\Enums\AdFormat` |
| **Abbondanza** (bleed) | Margine di stampa oltre il formato: +3 mm per lato | `PdfFormatChecker::BLEED_MM` |
| **Controllo formato** | Confronto tra le misure reali del PDF e il formato ADV + abbondanza + tolleranza | `FormatCheckStatus`, `TIMONE_PDF_FORMAT_TOLERANCE_MM` |
| **Override formato** | Conferma manuale che un PDF fuori formato va bene comunque | `format_override_confirmed_by/_at`, `Grid::confirmFormatOverride` |
| **I di copertina** | La copertina vera e propria, quella che si vede in edicola | `CoverSlot::I` (`pages.cover_slot = 'I'`) |
| **II di copertina** | Il retro della I: la pagina a sinistra della pagina 1, quando si apre la rivista | `CoverSlot::II` |
| **III di copertina** | Il lato interno della quarta: la pagina a destra dell'ultima pagina interna | `CoverSlot::III` |
| **IV di copertina** | Il retro della rivista | `CoverSlot::IV` |
| **Copertine** | Dal 2026-10-26 sono elementi separati del numero, sempre quattro, fuori dalla numerazione e dai conteggi delle pagine interne | `Issue::covers()`, `App\Support\IssueCovers` |
| **Bandella** (battente) | Lembo ripiegato agganciato alla I di copertina; facoltativo, vale 2 pagine di copertina nel peso (6 invece di 4) | `CoverSlot::Bandella` |
| **Pagine interne** | Le pagine numerate 1…N del timone, copertine escluse; il numero di pagine del numero | `issues.total_pages`, `Issue::pages()` |
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
| **Grammatura** | Peso della carta per metro quadrato (g/m²), quello scritto sulla risma/scheda della carta. È ciò che si inserisce nei form. Es. interno 80, copertina 170. Ammessa tra 30 e 400 g/m² (`config/peso_rivista.php`) | `grammatura_interno`, `grammatura_copertina` |
| **Peso a pagina** | Peso in grammi di una pagina (mezzo foglio) di un dato formato e grammatura: base_m × altezza_m × grammatura / 2. **Non si inserisce, si calcola**. Es. 210 × 270 mm a 80 g/m² → 2,268 g; a 170 g/m² → 4,8195 g. Confonderlo con la grammatura (0,8 invece di 80) dà un peso copia 100 volte più piccolo | `MagazineWeightCalculator::pesoPagina()`, riga sotto i campi grammatura (`PaperLine`) |
| **Segnatura** | Gruppo di pagine stampate su un unico foglio macchina e poi piegato: di solito multipli di 4 (4, 8, 16, 32). Per questo un numero di pagine interne non multiplo di 4 dà un avviso | avviso in `MagazineWeightCalculator` |
| **Formato (rivista)** | Base × altezza della rivista rifilata, in **millimetri** (es. 210 × 270). Ammesso tra 50 e 1000 mm (`config/peso_rivista.php`): 21 × 27 è un formato in cm e darebbe un peso 100 volte più piccolo | `formato_base_mm`, `formato_altezza_mm`, `WeightLimits` |
| **Refilo** | Taglio finale della rivista al formato: varia di qualche decimo di mm, da cui la tolleranza sul formato | `tolleranza_formato_mm` |
| **Scaglione (di peso)** | Fascia di peso per la spedizione (250, 350, 500, 1000 g); una copia ricade nel primo scaglione il cui limite è ≥ al peso | `config/peso_rivista.php`, `MagazineWeightCalculator::bracketFor()` |
| **Maggiorazione** | Percentuale aggiunta al peso della sola carta (default 2 %) per ciò che non è carta (stima, vedi [11-debito-tecnico.md](11-debito-tecnico.md) #33) | `maggiorazione_percentuale` |
| **Peso netto / lordo** | Netto: peso di tutte le copie della tiratura (kg). Lordo: netto + peso dei bancali | `pesoNettoKg`, `pesoLordoKg` |
| **Bancale** | Pallet su cui viaggia la tiratura; il suo peso (default 20 kg) entra nel peso lordo | `n_bancali`, `peso_bancale_kg` |
| **Tiratura** | Numero di copie stampate di un numero | `n_copie` |
| **Margine (± g)** | Scostamento massimo del peso teorico nel caso peggiore delle tolleranze, arrotondato per eccesso al grammo | `margineG` |
| **Peso reale / calibrazione** | Peso misurato su bilancia a stampa avvenuta, usato per suggerire una maggiorazione più precisa | `peso_reale_g`, `maggiorazioneSuggerita` |
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
| `upload_failed` | Voce del log applicativo scritta quando un caricamento di file fallisce prima della validazione, con il contesto per capirne la causa (`ContentCreate::_uploadErrored()`) |
| `timone:diagnose-upload` | Comando artisan che controlla configurazione e permessi da cui dipende il caricamento dei file |

## Valori degli enum (`app/Enums`)

| Enum | Valori (salvati in DB) |
|---|---|
| `UserRole` | `admin`, `redattore`, `commerciale`, `sola_lettura` |
| `Periodicity` | `mensile`, `settimanale`, `altro` |
| `IssueStatus` | `bozza`, `in_lavorazione`, `chiuso` |
| `PageStatus` | `da_assegnare`, `assegnata`, `in_bozza`, `revisionata`, `ok_stampa` |
| `PageContentType` | `editoriale`, `pubblicita`, `mista`, `bianca` |
| `CoverSlot` | `I`, `II`, `III`, `IV`, `bandella` (dal 2026-10-26; il vecchio `PageRole` è stato rimosso, la colonna `pages.role` resta solo come traccia) |
| `ContentType` | `articolo`, `pubblicita` |
| `EditorialStatus` | `da_scrivere`, `in_scrittura`, `in_revisione`, `pronto` |
| `AdFormat` | `pagina_intera`, `mezza_pagina_orizzontale`, `mezza_pagina_verticale`, `un_terzo_pagina_orizzontale`, `un_terzo_pagina_verticale`, `un_quarto_pagina`, `battente_copertina`, `doppia_pagina`, `copertina_seconda_terza_quarta`, `piedino`, `due_terzi_pagina`, `prima_romana`, `controsommario`, `elenco_inserzionisti`, `controeditoriale`, `pubbliredazionale` |
| `AdConfirmationStatus` | `in_trattativa`, `confermata`, `annullata` |
| `AdMaterialStatus` (calcolato, non salvato) | `prenotato`, `assegnato`, `completo` |
| `ThumbnailStatus` | `pending`, `processing`, `ready`, `failed` |
| `FormatCheckStatus` | `not_applicable`, `unverifiable`, `matching`, `mismatch` |
