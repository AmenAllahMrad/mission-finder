<?php

namespace Tests\Feature;

use App\Mail\MissionAlertMail;
use App\Models\Alerte;
use App\Models\Critere;
use App\Models\Mission;
use App\Models\ProfilRecherche;
use App\Models\RegleFiltrage;
use App\Models\RegleScoring;
use App\Models\Source;
use App\Models\Stack;
use App\Services\MissionAlertDispatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MissionAlertDispatcherServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_eligible_immediate_email_alert_is_sent_and_recorded(): void
    {
        Mail::fake();

        [$profil, $alerte] = $this->createProfileAndAlert();

        $mission = $this->createMission();

        $service = app(
            MissionAlertDispatcherService::class
        );

        $envoyees = $service->traiter($mission);

        $this->assertSame(1, $envoyees);

        Mail::assertSent(
            MissionAlertMail::class,
            function (MissionAlertMail $mail) use (
                $alerte,
                $mission
            ) {
                return $mail->hasTo($alerte->destination)
                    && $mail->mission->id === $mission->id
                    && $mail->score === 5;
            }
        );

        $this->assertDatabaseHas(
            'alertes_missions_envoyees',
            [
                'alerte_id' => $alerte->id,
                'mission_id' => $mission->id,
            ]
        );
    }

    public function test_same_alert_is_not_sent_twice(): void
    {
        Mail::fake();

        [$profil, $alerte] = $this->createProfileAndAlert();

        $mission = $this->createMission();

        $service = app(
            MissionAlertDispatcherService::class
        );

        $service->traiter($mission);

        $service->traiter($mission);

        Mail::assertSent(
            MissionAlertMail::class,
            1
        );

        $this->assertDatabaseCount(
            'alertes_missions_envoyees',
            1
        );
    }

    private function createProfileAndAlert(): array
    {
        $profil = ProfilRecherche::create([
            'nom' => 'Laravel Remote',
            'actif' => true,
        ]);

        $stack = Critere::create([
            'code' => 'stack',
            'label' => 'Stack technique',
            'type' => 'texte',
        ]);

        $tjm = Critere::create([
            'code' => 'tjm_min',
            'label' => 'TJM minimum',
            'type' => 'nombre',
        ]);

        $remote = Critere::create([
            'code' => 'remote',
            'label' => 'Type de remote',
            'type' => 'liste',
        ]);

        RegleFiltrage::create([
            'profil_recherche_id' => $profil->id,
            'critere_id' => $stack->id,
            'operateur' => 'contient',
            'valeur' => 'Laravel',
        ]);

        RegleFiltrage::create([
            'profil_recherche_id' => $profil->id,
            'critere_id' => $tjm->id,
            'operateur' => 'superieur_egal',
            'valeur' => '250',
        ]);

        RegleFiltrage::create([
            'profil_recherche_id' => $profil->id,
            'critere_id' => $remote->id,
            'operateur' => 'egal',
            'valeur' => 'full_remote',
        ]);

        RegleScoring::create([
            'profil_recherche_id' => $profil->id,
            'critere_id' => $stack->id,
            'poids' => 3,
        ]);

        RegleScoring::create([
            'profil_recherche_id' => $profil->id,
            'critere_id' => $remote->id,
            'poids' => 2,
        ]);

        $alerte = Alerte::create([
            'profil_recherche_id' => $profil->id,
            'canal' => 'email',
            'destination' => 'missionfinder@example.com',
            'frequence' => 'immediate',
            'seuil_score_min' => 5,
            'actif' => true,
        ]);

        return [$profil, $alerte];
    }

    private function createMission(): Mission
    {
        $source = Source::create([
            'nom' => 'Test Source',
            'type' => 'api',
            'url_base' => 'https://example.test',
            'parser_class' =>
                'App\\Scrapers\\RemoteOkParser',
            'frequence_polling_minutes' => 60,
            'actif' => true,
        ]);

        $mission = Mission::create([
            'source_id' => $source->id,
            'titre' => 'Senior Laravel Developer',
            'description' =>
                'Laravel backend development.',
            'entreprise' => 'Acme Corp',
            'tjm_min' => 300,
            'tjm_max' => 450,
            'remote_type' => 'full_remote',
            'localisation' => 'Europe',
            'secteur' => 'Software Development',
            'duree_mois' => 6,
            'date_publication' => '2026-08-11',
            'url_origine' =>
                'https://example.test/laravel-job',
            'hash_unique' => md5(uniqid()),
            'raw_data' => [],
        ]);

        $stack = Stack::create([
            'nom' => 'Laravel',
        ]);

        $mission->stacks()->attach(
            $stack->id
        );

        return $mission;
    }
}