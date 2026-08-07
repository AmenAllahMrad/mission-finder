<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegleFiltrage extends Model
{
    protected $table = 'regles_filtrage';

    public $timestamps = false;

    protected $fillable = [
        'profil_recherche_id',
        'critere_id',
        'operateur',
        'valeur',
    ];

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