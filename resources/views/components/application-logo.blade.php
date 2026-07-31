{{-- Icona timone (ruota del timone nautico) al posto del logo Laravel di default dello scaffold Breeze — coerente col nome del progetto. Disegnata a mano con forme SVG di base (nessun asset esterno), pensata per ereditare "fill-current" dalle classi passate da chi la usa (navigation.blade.php in piccolo, guest.blade.php in grande). --}}
<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" {{ $attributes }}>
    <path fill-rule="evenodd" clip-rule="evenodd"
        d="M50 4C24.6 4 4 24.6 4 50s20.6 46 46 46 46-20.6 46-46S75.4 4 50 4zm0 10c19.9 0 36 16.1 36 36s-16.1 36-36 36-36-16.1-36-36 16.1-36 36-36z" />
    <circle cx="50" cy="50" r="11" />
    <g>
        <rect x="47" y="6" width="6" height="30" rx="3" />
        <rect x="47" y="64" width="6" height="30" rx="3" />
        <rect x="6" y="47" width="30" height="6" rx="3" />
        <rect x="64" y="47" width="30" height="6" rx="3" />
    </g>
    <g transform="rotate(45 50 50)">
        <rect x="47" y="6" width="6" height="30" rx="3" />
        <rect x="47" y="64" width="6" height="30" rx="3" />
        <rect x="6" y="47" width="30" height="6" rx="3" />
        <rect x="64" y="47" width="30" height="6" rx="3" />
    </g>
</svg>
