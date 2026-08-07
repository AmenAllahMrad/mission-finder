<?php

namespace App\Services;

use App\Models\Mission;
use App\Models\Source;
use App\Models\Stack;
use Illuminate\Support\Str;
use App\Models\MissionSource;

class MissionImportService
{
    public function importer(Source $source, array $data): Mission
    {
        $titreNormalise = $this->normaliserTexte($data['titre'] ?? '');
        $entrepriseNormalisee = $this->normaliserTexte(
            $data['entreprise'] ?? ''
        );

        $hash = md5(
            $titreNormalise
            . '|'
            . $entrepriseNormalisee

        );

        $mission = Mission::firstOrCreate(
    [
        'hash_unique' => $hash,
    ],
    [
        'source_id' => $source->id,
        'titre' => $data['titre'],
        'description' => $data['description'] ?? null,
        'entreprise' => $data['entreprise'] ?? null,
        'tjm_min' => $data['tjm_min'] ?? null,
        'tjm_max' => $data['tjm_max'] ?? null,
        'remote_type' => $data['remote_type'] ?? null,
        'localisation' => $data['localisation'] ?? null,
        'secteur' => $data['secteur'] ?? null,
        'duree_mois' => $data['duree_mois'] ?? null,
        'date_publication' => $data['date_publication'] ?? null,
        'url_origine' => $data['url_origine'],
        'raw_data' => $data['raw_data'] ?? null,
    ]
);

MissionSource::updateOrCreate(
    [
        'mission_id' => $mission->id,
        'source_id' => $source->id,
    ],
    [
        'url_origine' => $data['url_origine'],
        'raw_data' => $data['raw_data'] ?? null,
        'derniere_detection' => now(),
    ]
);


        

       $stackIds = [];

foreach ($data['stacks'] ?? [] as $stackName) {
    $stackName = trim($stackName);

    if ($stackName === '') {
        continue;
    }

    $stack = Stack::firstOrCreate([
        'nom' => Str::lower($stackName),
    ]);

    $stackIds[] = $stack->id;
}

$mission->stacks()->syncWithoutDetaching($stackIds);

return $mission->refresh();
    }

    private function normaliserTexte(?string $texte): string
    {
        return Str::lower(
            preg_replace('/\s+/', ' ', trim($texte ?? ''))
        );
    }
}