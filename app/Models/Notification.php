<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Modèle non fourni dans le projet initial : ajouté ici car
// /admin/dashboard/recent-activities s'appuie explicitement dessus.
#[Fillable(['title', 'description', 'type', 'is_read', 'admin_id'])]
class Notification extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'type'    => NotificationType::class,
        'is_read' => 'boolean',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
