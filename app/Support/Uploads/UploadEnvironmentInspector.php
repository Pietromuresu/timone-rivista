<?php

namespace App\Support\Uploads;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Controlli sull'ambiente da cui dipende il caricamento dei file (Fase 1,
 * bug 9, 2026-10-26: "in produzione non si possono caricare media", senza
 * accesso al server). Ogni controllo corrisponde a una voce della
 * checklist in docs/09-deploy.md / docs/10-debugging.md: il comando
 * `php artisan timone:diagnose-upload` li stampa, e un upload fallito ne
 * scrive il riepilogo nel log (ContentCreate::_uploadErrored()), così chi
 * ha accesso al server legge la causa invece di doverla indovinare.
 *
 * Solo lettura: non modifica nulla. Il controllo PDF scrive e cancella un
 * file temporaneo di poche centinaia di byte.
 */
final class UploadEnvironmentInspector
{
    /** Disco su cui ContentCreator salva i file definitivi. */
    public const FINAL_DISK = 'local';

    /**
     * PDF minimo di una pagina, per verificare che Imagick+Ghostscript
     * leggano davvero un PDF (la policy di ImageMagick può vietarlo).
     */
    private const TINY_PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000052 00000 n \n0000000101 00000 n \ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n170\n%%EOF";

    /**
     * @param  int|null  $componentMaxKilobytes  regola `max:` del modulo che salva il file
     * @return list<array{check: string, status: 'ok'|'warn'|'fail'|'info', detail: string, hint: string}>
     */
    public static function inspect(?Request $request = null, ?int $componentMaxKilobytes = null, bool $probePdf = true): array
    {
        return array_values(array_filter([
            self::appUrl($request),
            self::phpLimits($componentMaxKilobytes),
            self::temporaryDisk(),
            self::finalDisk(),
            self::phpTempDir(),
            $probePdf ? self::pdfSupport() : null,
            self::queue(),
            self::storageLink(),
        ]));
    }

    private static function result(string $check, string $status, string $detail, string $hint = ''): array
    {
        return compact('check', 'status', 'detail', 'hint');
    }

    private static function appUrl(?Request $request): array
    {
        $appUrl = (string) config('app.url');

        if ($request === null) {
            return self::result(
                'APP_URL',
                'info',
                "APP_URL = {$appUrl}",
                "Deve coincidere ESATTAMENTE (http/https, nome o IP, porta) con l'indirizzo che gli utenti scrivono nel browser.",
            );
        }

        $configured = parse_url($appUrl);
        $actualScheme = $request->getScheme();
        $actualHost = $request->getHttpHost();
        $configuredHost = ($configured['host'] ?? '').(isset($configured['port']) ? ':'.$configured['port'] : '');
        $matches = ($configured['scheme'] ?? null) === $actualScheme && $configuredHost === $actualHost;

        return self::result(
            'APP_URL',
            $matches ? 'ok' : 'warn',
            "APP_URL = {$appUrl} — richiesta arrivata su {$actualScheme}://{$actualHost}",
            $matches ? '' : 'APP_URL diverso dall\'indirizzo reale: correggilo nel .env ed esegui `php artisan config:clear`. Se lo schema reale è https ma qui risulta http, il proxy non invia X-Forwarded-Proto.',
        );
    }

    private static function phpLimits(?int $componentMaxKilobytes): array
    {
        $upload = UploadLimits::iniBytes(ini_get('upload_max_filesize'));
        $post = UploadLimits::iniBytes(ini_get('post_max_size'));
        $livewireKb = UploadLimits::ruleMaxKilobytes(config('livewire.temporary_file_upload.rules') ?? []);
        // Il file più grande che l'app intende accettare: il limite del modulo
        // se noto, altrimenti quello dell'upload temporaneo Livewire.
        $appBytes = $componentMaxKilobytes !== null ? $componentMaxKilobytes * 1024 : ($livewireKb ?? 0) * 1024;
        $phpBytes = min(array_filter([$upload, $post]) ?: [PHP_INT_MAX]);
        $effective = UploadLimits::effective($componentMaxKilobytes);

        $detail = sprintf(
            'upload_max_filesize = %s, post_max_size = %s, regola Livewire = %s, limite effettivo = %s (%s)',
            ini_get('upload_max_filesize') ?: '?',
            ini_get('post_max_size') ?: '?',
            $livewireKb !== null ? UploadLimits::humanMegabytes($livewireKb * 1024) : 'nessuna',
            $effective['bytes'] !== null ? UploadLimits::humanMegabytes($effective['bytes']) : 'illimitato',
            $effective['source'] ?? '-',
        );

        return self::result(
            'Limiti PHP',
            $phpBytes < $appBytes ? 'warn' : 'ok',
            $detail,
            $phpBytes < $appBytes ? 'PHP accetta file più piccoli di quanto prevede l\'app: alza upload_max_filesize/post_max_size (docker/php/uploads.ini) e client_max_body_size di nginx.' : 'Verifica anche client_max_body_size di nginx (non leggibile da PHP).',
        );
    }

