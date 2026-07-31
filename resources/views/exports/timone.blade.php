<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Timone — {{ $issue->title }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #1f2937; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        p.subtitle { color: #6b7280; margin-top: 0; margin-bottom: 16px; }
        table.page-grid { width: 100%; border-collapse: separate; border-spacing: 4px; table-layout: fixed; }
        td.page-cell { width: 12.5%; vertical-align: top; border-width: 2px; border-style: solid; border-radius: 3px; padding: 4px; }
        td.page-cell.empty-cell { border: none; }
        .position { font-weight: bold; }
        .status-badge { display: inline-block; padding: 1px 4px; border-radius: 2px; font-size: 7px; float: right; }
        .content-line { margin-top: 3px; line-height: 1.3; }
        .thumb { width: 100%; margin-top: 3px; border: 1px solid #d1d5db; }
        .empty { font-style: italic; color: #9ca3af; margin-top: 3px; }
        .meta { color: #9ca3af; font-size: 8px; margin-top: 30px; }
        .legend { margin-bottom: 14px; }
        .legend-group { margin-bottom: 4px; }
        .legend-label { color: #6b7280; font-size: 8px; margin-right: 4px; }
        .legend-swatch { display: inline-block; padding: 1px 6px; border-radius: 2px; font-size: 8px; margin-right: 4px; }
    </style>
</head>
<body>
    {{--
        Fase 4 (§4): un solo posto per i colori (App\Enums\PageContentType/
        PageStatus, metodi hexColors()) invece della copia scritta a mano
        che c'era prima qui — quella copia era "scelta per corrispondere il
        più possibile" alle classi Tailwind, cioè poteva andare fuori
        sincrono ad ogni ritocco della palette senza che nessuno se ne
        accorgesse (Dompdf non esegue Tailwind, quindi non può leggere le
        classi CSS: serve comunque una rappresentazione esadecimale a
        parte, ma ora è la STESSA in entrambi i punti, non due copie
        indipendenti).
    --}}

    <h1>{{ $issue->magazine->name }} — {{ $issue->title }}</h1>
    <p class="subtitle">
        Timone completo &middot; generato il {{ now()->translatedFormat('d M Y, H:i') }}
        &middot; {{ $totalPages }} pagine
        @if ($filtersApplied)
            &middot; filtro attivo, non tutte le pagine del numero sono mostrate
        @endif
    </p>

    <div class="legend">
        <div class="legend-group">
            <span class="legend-label">Tipo pagina:</span>
            @foreach (\App\Enums\PageContentType::cases() as $type)
                @php $hex = $type->hexColors(); @endphp
                <span class="legend-swatch" style="background-color: {{ $hex['bg'] }}; color: {{ $hex['text'] }};">{{ $type->label() }}</span>
            @endforeach
        </div>
        <div class="legend-group">
            <span class="legend-label">Stato pagina:</span>
            @foreach (\App\Enums\PageStatus::cases() as $status)
                @php $hex = $status->hexColors(); @endphp
                <span class="legend-swatch" style="background-color: {{ $hex['bg'] }}; color: {{ $hex['text'] }}; border: 1px solid {{ $hex['border'] }};">{{ $status->label() }}</span>
            @endforeach
        </div>
    </div>

    @if ($totalPages === 0)
        <p class="empty">Nessuna pagina corrisponde ai filtri scelti.</p>
    @endif

    <table class="page-grid">
        @foreach ($pageRows as $row)
            <tr>
                @foreach ($row as $page)
                    @php
                        $typeColor = $page->content_type->hexColors();
                        $statusColor = $page->status->hexColors();
                        $latestFile = $page->files->first();
                    @endphp
                    <td class="page-cell" style="background-color: {{ $typeColor['bg'] }}; border-color: {{ $statusColor['border'] }};">
                        <span class="position" style="color: {{ $typeColor['text'] }};">{{ $page->position }}</span>
                        <span class="status-badge" style="background-color: {{ $statusColor['bg'] }}; color: {{ $statusColor['text'] }};">
                            {{ $page->status->label() }}
                        </span>

                        @if ($withThumbnails && $latestFile && $latestFile->thumbnail_status->value === 'ready' && \Illuminate\Support\Facades\Storage::disk($latestFile->disk)->exists($latestFile->thumbnail_path))
                            <img
                                class="thumb"
                                src="data:image/png;base64,{{ base64_encode(\Illuminate\Support\Facades\Storage::disk($latestFile->disk)->get($latestFile->thumbnail_path)) }}"
                                alt=""
                            />
                        @endif

                        {{--
                            Niente emoji qui a differenza dell'interfaccia
                            HTML: il font di default di Dompdf (Helvetica)
                            non ha i glifi, renderizza un riquadro "?" al
                            loro posto — scoperto rasterizzando davvero il
                            PDF generato con pdftoppm e guardandolo, non
                            solo controllando che il download avesse lo
                            status/content-type giusti.
                        --}}
                        @forelse ($page->contents as $content)
                            <p class="content-line">
                                [{{ $content->type->value === 'articolo' ? 'Art' : 'Pub' }}] {{ $content->displayLabel() }}
                                ({{ $content->pivot->occupied_percentage }}%)
                            </p>
                        @empty
                            <p class="empty">{{ $page->content_type->value === 'bianca' ? 'Pagina bianca' : 'Da assegnare' }}</p>
                        @endforelse
                    </td>
                @endforeach
                {{-- Celle vuote per completare l'ultima riga: senza,
                     table-layout:fixed distribuirebbe la larghezza in modo
                     diverso tra righe con meno di 8 celle. --}}
                @for ($i = $row->count(); $i < 8; $i++)
                    <td class="page-cell empty-cell"></td>
                @endfor
            </tr>
        @endforeach
    </table>

    <p class="meta">Timone Elettronico &middot; {{ $issue->magazine->name }}</p>
</body>
</html>
