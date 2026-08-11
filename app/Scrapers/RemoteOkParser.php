<?php

namespace App\Scrapers;

use App\Scrapers\Contracts\SourceParserInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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

        if (! is_array($data)) {
            throw new RuntimeException(
                'RemoteOK returned an invalid JSON response.'
            );
        }

        /*
         * Le premier élément retourné par RemoteOK
         * contient généralement des métadonnées.
         * Ce n'est pas une mission.
         */
        if (
            isset($data[0])
            && ! isset($data[0]['position'])
        ) {
            array_shift($data);
        }

        return $data;
    }

    public function normaliser(array $rawItem): array
    {
        $titre = trim(
            (string) ($rawItem['position'] ?? '')
        );

        $entreprise = trim(
            (string) ($rawItem['company'] ?? '')
        );

        $description = (string) (
            $rawItem['description'] ?? ''
        );

        $tags = is_array($rawItem['tags'] ?? null)
            ? $rawItem['tags']
            : [];

        /*
         * RemoteOK peut retourner des tags peu fiables.
         *
         * On ne garde comme stacks que les tags qui sont
         * également présents dans le titre ou la description.
         */
        $stacks = $this->filtrerStacksFiables(
            $tags,
            $titre,
            $description
        );

        return [
            'titre' => $titre,

            'description' =>
                $rawItem['description'] ?? null,

            'entreprise' =>
                $entreprise !== ''
                    ? $entreprise
                    : null,

            /*
             * RemoteOK fournit parfois des salaires,
             * mais ce ne sont pas des TJM freelance.
             */
            'tjm_min' => null,
            'tjm_max' => null,

            'remote_type' => 'full_remote',

            'localisation' =>
                $rawItem['location'] ?? null,

            'secteur' => null,

            'duree_mois' => null,

            'date_publication' =>
                isset($rawItem['date'])
                    ? date(
                        'Y-m-d',
                        strtotime($rawItem['date'])
                    )
                    : null,

            'url_origine' =>
                $rawItem['url'] ?? '',

            /*
             * Toujours conserver les données originales
             * pour audit/retraitement.
             */
            'raw_data' => $rawItem,

            'stacks' => $stacks,
        ];
    }

    private function filtrerStacksFiables(
        array $tags,
        string $titre,
        string $description
    ): array {
        /*
         * Nettoyage du HTML de la description.
         */
        $texte = html_entity_decode(
            strip_tags(
                $titre . ' ' . $description
            ),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $texte = Str::lower($texte);

        $stacks = [];

        foreach ($tags as $tag) {
            $tag = trim((string) $tag);

            if ($tag === '') {
                continue;
            }

            if ($this->tagEstPresentDansTexte(
                $texte,
                $tag
            )) {
                $stacks[] = $tag;
            }
        }

        return array_values(
            array_unique($stacks)
        );
    }

    private function tagEstPresentDansTexte(
        string $texte,
        string $tag
    ): bool {
        $tag = Str::lower(
            trim($tag)
        );

        if ($tag === '') {
            return false;
        }

        /*
         * On utilise des limites de mots afin d'éviter
         * des faux positifs.
         *
         * Exemple :
         * tag "go" ne doit pas correspondre à "ongoing".
         */
        $pattern = '/(?<![\pL\pN])'
            . preg_quote($tag, '/')
            . '(?![\pL\pN])/iu';

        return preg_match(
            $pattern,
            $texte
        ) === 1;
    }
}