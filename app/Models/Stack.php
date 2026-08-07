<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Stack extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'nom',
    ];

    public function missions(): BelongsToMany
    {
        return $this->belongsToMany(
            Mission::class,
            'mission_stack'
        );
    }
}