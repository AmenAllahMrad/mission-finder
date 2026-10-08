<?php

namespace App\Services;

use App\Mail\MissionAlertMail;
use App\Models\Alerte;
use App\Models\Mission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class MissionAlertDispatcherService
{
    public function __construct(
        private MissionAlertService $alertService,
        private MissionScoringService $scoringService
    ) {
    }

    /**
     * Traite toutes les alertes immédiates
     * éligibles pour une mission.
     */
    public function traiter(Mission $mission): int
    {
        $mission->loadMissing('stacks');

        $alertes = $this->alertService
            ->alertesEligibles($mission);

        $envoyees = 0;

        foreach ($alertes as $alerte) {
            /*
             * Daily / weekly sont traitées
             * séparément par le scheduler.
             */
            if ($alerte->frequence !== 'immediate') {
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

            /*
             * Le canal détermine le mode d'envoi.
             */
            switch ($alerte->canal) {
                case 'email':
                    $this->envoyerEmail(
                        $alerte,
                        $mission,
                        $score
                    );
                    break;

                case 'telegram':
                    $this->envoyerTelegram(
                        $alerte,
                        $mission,
                        $score
                    );
                    break;

                case 'webhook':
                    $this->envoyerWebhook(
                        $alerte,
                        $mission,
                        $score
                    );
                    break;

                default:
                    continue 2;
            }

            /*
             * IMPORTANT :
             * on marque l'alerte comme envoyée
             * uniquement après un envoi réussi.
             */
            $this->alertService->marquerCommeEnvoyee(
                $alerte,
                $mission
            );

            $envoyees++;
        }

        return $envoyees;
    }

    /**
     * Envoi Email.
     */
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

    /**
     * Envoi Telegram.
     *
     * La destination de l'alerte correspond au chat_id Telegram.
     */
    private function envoyerTelegram(
        Alerte $alerte,
        Mission $mission,
        int $score
    ): void {
        $token = config(
            'services.telegram.bot_token'
        );

        if (empty($token)) {
            throw new RuntimeException(
                'TELEGRAM_BOT_TOKEN n\'est pas configuré.'
            );
        }

        if (empty($alerte->destination)) {
            throw new RuntimeException(
                'La destination Telegram est vide.'
            );
        }

        $message = $this->construireMessageTelegram(
            $mission,
            $alerte,
            $score
        );

        Http::timeout(10)
            ->post(
                "https://api.telegram.org/bot{$token}/sendMessage",
                [
                    'chat_id' => $alerte->destination,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => false,
                ]
            )
            ->throw();
    }

    /**
     * Construit le message Telegram.
     */
    private function construireMessageTelegram(
        Mission $mission,
        Alerte $alerte,
        int $score
    ): string {
        $titre = e(
            $mission->titre ?? 'Mission sans titre'
        );

        $entreprise = e(
            $mission->entreprise ?? 'Entreprise non renseignée'
        );

        $remote = e(
            $mission->remote_type ?? 'Non précisé'
        );

        $localisation = e(
            $mission->localisation ?? 'Non précisée'
        );

        $profil = e(
            $alerte->profilRecherche?->nom
            ?? 'Profil'
        );

        $url = $mission->url_origine;

        $tjm = $this->formaterTjm($mission);

        return
            "🚀 <b>Nouvelle mission détectée</b>\n\n"
            . "<b>{$titre}</b>\n\n"
            . "🏢 <b>Entreprise :</b> {$entreprise}\n"
            . "💰 <b>TJM :</b> {$tjm}\n"
            . "🌐 <b>Remote :</b> {$remote}\n"
            . "📍 <b>Localisation :</b> {$localisation}\n"
            . "⭐ <b>Score :</b> {$score}\n"
            . "🎯 <b>Profil :</b> {$profil}\n\n"
            . "🔗 <a href=\"{$url}\">Voir la mission</a>";
    }

    /**
     * Envoi Webhook.
     */
    private function envoyerWebhook(
        Alerte $alerte,
        Mission $mission,
        int $score
    ): void {
        if (empty($alerte->destination)) {
            throw new RuntimeException(
                'La destination Webhook est vide.'
            );
        }

        $profil = $alerte->profilRecherche;

        Http::timeout(10)
            ->acceptJson()
            ->post(
                $alerte->destination,
                [
                    'event' => 'mission.alert',

                    'mission' => [
                        'id' => $mission->id,
                        'title' => $mission->titre,
                        'company' => $mission->entreprise,
                        'tjm_min' => $mission->tjm_min,
                        'tjm_max' => $mission->tjm_max,
                        'remote_type' => $mission->remote_type,
                        'location' => $mission->localisation,
                        'sector' => $mission->secteur,
                        'duration_months' => $mission->duree_mois,
                        'publication_date' => $mission->date_publication,
                        'status' => $mission->statut,
                        'score' => $score,
                        'url' => $mission->url_origine,
                    ],

                    'profile' => [
                        'id' => $profil?->id,
                        'name' => $profil?->nom,
                    ],

                    'alert' => [
                        'id' => $alerte->id,
                        'channel' => $alerte->canal,
                        'frequency' => $alerte->frequence,
                        'minimum_score' => $alerte->seuil_score_min,
                    ],

                    'sent_at' => now()->toIso8601String(),
                ]
            )
            ->throw();
    }

    /**
     * Format TJM.
     */
    private function formaterTjm(Mission $mission): string
    {
        if (
            $mission->tjm_min !== null
            && $mission->tjm_max !== null
        ) {
            return number_format(
                (float) $mission->tjm_min,
                0,
                ',',
                ' '
            )
            . ' – '
            . number_format(
                (float) $mission->tjm_max,
                0,
                ',',
                ' '
            )
            . ' €';
        }

        if ($mission->tjm_min !== null) {
            return number_format(
                (float) $mission->tjm_min,
                0,
                ',',
                ' '
            ) . ' €';
        }

        if ($mission->tjm_max !== null) {
            return number_format(
                (float) $mission->tjm_max,
                0,
                ',',
                ' '
            ) . ' €';
        }

        return 'Non précisé';
    }
}