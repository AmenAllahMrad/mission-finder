<?php

namespace Tests\Feature;

use App\Models\Critere;
use App\Models\Mission;
use App\Models\ProfilRecherche;
use App\Models\RegleFiltrage;
use App\Models\RegleScoring;
use App\Models\ScoreMissionProfil;
use App\Models\Source;
use App\Models\Stack;
use App\Services\MissionScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_score_is_sum_of_matching_rules(): void
    {
        $profil = $this->createProfil();

        $mission = $this->createMission(
            remoteType: 'full_remote',
            stacks: ['Laravel', 'PHP']
        );

        $service = app(MissionScoringService::class);

        $score = $service->calculer($mission, $profil);

        $this->assertSame(5, $score);

        $this->assertDatabaseHas('scores_missions_profils', [
            'mission_id' => $mission->id,
            'profil_recherche_id' => $profil->id,
            'score' => 5,
        ]);
    }

    public function test_only_matching_rules_contribute_to_score(): void
    {
        $profil = $this->createProfil();

        $mission = $this->createMission(
            remoteType: 'hybrid',
            stacks: ['Laravel']
        );

        $service = app(MissionScoringService::class);

        $score = $service->calculer($mission, $profil);

        /*
         * Laravel = +3
         * full_remote = 0
         */
        $this->assertSame(3, $score);
    }

    public function test_score_can_be_zero_when_no_scoring_rule_matches(): void
    {
        $profil = $this->createProfil();

        $mission = $this->createMission(
            remoteType: 'hybrid',
            stacks: ['Symfony']
        );

        $service = app(MissionScoringService::class);

        $score = $service->calculer($mission, $profil);

        $this->assertSame(0, $score);

        $this->assertDatabaseHas('scores_missions_profils', [
            'mission_id' => $mission->id,
            'profil_recherche_id' => $profil->id,
            'score' => 0,
        ]);
    }

    public function test_recalculation_updates_existing_score_without_creating_duplicate(): void
    {
        $profil = $this->createProfil();

        $mission = $this->createMission(
            remoteType: 'full_remote',
            stacks: ['Laravel']
        );

        $service = app(MissionScoringService::class);

        $firstScore = $service->calculer(
            $mission,
            $profil
        );

        $this->assertSame(5, $firstScore);

        /*
         * The mission is no longer full remote.
         * Laravel still matches, so score should become 3.
         */
        $mission->update([
            'remote_type' => 'hybrid',
        ]);

        $mission->refresh();

        $secondScore = $service->calculer(
            $mission,
            $profil
        );

        $this->assertSame(3, $secondScore);

        $this->assertDatabaseCount(
            'scores_missions_profils',
            1
        );

        $this->assertDatabaseHas(
            'scores_missions_profils',
            [
                'mission_id' => $mission->id,
                'profil_recherche_id' => $profil->id,
                'score' => 3,
            ]
        );
    }

    public function test_same_mission_can_have_different_scores_for_different_profiles(): void
    {
        $profil1 = $this->createProfil(
            'Laravel Remote'
        );

        $profil2 = $this->createProfil(
            'Laravel Remote Secondary'
        );

        /*
         * Change weights of the second profile.
         */
        $profil2->reglesScoring()
            ->whereHas('critere', function ($query) {
                $query->where('code', 'stack');
            })
            ->update([
                'poids' => 10,
            ]);

        $profil2->reglesScoring()
            ->whereHas('critere', function ($query) {
                $query->where('code', 'remote');
            })
            ->update([
                'poids' => 4,
            ]);

        $mission = $this->createMission(
            remoteType: 'full_remote',
            stacks: ['Laravel']
        );

        $service = app(MissionScoringService::class);

        $scoreProfil1 = $service->calculer(
            $mission,
            $profil1
        );

        $scoreProfil2 = $service->calculer(
            $mission,
            $profil2
        );

        $this->assertSame(5, $scoreProfil1);
        $this->assertSame(14, $scoreProfil2);

        $this->assertDatabaseCount(
            'scores_missions_profils',
            2
        );
    }

    private function createProfil(
        string $nom = 'Laravel Remote'
    ): ProfilRecherche {
        $profil = ProfilRecherche::create([
            'nom' => $nom,
            'actif' => true,
        ]);

        $stack = Critere::firstOrCreate(
            [
                'code' => 'stack',
            ],
            [
                'label' => 'Stack technique',
                'type' => 'texte',
            ]
        );

        $remote = Critere::firstOrCreate(
            [
                'code' => 'remote',
            ],
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

        return $profil;
    }

    private function createMission(
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
            'tjm_min' => 300,
            'tjm_max' => 450,
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

            $mission->stacks()->attach(
                $stack->id
            );
        }

        return $mission;
    }
}