    private static function temporaryDisk(): array
    {
        $disk = config('livewire.temporary_file_upload.disk') ?: config('filesystems.default');
        $directory = config('livewire.temporary_file_upload.directory') ?: 'livewire-tmp';

        return self::diskCheck("Disco temporaneo Livewire ({$disk}/{$directory})", $disk, $directory);
    }

    private static function finalDisk(): array
    {
        $result = self::diskCheck('Disco finale dei file ('.self::FINAL_DISK.'/contents)', self::FINAL_DISK, 'contents');

        if (config('filesystems.default') !== self::FINAL_DISK && $result['status'] === 'ok') {
            $result['status'] = 'warn';
            $result['hint'] = 'FILESYSTEM_DISK = '.config('filesystems.default').': l\'upload temporaneo usa quel disco, i file definitivi «'.self::FINAL_DISK.'». Se non è voluto, imposta FILESYSTEM_DISK=local come in locale.';
        }

        return $result;
    }

    private static function diskCheck(string $label, string $disk, string $directory): array
    {
        try {
            $storage = Storage::disk($disk);
        } catch (Throwable $e) {
            return self::result($label, 'fail', "Disco «{$disk}» non configurato: {$e->getMessage()}", 'Controlla config/filesystems.php e FILESYSTEM_DISK.');
        }

        if (config("filesystems.disks.{$disk}.driver") !== 'local') {
            return self::result($label, 'info', "Disco «{$disk}» non locale (driver ".config("filesystems.disks.{$disk}.driver").')', 'Verifica credenziali e permessi del bucket.');
        }

        $path = $storage->path($directory);
        $existing = $path;

        while (! file_exists($existing) && dirname($existing) !== $existing) {
            $existing = dirname($existing);
        }

        $writable = is_writable($existing);

        return self::result(
            $label,
            $writable ? 'ok' : 'fail',
            $path.($writable ? ' scrivibile' : " NON scrivibile (controllato su {$existing})"),
            $writable ? '' : 'Dai i permessi all\'utente di PHP-FPM: `chown -R www-data:www-data storage && chmod -R ug+rwX storage`.',
        );
    }

    private static function phpTempDir(): array
    {
        $dir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
        $writable = is_writable($dir);

        return self::result(
            'Cartella temporanea PHP',
            $writable ? 'ok' : 'fail',
            $dir.($writable ? ' scrivibile' : ' NON scrivibile'),
            $writable ? '' : 'PHP scrive lì ogni file ricevuto prima di passarlo a Laravel: rendila scrivibile o imposta upload_tmp_dir.',
        );
    }

    private static function pdfSupport(): array
    {
        if (! extension_loaded('imagick')) {
            return self::result('Lettura PDF (Imagick)', 'fail', 'Estensione PHP imagick non caricata', 'Senza imagick non si contano le pagine dei PDF e ogni PDF viene rifiutato: installa ext-imagick e ghostscript.');
        }

        $path = tempnam(sys_get_temp_dir(), 'timone-pdf-check');

        try {
            file_put_contents($path, self::TINY_PDF);
            $imagick = new \Imagick;
            $imagick->pingImage($path);
            $pages = $imagick->getNumberImages();
            $imagick->clear();

            return self::result('Lettura PDF (Imagick)', $pages === 1 ? 'ok' : 'warn', "PDF di prova letto: {$pages} pagina/e");
        } catch (Throwable $e) {
            return self::result(
                'Lettura PDF (Imagick)',
                'fail',
                'Imagick non riesce a leggere un PDF: '.$e->getMessage(),
                'Tipico: ghostscript mancante oppure policy di ImageMagick che vieta i PDF (<policy domain="coder" rights="none" pattern="PDF" /> in /etc/ImageMagick-*/policy.xml).',
            );
        } finally {
            @unlink($path);
        }
    }

    private static function queue(): array
    {
        $connection = config('queue.default');

        return self::result(
            'Coda (miniature)',
            'info',
            "QUEUE_CONNECTION = {$connection}",
            $connection === 'sync' ? '' : 'Le miniature si generano solo se un worker è attivo (`php artisan queue:work`, servizio «worker» in Docker): senza, i file restano «⏳ in coda».',
        );
    }

    private static function storageLink(): array
    {
        return self::result(
            'storage:link',
            'info',
            file_exists(public_path('storage')) ? 'public/storage presente' : 'public/storage assente',
            'Non necessario per i materiali del timone: PDF e miniature sono serviti dalle route content-files.* / page-files.*, non da /storage.',
        );
    }
}
