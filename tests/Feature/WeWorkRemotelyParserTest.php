<?php

namespace Tests\Feature;

use App\Scrapers\WeWorkRemotelyParser;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeWorkRemotelyParserTest extends TestCase
{
    public function test_fetch_reads_jobs_from_rss_feed(): void
    {
        $xml = file_get_contents(
            base_path('tests/Fixtures/weworkremotely.xml')
        );

        Http::fake([
            '*' => Http::response(
                $xml,
                200,
                ['Content-Type' => 'application/rss+xml']
            ),
        ]);

        $parser = new WeWorkRemotelyParser();

        $items = $parser->fetch();

        $this->assertCount(1, $items);

        $this->assertSame(
            'Acme Corp: Senior Laravel Developer',
            $items[0]['title']
        );

        $this->assertSame(
            'Anywhere in the World',
            $items[0]['region']
        );
    }

    public function test_normaliser_converts_wwr_job_to_mission_format(): void
    {
        $rawJob = [
            'title' => 'Acme Corp: Senior Laravel Developer',
            'region' => 'Anywhere in the World',
            'country' => 'Anywhere',
            'state' => '',
            'skills' => 'PHP, Laravel, Vue.js',
            'category' => 'Programming',
            'type' => 'Full-Time',
            'description' =>
                '<p>Build Laravel APIs and Vue.js applications.</p>',
            'pubDate' => 'Mon, 10 Aug 2026 10:00:00 +0000',
            'guid' =>
                'https://weworkremotely.com/remote-jobs/acme-senior-laravel-developer',
            'link' =>
                'https://weworkremotely.com/remote-jobs/acme-senior-laravel-developer',
        ];

        $parser = new WeWorkRemotelyParser();

        $mission = $parser->normaliser($rawJob);

        $this->assertSame(
            'Senior Laravel Developer',
            $mission['titre']
        );

        $this->assertSame(
            'Acme Corp',
            $mission['entreprise']
        );

        $this->assertSame(
            'full_remote',
            $mission['remote_type']
        );

        $this->assertSame(
            'Anywhere in the World',
            $mission['localisation']
        );

        $this->assertSame(
            'Programming',
            $mission['secteur']
        );

        $this->assertSame(
            '2026-08-10',
            $mission['date_publication']
        );

        $this->assertSame(
            'https://weworkremotely.com/remote-jobs/acme-senior-laravel-developer',
            $mission['url_origine']
        );

        $this->assertSame(
            [
                'PHP',
                'Laravel',
                'Vue.js',
            ],
            $mission['stacks']
        );

        $this->assertSame(
            $rawJob,
            $mission['raw_data']
        );
    }
}