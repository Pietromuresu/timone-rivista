---
titolo: Peso della rivista
slug: peso
categoria: Numero e rivista
ruoli: [admin, redattore, commerciale, sola_lettura]
parole_chiave: [peso, peso copia, scaglione, grammatura, formato, tiratura, maggiorazione, bancali, spedizione]
sinonimi: [quanto pesa, grammi, carta, spese postali, poste, peso reale]
ordine: 530
---

:::in-breve
Il riquadro **«⚖️ Peso»**, in cima alla pagina del numero, mostra il **peso teorico di una copia**, il **margine d'errore** (± grammi), lo **scaglione** di spedizione e il peso della **tiratura**.

1. Leggi il peso, per esempio «510,7 g ± 31 g (481 – 541 g)».
2. Guarda il colore dello scaglione: verde, giallo o rosso.
3. Per cambiare carta o formato di questo numero clicca **«Modifica parametri»**.
:::

:::screenshot peso
alt: Il riquadro «⚖️ Peso» del numero di esempio: peso di una copia, scaglione, dettaglio del calcolo e tiratura.
1 | 2% | 17.8% | **Peso di una copia**, con il margine d'errore.
2 | 2% | 23.6% | Lo **scaglione** di spedizione.
3 | 2% | 55.3% | La **tiratura**: peso di tutte le copie, con i bancali.
4 | 86.2% | 5.8% | **Modifica parametri**: carta, formato e copie di questo numero.
5 | 3% | 79.6% | **Simula**: quante pagine puoi aggiungere restando nello scaglione.
:::

## Peso copia e scaglione

- **Peso copia**: per esempio «510,7 g ± 31 g (481 – 541 g)». Il peso è teorico: la carta reale e il taglio non sono mai esatti, e il «±» è lo scostamento massimo nel caso peggiore. Il riquadro «ℹ️ Come è calcolato il margine» elenca le tolleranze usate.
- **Scaglione**: badge **verde** se la copia resta nello scaglione anche nel caso peggiore; **giallo** se il margine potrebbe farla passare allo scaglione successivo («Rischio di passare allo scaglione X g»); **rosso** se supera 1000 g (fuori scaglione).
- Gli scaglioni sono 250, 350, 500 e 1000 g. Il limite è incluso: 500,0 g sta nello scaglione 500.

:::dettagli
- Le formule sono le stesse del foglio Excel usato finora.
- **Interno / Copertina / Maggiorazione / Tiratura**: il dettaglio del calcolo. Le **pagine interne** sono quelle del timone (per esempio 208). Le **pagine di copertina** sono le copertine che esistono: 4, più 2 per ogni battente (6 o 8).
- Quando cambi le pagine interne o aggiungi o togli un battente, il peso si aggiorna da solo.
:::

## Modificare i parametri di questo numero

Admin e Redattore: bottone **«Modifica parametri»**.

1. In cima c'è la sezione **«Carta»**: grammatura interno, grammatura copertina e pagine copertina.
2. **Grammatura**: scrivi quella stampata sulla carta, in g/m² (per esempio **80** per l'interno, **170** per la copertina), **non il peso di una pagina**. Il peso a pagina lo calcola il sistema e lo mostra sotto il campo.
3. Formato in **millimetri** (210 × 270, non 21 × 27), n. copie, n. bancali.
4. L'**anteprima** in fondo alla finestra mostra il nuovo peso, il margine e lo scaglione **prima di salvare**.
5. **«Salva» modifica solo questo numero**, mai i valori della rivista.

:::dettagli
- Per un numero già creato le **pagine copertina non si scrivono a mano** (campo grigio): sono le copertine esistenti, 4, 6 o 8 con i battenti.
- Il peso a pagina sotto la grammatura, per esempio «80 g/m² → 2,268 g a pagina · 208 pagine = 471,7 g».
- Grammature sotto 30 o sopra 400 vengono rifiutate con un messaggio. Puoi usare la virgola o il punto (`80,5` o `80.5`).
- Un formato sotto 50 viene rifiutato perché quasi certamente è in centimetri.
- Peso reale, maggiorazione e tolleranze stanno sotto «Avanzate».
- L'anteprima mostra la differenza dal peso salvato, per esempio «+12,4 g rispetto al valore salvato …, passa da scaglione 500 g a 1.000 g.».
- I valori diversi da quelli della rivista hanno il badge **«modificato»**. **«Ripristina default rivista»** li riporta a quelli della rivista; poi premi comunque «Salva».
- Le pagine interne devono essere un numero **pari**, perché un foglio ha due facciate: un numero dispari blocca il calcolo con un messaggio rosso. Se non sono un multiplo di 4 compare solo un avviso.
- Ogni modifica resta nella [cronologia](manuale:storico-e-cronologia). Le modifiche ai parametri del peso non si annullano con Ctrl+Z.
:::

## Peso reale dopo la stampa

1. Pesa una copia e scrivi il valore in «Peso reale misurato (g)».
2. Il riquadro mostra la differenza dal teorico e la **maggiorazione** che avrebbe dato il peso esatto.
3. Un Admin può salvarla con **«Copia nei default della rivista»**, dopo una conferma. Vale solo per i numeri creati da quel momento.

Il suggerimento non viene mai applicato da solo.

## Simulazione

Nel campo «Simula +/- pagine interne» scrivi per esempio `8` o `-4` per vedere peso e scaglione con quelle pagine in più o in meno. Non modifica nulla. Sotto, il riquadro indica quante pagine puoi ancora aggiungere restando nello scaglione attuale, a passi di 2 pagine.

## Da dove vengono i valori

I valori di partenza vengono dalla rivista ([Creare e modificare una rivista](manuale:rivista)) e si possono cambiare già quando crei il numero, nella sezione «Carta e peso» ([Creare un numero](manuale:creare-numero)).

## Vedi anche

- [Problemi frequenti](manuale:problemi-frequenti) (messaggi del pannello Peso)
- [Le copertine e i battenti](manuale:copertine)
- [Creare e modificare una rivista](manuale:rivista)
