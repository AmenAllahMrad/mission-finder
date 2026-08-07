<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alerte extends Model
{
    protected $table = 'alertes';

    public $timestamps = false;

    protected $fillable = [
        'profil_recherche_id',
        'canal',
        'destination',
        'frequence',
        'seuil_score_min',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'seuil_score_min' => 'integer',
            'actif' => 'boolean',
        ];
    }

    public function profilRecherche(): BelongsTo
    {
        return $this->belongsTo(
            ProfilRecherche::class,
            'profil_recherche_id'
        );
    }
}