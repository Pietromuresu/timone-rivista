# Progetto: Timone Editoriale Elettronico
### Specifica tecnica unificata — basata sullo standard di mercato (rif. TK.Digital Timone System) e sulle richieste raccolte

---

## 0. Premessa e provenienza dei contenuti

Questo documento unifica in un unico progetto coerente:

1. La specifica tecnica dettagliata contenuta nel pacchetto caricato (`SPECIFICA_PROGETTO.md`, `CHAT_CONTEXT.json`, `timone_cartaceo_riferimento.jpg`), che ricalca la struttura funzionale degli strumenti professionali di timone elettronico oggi sul mercato (pubblicazioni → edizioni → griglia pagine → contenuti → pubblicità → file → utenti → cronologia → esportazioni).
2. Le richieste emerse nella conversazione precedente: gestione di **più riviste/testate separate**, **collaborazione in tempo reale multi-utente** (tipo Google Docs), **anteprime PDF in stile Grafò**, **numero di pagine modificabile anche a lavorazione avviata**, **formati pubblicitari multipli**.
3. Il riferimento visivo fornito (`timone_cartaceo_riferimento.jpg`): il foglio cartaceo con la griglia numerata usato oggi manualmente, che deve guidare la disposizione a doppie pagine della griglia digitale.

Il risultato è un'unica specifica coerente, senza duplicazioni, pronta da consegnare a Claude Code per l'implementazione.

---

## 1. Obiettivo

Realizzare un'applicazione web interna per pianificare, organizzare e controllare le pagine di più riviste (pubblicazioni), ciascuna con le proprie edizioni periodiche, sostituendo il timone cartaceo/Excel con uno strumento digitale collaborativo.

Il sistema **non sostituisce InDesign** nell'impaginazione definitiva: è uno strumento di pianificazione e coordinamento editoriale/commerciale, con:

- pubblicazioni (riviste) ed edizioni (numeri);
- griglia delle pagine e delle doppie pagine, fedele alla disposizione del timone cartaceo;
- drag-and-drop e riordino da tastiera;
- contenuti multipagina e indivisibili;
- pubblicità con formati configurabili e calcolo del carico pubblicitario;
- stati, colori e blocco pagine;
- miniature PDF/JPG generate automaticamente;
- note e allegati;
- utenti, ruoli e permessi granulari, per pubblicazione;
- **collaborazione in tempo reale tra più utenti connessi contemporaneamente**;
- cronologia, versioni ed esportazioni (PDF, CSV, Excel, JSON, ZIP allegati).

---

## 2. Stack tecnico

- **PHP 8.4+**, **Laravel** (framework applicativo)
- **Livewire 3** (componenti reattivi server-side) + **Alpine.js** (interattività client)
- **MySQL 8.4+**
- **Tailwind CSS**
- **SortableJS** per il drag-and-drop
- **PDF.js** per la visualizzazione dei PDF nel browser, senza download
- **ImageMagick + Ghostscript** per la generazione delle miniature dai PDF caricati
- **Dompdf** o **mPDF** per le esportazioni PDF del timone
- **Laravel Reverb** (WebSocket server nativo, self-hosted) + **Laravel Echo** + **Redis** per la collaborazione in tempo reale e per le code asincrone (generazione miniature, notifiche)
- **Composer**, **Docker** (docker-compose), **Nginx**, **HTTPS**

Questa scelta di stack rende il progetto autosufficiente e a basso carico di manutenzione per il sistemista: nessun servizio esterno a pagamento (niente Pusher, niente SaaS terzi), tutto self-hosted in container.

---

## 3. Struttura logica

```text
Login
 └── Pubblicazioni (riviste/testate)
      └── Edizioni (numeri/uscite)
           ├── Timone (griglia pagine, doppie pagine)
           ├── Contenuti (articoli, rubriche, redazionali...)
           ├── Pubblicità (ordini, formati, carico pubblicitario)
           ├── File (PDF, JPG, allegati, miniature)
           ├── Utenti e permessi (per pubblicazione)
           ├── Cronologia e versioni
           └── Esportazioni (PDF, CSV, Excel, JSON, ZIP)
```

Un utente può avere accesso a una o più pubblicazioni (tabella pivot `user_publication`): un redattore della rivista A non vede né modifica la rivista B se non abilitato.

---

## 4. Entità principali

