---
titolo: Problemi frequenti
slug: problemi-frequenti
categoria: Riferimento
ruoli: [admin, redattore, commerciale, sola_lettura]
parole_chiave: [errore, messaggio, problema, non funziona, rifiutato, non posso, avviso]
sinonimi: [aiuto, bug, non va, bloccato, errore rosso, cosa significa]
ordine: 730
---

:::in-breve
Hai visto un messaggio e non sai cosa fare? Cercalo qui sotto: per ognuno trovi cosa significa e cosa fare.
:::

## Pagine e spostamenti

:::problema «Non posso annullare: la pagina 12 è stata modificata da …»
**Significa**: dopo la tua azione un collega ha cambiato le stesse pagine o lo stesso contenuto, oppure lo spazio dove tornerebbe il contenuto è stato occupato.
**Cosa fare**: niente è cambiato. Sistema a mano; il passo resta «non annullabile». Vedi [Annullare e ripetere](manuale:annullare-ripetere).
:::

:::problema Lo spostamento di una pagina viene rifiutato con un avviso
**Significa**: un collega ha spostato o scambiato delle pagine e la tua vista non era ancora aggiornata, oppure la pagina è bloccata.
**Cosa fare**: la vista si allinea da sola: riprova. Se la pagina è bloccata (🔒), sbloccala. Vedi [Spostare le pagine](manuale:spostare-pagine).
:::

:::problema «⚠ Oltre capienza» in rosso sulla pagina
**Significa**: i contenuti della pagina, sommati, superano una pagina intera. Succede con dati vecchi convertiti.
**Cosa fare**: riduci a mano lo spazio di uno dei contenuti, dal menu accanto al contenuto. Vedi [Spazio occupato](manuale:spazio-occupato).
:::

:::problema «Seleziona almeno 2 pagine da collegare» o «Sono già collegate in un gruppo»
**Significa**: l'azione della selezione non si può fare con le pagine scelte. Il bottone è attenuato.
**Cosa fare**: cambia la selezione. Vedi [Selezionare più pagine](manuale:selezione-multipla).
:::

:::problema «Solo la redazione può spostare questo contenuto»
**Significa**: sei Commerciale e il contenuto è un articolo (o la pagina contiene articoli).
**Cosa fare**: chiedi a un redattore. Vedi [Chi può fare cosa](manuale:ruoli).
:::

## Contenuti e spazio

:::problema «richiede 1/2 di pagina, ma resta libero solo 1/3 di pagina»
**Significa**: il contenuto non ci sta. Nessuna pagina è stata modificata.
**Cosa fare**: scegli un'altra pagina di partenza o libera spazio. Vedi [Mettere un contenuto su una pagina](manuale:assegnare-contenuto).
:::

:::problema «Sulla pagina ci sono già «Acme» (1/2) e «Beta» (1/4): manca 1/4 di pagina.»
**Significa**: nella posizione scelta nel modulo non c'è spazio, quindi il contenuto non viene creato.
**Cosa fare**: scegli un'altra posizione o lascia «Da assegnare». Vedi [Creare un articolo o una pubblicità](manuale:creare-contenuto).
:::

:::problema «Crea contenuto» segnala un errore sotto «Pagine intere» / «Frazione»
**Significa**: manca l'occupazione: almeno uno dei due campi deve essere più di zero.
**Cosa fare**: indica quanto spazio occupa. Vedi [Spazio occupato](manuale:spazio-occupato).
:::

## File e PDF

:::problema «Il file non è arrivato al server…»
**Significa**: il caricamento è stato rifiutato prima ancora di controllare il file.
**Cosa fare**: riprova. Se si ripete avvisa l'amministratore indicando l'ora del tentativo.
:::

:::problema Messaggio rosso sotto il riquadro del file
**Significa**: il file è del tipo sbagliato o troppo grande. Per la dimensione il messaggio dice quanto pesa e qual è il massimo.
**Cosa fare**: usa un PDF o un'immagine JPG, PNG, WEBP entro il limite indicato. Vedi [Caricare un PDF o un'immagine](manuale:caricare-file).
:::

