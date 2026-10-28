<?php

namespace App\Support\Deletion;

use App\Models\ActivityLog;
use App\Models\ContentFile;
use App\Models\PageFile;
use App\Models\PageReorderLog;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Eliminazione di un utente da parte di un admin (revisione 2, Fase 4.3).
 * Il suo lavoro resta: file caricati, spostamenti e cronologia hanno la
 * chiave verso l'utente in nullOnDelete (migration 2026_10_28_100000) e
 * l'interfaccia mostra «Utente eliminato». Gli accessi alle riviste
 * spariscono con la cascata di magazine_user. Chi e quando possono
 * eliminarlo lo decide UserPolicy::delete() (mai sé stessi, mai l'ultimo
 * admin).
 */
final class UserDeletion implements DeletionTarget
{
    public function __construct(private readonly User $user) {}

    public function model(): object
    {
        return $this->user;
    }

    public function label(): string
    {
        return "l'utente «{$this->user->name}» ({$this->user->email})";
    }

    /** L'email: unica, a differenza del nome. */
    public function confirmationText(): string
    {
        return $this->user->email;
    }

    public function summary(): DeletionSummary
    {
        $files = ContentFile::where('uploaded_by', $this->user->id)->count() + PageFile::where('uploaded_by', $this->user->id)->count();

        return new DeletionSummary([
            'ruolo: '.$this->user->role->label(),
            DeletionSummary::count($this->user->magazines()->count(), 'accesso a una rivista (viene tolto)', 'accessi a riviste (vengono tolti)'),
            DeletionSummary::count($files, 'file caricato: resta, con «Utente eliminato»', 'file caricati: restano, con «Utente eliminato»'),
            DeletionSummary::count(PageReorderLog::where('user_id', $this->user->id)->count() + ActivityLog::where('user_id', $this->user->id)->count(), 'voce di cronologia: resta', 'voci di cronologia: restano'),
            'sessioni aperte: chiuse subito',
        ]);
    }

    public function delete(User $by): void
    {
        $user = $this->user;

        DB::transaction(function () use ($user, $by) {
            ActivityLogger::log(
                issue: null,
                entityType: 'User',
                entityId: $user->id,
                action: 'user.deleted',
                description: "Utente «{$user->name}» ({$user->email}) eliminato da {$by->name}",
                old: ['name' => $user->name, 'email' => $user->email, 'role' => $user->role->value],
            );

            self::invalidateSessions($user);
            $user->delete();
        });

        Log::info('user.deleted', ['user_id' => $user->id, 'email' => $user->email, 'by_user_id' => $by->id]);
    }

    public function redirectUrl(): string
    {
        return route('users.index');
    }

    /**
     * Con le sessioni nel database si cancellano le sue righe: l'utente è
     * fuori subito. Con altri driver non si possono elencare, ma una
     * sessione che punta a un utente che non esiste più non autentica
     * nessuno: alla richiesta successiva è comunque disconnesso. Si legge
     * la configurazione di sessione, non la si modifica (CLAUDE.md §4).
     */
    private static function invalidateSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }
    }
}