### 4.1 Pubblicazione (rivista/testata)
Campi: nome, codice, descrizione, formato pagina predefinito (larghezza/altezza mm), colore identificativo, impostazioni (soglie di carico pubblicitario, regole tipografiche come il multiplo di quattro).

### 4.2 Edizione (numero/uscita)
Campi: pubblicazione di riferimento, nome, numero, data di uscita, data di chiusura, **numero totale di pagine** (`total_pages`, modificabile anche a lavorazione avviata — vedi §6.1), stato (bozza/attiva/chiusa/in stampa/archiviata), **revisione** (intero, per optimistic locking — vedi §6.5), configurazione (JSON).

### 4.3 Pagina
Campi: posizione fisica, numero stampato (può non coincidere con la posizione fisica: inserti, pagine non numerate, pieghevoli), lato (sinistra/destra/singola), tipo pagina (copertina 1ª/2ª/3ª/4ª, normale, inserto, non numerata), stato, sezione, titolo, note, colore, miniatura, **blocco** (lucchetto), revisione, data ultima modifica.

### 4.4 Contenuto
Può occupare una o più pagine, anche non contigue se necessario. Campi: titolo, sottotitolo, descrizione, tipo (configurabile), sezione, pagine richieste, responsabile, autore, scadenza, colore, **indivisibile** (booleano), assegnato/non assegnato.

### 4.5 Relazione Pagina↔Contenuto (`page_content`)
Consente a una pagina di contenere più contenuti (es. metà pubblicità + metà articolo) con un campo `coverage_percentage` per la quota di pagina occupata da ciascuno — usato anche nel calcolo del carico pubblicitario quando una pubblicità condivide la pagina con contenuto editoriale.

### 4.6 File/Allegati
PDF, JPG, PNG, TIFF, DOCX, XLSX, ZIP, collegamenti esterni. Ogni file genera (se PDF) una miniatura automatica; storico versioni conservato (nessuna sovrascrittura silenziosa).

### 4.7 Pubblicità (ordine pubblicitario)
Campi: cliente, agenzia, campagna, codice ordine, formato, quantità, posizione richiesta, posizione assegnata, importo, stato materiale (mancante/ricevuto/approvato), scadenza, note.

### 4.8 Formati pubblicitari (configurabili dall'amministratore)
Ogni formato ha: nome, codice, **quota di pagina equivalente**, orientamento (orizzontale/verticale/non applicabile), larghezza/altezza mm (facoltative), colore, icona, ordine, stato attivo.

Valori iniziali (comprendono tutti i formati già richiesti — pagina intera, mezza orizzontale/verticale, un terzo orizzontale/verticale, un quarto — più i formati standard di mercato):

| Formato | Orientamento | Quota equivalente |
|---|---|---:|
| Doppia pagina | — | 2,0000 |
| Pagina intera | — | 1,0000 |
| Mezza pagina orizzontale | orizzontale | 0,5000 |
| Mezza pagina verticale | verticale | 0,5000 |
| Un terzo di pagina orizzontale | orizzontale | 0,3333 |
| Un terzo di pagina verticale | verticale | 0,3333 |
| Un quarto di pagina | — | 0,2500 |
| Un sesto di pagina | — | 0,1667 |
| Ottavo di pagina | — | 0,1250 |

Per formati speciali/non standard, la quota equivalente è modificabile manualmente (es. 0,20 o 0,75). I formati sono gestiti per pubblicazione, non globali, così ogni rivista può avere il proprio listino.

### 4.9 Sezioni/Rubriche
Per raggruppare contenuti per categoria (es. "Prova su strada", "Attualità"); legate alla pubblicazione di appartenenza.

### 4.10 Utenti e ruoli
Ruoli: **amministratore**, **direttore**, **caporedattore**, **grafico**, **commerciale**, **lettore**. Permessi granulari per pubblicazione tramite pivot `user_publication` (es. `publication.create`, `edition.create`, `edition.delete`, `page.move`, `page.edit`, `page.lock`, `content.edit`, `advertising.edit`, `file.upload`, `export.pdf`, `users.manage`).

---

## 5. Vista timone

La schermata principale rispecchia la disposizione del timone cartaceo di riferimento: copertina isolata, poi doppie pagine affiancate fino alla quarta di copertina.

```text
Copertina
   [1]

Prima apertura
[2] [3]

[4] [5]
[6] [7]
...
[126] [127]

Quarta di copertina
[128]
```

