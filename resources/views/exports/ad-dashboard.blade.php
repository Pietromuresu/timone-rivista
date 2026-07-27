<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Carico pubblicitario — {{ $issue->title }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        h2 { font-size: 14px; margin-top: 24px; margin-bottom: 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 4px; }
        p.subtitle { color: #6b7280; margin-top: 0; }
        .load-figure { font-size: 28px; font-weight: bold; }
        .over-threshold { color: #dc2626; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { text-align: left; padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
        th { background-color: #f3f4f6; font-weight: bold; }
        .meta { color: #9ca3af; font-size: 10px; margin-top: 30px; }
    </style>
</head>
<body>
    <h1>{{ $issue->magazine->name }} — {{ $issue->title }}</h1>
    <p class="subtitle">Cruscotto pubblicitario &middot; generato il {{ now()->translatedFormat('d M Y, H:i') }}</p>

    <p class="load-figure {{ $adThreshold !== null && $adLoad['adLoadPercentage'] > (float) $adThreshold ? 'over-threshold' : '' }}">
        {{ $adLoad['adLoadPercentage'] }}% carico pubblicitario
        @if ($adThreshold !== null && $adLoad['adLoadPercentage'] > (float) $adThreshold)
            (sopra la soglia del {{ $adThreshold }}%)
        @endif
    </p>

    <h2>Riepilogo</h2>
    <table>
        <tr><th>Pagine totali</th><td>{{ $adLoad['totalPages'] }}</td></tr>
        <tr><th>Pagine equivalenti pubblicità</th><td>{{ $adLoad['adEquivalentPages'] }}</td></tr>
        <tr><th>Pagine equivalenti editoriali</th><td>{{ $adLoad['editorialEquivalentPages'] }}</td></tr>
        <tr><th>Inserzioni assegnate</th><td>{{ $adLoad['assignedAdCount'] }}</td></tr>
        <tr><th>Soglia allarme</th><td>{{ $adThreshold !== null ? $adThreshold.'%' : 'non impostata' }}</td></tr>
    </table>

    <h2>Per formato pubblicitario</h2>
    @if (empty($adLoad['formatBreakdown']))
        <p>Nessuna pubblicità assegnata.</p>
    @else
        <table>
            <tr><th>Formato</th><th>Conteggio</th></tr>
            @foreach ($adLoad['formatBreakdown'] as $format => $count)
                <tr><td>{{ \App\Enums\AdFormat::from($format)->label() }}</td><td>{{ $count }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Per stato commerciale</h2>
    @if (empty($adLoad['confirmationBreakdown']))
        <p>Nessuna pubblicità assegnata.</p>
    @else
        <table>
            <tr><th>Stato</th><th>Conteggio</th></tr>
            @foreach ($adLoad['confirmationBreakdown'] as $status => $count)
                <tr><td>{{ \App\Enums\AdConfirmationStatus::from($status)->label() }}</td><td>{{ $count }}</td></tr>
            @endforeach
        </table>
    @endif

    <p class="meta">Timone Elettronico &middot; {{ $issue->magazine->name }}</p>
</body>
</html>
