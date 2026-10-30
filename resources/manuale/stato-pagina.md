---
titolo: Cambiare lo stato di una pagina
slug: stato-pagina
categoria: Lavorare sul timone
ruoli: [admin, redattore, commerciale]
parole_chiave: [stato, da assegnare, assegnata, in bozza, revisionata, ok stampa, approvare, stato automatico, pagina piena]
sinonimi: [approvazione, avanzamento, pronta, stampa, cambiare stato]
ordine: 140
---

:::in-breve
Ogni pagina ha uno stato, mostrato dall'etichetta colorata in alto sulla card e dal colore del bordo.

1. Clicca sull'etichetta colorata in alto sulla card o sulla riga.
2. Scegli il nuovo stato dal menu.

Gli stati sono: **Da assegnare**, **Assegnata**, **In bozza**, **Revisionata**, **Ok stampa**.

Lo stato cambia anche da solo: una pagina che **diventa piena** passa da «Da assegnare» ad **«Assegnata»**; una pagina che **non è più piena** torna **«Da assegnare»**, qualunque stato avesse. Puoi sempre cambiarlo a mano.
:::

Funziona in tutte e tre le viste. Non c'è un ordine obbligato: puoi passare da uno stato all'altro liberamente, **eccetto verso «Ok stampa»**.

## Stato automatico

Quando assegni, togli, estendi un contenuto o ne cambi lo spazio, il timone guarda ogni pagina coinvolta:

- **diventa piena** (12/12, anche «⚠ Oltre capienza») ed è «Da assegnare» → **«Assegnata»**. Se è già più avanti («Assegnata», «In bozza», «Revisionata», «Ok stampa») resta com'è;
- **non è più piena** → torna **«Da assegnare»**. Se era «In bozza», «Revisionata» o «Ok stampa» compare un messaggio, per esempio «La pagina 12 è tornata «Da assegnare» (era «Ok stampa»).»;
- **resta non piena** (per esempio da 1/2 a 3/4) o **resta piena** → lo stato non cambia.

:::dettagli
- Vale per pagine interne, copertine e battenti, e per ogni pagina di un contenuto su più pagine: un articolo di 2 e 1/2 pagine rende «Assegnata» le prime due, la terza (a metà) non cambia.
- Vale anche quando l'operazione la fa il Commerciale, che a mano lo stato non lo può cambiare.
- Spostare o scambiare pagine (anche le copertine) non cambia lo stato: la pagina se lo porta dietro. Lo stesso vale per frecce e rotazione, che non cambiano lo spazio.
- Le pagine bloccate non cambiano mai stato.
- Con [Annulla](manuale:annullare-ripetere) torna anche lo stato di prima, «Ok stampa» compreso: il cambio automatico fa parte della stessa azione.
- Nella [Cronologia](manuale:storico-e-cronologia) il cambio compare come «Stato pagina 12: Da assegnare → Assegnata (automatico, dopo: Assegna «Acme» (pagina 12))».
- Lo stato si controlla solo quando cambia lo spazio: le pagine riempite prima di questa funzione restano com'erano finché qualcuno non lo cambia o l'amministratore non le riallinea (vedi [Note per l'amministratore](manuale:note-amministratore)).
:::

## «Ok stampa» richiede il PDF

**Ogni pagina deve avere un'anteprima (dal contenuto assegnato) prima di poter essere segnata «Ok stampa»**. Se provi a farlo su una pagina senza anteprima compare un messaggio d'errore e lo stato non cambia. Carica prima il file sul contenuto: vedi [Caricare un PDF o un'immagine](manuale:caricare-file).

:::dettagli
- Per cambiare lo stato di tante pagine insieme usa la [selezione](manuale:selezione-multipla).
- Su una [pagina bloccata](manuale:bloccare-pagina) lo stato non si cambia.
- Le pagine «Revisionata» o «Ok stampa» ancora senza anteprima compaiono negli [avvisi](manuale:avvisi).
- Il colore di ogni stato è nella [Legenda](manuale:legenda).
:::

## Vedi anche

- [Selezionare più pagine](manuale:selezione-multipla)
- [Avvisi automatici](manuale:avvisi)
- [Legenda](manuale:legenda)
