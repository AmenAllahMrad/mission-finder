<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreMissionProfil extends Model
{
    protected $table = 'scores_missions_profils';

    public $timestamps = false;

    protected $fillable = [
        'mission_id',
        'profil_recherche_id',
        'score',
        'calcule_le',
    ];

    protected $casts = [
        'score' => 'integer',
        'calcule_le' => 'datetime',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function profilRecherche(): BelongsTo
    {
        return $this->belongsTo(ProfilRecherche::class);
    }
}