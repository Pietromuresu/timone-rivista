<x-mail::message>
# Scadenza stampa in avvicinamento

Il numero **{{ $issue->title }}** di **{{ $magazine->name }}** ha una scadenza di stampa
{{ $daysRemaining >= 0 ? 'tra ' . $daysRemaining . ' ' . ($daysRemaining === 1 ? 'giorno' : 'giorni') : 'da ' . abs($daysRemaining) . ' ' . (abs($daysRemaining) === 1 ? 'giorno' : 'giorni') }}
: **{{ $issue->print_deadline_at->translatedFormat('d F Y') }}**.

Stato attuale del numero: **{{ $issue->status->label() }}**.

<x-mail::button :url="route('issues.show', [$magazine, $issue])">
Apri il timone
</x-mail::button>

Sei il redattore di riferimento di questa rivista — questa mail viene inviata una sola volta per scadenza.

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>
