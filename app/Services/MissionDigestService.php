<?php

namespace App\Services;

use App\Mail\MissionDigestMail;
use App\Models\Alerte;
use App\Models\AlerteMissionEnvoyee;
use App\Models\Mission;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class MissionDigestService
{
    public function __construct(
        private MissionFilteringService $filteringService,
        private MissionScoringService $scoringService,
        private MissionAlertService $alertService
    ) {
    }

    public function traiterFrequence(
        string $frequence
    ): int {
        if (! in_array(
            $frequence,
            ['daily', 'weekly'],
            true
        )) {
            throw new InvalidArgumentException(
                'Digest frequency must be daily or weekly.'
            );
        }

        $alertes = Alerte::query()
            ->with('profilRecherche')
            ->where('actif', true)
            ->where('canal', 'email')
            ->where('frequence', $frequence)
            ->get();

        $emailsEnvoyes = 0;

        foreach ($alertes as $alerte) {
            $profil = $alerte->profilRecherche;

            if (! $profil || ! $profil->actif) {
                continue;
            }

            $missionsEligibles = collect();
            $scores = [];

            Mission::query()
                ->with('stacks')
                ->chunkById(
                    100,
                    function ($missions) use (
                        $alerte,
                        $profil,
                        $missionsEligibles,
                        &$scores
                    ) {
                        foreach ($missions as $mission) {

                            /*
                             * La mission doit satisfaire
                             * tous les filtres du profil.
                             */
                            if (
                                ! $this->filteringService
                                    ->correspond(
                                        $mission,
                                        $profil
                                    )
                            ) {
                                continue;
                            }

                            /*
                             * Calcul du score.
                             */
                            $score = $this->scoringService
                                ->calculer(
                                    $mission,
                                    $profil
                                );

                            if (
                                $score
                                < (int) $alerte->seuil_score_min
                            ) {
                                continue;
                            }

                            /*
                             * Ne pas inclure une mission
                             * déjà envoyée pour cette alerte.
                             */
                            $dejaEnvoyee =
                                AlerteMissionEnvoyee::query()
                                    ->where(
                                        'alerte_id',
                                        $alerte->id
                                    )
                                    ->where(
                                        'mission_id',
                                        $mission->id
                                    )
                                    ->exists();

                            if ($dejaEnvoyee) {
                                continue;
                            }

                            $missionsEligibles->push(
                                $mission
                            );

                            $scores[$mission->id] =
                                $score;
                        }
                    }
                );

            /*
             * Aucun email si aucune nouvelle
             * mission éligible.
             */
            if ($missionsEligibles->isEmpty()) {
                continue;
            }

            /*
             * Un seul email contient toutes
             * les missions du digest.
             */
            Mail::to($alerte->destination)
                ->send(
                    new MissionDigestMail(
                        alerte: $alerte,
                        missions: $missionsEligibles,
                        scores: $scores
                    )
                );

            /*
             * On marque les missions comme envoyées
             * seulement après le succès de l'email.
             */
            foreach ($missionsEligibles as $mission) {
                $this->alertService
                    ->marquerCommeEnvoyee(
                        $alerte,
                        $mission
                    );
            }

            $emailsEnvoyes++;
        }

        return $emailsEnvoyes;
    }
}