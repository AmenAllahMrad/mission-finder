<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlerteMissionEnvoyee extends Model
{
    protected $table = 'alertes_missions_envoyees';

    public $timestamps = false;

    protected $fillable = [
        'alerte_id',
        'mission_id',
        'envoyee_le',
    ];

    protected $casts = [
        'envoyee_le' => 'datetime',
    ];

    public function alerte(): BelongsTo
    {
        return $this->belongsTo(Alerte::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }
}