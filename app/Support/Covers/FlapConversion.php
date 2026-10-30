<?php

namespace App\Support\Covers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Dalla bandella ai battenti (2026-10-30, approvato nella Fase A0): la
 * bandella di un numero diventa la PRIMA pagina del battente di I (stessa
 * riga di `pages`: contenuti, file, stato, blocco e commenti restano
 * agganciati), la seconda pagina nasce vuota. Le facciate di copertina
 * restano 6, quindi il peso non cambia. Nei default della rivista, 6
 * pagine di copertina diventano «battente I» (8 o più: entrambi).
 *
 * Query builder e valori scritti per esteso, non modelli ed enum: una
 * migrazione deve continuare a funzionare anche quando i modelli
 * cambieranno (stesso principio di CoverConversion).
 */
final class FlapConversion
{
    private const BANDELLA = CoverConversion::LEGACY_BANDELLA;

    private const FLAP_I = ['BI1', 'BI2'];

    private const FLAP_IV = ['BIV1', 'BIV2'];

    /**
     * Conteggi per il controllo prima/dopo (`timone:battenti-dry-run`),
     * senza scrivere nulla.
     *
     * @return array<string, int>
     */
    public static function counts(): array
    {
        $bandelle = DB::table('pages')->where('cover_slot', self::BANDELLA)->pluck('id');
        $hasFlapColumn = Schema::hasColumn('magazines', 'default_cover_flaps');

        return [
            'numeri' => DB::table('issues')->count(),
            'numeri con bandella' => $bandelle->count(),
            'bandelle con contenuti' => DB::table('page_content')->whereIn('page_id', $bandelle)->distinct()->count('page_id'),
            'bandelle con file di pagina' => DB::table('page_files')->whereIn('page_id', $bandelle)->distinct()->count('page_id'),
            'bandelle con stato o note' => DB::table('pages')->whereIn('id', $bandelle)->where(fn ($q) => $q->where('status', '!=', 'da_assegnare')->orWhereNotNull('notes'))->count(),
            'bandelle bloccate' => DB::table('pages')->whereIn('id', $bandelle)->whereNotNull('locked_at')->count(),
            'bandelle con commenti' => Schema::hasTable('page_comments') ? DB::table('page_comments')->whereIn('page_id', $bandelle)->distinct()->count('page_id') : 0,
            'numeri con battente I' => DB::table('pages')->where('cover_slot', 'BI1')->count(),
            'numeri con battente IV' => DB::table('pages')->where('cover_slot', 'BIV1')->count(),
            'pagine di battente con contenuti' => DB::table('page_content')->whereIn('page_id', DB::table('pages')->whereIn('cover_slot', [...self::FLAP_I, ...self::FLAP_IV])->select('id'))->distinct()->count('page_id'),
            'riviste' => DB::table('magazines')->count(),
            'riviste con 6 pagine di copertina' => DB::table('magazines')->where('pagine_copertina', 6)->count(),
            'riviste con 8 o più pagine di copertina' => DB::table('magazines')->where('pagine_copertina', '>=', 8)->count(),
            'riviste con default battente I' => $hasFlapColumn ? DB::table('magazines')->where('default_cover_flaps', 'I')->count() : 0,
            'riviste con default entrambi i battenti' => $hasFlapColumn ? DB::table('magazines')->where('default_cover_flaps', 'entrambi')->count() : 0,
        ];
    }

    /** Bandella → battente di I, e default delle riviste. Richiede la colonna default_cover_flaps. */
    public static function apply(): void
    {
        DB::transaction(function () {
            $now = now();

            foreach (DB::table('pages')->where('cover_slot', self::BANDELLA)->get(['id', 'issue_id']) as $bandella) {
                DB::table('pages')->where('id', $bandella->id)->update(['cover_slot' => 'BI1', 'updated_at' => $now]);
                DB::table('pages')->insert([
                    'issue_id' => $bandella->issue_id,
                    'position' => null,
                    'cover_slot' => 'BI2',
                    'content_type' => 'bianca',
                    'status' => 'da_assegnare',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                self::syncCoverPages($bandella->issue_id);
            }

            DB::table('magazines')->where('pagine_copertina', '>=', 8)->update(['default_cover_flaps' => 'entrambi']);
            DB::table('magazines')->where('pagine_copertina', 6)->update(['default_cover_flaps' => 'I']);
        });
    }

    /**
     * Ritorno alla bandella. Possibile solo se nessun numero usa ciò che la
     * bandella non sapeva rappresentare (il battente di IV, una seconda
     * pagina di battente non vuota): in quel caso si ferma PRIMA di
     * cambiare qualunque cosa, con l'elenco dei numeri da sistemare.
     */
    public static function revert(): void
    {
        $problems = self::revertProblems();

        if ($problems !== []) {
            throw new RuntimeException("Non posso tornare alla bandella senza perdere dati:\n- ".implode("\n- ", $problems));
        }

        DB::transaction(function () {
            foreach (DB::table('pages')->where('cover_slot', 'BI1')->get(['id', 'issue_id']) as $first) {
                DB::table('pages')->where('issue_id', $first->issue_id)->where('cover_slot', 'BI2')->delete();
                DB::table('pages')->where('id', $first->id)->update(['cover_slot' => self::BANDELLA]);
                self::syncCoverPages($first->issue_id);
            }

            // Un BI2 rimasto senza BI1 (non dovrebbe esistere): vuoto, verificato sopra.
            DB::table('pages')->where('cover_slot', 'BI2')->delete();
        });
    }

    /** @return list<string> */
    public static function revertProblems(): array
    {
        $problems = [];

        foreach (DB::table('pages')->whereIn('cover_slot', self::FLAP_IV)->distinct()->pluck('issue_id') as $issueId) {
            $problems[] = "numero #{$issueId}: ha il battente di IV";
        }

        foreach (DB::table('pages')->where('cover_slot', 'BI2')->get() as $second) {
            $used = DB::table('page_content')->where('page_id', $second->id)->exists()
                || DB::table('page_files')->where('page_id', $second->id)->exists()
                || (Schema::hasTable('page_comments') && DB::table('page_comments')->where('page_id', $second->id)->exists())
                || $second->locked_at !== null
                || $second->status !== 'da_assegnare';

            if ($used) {
                $problems[] = "numero #{$second->issue_id}: la pagina 2 del battente di I non è vuota";
            }
        }

        return $problems;
    }

    /** Facciate di copertina del peso = copertine che esistono (la bandella ne valeva 2). */
    private static function syncCoverPages(int $issueId): void
    {
        $covers = DB::table('pages')->where('issue_id', $issueId)->whereNotNull('cover_slot')->count();
        $bandella = DB::table('pages')->where('issue_id', $issueId)->where('cover_slot', self::BANDELLA)->exists();

        DB::table('issues')->where('id', $issueId)->update(['pagine_copertina' => $covers + ($bandella ? 1 : 0)]);
    }
}
