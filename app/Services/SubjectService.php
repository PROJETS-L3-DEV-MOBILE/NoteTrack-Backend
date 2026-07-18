<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Classe;
use App\Models\Subject;
use App\Models\UE;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SubjectService
{
    /**
     * Matières groupées par niveau puis par UE (GET /admin/subjects).
     *
     * Seuls les niveaux (classes) ayant au moins une UE sont renvoyés (une
     * classe fraîchement créée sans UE n'a rien à afficher ici).
     */
    public function groupedByLevel(): Collection
    {
        return Classe::query()
            ->whereHas('ues')
            ->withCount(['ues', 'subjects'])
            ->with(['ues' => fn ($q) => $q->with(['subjects' => fn ($q) => $q->with(['teacher', 'semester'])])])
            ->latest('created_at')
            ->get();
    }

    /**
     * Création d'une UE (createUESchema). `data` attendu en snake_case :
     * name, class_id, color.
     */
    public function createUE(array $data, Admin $admin): UE
    {
        return UE::create([
            'code'     => $this->generateUniqueCode($data['name']),
            'label'    => $data['name'],
            'color'    => $data['color'],
            'class_id' => $data['class_id'],
            'admin_id' => $admin->id,
        ])->fresh();
    }

    public function updateUE(UE $ue, array $data): UE
    {
        $ue->update([
            'label'    => $data['name'],
            'color'    => $data['color'],
            'class_id' => $data['class_id'],
        ]);

        return $ue->fresh();
    }

    /**
     * Supprime l'UE. Cascade DB déjà en place : supprime ses matières
     * (subjects.ue_id) et, par ricochet, leurs notes (notes.subject_id).
     */
    public function deleteUE(UE $ue): void
    {
        $ue->delete();
    }

    /**
     * Création d'une matière (createSubjectSchema) rattachée à une UE.
     * `ue_id` vient du paramètre de route (POST /admin/ues/{ue}/subjects),
     * pas du payload — cf. CreateSubjectRequest.
     */
    public function createSubject(UE $ue, array $data, Admin $admin): Subject
    {
        return Subject::create([
            'name'        => $data['name'],
            'teacher_id'  => $data['teacher_id'],
            'semester_id' => $data['semester_id'],
            'coefficient' => $data['coefficient'],
            'threshold'   => $data['threshold'],
            'credits'     => $data['credits'],
            'ue_id'       => $ue->id,
            'admin_id'    => $admin->id,
        ])->fresh(['teacher', 'semester']);
    }

    public function updateSubject(Subject $subject, array $data): Subject
    {
        $subject->update([
            'name'        => $data['name'],
            'teacher_id'  => $data['teacher_id'],
            'semester_id' => $data['semester_id'],
            'coefficient' => $data['coefficient'],
            'threshold'   => $data['threshold'],
            'credits'     => $data['credits'],
        ]);

        return $subject->fresh(['teacher', 'semester']);
    }

    /**
     * Supprime la matière. Cascade DB déjà en place : supprime ses notes
     * (notes.subject_id).
     */
    public function deleteSubject(Subject $subject): void
    {
        $subject->delete();
    }

    private function generateUniqueCode(string $label): string
    {
        $base = Str::upper(Str::slug($label, '-'));

        do {
            $code = "{$base}-" . Str::upper(Str::random(4));
        } while (UE::where('code', $code)->exists());

        return $code;
    }
}
