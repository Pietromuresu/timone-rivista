<x-mail::message>
# {{ $headline }}

@foreach ($details as $line)
- {{ $line }}
@endforeach

**Cosa fare:** {{ $whatToDo }}

Istruzioni complete: `docs/OPERATIONS.md`, sezione «Backup e ripristino», nella cartella del progetto sul server.

Installazione: {{ $appUrl }}<br>
{{ config('app.name') }}
</x-mail::message>
