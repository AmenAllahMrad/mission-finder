<?php

namespace App\Services;

use App\Models\Mission;
use App\Models\ProfilRecherche;
use App\Models\ScoreMissionProfil;

class MissionScoringService
{
    public function __construct(
        private MissionFilteringService $filteringService
    ) {
    }

    public function calculer(
        Mission $mission,
        ProfilRecherche $profil
    ): int {
        $profil->loadMissing([
            'reglesFiltrage.critere',
            'reglesScoring.critere',
        ]);

        $mission->loadMissing('stacks');

        $score = 0;

        foreach ($profil->reglesScoring as $regleScoring) {
            /*
             * La condition à tester vient de la règle de filtrage
             * portant sur le même critère.
             */
            $regleFiltrage = $profil->reglesFiltrage
                ->firstWhere(
                    'critere_id',
                    $regleScoring->critere_id
                );

            if (! $regleFiltrage) {
                continue;
            }

            if (
                $this->filteringService->respecteRegle(
                    $mission,
                    $regleFiltrage
                )
            ) {
                $score += (int) $regleScoring->poids;
            }
        }

        ScoreMissionProfil::updateOrCreate(
            [
                'mission_id' => $mission->id,
                'profil_recherche_id' => $profil->id,
            ],
            [
                'score' => $score,
                'calcule_le' => now(),
            ]
        );

        return $score;
    }

    public function calculerPourProfilsActifs(
        Mission $mission
    ): void {
        ProfilRecherche::query()
            ->where('actif', true)
            ->each(function (ProfilRecherche $profil) use ($mission) {
                $this->calculer($mission, $profil);
            });
    }
}