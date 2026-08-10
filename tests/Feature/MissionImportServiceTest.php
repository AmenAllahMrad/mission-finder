<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\MissionSource;
use App\Models\Source;
use App\Services\MissionImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_mission_is_not_duplicated_and_status_is_preserved(): void
    {
        $source = $this->createSource(
            'RemoteOK',
            'App\\Scrapers\\RemoteOkParser'
        );

        $service = new MissionImportService();

        $data = $this->missionData();

        $mission1 = $service->importer($source, $data);

        $mission1->update([
            'statut' => 'interessant',
        ]);

        // Simulate refreshed source information.
        $data['description'] = 'Updated description';

        $mission2 = $service->importer($source, $data);

        $this->assertSame(
            $mission1->id,
            $mission2->id
        );

        $this->assertDatabaseCount('missions', 1);

        $this->assertDatabaseCount(
            'mission_sources',
            1
        );

        $mission2->refresh();

        $this->assertSame(
            'interessant',
            $mission2->statut
        );

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

        $service = new MissionImportService();

        $data = $this->missionData();

        $missionRemoteOk = $service->importer(
            $remoteOk,
            $data
        );

        $data['url_origine'] =
            'https://weworkremotely.com/remote-jobs/acme-laravel';

        $data['raw_data'] = [
            'source' => 'wwr',
        ];

        $missionWwr = $service->importer(
            $wwr,
            $data
        );

        $this->assertSame(
            $missionRemoteOk->id,
            $missionWwr->id
        );

        $this->assertDatabaseCount('missions', 1);

        $this->assertDatabaseCount(
            'mission_sources',
            2
        );

        $this->assertSame(
            2,
            $missionWwr->sourceOccurrences()->count()
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

        $service = new MissionImportService();

        $data = $this->missionData();

        $data['stacks'] = [
            'PHP',
            'Laravel',
        ];

        $mission = $service->importer(
            $remoteOk,
            $data
        );

        $data['url_origine'] =
            'https://weworkremotely.com/remote-jobs/acme-laravel';

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

        $this->assertSame(
            [
                'laravel',
                'php',
                'vue.js',
            ],
            $stacks
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
            'titre' => 'Senior Laravel Developer',
            'description' => 'Initial description',
            'entreprise' => 'Acme Corp',
            'tjm_min' => 300,
            'tjm_max' => 450,
            'remote_type' => 'full_remote',
            'localisation' => 'Europe',
            'secteur' => 'Software Development',
            'duree_mois' => 6,
            'date_publication' => '2026-08-10',
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