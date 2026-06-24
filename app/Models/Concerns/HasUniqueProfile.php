<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * @method static void creating(callable $callback)
 */
trait HasUniqueProfile
{
    protected static function bootHasUniqueProfile(): void
    {
        static::creating(function (Model $model): void {
            $user = User::findOrFail($model->user_id);

            if (!$user) {
                throw new RuntimeException('Utilisateur introuvable pour ce profil.');
            }

            if ($user->admin || $user->teacher || $user->student) {
                throw new RuntimeException(
                    "L'utilisateur {$user->email} possède déjà un profil associé."
                );
            }
        });
    }
}