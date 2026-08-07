<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}