:::problema Il PDF viene rifiutato perché illeggibile
**Significa**: il PDF è danneggiato o protetto.
**Cosa fare**: esporta di nuovo il PDF senza protezione e ricaricalo.
:::

:::problema ⚠️ PDF rossa sulla card
**Significa**: la generazione dell'anteprima è fallita; il file probabilmente è danneggiato o non è un PDF valido.
**Cosa fare**: allega un file diverso da [Modifica contenuto](manuale:modificare-contenuto), oppure crea un nuovo contenuto con un altro file.
:::

:::problema «⚠️ formato» rosso
**Significa**: le dimensioni del PDF non corrispondono al formato della pubblicità. Non blocca nulla.
**Cosa fare**: fai rifare il file, oppure clicca «accetta» se va bene comunque.
:::

:::problema Avviso giallo dopo «📎 Carica materiale»
**Significa**: sulla pagina non c'era più spazio: il contenuto è creato ma non assegnato lì.
**Cosa fare**: lo trovi nel pannello Contenuti; mettilo su un'altra pagina.
:::

:::problema Non riesco a mettere «Ok stampa»
**Significa**: la pagina non ha ancora un'anteprima dal contenuto assegnato.
**Cosa fare**: carica il PDF sul contenuto. Vedi [Cambiare lo stato di una pagina](manuale:stato-pagina).
:::

## Numero e peso

:::problema «Parametri peso da configurare»
**Significa**: mancano dei valori, di solito il formato o le grammature. Succede per i numeri creati prima del calcolo del peso.
**Cosa fare**: inseriscili con «Modifica parametri». Vedi [Peso della rivista](manuale:peso).
:::

:::problema «Grammatura … fuori dall'intervallo ammesso» o «Formato … fuori dall'intervallo ammesso»
**Significa**: sul numero è salvato un valore con l'unità sbagliata: il peso di una pagina (per esempio 0,8) invece della grammatura (80), oppure il formato in centimetri (21) invece che in millimetri (210).
**Cosa fare**: correggilo con «Modifica parametri».
:::

:::problema Messaggio rosso: pagine interne dispari
**Significa**: un foglio ha due facciate, quindi il peso non si calcola con un numero dispari di pagine.
**Cosa fare**: porta le pagine a un numero pari. Vedi [Aggiungere o togliere pagine](manuale:pagine-interne).
:::

:::problema «⚠️ 126 non è multiplo di 4: non stampabile così»
**Significa**: il totale delle pagine non forma quartini interi.
**Cosa fare**: aggiungi o togli pagine come suggerito dopo la freccia. Vedi [Segnature](manuale:segnature).
:::

:::problema Errore rosso nelle «Pubblicità previste»: non ci stanno
**Significa**: le pubblicità previste non entrano nelle pagine del numero.
**Cosa fare**: riduci le pubblicità o aumenta le pagine; finché non ci stanno il numero non viene creato.
:::

:::problema Il numero non si chiude
**Significa**: c'è almeno una pubblicità non ancora «Completo»; il messaggio elenca i clienti.
**Cosa fare**: aspetta il materiale o elimina la prenotazione. Vedi [Chiudere un numero](manuale:chiudere-numero).
:::

:::problema «Il numero che avevi aperto è stato eliminato da un amministratore»
**Significa**: un amministratore ha eliminato il numero mentre lo avevi aperto.
**Cosa fare**: sei tornato all'elenco delle riviste; il numero non esiste più.
:::

## Aggiornamenti e manuale

:::problema Avviso giallo fisso sugli aggiornamenti in tempo reale
**Significa**: per qualche minuto non arrivano aggiornamenti in tempo reale, di solito per la rete.
**Cosa fare**: niente: la pagina controlla da sola se c'è qualcosa di nuovo e si aggiorna allo stesso modo, con un ritardo massimo di un secondo.
:::

:::problema «Il manuale utente non è disponibile»
**Significa**: il manuale non è stato trovato sul server.
**Cosa fare**: avvisa chi gestisce l'installazione.
:::

## Vedi anche

- [Annullare e ripetere](manuale:annullare-ripetere)
- [Caricare un PDF o un'immagine](manuale:caricare-file)
- [Chi può fare cosa](manuale:ruoli)
- [Note per l'amministratore](manuale:note-amministratore)
