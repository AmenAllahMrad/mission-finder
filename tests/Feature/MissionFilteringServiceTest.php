<?php

namespace Tests\Feature;

use App\Models\Critere;
use App\Models\Mission;
use App\Models\ProfilRecherche;
use App\Models\RegleFiltrage;
use App\Models\Source;
use App\Models\Stack;
use App\Services\MissionFilteringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionFilteringServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_mission_matches_when_all_profile_rules_are_satisfied(): void
    {
        $profil = $this->createLaravelRemoteProfile();

        $mission = $this->createMission(
            tjmMin: 300,
            remoteType: 'full_remote',
            stacks: ['PHP', 'Laravel']
        );

        $service = new MissionFilteringService();

        $this->assertTrue(
            $service->correspond($mission, $profil)
        );
    }

    public function test_mission_does_not_match_when_stack_rule_fails(): void
    {
        $profil = $this->createLaravelRemoteProfile();

        $mission = $this->createMission(
            tjmMin: 300,
            remoteType: 'full_remote',
            stacks: ['PHP', 'Symfony']
        );

        $service = new MissionFilteringService();

        $this->assertFalse(
            $service->correspond($mission, $profil)
        );
    }

    public function test_mission_does_not_match_when_tjm_is_too_low(): void
    {
        $profil = $this->createLaravelRemoteProfile();

        $mission = $this->createMission(
            tjmMin: 200,
            remoteType: 'full_remote',
            stacks: ['Laravel']
        );

        $service = new MissionFilteringService();

        $this->assertFalse(
            $service->correspond($mission, $profil)
        );
    }

    public function test_mission_does_not_match_when_remote_type_is_wrong(): void
    {
        $profil = $this->createLaravelRemoteProfile();

        $mission = $this->createMission(
            tjmMin: 350,
            remoteType: 'hybrid',
            stacks: ['Laravel']
        );

        $service = new MissionFilteringService();

        $this->assertFalse(
            $service->correspond($mission, $profil)
        );
    }

    public function test_stack_matching_is_case_insensitive(): void
    {
        $profil = $this->createLaravelRemoteProfile();

        $mission = $this->createMission(
            tjmMin: 300,
            remoteType: 'FULL_REMOTE',
            stacks: ['LARAVEL']
        );

        $service = new MissionFilteringService();

        $this->assertTrue(
            $service->correspond($mission, $profil)
        );
    }

    private function createLaravelRemoteProfile(): ProfilRecherche
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

        return $profil;
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
            'parser_class' => 'App\\Scrapers\\RemoteOkParser',
            'frequence_polling_minutes' => 60,
            'actif' => true,
        ]);

        $mission = Mission::create([
            'source_id' => $source->id,
            'titre' => 'Senior Laravel Developer',
            'description' => 'Test mission',
            'entreprise' => 'Acme Corp',
            'tjm_min' => $tjmMin,
            'tjm_max' => 500,
            'remote_type' => $remoteType,
            'localisation' => 'Europe',
            'secteur' => 'Software Development',
            'duree_mois' => 6,
            'date_publication' => '2026-08-10',
            'url_origine' => 'https://example.test/job/' . uniqid(),
            'hash_unique' => md5(uniqid()),
            'raw_data' => [],
        ]);

        foreach ($stacks as $stackName) {
            $stack = Stack::firstOrCreate([
                'nom' => $stackName,
            ]);

            $mission->stacks()->attach($stack->id);
        }

        return $mission;
    }
}