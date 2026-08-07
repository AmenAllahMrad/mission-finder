<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Critere extends Model
{
    protected $table = 'criteres';

    public $timestamps = false;

    protected $fillable = [
        'code',
        'label',
        'type',
    ];

    public function reglesFiltrage(): HasMany
{
    return $this->hasMany(RegleFiltrage::class);
}

public function reglesScoring(): HasMany
{
    return $this->hasMany(RegleScoring::class);
}

}