Ogni scheda pagina mostra: numero, **miniatura** (thumbnail del PDF/JPG caricato, in stile Grafò), titolo breve, stato, tipo, responsabile, indicatori per note e allegati, lucchetto se bloccata, colore categoria, e — quando la collaborazione realtime è attiva — un piccolo badge con l'utente che sta modificando quella pagina in quel momento.

---

## 6. Funzioni principali

### 6.1 Numero totale di pagine modificabile in ogni momento

Alla creazione dell'edizione l'utente inserisce il numero totale di pagine; il sistema crea tutte le posizioni, assegna lato sinistra/destra, distingue copertine e pagine speciali, segnala se il totale non è multiplo di quattro (senza bloccare i casi speciali autorizzati).

**Il numero totale di pagine resta modificabile anche dopo la creazione e a lavorazione avviata.** Prima di confermare, il sistema mostra sempre l'impatto dell'operazione:

- **Aumento**: si sceglie quante pagine aggiungere e se in coda o in una posizione specifica; il sistema rinumera automaticamente posizioni fisiche e, se richiesto, i numeri stampati; ricalcola lati, doppie pagine e carico pubblicitario.
- **Riduzione**: il sistema impedisce la cancellazione silenziosa di pagine occupate, bloccate o approvate; mostra contenuti, pubblicità e allegati coinvolti; consente di spostarli tra i non assegnati prima della rimozione; richiede conferma esplicita; registra l'operazione in cronologia; ricalcola lati, doppie pagine e carico pubblicitario.

Ogni modifica genera un evento broadcasting (`IssuePageCountUpdated`) così tutti gli utenti collegati vedono la griglia aggiornarsi senza ricaricare la pagina.

### 6.2 Drag-and-drop

