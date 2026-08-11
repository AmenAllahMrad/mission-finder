<?php

namespace App\Services;

use App\Mail\MissionAlertMail;
use App\Models\Alerte;
use App\Models\Mission;
use Illuminate\Support\Facades\Mail;

class MissionAlertDispatcherService
{
    public function __construct(
        private MissionAlertService $alertService,
        private MissionScoringService $scoringService
    ) {
    }

    public function traiter(Mission $mission): int
    {
        $mission->loadMissing('stacks');

        $alertes = $this->alertService
            ->alertesEligibles($mission);

        $envoyees = 0;

        foreach ($alertes as $alerte) {

            /*
             * Pour l'instant F8 commence par
             * les notifications immédiates.
             *
             * daily / weekly seront traitées ensuite
             * par le scheduler.
             */
            if ($alerte->frequence !== 'immediate') {
                continue;
            }

            if ($alerte->canal !== 'email') {
                continue;
            }

            $profil = $alerte->profilRecherche;

            if (! $profil) {
                continue;
            }

            $score = $this->scoringService->calculer(
                $mission,
                $profil
            );

            $this->envoyerEmail(
                $alerte,
                $mission,
                $score
            );

            /*
             * Seulement APRÈS réussite de Mail::send().
             */
            $this->alertService->marquerCommeEnvoyee(
                $alerte,
                $mission
            );

            $envoyees++;
        }

        return $envoyees;
    }

    private function envoyerEmail(
        Alerte $alerte,
        Mission $mission,
        int $score
    ): void {
        Mail::to($alerte->destination)
            ->send(
                new MissionAlertMail(
                    mission: $mission,
                    profil: $alerte->profilRecherche,
                    score: $score
                )
            );
    }
}