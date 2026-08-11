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
                base_path(
                    'tests/Fixtures/remoteok.json'
                )
            ),
            true
        );

        Http::fake([
            '*' => Http::response(
                $payload,
                200,
                [
                    'Content-Type' =>
                        'application/json',
                ]
            ),
        ]);

        $parser = new RemoteOkParser();

        $items = $parser->fetch();

        /*
         * Le premier objet metadata doit être supprimé.
         */
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
                base_path(
                    'tests/Fixtures/remoteok.json'
                )
            ),
            true
        );

        $rawJob = $payload[1];

        $parser = new RemoteOkParser();

        $mission = $parser->normaliser(
            $rawJob
        );

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

        /*
         * Ces tags sont présents dans le contenu
         * de la mission, donc ils doivent être conservés.
         */
        $this->assertSame(
            [
                'PHP',
                'Laravel',
                'API',
            ],
            $mission['stacks']
        );

        /*
         * Les données RemoteOK originales
         * doivent rester intactes.
         */
        $this->assertSame(
            $rawJob,
            $mission['raw_data']
        );
    }

    public function test_normaliser_ignores_remoteok_tags_not_supported_by_job_content(): void
    {
        $rawJob = [
            'id' => '999',
            'position' => 'Labourers',
            'company' => 'Simmons Civil',

            'description' =>
                'Civil construction and maintenance work in Gladstone.',

            'location' => 'Gladstone',

            'date' =>
                '2026-08-05T10:00:00+00:00',

            'url' =>
                'https://remoteok.com/test-job',

            /*
             * On reproduit volontairement le problème
             * rencontré dans les vraies données RemoteOK.
             */
            'tags' => [
                'laravel',
                'golang',
                'shopify',
                'construction',
            ],
        ];

        $parser = new RemoteOkParser();

        $mission = $parser->normaliser(
            $rawJob
        );

        /*
         * Ces technologies ne sont jamais mentionnées
         * dans le titre ni dans la description.
         */
        $this->assertNotContains(
            'laravel',
            $mission['stacks']
        );

        $this->assertNotContains(
            'golang',
            $mission['stacks']
        );

        $this->assertNotContains(
            'shopify',
            $mission['stacks']
        );

        /*
         * Construction est réellement mentionné
         * dans la description.
         */
        $this->assertContains(
            'construction',
            $mission['stacks']
        );

        /*
         * Même les tags rejetés doivent rester
         * disponibles dans raw_data.
         */
        $this->assertSame(
            $rawJob,
            $mission['raw_data']
        );
    }

    public function test_short_tag_does_not_match_inside_another_word(): void
    {
        $rawJob = [
            'id' => '1000',
            'position' => 'Operations Manager',
            'company' => 'Acme Corp',

            'description' =>
                'Manage ongoing business operations.',

            'location' => 'Europe',

            'date' =>
                '2026-08-10T10:00:00+00:00',

            'url' =>
                'https://remoteok.com/test-go',

            'tags' => [
                'go',
            ],
        ];

        $parser = new RemoteOkParser();

        $mission = $parser->normaliser(
            $rawJob
        );

        /*
         * "go" ne doit pas matcher le mot "ongoing".
         */
        $this->assertNotContains(
            'go',
            $mission['stacks']
        );
    }
}