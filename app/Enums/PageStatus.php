<?php

namespace App\Enums;

enum PageStatus: string
{
    case DaAssegnare = 'da_assegnare';
    case Assegnata = 'assegnata';
    case InBozza = 'in_bozza';
    case Revisionata = 'revisionata';
    case OkStampa = 'ok_stampa';

    public function label(): string
    {
        return match ($this) {
            self::DaAssegnare => 'Da assegnare',
            self::Assegnata => 'Assegnata',
            self::InBozza => 'In bozza',
            self::Revisionata => 'Revisionata',
            self::OkStampa => 'Ok stampa',
        };
    }

    /**
     * Sfondo/testo del badge di stato (griglia, lista) — usato anche come
     * base cromatica di borderClasses()/hexColors() sotto, tutti e tre i
     * metodi vanno tenuti allineati sullo stesso colore per caso quando si
     * ritocca la palette (Fase 4, §4: "documenta la palette in un'unica
     * fonte" — i tre metodi di questo enum SONO quella fonte, per la UI
     * Tailwind dal vivo e per l'export Dompdf via hexColors()).
     */
    public function colorClasses(): string
    {
        return match ($this) {
            self::DaAssegnare => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
            self::Assegnata => 'bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300',
            self::InBozza => 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-300',
            self::Revisionata => 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300',
            self::OkStampa => 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300',
        };
    }

    /**
     * Bordo spesso applicato alla card/riga stessa (Fase 4, §4: lo stato
     * deve essere riconoscibile "a colpo d'occhio" come colore dominante,
     * non solo un'etichetta piccola) — canale visivo separato dal colore
     * di sfondo, che sulla card resta quello del tipo pagina
     * (PageContentType::colorClasses()): due informazioni diverse (tipo
     * pagina, stato), due canali diversi (sfondo, bordo), niente conflitto
     * tra classi `bg-*`/`border-*` che si sovrascriverebbero a vicenda.
     * Colore più saturo del badge sopra apposta, per restare leggibile
     * come bordo sottile sopra qualunque sfondo pastello del tipo pagina.
     */
    public function borderClasses(): string
    {
        return match ($this) {
            self::DaAssegnare => 'border-gray-400 dark:border-gray-500',
            self::Assegnata => 'border-sky-500 dark:border-sky-400',
            self::InBozza => 'border-yellow-500 dark:border-yellow-400',
            self::Revisionata => 'border-orange-500 dark:border-orange-400',
            self::OkStampa => 'border-green-500 dark:border-green-400',
        };
    }

    /**
     * Stessa palette di colorClasses()/borderClasses() sopra, in esadecimale
     * — Dompdf (esportazione PDF del timone) non esegue Tailwind, quindi
     * non può leggere le classi CSS: prima di questo metodo l'export
     * teneva una propria copia dei colori scritta a mano in
     * resources/views/exports/timone.blade.php, "scelta per corrispondere
     * il più possibile" alle classi Tailwind — una copia che poteva
     * andare fuori sincrono ad ogni ritocco della palette qui sopra senza
     * che nessuno se ne accorgesse. Ora l'export chiama questo metodo
     * invece di avere una propria copia.
     *
     * @return array{bg: string, text: string, border: string}
     */
    public function hexColors(): array
    {
        return match ($this) {
            self::DaAssegnare => ['bg' => '#f3f4f6', 'text' => '#4b5563', 'border' => '#9ca3af'],
            self::Assegnata => ['bg' => '#e0f2fe', 'text' => '#0369a1', 'border' => '#0ea5e9'],
            self::InBozza => ['bg' => '#fef9c3', 'text' => '#a16207', 'border' => '#eab308'],
            self::Revisionata => ['bg' => '#ffedd5', 'text' => '#c2410c', 'border' => '#f97316'],
            self::OkStampa => ['bg' => '#dcfce7', 'text' => '#15803d', 'border' => '#22c55e'],
        };
    }
}
