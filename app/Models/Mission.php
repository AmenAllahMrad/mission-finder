<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mission extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'source_id',
        'titre',
        'description',
        'entreprise',
        'tjm_min',
        'tjm_max',
        'remote_type',
        'localisation',
        'duree_mois',
        'date_publication',
        'url_origine',
        'hash_unique',
        'raw_data',
        'score',
        'statut',
        'date_candidature',
    ];

    protected function casts(): array
    {
        return [
            'tjm_min' => 'decimal:2',
            'tjm_max' => 'decimal:2',
            'duree_mois' => 'integer',
            'date_publication' => 'date',
            'raw_data' => 'array',
            'score' => 'integer',
            'date_candidature' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}