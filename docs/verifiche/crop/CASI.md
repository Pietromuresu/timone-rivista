# Verifica del ritaglio delle miniature

Generato da `node scripts/verifica-crop.mjs` con i PDF di prova di `tests/Support/CropFixtures.php` (sesti colorati: 1 rosso, 2 arancio, 3 giallo, 4 verde, 5 blu, 6 viola).

| Numero | Pagina | Caso | Cosa si deve vedere |
|---|---|---|---|
| A4 | 1 | mezza-verticale-sinistra | sesti 1, 3, 5 |
| A4 | 2 | mezza-verticale-destra | sesti 2, 4, 6 |
| A4 | 3 | mezza-orizzontale-sopra | sesti 1, 2 e metà alta di 3, 4 |
| A4 | 4 | mezza-orizzontale-sotto | metà bassa di 3, 4 e sesti 5, 6 |
| A4 | 5 | terzo-verticale | striscia sinistra (2/3 della larghezza) di 1, 3, 5 |
| A4 | 6 | piedino | sesti 5, 6 |
| A4 | 7 | vetrine-quattro-angoli | in ogni angolo il suo quarto |
| A4 | 8 | due-terzi | due terzi sinistri: 1, 3, 5 e parte di 2, 4, 6 |
| A4 | 9 | forma-a-L | 1, 2, 3, 5 e metà di 4 e 6; vetrina vuota in basso a destra |
| A4 | 10 | tre-pagine-1 | pagina 1·x intera |
| A4 | 11 | tre-pagine-2 | pagina 2·x intera |
| A4 | 12 | tre-pagine-3 | pagina 3·x, metà superiore: 3·1, 3·2 e metà di 3·3, 3·4 |
| A4 | 13 | gia-nel-formato | il file verde «MEZZA» intero |
| A4 | 14 | abbondanza | come mezza verticale sinistra, senza il bordo dell'abbondanza |
| 210×270 | 1 | a4-su-210x270 | metà sinistra dell'A4, centrata in altezza |

File: `card-<numero>-<pagina>-<caso>.png` (timone), `export-timone-<numero>-<foglio>.png` (export del timone, tutte le pagine), `sfogliabile-<numero>-<pagina>-<caso>.png` (PDF sfogliabile).
