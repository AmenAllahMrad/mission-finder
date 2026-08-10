<?php

namespace Tests\Feature;

use App\Scrapers\RemoteOkParser;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RemoteOkParserTest extends TestCase
{
    public function test_fetch_returns_jobs_and_ignores_metadata_object(): void
    {
        $payload = json_decode(
            file_get_contents(
                base_path('tests/Fixtures/remoteok.json')
            ),
            true
        );

        Http::fake([
            '*' => Http::response(
                $payload,
                200,
                ['Content-Type' => 'application/json']
            ),
        ]);

        $parser = new RemoteOkParser();

        $items = $parser->fetch();

        $this->assertCount(1, $items);

        $this->assertSame(
            '123456',
            (string) $items[0]['id']
        );

        $this->assertSame(
            'Senior Laravel Developer',
            $items[0]['position']
        );
    }

    public function test_normaliser_converts_remoteok_job_to_mission_format(): void
    {
        $payload = json_decode(
            file_get_contents(
                base_path('tests/Fixtures/remoteok.json')
            ),
            true
        );

        $rawJob = $payload[1];

        $parser = new RemoteOkParser();

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
            'Europe',
            $mission['localisation']
        );

        $this->assertSame(
            '2026-08-10',
            $mission['date_publication']
        );

        $this->assertSame(
            'https://remoteok.com/remote-jobs/123456',
            $mission['url_origine']
        );

        $this->assertSame(
            ['PHP', 'Laravel', 'API'],
            $mission['stacks']
        );

        $this->assertSame(
            $rawJob,
            $mission['raw_data']
        );
    }
}