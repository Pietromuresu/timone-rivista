<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Timone — {{ $issue->title }}</title>
    {{--
        Export PDF del timone (dompdf). Restyling del 2026-10-27 su richiesta
        dell'utente ("è terribile, rendilo bello"): un foglio da appendere in
        redazione, come il timone cartaceo.
        - Pagine tutte della stessa misura, con le proporzioni del formato
          del numero ($layoutWidthPt × $layoutHeightPt), affiancate in doppie
          pagine come si sfoglia la rivista (pagina 1 da sola a destra);
          con un filtro attivo, una pagina per casella.
        - Dentro ogni pagina la disposizione dei contenuti della card
          (App\Support\ExportPageLayout ← Page::layout()), con la miniatura di
          ciascun contenuto se richiesta e il nome dentro il blocco
          (partials/timone-sheet).
        - Stato della pagina come pallino colorato accanto al numero
          (PageStatus::hexColors(), unica fonte dei colori).
        - In testa i numeri della barra di riepilogo del timone
          (App\Support\IssueSummary), così il foglio stampato dice le stesse
          cose dello schermo.
        Solo misure assolute (pt) e tabelle: è ciò che dompdf impagina in
        modo affidabile. Niente emoji: il font PDF non le ha.
    --}}
    <style>
        @page { margin: 22pt 24pt 26pt 24pt; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 7pt; color: #1f2937; margin: 0; }

        .head { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
        .head td { vertical-align: bottom; padding: 0; }
        .magazine { font-size: 7pt; letter-spacing: 1.2pt; text-transform: uppercase; color: #6b7280; }
        .issue-title { font-size: 17pt; font-weight: bold; color: #111827; margin-top: 1pt; }
        .generated { text-align: right; color: #6b7280; font-size: 7pt; line-height: 1.5; }
        .filter-note { color: #b45309; }

        .stats { width: 100%; border-collapse: separate; border-spacing: 4pt 0; margin-bottom: 10pt; }
        .stats td { background-color: #f3f4f6; border-radius: 3pt; padding: 4pt 6pt; vertical-align: top; }
        .stat-value { font-size: 11pt; font-weight: bold; color: #111827; }
        .stat-label { font-size: 6pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.4pt; }
        .stat-sub { font-size: 6.5pt; color: #4b5563; }

        h2 { font-size: 8pt; text-transform: uppercase; letter-spacing: 1pt; color: #6b7280; font-weight: bold; margin: 0 0 4pt 0; padding-bottom: 2pt; border-bottom: 0.5pt solid #e5e7eb; }

        table.spreads { border-collapse: separate; border-spacing: 0 6pt; margin-bottom: 4pt; }
        tr.spread-row { page-break-inside: avoid; }
        td.spread { padding: 0 6pt 0 0; vertical-align: top; }
        table.spread-pages { border-collapse: collapse; }
        td.slot { padding: 0; vertical-align: top; }

        .page-head { height: 9pt; font-size: 6.5pt; color: #374151; white-space: nowrap; }
        .page-num { font-weight: bold; font-size: 7.5pt; color: #111827; }
        .dot { display: inline-block; width: 4.5pt; height: 4.5pt; border-radius: 2.25pt; margin-right: 1.5pt; }
        .sheet { position: relative; overflow: hidden; background-color: #ffffff; border: 0.6pt solid #9ca3af; }
        /* Una sola linea di piega tra le due pagine di un'apertura: due bordi
           da 0.3pt sovrapposti sparivano in stampa/rasterizzazione. */
        .sheet.left { border-right: 0.6pt solid #9ca3af; }
        .sheet.right { border-left: none; }
        .sheet.locked { border-color: #4b5563; border-width: 1pt; }
        .sheet.cover { border-color: #818cf8; border-width: 0.8pt; }
        .blank { position: absolute; left: 0; right: 0; top: 42%; text-align: center; color: #d1d5db; font-size: 6pt; letter-spacing: 0.5pt; text-transform: uppercase; }

        .block { position: absolute; overflow: hidden; }
        .block img { position: absolute; left: 0; top: 0; width: 100%; height: 100%; }
        .block-text { position: absolute; left: 1.5pt; right: 1.5pt; top: 1.2pt; font-size: 5.2pt; line-height: 1.2; color: #1f2937; }
        .block-caption { position: absolute; left: 0; right: 0; bottom: 0; background-color: #ffffff; color: #111827; font-size: 4.8pt; line-height: 1.15; padding: 0.6pt 1.2pt; }
        .fraction { color: #6b7280; }
        .placeholder-tag { font-size: 4.4pt; font-weight: bold; color: #b45309; text-transform: uppercase; }
        .free { position: absolute; border: 0.4pt dashed #cbd5e1; }

        .covers-row td.cover-slot { padding: 0 10pt 0 0; vertical-align: top; }
        .cover-label { font-weight: bold; color: #4f46e5; font-size: 6.5pt; }

        .legend { margin-top: 6pt; color: #4b5563; font-size: 6.5pt; }
        .legend .item { margin-right: 9pt; white-space: nowrap; }
        .swatch { display: inline-block; width: 9pt; height: 6pt; vertical-align: middle; margin-right: 2pt; border: 0.4pt solid #9ca3af; }

        .footer { position: fixed; bottom: -16pt; left: 0; right: 0; font-size: 6pt; color: #9ca3af; }
    </style>
</head>
<body>
    @php
        // Misure della pagina disegnata: le passa TimoneExportController
        // (proporzioni del formato del numero); default se la vista è
        // renderizzata da sola (un test lo fa).
        $layoutWidthPt ??= 60;
        $layoutHeightPt ??= 80;
        $summary ??= null;
        $spreadRows ??= [];
        $articleFill = '#e0f2fe';
        $adFill = '#fef3c7';
        // Caratteri che stanno su una riga di un blocco largo $widthPt (font 5pt).
        $lineChars = fn (float $widthPt) => max(4, (int) floor($widthPt / 2.55));
        // Una pagina disegnata (partials/timone-sheet).
        $renderSheet = function ($page, string $side, string $title) use ($layoutWidthPt, $layoutHeightPt, $withThumbnails, $articleFill, $adFill, $lineChars) {
            $status = $page->status->hexColors();
            $layout = \App\Support\ExportPageLayout::forPage($page, $layoutWidthPt, $layoutHeightPt, $withThumbnails);
            $legacyThumb = $layout === null && $withThumbnails ? \App\Support\ExportThumbnail::dataUri($page->previewFile()) : null;

            return view('exports.partials.timone-sheet', compact('page', 'side', 'title', 'status', 'layout', 'legacyThumb', 'layoutWidthPt', 'layoutHeightPt', 'articleFill', 'adFill', 'lineChars'))->render();
        };
    @endphp

    <div class="footer">
        <table style="width: 100%; border-collapse: collapse;"><tr>
            <td>Timone Elettronico &middot; {{ $issue->magazine->name }} &middot; {{ $issue->title }}</td>
            {{-- "Foglio N di M" lo scrive TimoneExportController sul canvas di
                 dompdf, che conosce il totale solo dopo l'impaginazione
                 (counter(pages) in CSS qui dava sempre 0). --}}
            <td></td>
        </tr></table>
    </div>

    <table class="head">
        <tr>
            <td>
                <div class="magazine">{{ $issue->magazine->name }}</div>
                <div class="issue-title">{{ $issue->title }}</div>
            </td>
            <td class="generated">
                Timone &middot; {{ $totalPages }} pagine interne{{ $filtersApplied ? ' mostrate' : '' }}<br>
                @if ($issue->issue_date)
                    Uscita {{ $issue->issue_date->translatedFormat('d F Y') }}<br>
                @endif
                Generato il {{ now()->translatedFormat('d M Y, H:i') }}
                @if ($filtersApplied)
                    <br><span class="filter-note">Filtro attivo: non tutte le pagine del numero sono mostrate</span>
                @endif
            </td>
        </tr>
    </table>

    @if ($summary)
        <table class="stats">
            <tr>
                <td>
                    <div class="stat-label">Pagine interne</div>
                    <div class="stat-value">{{ $summary['internalPages'] }}</div>
                    <div class="stat-sub">{{ $summary['fullPages'] }} piene &middot; {{ $summary['partialPages'] }} parziali &middot; {{ $summary['blankPages'] }} bianche</div>
                </td>
                <td>
                    <div class="stat-label">Spazio libero</div>
                    <div class="stat-value">{{ $summary['freeLabel'] }}</div>
                </td>
                <td>
                    <div class="stat-label">Pubblicità</div>
                    <div class="stat-value">{{ number_format($summary['adPercentage'], 2, ',', '.') }}%</div>
                    <div class="stat-sub">delle pagine interne</div>
                </td>
                <td>
                    <div class="stat-label">Da assegnare</div>
                    <div class="stat-value">{{ $summary['placeholderCount'] }}</div>
                    <div class="stat-sub">
                        {{ collect($summary['placeholderByFormat'])->map(fn ($n, $format) => \App\Enums\AdFormat::from($format)->label().' '.$n)->implode(', ') ?: 'nessun segnaposto' }}
                    </div>
                </td>
                <td>
                    <div class="stat-label">Copertine</div>
                    <div class="stat-value">{{ $summary['coversAssigned'] }}/{{ $summary['coversTotal'] }}</div>
                    <div class="stat-sub">assegnate</div>
                </td>
                <td>
                    <div class="stat-label">Peso copia</div>
                    @if ($summary['weight']['complete'])
                        <div class="stat-value">{{ \App\Support\Weight\WeightFormatter::grams($summary['weight']['copyGrams']) }} g</div>
                        <div class="stat-sub">{{ $summary['weight']['outOfBracket'] ? 'fuori scaglione' : 'scaglione '.\App\Support\Weight\WeightFormatter::bracket($summary['weight']['bracket']) }}</div>
                    @else
                        <div class="stat-value">&mdash;</div>
                        <div class="stat-sub">parametri incompleti</div>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    @if ($coverPages->isNotEmpty())
        <h2>Copertine</h2>
        <table class="spreads">
            <tr class="spread-row covers-row">
                @foreach ($coverPages as $page)
                    <td class="cover-slot">{!! $renderSheet($page, 'cover', $page->cover_slot->label()) !!}</td>
                @endforeach
            </tr>
        </table>
    @endif

    <h2>Pagine interne</h2>
    @if ($spreadRows === [])
        <p style="color: #9ca3af;">Nessuna pagina da mostrare.</p>
    @endif
    <table class="spreads">
        @foreach ($spreadRows as $row)
            <tr class="spread-row">
                @foreach ($row as $slot)
                    <td class="spread">
                        <table class="spread-pages">
                            <tr>
                                @foreach ($slot as $index => $page)
                                    <td class="slot" style="width: {{ $layoutWidthPt }}pt;">
                                        @if ($page)
                                            {!! $renderSheet($page, count($slot) === 2 ? ($index === 0 ? 'left' : 'right') : 'single', (string) $page->position) !!}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        </table>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <div class="legend">
        <span class="item"><span class="swatch" style="background-color: {{ $articleFill }};"></span>Articolo</span>
        <span class="item"><span class="swatch" style="background-color: {{ $adFill }};"></span>Pubblicità</span>
        <span class="item"><span class="swatch" style="background-color: {{ $adFill }}; border: 0.6pt dashed #d97706;"></span>Da assegnare</span>
        <span class="item"><span class="swatch" style="background-color: #ffffff; border: 0.5pt dashed #cbd5e1;"></span>Spazio libero</span>
        &nbsp;&nbsp;Stato:
        @foreach (\App\Enums\PageStatus::cases() as $status)
            <span class="item"><span class="dot" style="background-color: {{ $status->hexColors()['border'] }};"></span>{{ $status->label() }}</span>
        @endforeach
    </div>
</body>
</html>
