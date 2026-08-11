<?php

namespace App\Services;

use App\Models\Mission;
use App\Models\MissionSource;
use App\Models\Source;
use App\Models\Stack;
use Illuminate\Support\Str;

class MissionImportService
{
    public function __construct(
    private MissionScoringService $scoringService
) {
}

    public function importer(Source $source, array $data): Mission
    {
        $titreNormalise = $this->normaliserTexte($data['titre'] ?? '');

        $entrepriseNormalisee = $this->normaliserTexte(
            $data['entreprise'] ?? ''
        );

        // Cross-source canonical hash.
        $hash = md5(
            $titreNormalise
            . '|'
            . $entrepriseNormalisee
        );

        $mission = Mission::where('hash_unique', $hash)->first();

        /*
         * Only non-null incoming values are used so that a source
         * with less information does not erase useful data collected
         * from another source.
         */
        $offreData = array_filter([
            'titre' => $data['titre'] ?? null,
            'description' => $data['description'] ?? null,
            'entreprise' => $data['entreprise'] ?? null,
            'tjm_min' => $data['tjm_min'] ?? null,
            'tjm_max' => $data['tjm_max'] ?? null,
            'remote_type' => $data['remote_type'] ?? null,
            'localisation' => $data['localisation'] ?? null,
            'secteur' => $data['secteur'] ?? null,
            'duree_mois' => $data['duree_mois'] ?? null,
            'date_publication' => $data['date_publication'] ?? null,
        ], fn ($value) => $value !== null);

        if ($mission === null) {
            $mission = Mission::create(array_merge(
                $offreData,
                [
                    'source_id' => $source->id,
                    'hash_unique' => $hash,
                    'url_origine' => $data['url_origine'],
                    'raw_data' => $data['raw_data'] ?? null,
                ]
            ));
        } else {
            /*
             * Refresh normalized offer information without touching:
             * - statut
             * - date_candidature
             * - source_id
             * - score
             */
            $mission->fill($offreData);

            /*
             * The canonical URL/raw payload remain associated with
             * the first source, but may be refreshed when that same
             * source is collected again.
             */
            if ((int) $mission->source_id === (int) $source->id) {
                $mission->url_origine = $data['url_origine'];
                $mission->raw_data = $data['raw_data'] ?? null;
            }

            $mission->save();
        }

        /*
         * Preserve every platform where the canonical mission
         * was detected.
         */
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

        /*
         * Merge stacks instead of replacing stacks discovered
         * by another source.
         */
        $stackIds = [];

        foreach ($data['stacks'] ?? [] as $stackName) {
            $stackName = trim((string) $stackName);

            if ($stackName === '') {
                continue;
            }

            $stack = Stack::firstOrCreate([
                'nom' => Str::lower($stackName),
            ]);

            $stackIds[] = $stack->id;
        }

        $mission->stacks()->syncWithoutDetaching($stackIds);

        $mission->refresh();

        $this->scoringService->calculerPourProfilsActifs($mission);

        return $mission->refresh();
    }

    private function normaliserTexte(?string $texte): string
    {
        return Str::lower(
            preg_replace(
                '/\s+/',
                ' ',
                trim($texte ?? '')
            )
        );
    }
}