<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'nom',
        'type',
        'url_base',
        'parser_class',
        'frequence_polling_minutes',
        'credentials',
        'actif',
        'derniere_execution',
        'dernier_statut',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'actif' => 'boolean',
            'derniere_execution' => 'datetime',
        ];
    }

    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class);
    }

    public function missionOccurrences(): HasMany
{
    return $this->hasMany(MissionSource::class);
}

}