{{-- Una pagina del timone nell'export PDF (vedi exports/timone.blade.php):
     numero/etichetta e pallino di stato sopra, poi la pagina disegnata con
     i blocchi dei contenuti (posizioni in pt da App\Support\ExportPageLayout).
     Il testo nei blocchi è accorciato in PHP ($lineChars): dompdf non
     gestisce text-overflow. --}}
<div class="page-head">
    <span class="dot" style="background-color: {{ $status['border'] }};"></span><span class="{{ $side === 'cover' ? 'cover-label' : 'page-num' }}">{{ $title }}</span>
    @if ($page->isLocked())
        <span style="color: #6b7280;">&middot; bloccata</span>
    @endif
</div>
<div class="sheet {{ $side }} {{ $page->isLocked() ? 'locked' : '' }}" style="width: {{ $layoutWidthPt }}pt; height: {{ $layoutHeightPt }}pt;">
    @if ($layout)
        @foreach ($layout['blocks'] as $block)
            @php
                $fill = $block['isAd'] ? $adFill : $articleFill;
                // Segnaposto: il titolo automatico ripeterebbe l'etichetta
                // "Da assegnare" («Pubblicità 1/2 — da assegnare»).
                $label = $block['placeholder'] ? \Illuminate\Support\Str::before($block['label'], \App\Support\AdPlaceholder::TITLE_SUFFIX) : $block['label'];
                $border = $block['placeholder'] ? 'border: 0.7pt dashed #d97706;' : 'border: 0.4pt solid #ffffff;';
            @endphp
            @foreach ($block['extra'] as $piece)
                <div class="block" style="left: {{ $piece['x'] }}pt; top: {{ $piece['y'] }}pt; width: {{ $piece['w'] }}pt; height: {{ $piece['h'] }}pt; background-color: {{ $fill }}; {{ $border }}"></div>
            @endforeach
            <div class="block" style="left: {{ $block['x'] }}pt; top: {{ $block['y'] }}pt; width: {{ $block['w'] }}pt; height: {{ $block['h'] }}pt; background-color: {{ $fill }}; {{ $border }}">
                @if ($block['src'])
                    <img src="{{ $block['src'] }}" alt="" />
                    @if ($block['h'] >= 12)
                        <div class="block-caption">{{ \Illuminate\Support\Str::limit($label, $lineChars($block['w']), '…') }}</div>
                    @endif
                @elseif ($block['h'] >= 7)
                    <div class="block-text">
                        @if ($block['placeholder'])
                            <span class="placeholder-tag">Da assegnare</span><br>
                        @endif
                        {{ \Illuminate\Support\Str::limit($label, $lineChars($block['w']) * max(1, (int) floor(($block['h'] - 3) / 6.5)), '…') }}
                        @if ($block['h'] >= 16)
                            <br><span class="fraction">{{ $block['fraction'] === '1' ? 'pagina intera' : $block['fraction'] }}</span>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
        @foreach ($layout['free'] as $free)
            <div class="free" style="left: {{ $free['x'] }}pt; top: {{ $free['y'] }}pt; width: {{ $free['w'] }}pt; height: {{ $free['h'] }}pt;"></div>
        @endforeach
    @elseif ($legacyThumb)
        <img src="{{ $legacyThumb }}" alt="" style="width: 100%; height: 100%;" />
    @else
        <div class="blank">bianca</div>
    @endif
</div>