Modalità supportate:
- **scambio** (predefinita): due pagine si scambiano di posizione;
- **inserimento con slittamento**: la pagina trascinata si inserisce nella posizione target e le altre slittano;
- spostamento di una doppia pagina intera;
- spostamento di un blocco multipagina (contenuto indivisibile su più pagine, si muove come un'unità);
- trascinamento di contenuti non assegnati dal pannello laterale direttamente sulla pagina libera.

### 6.3 Riordino da tastiera (metodo alternativo al mouse)

Selezione di una pagina (click o navigazione con Tab/frecce) e spostamento con **Ctrl+Alt+Frecce** (su/giù/sinistra/destra nella griglia), con feedback visivo immediato e possibilità di annullare (undo) l'ultimo spostamento. Utile per accessibilità e per chi preferisce non usare il mouse.

### 6.4 Selezione multipla e azioni di massa

Selezione: Ctrl/Cmd+clic, Shift+clic, intervallo, selezione rettangolare.
Azioni di massa: spostamento, cambio stato, cambio colore, cambio responsabile, blocco, cancellazione contenuto, esportazione.

### 6.5 Concorrenza: optimistic locking + broadcasting in tempo reale

Questi due meccanismi lavorano insieme e coprono esigenze diverse:

- **Optimistic locking (sicurezza dei dati)**: ogni edizione ha un campo `revision`. Il client invia sempre la revisione corrente insieme a ogni richiesta di modifica (es. spostamento pagine). Il server confronta la revisione: se è cambiata nel frattempo (un altro utente ha già salvato), la richiesta viene rifiutata e il client si riallinea con lo stato corrente. Gli spostamenti vengono eseguiti in transazione SQL con blocco delle righe coinvolte, per evitare corruzione dei dati anche in caso di richieste quasi simultanee.
- **Broadcasting in tempo reale (esperienza utente tipo Google Docs)**: subito dopo che una modifica è stata salvata con successo, un evento Laravel (`ShouldBroadcast`) la trasmette via **Laravel Reverb** a un canale presence dedicato all'edizione (`presence-edition.{id}`). Tutti gli utenti collegati sulla stessa edizione vedono la card muoversi, il contenuto assegnarsi, lo stato cambiare — senza dover ricaricare la pagina.
- **Presence channel**: mostra avatar/nomi degli utenti attualmente connessi su quell'edizione, aggiornati via eventi `here`/`joining`/`leaving` di Laravel Echo.
- **Badge "chi sta modificando"**: quando un utente ha selezionato/sta trascinando una pagina, un badge con il suo nome appare su quella card per gli altri, riducendo il rischio che due persone tentino di spostare la stessa pagina nello stesso istante (il conflitto viene comunque risolto in sicurezza dall'optimistic locking).
- **Fallback**: se il WebSocket non è raggiungibile (rete aziendale restrittiva), fallback automatico a polling Livewire ogni 3-5 secondi.

Eventi broadcasting principali: `PageMoved`, `ContentAssigned`/`ContentUnassigned`, `PageStatusUpdated`, `AdvertisingLoadRecalculated`, `IssuePageCountUpdated`, `PageFileUploaded`, `PageLocked`/`PageUnlocked`.

### 6.6 Blocchi e pagine bloccate

Un contenuto multipagina può essere: libero, indivisibile, doppia pagina, ancorato, bloccato. Una pagina bloccata non può essere spostata, eliminata, sovrascritta, o modificata da utenti non autorizzati (permesso `page.lock` / `page.edit`).

### 6.7 Doppie pagine

Funzioni: unisci, separa, sposta insieme, anteprima unica, linea centrale visibile, impedisci inversione sinistra/destra.

### 6.8 Copertine e pagine speciali

Gestite separatamente: prima/seconda/terza/quarta di copertina, inserti, allegati, pagine non numerate, pieghevoli. La posizione fisica non coincide obbligatoriamente con il numero stampato.

### 6.9 Pannello contenuti non assegnati

Elenco laterale con titolo, tipo, pagine richieste, autore/cliente, scadenza, stato; trascinabile sulle pagine libere.

---

## 7. Cruscotto pubblicitario (carico pubblicitario)

Mostrato in tempo reale (aggiornato anche via broadcasting) per ogni edizione:

- numero di doppie pagine pubblicitarie, pagine intere, mezze pagine, terzi di pagina, e altri formati raggruppati per tipologia;
- totale inserzioni;
- totale pagine pubblicitarie equivalenti;
- totale pagine dell'edizione;
- pagine non pubblicitarie (editoriali) equivalenti;
- **carico pubblicitario percentuale**.

Formula:

```text
pagine pubblicitarie equivalenti = Σ (quantità × quota equivalente del formato)
carico pubblicitario % = pagine pubblicitarie equivalenti / totale pagine edizione × 100
```

Esempio: in un'edizione di 100 pagine con 12 pagine intere, 6 mezze pagine e 3 terzi di pagina → 12 + 3 + 1 = 16 pagine equivalenti → carico pubblicitario 16%.

Il denominatore predefinito è il totale pagine dell'edizione; nelle impostazioni è possibile creare viste alternative (es. escludendo copertine o inserti), mantenendo sempre visibile il calcolo principale.

Il cruscotto si aggiorna automaticamente quando: viene aggiunta/rimossa/modificata una pubblicità, cambia formato o quantità di un'inserzione, cambia il numero totale di pagine, una pubblicità viene assegnata o rimossa dal timone.

Distingue sempre tre valori: **pubblicità prenotata**, **pubblicità assegnata nel timone**, **pubblicità con materiale ricevuto o approvato** — per confrontare il carico commerciale previsto con quello effettivamente pronto per la stampa.

---

## 8. Anteprime PDF (stile Grafò)

Flusso:

1. Upload del PDF/JPG sulla pagina (drag&drop del file o selezione da filesystem).
2. Controllo MIME reale e dimensione massima.
3. Salvataggio **fuori dalla web root**.
4. Generazione miniatura in **coda asincrona** (Laravel Queue + Redis, container `worker` dedicato) via ImageMagick/Ghostscript, con indicatore "generazione anteprima in corso..." sulla card nel frattempo.
5. Associazione della miniatura alla pagina o al contenuto; visualizzazione diretta nella card della griglia timone.
6. Apertura del PDF a schermo intero dentro l'applicazione con **PDF.js** (nessun download necessario).
7. Per PDF multipagina: opzione solo prima pagina, tutte le pagine, o intervallo scelto.
8. **Storico versioni**: un nuovo caricamento sulla stessa pagina non sovrascrive il precedente; resta consultabile con data e utente di caricamento.
9. Il caricamento genera anch'esso un evento broadcasting (`PageFileUploaded`), visibile in tempo reale a tutti gli utenti collegati.

---

## 9. Cronologia e versioni

Ogni azione registra: utente, data/ora, entità, azione, valore precedente, valore nuovo, indirizzo IP.

Versioni nominate dell'edizione (snapshot JSON), per esempio "prima riunione", "dopo inserimento pubblicità", "chiusura definitiva", con possibilità di ripristino.

---

## 10. Controlli automatici

Il sistema segnala automaticamente:

- pagine mancanti o duplicate;
- articolo spezzato su pagine non contigue senza motivo;
- doppia pagina non valida;
- pubblicità sul lato sbagliato rispetto alla posizione richiesta;
- numero di pagine insufficiente per i contenuti assegnati;
- modifica di una pagina già approvata;
- file definitivo mancante in prossimità della scadenza;
- scadenza superata;
- totale pagine non multiplo di quattro;
- carico pubblicitario oltre la soglia configurata;
- discrepanza tra pubblicità prenotata e assegnata;
- formato pubblicitario privo di quota equivalente;
- copertina incompleta;
- pagina bloccata coinvolta in un tentativo di spostamento.

---

## 11. Esportazioni

- PDF (A4/A3, verticale/orizzontale, con o senza miniature, solo pubblicità, solo pagine non approvate, o solo pagine selezionate) tramite Dompdf/mPDF;
- CSV, Excel, JSON;
- ZIP degli allegati.

---

## 12. Sicurezza

- `password_hash`/`password_verify`;
- Eloquent/PDO con prepared statement (nessuna query concatenata);
- protezione CSRF (nativa Laravel);
- session cookie HttpOnly, Secure, SameSite;
- rigenerazione sessione al login;
- rate limiting sulle API;
- autorizzazioni (Laravel Policy) su ogni endpoint, per ruolo e per pubblicazione;
- validazione server-side su tutti gli input;
- escaping HTML in output (Blade lo fa di default);
- controllo MIME reale sui file caricati (non solo estensione);
- nomi file casuali in storage;
- upload fuori dalla web root;
- backup periodico di database e file.

---

## 13. API principali

```text
GET    /api/editions
POST   /api/editions
GET    /api/editions/{id}
PUT    /api/editions/{id}
DELETE /api/editions/{id}
POST   /api/editions/{id}/close
POST   /api/editions/{id}/duplicate

GET    /api/editions/{id}/pages
GET    /api/pages/{id}
PUT    /api/pages/{id}
POST   /api/pages/reorder
POST   /api/pages/swap
POST   /api/pages/move-block
POST   /api/pages/{id}/lock
POST   /api/pages/{id}/unlock
POST   /api/pages/bulk-update

GET    /api/editions/{id}/contents
POST   /api/contents
PUT    /api/contents/{id}
DELETE /api/contents/{id}
POST   /api/contents/{id}/assign
POST   /api/contents/{id}/unassign

POST   /api/files/upload
GET    /api/files/{id}
DELETE /api/files/{id}
GET    /api/editions/{id}/activity
POST   /api/editions/{id}/versions
POST   /api/versions/{id}/restore
```

Esempio di richiesta di spostamento (con revisione per optimistic locking):

```json
{
  "edition_id": 12,
  "mode": "swap",
  "source_page_ids": [204, 205, 206, 207],
  "target_position": 40,
  "edition_revision": 18
}
```

Regola lato/pagina:

```php
function determinePageSide(int $pageNumber): string
{
    return $pageNumber % 2 === 0 ? 'left' : 'right';
}
```
(le copertine sono gestite separatamente da questa regola generale)

---

## 14. Schema database (tabelle)

```text
users
user_publication        -- pivot: accesso utente per pubblicazione
roles
permissions
role_permissions
user_roles
publications
editions
pages
content_items
page_content
content_types
page_statuses
sections
files
comments
activity_logs
edition_versions
advertisers
advertising_orders
advertising_formats
advertising_positions
page_locks
notifications
```

### Schema SQL iniziale (estratto essenziale, ampliabile in fase di migrazione Laravel)

```sql
CREATE DATABASE editorial_flatplan
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE editorial_flatplan;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE publications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    code VARCHAR(50) NULL,
    description TEXT NULL,
    default_page_width_mm DECIMAL(8,2) NULL,
    default_page_height_mm DECIMAL(8,2) NULL,
    color VARCHAR(20) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE user_publication (
    user_id BIGINT UNSIGNED NOT NULL,
    publication_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(50) NOT NULL,
    PRIMARY KEY (user_id, publication_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE
);

CREATE TABLE editions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    publication_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    issue_number VARCHAR(50) NULL,
    publication_date DATE NULL,
    closing_date DATE NULL,
    total_pages INT UNSIGNED NOT NULL,
    status ENUM('draft','active','closed','printed','archived')
        NOT NULL DEFAULT 'draft',
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    settings JSON NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (publication_id)
        REFERENCES publications(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE content_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    color VARCHAR(20) NULL,
    icon VARCHAR(50) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE page_statuses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    color VARCHAR(20) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_final BOOLEAN NOT NULL DEFAULT FALSE,
    is_locked BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    edition_id BIGINT UNSIGNED NOT NULL,
    physical_position INT UNSIGNED NOT NULL,
    printed_number VARCHAR(30) NULL,
    page_type ENUM(
        'cover_front','cover_inside_front','normal',
        'cover_inside_back','cover_back','insert','unnumbered'
    ) NOT NULL DEFAULT 'normal',
    side ENUM('left','right','single') NOT NULL,
    page_status_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NULL,
    notes TEXT NULL,
    color VARCHAR(20) NULL,
    thumbnail_path VARCHAR(500) NULL,
    is_locked BOOLEAN NOT NULL DEFAULT FALSE,
    locked_by BIGINT UNSIGNED NULL,
    locked_at DATETIME NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE,
    FOREIGN KEY (page_status_id) REFERENCES page_statuses(id),
    FOREIGN KEY (locked_by) REFERENCES users(id),
    UNIQUE KEY uk_edition_position (edition_id, physical_position)
);

CREATE TABLE content_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    edition_id BIGINT UNSIGNED NOT NULL,
    content_type_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(255) NULL,
    description TEXT NULL,
    requested_pages INT UNSIGNED NOT NULL DEFAULT 1,
    responsible_user_id BIGINT UNSIGNED NULL,
    author_name VARCHAR(180) NULL,
    deadline DATE NULL,
    color VARCHAR(20) NULL,
    is_indivisible BOOLEAN NOT NULL DEFAULT FALSE,
    is_unassigned BOOLEAN NOT NULL DEFAULT TRUE,
    metadata JSON NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE,
    FOREIGN KEY (content_type_id) REFERENCES content_types(id),
    FOREIGN KEY (responsible_user_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE page_content (
    page_id BIGINT UNSIGNED NOT NULL,
    content_item_id BIGINT UNSIGNED NOT NULL,
    sequence_number INT UNSIGNED NOT NULL DEFAULT 1,
    coverage_percentage DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    PRIMARY KEY (page_id, content_item_id),
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    FOREIGN KEY (content_item_id) REFERENCES content_items(id) ON DELETE CASCADE
);

CREATE TABLE files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    edition_id BIGINT UNSIGNED NOT NULL,
    page_id BIGINT UNSIGNED NULL,
    content_item_id BIGINT UNSIGNED NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    thumbnail_path VARCHAR(500) NULL,
    mime_type VARCHAR(150) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    checksum_sha256 CHAR(64) NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE SET NULL,
    FOREIGN KEY (content_item_id) REFERENCES content_items(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE advertising_formats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    publication_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(40) NOT NULL,
    page_equivalent DECIMAL(8,4) NOT NULL,
    orientation ENUM('horizontal','vertical','not_applicable')
        NOT NULL DEFAULT 'not_applicable',
    width_mm DECIMAL(8,2) NULL,
    height_mm DECIMAL(8,2) NULL,
    color VARCHAR(20) NULL,
    icon VARCHAR(50) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE,
    UNIQUE KEY uk_publication_code (publication_id, code)
);

CREATE TABLE advertising_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    edition_id BIGINT UNSIGNED NOT NULL,
    advertising_format_id BIGINT UNSIGNED NOT NULL,
    client_name VARCHAR(180) NOT NULL,
    agency_name VARCHAR(180) NULL,
    campaign_name VARCHAR(180) NULL,
    order_code VARCHAR(80) NULL,
    quantity DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    requested_position VARCHAR(180) NULL,
    amount DECIMAL(12,2) NULL,
    material_status ENUM('missing','received','approved')
        NOT NULL DEFAULT 'missing',
    deadline DATE NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE,
    FOREIGN KEY (advertising_format_id) REFERENCES advertising_formats(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    edition_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE edition_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    edition_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    version_name VARCHAR(180) NULL,
    snapshot JSON NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    UNIQUE KEY uk_edition_version (edition_id, version_number)
);
```

*(Tabelle aggiuntive da modellare in Laravel migration: `roles`, `permissions`, `role_permissions`, `user_roles`, `sections`, `comments`, `advertising_positions`, `page_locks`, `notifications` — seguono lo stesso pattern delle tabelle sopra.)*

---

## 15. Docker e deploy (basso carico per il sistemista)

`docker-compose.yml` con i servizi:

- `app` — PHP-FPM + Laravel, con **ImageMagick e Ghostscript** installati nell'immagine per le miniature PDF;
- `worker` — stesso codice di `app`, dedicato a processare le code Laravel (miniature, notifiche, esportazioni pesanti);
- `webserver` — Nginx;
- `db` — MySQL 8.4;
- `reverb` — server WebSocket Laravel Reverb;
- `redis` — driver di code, cache e broadcasting.

Avvio in un solo comando: `docker compose up -d`, poi al primo avvio `docker compose exec app php artisan migrate --seed`.

Volume Docker dedicato e persistente per `storage/uploads`, `storage/thumbnails`, `storage/exports`.

`.env.example` completo e commentato, incluse le variabili Reverb (`REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`).

Nessuna dipendenza da servizi esterni a pagamento: tutto self-hosted. Backup: script bash per dump periodico di MySQL e degli allegati.

---

## 16. Struttura cartelle (adattata a Laravel)

```text
project/
├── app/
│   ├── Models/
│   ├── Http/Controllers/
│   ├── Livewire/              (componenti timone, dashboard pubblicitario, ecc.)
│   ├── Events/                (PageMoved, ContentAssigned, ecc. — broadcasting)
│   ├── Policies/
│   ├── Services/               (calcolo carico pubblicitario, gestione foliazione)
│   ├── Jobs/                   (generazione miniature in coda)
│   └── Http/Middleware/
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── public/
├── resources/
│   ├── views/
│   ├── js/                     (Echo, Alpine, Sortable.js)
│   └── css/
├── routes/
│   ├── web.php
│   ├── api.php
│   └── channels.php            (definizione canali broadcasting/presence)
├── storage/
│   ├── uploads/
│   ├── thumbnails/
│   ├── exports/
│   └── logs/
├── tests/
├── docker-compose.yml
├── composer.json
└── .env
```

---

## 17. Prestazioni obiettivo

- 500 pagine per edizione;
- 5.000 contenuti;
- 20 utenti contemporanei collegati in tempo reale sulla stessa edizione;
- 10.000 file;
- 100.000 eventi di cronologia.

---

## 18. MVP (prima versione da consegnare)

1. Autenticazione e gestione utenti/ruoli.
2. Pubblicazioni (riviste) con utenti abilitati per pubblicazione.
3. Edizioni (numeri), con numero pagine modificabile fin da subito.
4. Generazione automatica pagine e griglia a doppie pagine fedele al riferimento cartaceo.
5. Drag-and-drop (modalità scambio come predefinita) **e riordino da tastiera**.
6. Titolo, note, tipi e colori pagina.
7. Stati pagina configurabili.
8. Blocco pagina.
9. Upload PDF/JPG con **miniature automatiche** e visualizzatore PDF.js.
10. Cronologia delle modifiche.
11. Formati pubblicitari configurabili e contatore/carico pubblicitario in tempo reale.
12. **Collaborazione in tempo reale (Reverb + presence channel)** — inclusa fin dall'MVP su richiesta esplicita, non rimandata a una fase successiva.
13. Esportazione PDF del timone.
14. Backup.

## 19. Fasi successive

- Contenuti multipagina avanzati e pannello non assegnati completo;
- gestione pubblicitaria estesa (posizioni, conflitti automatici);
- selezione multipla e azioni di massa complete;
- versioni nominate e confronto tra versioni;
- commenti sulle pagine;
- notifiche (scadenze, materiale mancante);
- integrazione diretta con InDesign (export dati verso pacchetto InDesign, se richiesto in futuro).

---

## 20. Riferimento visivo

Il file `timone_cartaceo_riferimento.jpg` allegato al progetto documenta il metodo cartaceo attualmente in uso (griglia numerata con annotazioni manuali) e resta il riferimento guida per la disposizione a doppie pagine della griglia digitale (§5).
