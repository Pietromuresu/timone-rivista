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
        td.page-cell { width: 12.5%; vertical-align: top; border: 1px solid #9ca3af; border-radius: 3px; padding: 4px; }
        td.page-cell.empty-cell { border: none; }
        .position { font-weight: bold; }
        .status-badge { display: inline-block; padding: 1px 4px; border-radius: 2px; font-size: 7px; float: right; }
        .content-line { margin-top: 3px; line-height: 1.3; }
        .thumb { width: 100%; margin-top: 3px; border: 1px solid #d1d5db; }
        .empty { font-style: italic; color: #9ca3af; margin-top: 3px; }
        .meta { color: #9ca3af; font-size: 8px; margin-top: 30px; }
    </style>
</head>
<body>
    @php
        // Dompdf non esegue Tailwind (nessun build step lato server): stessa
        // scelta già presa in exports/ad-dashboard.blade.php, colori scritti
        // a mano qui invece di generare classi CSS che non verrebbero mai
        // risolte. Valori scelti per corrispondere il più possibile alle
        // classi Tailwind già usate nell'interfaccia (colorClasses() sugli
        // enum PageContentType/PageStatus).
        $typeColors = [
            'editoriale' => ['bg' => '#dbeafe', 'text' => '#1e40af'],
            'pubblicita' => ['bg' => '#fef3c7', 'text' => '#92400e'],
            'mista' => ['bg' => '#f3e8ff', 'text' => '#6b21a8'],
            'bianca' => ['bg' => '#f3f4f6', 'text' => '#6b7280'],
        ];
        $statusColors = [
            'da_assegnare' => ['bg' => '#f3f4f6', 'text' => '#4b5563'],
            'assegnata' => ['bg' => '#e0f2fe', 'text' => '#0369a1'],
            'in_bozza' => ['bg' => '#fef9c3', 'text' => '#a16207'],
            'revisionata' => ['bg' => '#ffedd5', 'text' => '#c2410c'],
            'ok_stampa' => ['bg' => '#dcfce7', 'text' => '#15803d'],
        ];
    @endphp

    <h1>{{ $issue->magazine->name }} — {{ $issue->title }}</h1>
    <p class="subtitle">
        Timone completo &middot; generato il {{ now()->translatedFormat('d M Y, H:i') }}
        &middot; {{ $totalPages }} pagine
        @if ($filtersApplied)
            &middot; filtro attivo, non tutte le pagine del numero sono mostrate
        @endif
    </p>

    @if ($totalPages === 0)
        <p class="empty">Nessuna pagina corrisponde ai filtri scelti.</p>
    @endif

    <table class="page-grid">
        @foreach ($pageRows as $row)
            <tr>
                @foreach ($row as $page)
                    @php
                        $typeColor = $typeColors[$page->content_type->value];
                        $statusColor = $statusColors[$page->status->value];
                        $latestFile = $page->files->first();
                    @endphp
                    <td class="page-cell" style="background-color: {{ $typeColor['bg'] }};">
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
