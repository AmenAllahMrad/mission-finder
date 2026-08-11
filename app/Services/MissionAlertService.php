<?php

namespace App\Services;

use App\Models\Alerte;
use App\Models\AlerteMissionEnvoyee;
use App\Models\Mission;
use Illuminate\Support\Collection;

class MissionAlertService
{
    public function __construct(
        private MissionFilteringService $filteringService,
        private MissionScoringService $scoringService
    ) {
    }

    /**
     * Retourne les alertes actives auxquelles
     * cette mission est éligible.
     */
    public function alertesEligibles(Mission $mission): Collection
    {
        return Alerte::query()
            ->with([
                'profilRecherche.reglesFiltrage.critere',
                'profilRecherche.reglesScoring.critere',
            ])
            ->where('actif', true)
            ->get()
            ->filter(function (Alerte $alerte) use ($mission) {
                $profil = $alerte->profilRecherche;

                if (! $profil || ! $profil->actif) {
                    return false;
                }

                /*
                 * Une mission doit d'abord respecter
                 * TOUS les filtres du profil.
                 */
                if (
                    ! $this->filteringService->correspond(
                        $mission,
                        $profil
                    )
                ) {
                    return false;
                }

                /*
                 * Calcul / mise à jour du score
                 * pour ce profil.
                 */
                $score = $this->scoringService->calculer(
                    $mission,
                    $profil
                );

                /*
                 * Vérification du seuil minimal.
                 */
                if (
                    $score < (int) $alerte->seuil_score_min
                ) {
                    return false;
                }

                /*
                 * Ne jamais proposer une alerte
                 * déjà envoyée pour cette mission.
                 */
                $dejaEnvoyee = AlerteMissionEnvoyee::query()
                    ->where('alerte_id', $alerte->id)
                    ->where('mission_id', $mission->id)
                    ->exists();

                return ! $dejaEnvoyee;
            })
            ->values();
    }

    /**
     * Marque une alerte comme effectivement envoyée.
     *
     * Cette méthode sera appelée seulement APRES
     * l'envoi réel réussi.
     */
    public function marquerCommeEnvoyee(
        Alerte $alerte,
        Mission $mission
    ): AlerteMissionEnvoyee {
        return AlerteMissionEnvoyee::firstOrCreate(
            [
                'alerte_id' => $alerte->id,
                'mission_id' => $mission->id,
            ],
            [
                'envoyee_le' => now(),
            ]
        );
    }
}