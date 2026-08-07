<?php

namespace App\Scrapers;

use App\Scrapers\Contracts\SourceParserInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

class WeWorkRemotelyParser implements SourceParserInterface
{
    private const RSS_URL =
        'https://weworkremotely.com/remote-jobs.rss';

    public function fetch(): array
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'MissionFinder/1.0',
                'Accept' => 'application/rss+xml, application/xml, text/xml',
            ])
            ->get(self::RSS_URL);

        if ($response->failed()) {
            throw new RuntimeException(
                'We Work Remotely RSS request failed with HTTP status '
                . $response->status()
            );
        }

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string(
            $response->body(),
            SimpleXMLElement::class,
            LIBXML_NOCDATA
        );

        if ($xml === false) {
            throw new RuntimeException(
                'Unable to parse We Work Remotely RSS feed.'
            );
        }

        $items = [];

        foreach ($xml->channel->item ?? [] as $item) {
            $items[] = json_decode(
                json_encode($item),
                true
            );
        }

        return $items;
    }

   public function normaliser(array $rawItem): array
{
    $rawTitle = trim(
        (string) ($rawItem['title'] ?? '')
    );

    $entreprise = null;
    $titre = $rawTitle;

    if (str_contains($rawTitle, ':')) {
        [$entreprise, $titre] = array_map(
            'trim',
            explode(':', $rawTitle, 2)
        );
    }

    $description = $rawItem['description'] ?? null;

    $link = $rawItem['link'] ?? '';

    if (is_array($link)) {
        $link = $link[0] ?? '';
    }

    $date = null;

    if (!empty($rawItem['pubDate'])) {
        $timestamp = strtotime(
            (string) $rawItem['pubDate']
        );

        if ($timestamp !== false) {
            $date = date('Y-m-d', $timestamp);
        }
    }

    $region = $rawItem['region'] ?? null;

    $localisation = is_string($region)
        && trim($region) !== ''
            ? trim($region)
            : null;

    $skills = $rawItem['skills'] ?? [];

    if (is_string($skills)) {
        $skills = preg_split(
            '/\s*(?:,|;|\/|\band\b)\s*/i',
            $skills,
            -1,
            PREG_SPLIT_NO_EMPTY
        );
    }

    if (!is_array($skills)) {
        $skills = [];
    }

    $stacks = array_values(
        array_unique(
            array_filter(
                array_map(
                    fn ($skill) => trim((string) $skill),
                    $skills
                )
            )
        )
    );

    $secteur = $rawItem['category'] ?? null;

    if (!is_string($secteur) || trim($secteur) === '') {
        $secteur = null;
    } else {
        $secteur = trim($secteur);
    }

    return [
        'titre' => $titre,
        'description' => is_string($description)
            ? $description
            : null,

        'entreprise' => $entreprise,

        'tjm_min' => null,
        'tjm_max' => null,

        'remote_type' => 'full_remote',

        'localisation' => $localisation,

        'secteur' => $secteur,

        'duree_mois' => null,

        'date_publication' => $date,

        'url_origine' => (string) $link,

        'raw_data' => $rawItem,

        'stacks' => $stacks,
    ];
}
}