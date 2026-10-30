---
titolo: Chi può fare cosa
slug: ruoli
categoria: Riferimento
ruoli: [admin, redattore, commerciale, sola_lettura]
parole_chiave: [ruoli, permessi, admin, redattore, commerciale, sola lettura, non posso, non posso spostare, spostare articoli]
sinonimi: [autorizzazioni, accesso, chi può, perché non posso, bloccato, disattivato]
ordine: 720
---

:::in-breve
Quello che vedi e puoi fare dipende dal tuo **ruolo**:

1. **Admin**: fa tutto, su tutte le riviste.
2. **Redattore**: lavora su tutto il timone delle riviste a cui ha accesso.
3. **Commerciale**: lavora **solo sulle pubblicità** delle riviste a cui ha accesso.
4. **Sola lettura**: consulta, legge i commenti, esporta.
:::

## Tabella riassuntiva

| | Admin | Redattore | Commerciale | Sola lettura |
|---|:---:|:---:|:---:|:---:|
| Vede le riviste | Tutte | Con accesso | Con accesso | Con accesso |
| Commenti | ✅ | ✅ | ✅ | Solo legge |
| Pubblicità (anche segnaposto) | ✅ | ✅ | ✅ | ❌ |
| Articoli, pagine con articoli, stati, blocchi, gruppi, pagine interne, scadenza | ✅ | ✅ | ❌ | ❌ |
| Crea numeri | ✅ | ✅ | ❌ | ❌ |
| Crea riviste, gestisce utenti, elimina | ✅ | ❌ | ❌ | ❌ |

## Admin

- Vede tutte le riviste, sempre.
- Commenti: legge, scrive ed elimina anche quelli degli altri.
- Pubblicità, articoli, pagine (stati, blocchi, gruppi, pagine interne, scadenza).
- Crea numeri e riviste.
- Gestisce ed elimina utenti; elimina riviste e numeri.

## Redattore

- Vede solo le riviste a cui gli è stato dato accesso.
- Commenti: legge e scrive.
- Pubblicità, articoli e pagine, e crea numeri, solo sulle sue riviste.
- Non crea riviste e non gestisce gli utenti.

## Commerciale

Lavora **solo sulle pubblicità** delle riviste a cui ha accesso:

- le crea («+ Nuovo contenuto», dove il tipo è già Pubblicità, e il caricamento multiplo);
- le modifica (✏️, doppio click sul nome);
- le sposta: trascinamento, frecce e rotazione nel menu «⋯», «Posizione nel timone» nella finestra, togliere dalla pagina;
- sposta anche pagine intere o gruppi di pagine, ma solo se **non contengono articoli**;
- legge e scrive commenti;
- nella selezione vede solo seleziona e deseleziona.

Il Commerciale non può spostare gli articoli né le pagine che contengono articoli. Stato e blocco delle pagine, gruppi, pagine interne e scadenza stampa restano della redazione. Lo [stato automatico](manuale:stato-pagina#stato-automatico) vale anche per lui: una pagina che riempie con una pubblicità diventa «Assegnata».

:::dettagli
- Sugli articoli vede i comandi disattivati con il tooltip **«Solo la redazione può spostare questo contenuto»**.
- Il contenuto di un articolo nel pannello ha il cursore 🚫 e non si trascina.
- Una pagina con articoli non ha la maniglia ⠿ (tooltip «Solo la redazione può spostare pagine che contengono articoli»).
:::

## Sola lettura

Vede solo le riviste a cui ha accesso, legge i commenti ma non li scrive, non modifica nulla.

## Per tutti

- Il manuale è visibile a qualunque utente collegato: è materiale di aiuto, non un'azione che modifica dati.
- Chi può solo consultare può comunque esportare il report del cruscotto pubblicitario in CSV/PDF, il PDF del timone e il PDF sfogliabile: l'esportazione non richiede permessi di modifica.

## Vedi anche

- [Gestire gli utenti](manuale:utenti)
- [Commenti sulle pagine](manuale:commenti)
- [Problemi frequenti](manuale:problemi-frequenti)
