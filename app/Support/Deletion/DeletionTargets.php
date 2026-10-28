<?php

namespace App\Support\Deletion;

use App\Models\Issue;
use App\Models\Magazine;
use App\Models\User;

/**
 * Da «tipo + id» (proprietà pubbliche del componente Livewire, quindi solo
 * scalari) all'oggetto da eliminare. Un nuovo tipo eliminabile = una riga
 * qui e una classe DeletionTarget.
 */
final class DeletionTargets
{
    public const KINDS = ['issue', 'magazine', 'user'];

    public static function resolve(string $kind, int $id): DeletionTarget
    {
        return match ($kind) {
            'issue' => new IssueDeletion(Issue::with('magazine')->findOrFail($id)),
            'magazine' => new MagazineDeletion(Magazine::findOrFail($id)),
            'user' => new UserDeletion(User::findOrFail($id)),
        };
    }
}
