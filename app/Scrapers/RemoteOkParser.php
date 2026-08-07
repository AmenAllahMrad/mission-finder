<?php

namespace App\Scrapers;

use App\Scrapers\Contracts\SourceParserInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RemoteOkParser implements SourceParserInterface
{
    private const API_URL = 'https://remoteok.com/api';

    public function fetch(): array
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'MissionFinder/1.0',
                'Accept' => 'application/json',
            ])
            ->get(self::API_URL);

        if ($response->failed()) {
            throw new RuntimeException(
                'RemoteOK request failed with HTTP status '
                . $response->status()
            );
        }

        $data = $response->json();

        if (!is_array($data)) {
            throw new RuntimeException(
                'RemoteOK returned an invalid JSON response.'
            );
        }

        /*
         * RemoteOK's first array element is metadata,
         * not a mission.
         */
        if (isset($data[0]) && !isset($data[0]['position'])) {
            array_shift($data);
        }

        return $data;
    }

    public function normaliser(array $rawItem): array
    {
        $titre = trim((string) ($rawItem['position'] ?? ''));
        $entreprise = trim((string) ($rawItem['company'] ?? ''));

        $tags = $rawItem['tags'] ?? [];

        if (!is_array($tags)) {
            $tags = [];
        }

        return [
            'titre' => $titre,
            'description' => $rawItem['description'] ?? null,
            'entreprise' => $entreprise ?: null,

            'tjm_min' => null,
            'tjm_max' => null,

            'remote_type' => 'full_remote',

            'localisation' => $rawItem['location'] ?? null,

            'duree_mois' => null,

            'date_publication' => isset($rawItem['date'])
                ? date('Y-m-d', strtotime($rawItem['date']))
                : null,

            'url_origine' => $rawItem['url'] ?? '',

            'raw_data' => $rawItem,

            'stacks' => array_values(
                array_filter(
                    array_map(
                        fn ($tag) => trim((string) $tag),
                        $tags
                    )
                )
            ),
        ];
    }
}