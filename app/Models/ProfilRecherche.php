<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfilRecherche extends Model
{
    protected $table = 'profils_recherche';

    public $timestamps = false;

    protected $fillable = [
        'nom',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function reglesFiltrage(): HasMany
    {
        return $this->hasMany(
            RegleFiltrage::class,
            'profil_recherche_id'
        );
    }

    public function reglesScoring(): HasMany
    {
        return $this->hasMany(
            RegleScoring::class,
            'profil_recherche_id'
        );
    }

    public function alertes(): HasMany
{
    return $this->hasMany(
        Alerte::class,
        'profil_recherche_id'
    );
}

public function scoresMissions(): HasMany
{
    return $this->hasMany(
        ScoreMissionProfil::class,
        'profil_recherche_id'
    );
}

}