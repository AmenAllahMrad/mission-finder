<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegleScoring extends Model
{
    protected $table = 'regles_scoring';

    public $timestamps = false;

    protected $fillable = [
        'profil_recherche_id',
        'critere_id',
        'poids',
    ];

    protected function casts(): array
    {
        return [
            'poids' => 'integer',
        ];
    }

    public function profilRecherche(): BelongsTo
    {
        return $this->belongsTo(
            ProfilRecherche::class,
            'profil_recherche_id'
        );
    }

    public function critere(): BelongsTo
    {
        return $this->belongsTo(Critere::class);
    }
}