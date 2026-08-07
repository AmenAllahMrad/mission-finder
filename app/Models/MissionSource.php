<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionSource extends Model
{
    protected $table = 'mission_sources';

    public $timestamps = false;

    protected $fillable = [
        'mission_id',
        'source_id',
        'url_origine',
        'raw_data',
        'derniere_detection',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'derniere_detection' => 'datetime',
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}