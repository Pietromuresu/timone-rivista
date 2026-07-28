<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Issue;

/**
 * A differenza delle altre classi in App\Support (PageReorderer,
 * PagePercentageAllocator, AdLoadCalculator, PageCountResizer), questa
 * NON è pura: scrive su database e legge l'utente/IP della richiesta
 * corrente. Resta comunque sotto App\Support perché è un servizio di
 * supporto a scopo unico, non logica di dominio — un helper statico è
 * una scelta pragmatica coerente con come `broadcast()` (facade Laravel)
 * è già chiamato direttamente nei componenti Livewire di questo
 * progetto, non un'astrazione/interfaccia con dependency injection: per
 * un'unica implementazione, in un progetto di queste dimensioni,
 * un'interfaccia a sé sarebbe complessità senza beneficio reale (vedi
 * CLAUDE.md, "non sacrificare la velocità di consegna delle fasi per
 * un'aderenza formale a SOLID").
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public static function log(
        ?Issue $issue,
        string $entityType,
        ?int $entityId,
        string $action,
        string $description,
        array $old = [],
        array $new = [],
    ): void {
        ActivityLog::create([
            'issue_id' => $issue?->id,
            'user_id' => auth()->id(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'description' => $description,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()?->ip(),
        ]);
    }
}
