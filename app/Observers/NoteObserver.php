<?php

namespace App\Observers;

use App\Enums\SessionStatus;
use App\Models\Note;
use App\Models\NoteHistory;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class NoteObserver
{
    /**
     * RG08 : aucune création n'est possible sur une session verrouillée.
     */
    public function creating(Note $note): void
    {
        $this->assertSessionEditable($note);
    }

    /**
     * RG08 : aucune modification n'est possible sur une session verrouillée.
     * RG05 : toute modification après publication est horodatée et tracée
     * (ancienne valeur, nouvelle valeur, auteur).
     */
    public function updating(Note $note): void
    {
        $this->assertSessionEditable($note);

        if ($note->getOriginal('is_published') && $note->isDirty('value')) {
            NoteHistory::create([
                'note_id'    => $note->id,
                'old_value'  => $note->getOriginal('value'),
                'new_value'  => $note->value,
                'changed_by' => Auth::id() ?? $note->created_by,
                'changed_at' => now(),
            ]);
        }
    }

    private function assertSessionEditable(Note $note): void
    {
        // Requête volontairement fraîche (pas $note->session) : si la relation
        // a déjà été chargée sur ce modèle avant que la session soit verrouillée
        // ailleurs, Eloquent renverrait une copie en cache avec l'ancien statut,
        // et RG08 laisserait passer une modification qui aurait dû être bloquée.
        // Bug confirmé par le smoke test (lock() après un premier accès à
        // $note->session ne bloquait pas la modification suivante).
        $session = $note->session()->first();

        if ($session && $session->status === SessionStatus::Verrouillee) {
            throw new RuntimeException(
                'Impossible de modifier cette note : la session est verrouillée (RG08).'
            );
        }
    }
}
