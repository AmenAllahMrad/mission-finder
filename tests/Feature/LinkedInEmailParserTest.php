<?php

namespace Tests\Feature;

use App\Scrapers\LinkedInEmailParser;
use Tests\TestCase;

class LinkedInEmailParserTest extends TestCase
{
    public function test_normaliser_converts_linkedin_email_job_to_mission_format(): void
    {
        $parser =
            new LinkedInEmailParser();

        $data =
            $parser->normaliser([
                'titre' =>
                    'Senior Laravel Developer',

                'entreprise' =>
                    'Example Company',

                'localisation' =>
                    'France - Remote',

                'url_origine' =>
                    'https://www.linkedin.com/jobs/view/1234567890',

                'date_publication' =>
                    '2026-08-11',

                'email_uid' =>
                    123,

                'email_subject' =>
                    'New jobs for Laravel Developer',

                'context' =>
                    'Senior Laravel Developer Example Company France Remote PHP Laravel Docker AWS',
            ]);

        $this->assertSame(
            'Senior Laravel Developer',
            $data['titre']
        );

        $this->assertSame(
            'Example Company',
            $data['entreprise']
        );

        $this->assertSame(
            'France - Remote',
            $data['localisation']
        );

        $this->assertSame(
            'full_remote',
            $data['remote_type']
        );

        $this->assertSame(
            '2026-08-11',
            $data['date_publication']
        );

        $this->assertSame(
            'https://www.linkedin.com/jobs/view/1234567890',
            $data['url_origine']
        );

        $this->assertContains(
            'Laravel',
            $data['stacks']
        );

        $this->assertContains(
            'PHP',
            $data['stacks']
        );

        $this->assertContains(
            'Docker',
            $data['stacks']
        );

        $this->assertContains(
            'AWS',
            $data['stacks']
        );
    }
}