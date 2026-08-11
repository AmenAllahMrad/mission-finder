<?php

namespace Tests\Feature;

use App\Jobs\ProcessMissionAlertsJob;
use App\Models\Critere;
use App\Models\Mission;
use App\Models\ProfilRecherche;
use App\Models\RegleFiltrage;
use App\Models\RegleScoring;
use App\Models\Source;
use App\Services\MissionImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MissionImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Empêche les jobs de queue de s'exécuter réellement
         * pendant les tests.
         */
        Queue::fake();
    }

    public function test_same_mission_is_not_duplicated_and_status_is_preserved(): void
    {
        $source = $this->createSource(
            'RemoteOK',
            'App\\Scrapers\\RemoteOkParser'
        );

        $service = app(MissionImportService::class);

        $data = $this->missionData();

        $mission1 = $service->importer(
            $source,
            $data
        );

        $mission1->update([
            'statut' => 'interessant',
        ]);

        /*
         * Simule une nouvelle collecte de la même mission
         * avec une description mise à jour.
         */
        $data['description'] = 'Updated description';

        $mission2 = $service->importer(
            $source,
            $data
        );

        /*
         * Même mission :
         * l'identifiant ne doit pas changer.
         */
        $this->assertSame(
            $mission1->id,
            $mission2->id
        );

        $this->assertDatabaseCount(
            'missions',
            1
        );

        /*
         * Même source + même mission :
         * une seule occurrence source.
         */
        $this->assertDatabaseCount(
            'mission_sources',
            1
        );

        $mission2->refresh();

        /*
         * Le statut manuel doit être conservé.
         */
        $this->assertSame(
            'interessant',
            $mission2->statut
        );

        /*
         * Les informations de la mission peuvent
         * néanmoins être actualisées.
         */
        $this->assertSame(
            'Updated description',
            $mission2->description
        );
    }

    public function test_same_mission_from_two_sources_has_one_canonical_mission(): void
    {
        $remoteOk = $this->createSource(
            'RemoteOK',
            'App\\Scrapers\\RemoteOkParser'
        );

        $wwr = $this->createSource(
            'We Work Remotely',
            'App\\Scrapers\\WeWorkRemotelyParser',
            'rss'
        );

        $service = app(MissionImportService::class);

        $data = $this->missionData();

        /*
         * Première apparition :
         * RemoteOK.
         */
        $missionRemoteOk = $service->importer(
            $remoteOk,
            $data
        );

        /*
         * Même titre + même entreprise,
         * mais provenant de WWR.
         */
        $data['url_origine'] =
            'https://weworkremotely.com/remote-jobs/acme-laravel';

        $data['raw_data'] = [
            'source' => 'wwr',
        ];

        $missionWwr = $service->importer(
            $wwr,
            $data
        );

        /*
         * Les deux sources doivent pointer vers
         * la même mission canonique.
         */
        $this->assertSame(
            $missionRemoteOk->id,
            $missionWwr->id
        );

        $this->assertDatabaseCount(
            'missions',
            1
        );

        /*
         * Mais on doit conserver les deux occurrences :
         *
         * RemoteOK
         * WWR
         */
        $this->assertDatabaseCount(
            'mission_sources',
            2
        );

        $this->assertSame(
            2,
            $missionWwr
                ->sourceOccurrences()
                ->count()
        );
    }

    public function test_stacks_from_multiple_sources_are_merged(): void
    {
        $remoteOk = $this->createSource(
            'RemoteOK',
            'App\\Scrapers\\RemoteOkParser'
        );

        $wwr = $this->createSource(
            'We Work Remotely',
            'App\\Scrapers\\WeWorkRemotelyParser',
            'rss'
        );

        $service = app(MissionImportService::class);

        /*
         * RemoteOK détecte PHP + Laravel.
         */
        $data = $this->missionData();

        $data['stacks'] = [
            'PHP',
            'Laravel',
        ];

        $mission = $service->importer(
            $remoteOk,
            $data
        );

        /*
         * WWR détecte Laravel + Vue.js
         * pour la même mission.
         */
        $data['url_origine'] =
            'https://weworkremotely.com/remote-jobs/acme-laravel';

        $data['raw_data'] = [
            'source' => 'wwr',
        ];

        $data['stacks'] = [
            'Vue.js',
            'Laravel',
        ];

        $mission = $service->importer(
            $wwr,
            $data
        );

        $stacks = $mission
            ->stacks()
            ->pluck('nom')
            ->sort()
            ->values()
            ->all();

        /*
         * Laravel ne doit pas être dupliqué
         * et PHP ne doit pas disparaître.
         */
        $this->assertSame(
            [
                'laravel',
                'php',
                'vue.js',
            ],
            $stacks
        );
    }

    public function test_imported_mission_is_scored_automatically_for_active_profiles(): void
    {
        $source = $this->createSource(
            'RemoteOK',
            'App\\Scrapers\\RemoteOkParser'
        );

        /*
         * Création des critères.
         */
        $stackCritere = Critere::create([
            'code' => 'stack',
            'label' => 'Stack technique',
            'type' => 'texte',
        ]);

        $remoteCritere = Critere::create([
            'code' => 'remote',
            'label' => 'Type de remote',
            'type' => 'liste',
        ]);

        /*
         * Profil actif.
         */
        $profil = ProfilRecherche::create([
            'nom' => 'Laravel Remote',
            'actif' => true,
        ]);

        /*
         * Filtrage :
         * stack contient Laravel
         */
        RegleFiltrage::create([
            'profil_recherche_id' => $profil->id,
            'critere_id' => $stackCritere->id,
            'operateur' => 'contient',
            'valeur' => 'Laravel',
        ]);

        /*
         * Filtrage :
         * remote = full_remote
         */
        RegleFiltrage::create([
            'profil_recherche_id' => $profil->id,
            'critere_id' => $remoteCritere->id,
            'operateur' => 'egal',
            'valeur' => 'full_remote',
        ]);

        /*
         * Scoring :
         * Laravel = +3
         */
        RegleScoring::create([
            'profil_recherche_id' => $profil->id,
            'critere_id' => $stackCritere->id,
            'poids' => 3,
        ]);

        /*
         * Scoring :
         * full_remote = +2
         */
        RegleScoring::create([
            'profil_recherche_id' => $profil->id,
            'critere_id' => $remoteCritere->id,
            'poids' => 2,
        ]);

        $service = app(
            MissionImportService::class
        );

        $mission = $service->importer(
            $source,
            $this->missionData()
        );

        /*
         * Laravel +3
         * full_remote +2
         *
         * Score attendu = 5
         */
        $this->assertDatabaseHas(
            'scores_missions_profils',
            [
                'mission_id' => $mission->id,
                'profil_recherche_id' => $profil->id,
                'score' => 5,
            ]
        );
    }

    public function test_import_dispatches_alert_processing_job(): void
    {
        $source = $this->createSource(
            'RemoteOK',
            'App\\Scrapers\\RemoteOkParser'
        );

        $service = app(
            MissionImportService::class
        );

        $mission = $service->importer(
            $source,
            $this->missionData()
        );

        /*
         * Après l'import et le scoring,
         * MissionFinder doit envoyer un job
         * de traitement des alertes dans la queue.
         */
        Queue::assertPushed(
            ProcessMissionAlertsJob::class,
            function (
                ProcessMissionAlertsJob $job
            ) use ($mission) {
                return $job->missionId
                    === $mission->id;
            }
        );
    }

    private function createSource(
        string $nom,
        string $parserClass,
        string $type = 'api'
    ): Source {
        return Source::create([
            'nom' => $nom,
            'type' => $type,
            'url_base' => 'https://example.test',
            'parser_class' => $parserClass,
            'frequence_polling_minutes' => 60,
            'actif' => true,
        ]);
    }

    private function missionData(): array
    {
        return [
            'titre' =>
                'Senior Laravel Developer',

            'description' =>
                'Initial description',

            'entreprise' =>
                'Acme Corp',

            'tjm_min' => 300,

            'tjm_max' => 450,

            'remote_type' =>
                'full_remote',

            'localisation' =>
                'Europe',

            'secteur' =>
                'Software Development',

            'duree_mois' => 6,

            'date_publication' =>
                '2026-08-10',

            'url_origine' =>
                'https://remoteok.com/remote-jobs/acme-laravel',

            'raw_data' => [
                'source' => 'remoteok',
            ],

            'stacks' => [
                'PHP',
                'Laravel',
            ],
        ];
    }
}