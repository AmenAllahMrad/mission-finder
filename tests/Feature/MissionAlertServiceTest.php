<?php

namespace Tests\Feature;

use App\Models\Alerte;
use App\Models\AlerteMissionEnvoyee;
use App\Models\Critere;
use App\Models\Mission;
use App\Models\ProfilRecherche;
use App\Models\RegleFiltrage;
use App\Models\RegleScoring;
use App\Models\Source;
use App\Models\Stack;
use App\Services\MissionAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_mission_above_threshold_is_eligible_for_alert(): void
    {
        [$profil, $alerte] = $this->createProfileAndAlert();

        $mission = $this->createMission(
            tjmMin: 300,
            remoteType: 'full_remote',
            stacks: ['Laravel']
        );

        $service = app(MissionAlertService::class);

        $alertes = $service->alertesEligibles($mission);

        $this->assertCount(1, $alertes);

        $this->assertSame(
            $alerte->id,
            $alertes->first()->id
        );
    }

    public function test_mission_failing_filter_is_not_eligible(): void
    {
        [$profil, $alerte] = $this->createProfileAndAlert();

        $mission = $this->createMission(
            tjmMin: 200,
            remoteType: 'full_remote',
            stacks: ['Laravel']
        );

        $service = app(MissionAlertService::class);

        $alertes = $service->alertesEligibles($mission);

        $this->assertCount(0, $alertes);
    }

    public function test_mission_below_score_threshold_is_not_eligible(): void
    {
        [$profil, $alerte] = $this->createProfileAndAlert(
            seuil: 6
        );

        $mission = $this->createMission(
            tjmMin: 300,
            remoteType: 'full_remote',
            stacks: ['Laravel']
        );

        /*
         * Laravel +3
         * full_remote +2
         * score = 5
         * threshold = 6
         */
        $service = app(MissionAlertService::class);

        $alertes = $service->alertesEligibles($mission);

        $this->assertCount(0, $alertes);
    }

    public function test_inactive_alert_is_not_eligible(): void
    {
        [$profil, $alerte] = $this->createProfileAndAlert();

        $alerte->update([
            'actif' => false,
        ]);

        $mission = $this->createMission(
            tjmMin: 300,
            remoteType: 'full_remote',
            stacks: ['Laravel']
        );

        $service = app(MissionAlertService::class);

        $this->assertCount(
            0,
            $service->alertesEligibles($mission)
        );
    }

    public function test_already_sent_alert_is_not_proposed_again(): void
    {
        [$profil, $alerte] = $this->createProfileAndAlert();

        $mission = $this->createMission(
            tjmMin: 300,
            remoteType: 'full_remote',
            stacks: ['Laravel']
        );

        AlerteMissionEnvoyee::create([
            'alerte_id' => $alerte->id,
            'mission_id' => $mission->id,
            'envoyee_le' => now(),
        ]);

        $service = app(MissionAlertService::class);

        $this->assertCount(
            0,
            $service->alertesEligibles($mission)
        );
    }

    public function test_marking_alert_as_sent_does_not_create_duplicate(): void
    {
        [$profil, $alerte] = $this->createProfileAndAlert();

        $mission = $this->createMission(
            tjmMin: 300,
            remoteType: 'full_remote',
            stacks: ['Laravel']
        );

        $service = app(MissionAlertService::class);

        $first = $service->marquerCommeEnvoyee(
            $alerte,
            $mission
        );

        $second = $service->marquerCommeEnvoyee(
            $alerte,
            $mission
        );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertDatabaseCount(
            'alertes_missions_envoyees',
            1
        );
    }

    private function createProfileAndAlert(
        int $seuil = 5
    ): array {
        $profil = ProfilRecherche::create([
            'nom' => 'Laravel Remote',
            'actif' => true,
        ]);

        $stack = Critere::firstOrCreate(
            ['code' => 'stack'],
            [
                'label' => 'Stack technique',
                'type' => 'texte',
            ]
        );

        $tjm = Critere::firstOrCreate(
            ['code' => 'tjm_min'],
            [
                'label' => 'TJM minimum',
                'type' => 'nombre',
            ]
        );

        $remote = Critere::firstOrCreate(
            ['code' => 'remote'],
            [
                'label' => 'Type de remote',
                'type' => 'liste',
            ]
        );

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
            'destination' => 'test@example.com',
            'frequence' => 'immediate',
            'seuil_score_min' => $seuil,
            'actif' => true,
        ]);

        return [
            $profil,
            $alerte,
        ];
    }

    private function createMission(
        float $tjmMin,
        string $remoteType,
        array $stacks
    ): Mission {
        $source = Source::create([
            'nom' => 'Test Source ' . uniqid(),
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
            'description' => 'Laravel backend development.',
            'entreprise' => 'Acme Corp',
            'tjm_min' => $tjmMin,
            'tjm_max' => 450,
            'remote_type' => $remoteType,
            'localisation' => 'Europe',
            'secteur' => 'Software Development',
            'duree_mois' => 6,
            'date_publication' => '2026-08-11',
            'url_origine' =>
                'https://example.test/job/' . uniqid(),
            'hash_unique' => md5(uniqid()),
            'raw_data' => [],
        ]);

        foreach ($stacks as $stackName) {
            $stack = Stack::firstOrCreate([
                'nom' => $stackName,
            ]);

            $mission->stacks()->attach(
                $stack->id
            );
        }

        return $mission;
    }
}