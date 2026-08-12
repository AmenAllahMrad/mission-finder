<?php

namespace App\Scrapers;

use App\Models\Source;
use App\Scrapers\Contracts\SourceAwareParserInterface;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class FreeWorkParser implements SourceAwareParserInterface
{
    private const DEFAULT_URL =
        'https://www.free-work.com/fr/tech-it/jobs?contracts=contractor';

    private const MAX_JOBS = 5;

    private ?Source $source = null;

    /*
    |--------------------------------------------------------------------------
    | Source
    |--------------------------------------------------------------------------
    */

    public function setSource(Source $source): static
    {
        $this->source = $source;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Fetch
    |--------------------------------------------------------------------------
    */

    public function fetch(): array
    {
        $listingUrl = trim(
            (string) (
                $this->source?->url_base
                ?: self::DEFAULT_URL
            )
        );

        /*
         * Première page uniquement.
         */
        $response = Http::timeout(20)
            ->withHeaders([
                'User-Agent' => 'MissionFinder/1.0',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
            ->get($listingUrl);

        if ($response->failed()) {
            throw new RuntimeException(
                'Free-Work listing request failed with HTTP status '
                . $response->status()
            );
        }

        $urls = $this->extractJobUrls(
            $response->body()
        );

        if (count($urls) === 0) {
            throw new RuntimeException(
                'Aucune URL de mission Free-Work détectée.'
            );
        }

        /*
         * Limite volontaire :
         * 5 fiches maximum par collecte.
         */
        $urls = array_slice(
            $urls,
            0,
            self::MAX_JOBS
        );

        $items = [];

        foreach ($urls as $url) {
            $detailResponse = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => 'MissionFinder/1.0',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($url);

            /*
             * Une fiche inaccessible ne doit pas
             * bloquer toute la collecte.
             */
            if ($detailResponse->failed()) {
                continue;
            }

            $item = $this->extractJobDetail(
                $url,
                $detailResponse->body()
            );

            if (
                empty($item['titre']) ||
                empty($item['url_origine'])
            ) {
                continue;
            }

            $items[] = $item;

            /*
             * Petite pause pour rester raisonnable
             * entre deux requêtes de détail.
             */
            usleep(250000);
        }

        return $items;
    }

    /*
    |--------------------------------------------------------------------------
    | Normalisation
    |--------------------------------------------------------------------------
    */

    public function normaliser(
        array $rawItem
    ): array {
        $titre = trim(
            (string) (
                $rawItem['titre']
                ?? ''
            )
        );

        $description = trim(
            (string) (
                $rawItem['description']
                ?? ''
            )
        );

        $entreprise = trim(
            (string) (
                $rawItem['entreprise']
                ?? ''
            )
        );

        $localisation = trim(
            (string) (
                $rawItem['localisation']
                ?? ''
            )
        );

        return [
            'titre' => $titre,

            'description' =>
                $description !== ''
                    ? $description
                    : null,

            'entreprise' =>
                $entreprise !== ''
                    ? $entreprise
                    : null,

            'tjm_min' =>
                isset($rawItem['tjm_min'])
                    ? (float) $rawItem['tjm_min']
                    : null,

            'tjm_max' =>
                isset($rawItem['tjm_max'])
                    ? (float) $rawItem['tjm_max']
                    : null,

            'remote_type' =>
                $rawItem['remote_type']
                ?? null,

            'localisation' =>
                $localisation !== ''
                    ? $localisation
                    : null,

            'secteur' =>
                $rawItem['secteur']
                ?? null,

            'duree_mois' =>
                isset($rawItem['duree_mois'])
                    ? (int) $rawItem['duree_mois']
                    : null,

            'date_publication' =>
                $rawItem['date_publication']
                ?? null,

            'url_origine' =>
                (string) (
                    $rawItem['url_origine']
                    ?? ''
                ),

            /*
             * Payload brut de la fiche extraite.
             */
            'raw_data' => $rawItem,

            'stacks' => $this->detectStacks(
                $titre
                . ' '
                . $description
            ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | URLs de missions depuis la liste
    |--------------------------------------------------------------------------
    */

    private function extractJobUrls(
        string $html
    ): array {
        $xpath = $this->makeXPath(
            $html
        );

        /*
         * Format actuel des URLs Free-Work :
         *
         * /fr/tech-it/job-mission/...
         */
        $nodes = $xpath->query(
            '//a[contains(@href, "/fr/tech-it/job-mission/")]'
        );

        if (! $nodes) {
            return [];
        }

        $urls = [];

        foreach ($nodes as $node) {
            /*
             * DOMXPath retourne des DOMNode.
             * getAttribute() nécessite DOMElement.
             */
            if (! $node instanceof DOMElement) {
                continue;
            }

            $href = trim(
                $node->getAttribute(
                    'href'
                )
            );

            if ($href === '') {
                continue;
            }

            $url = $this->absoluteUrl(
                $href
            );

            if (! $url) {
                continue;
            }

            /*
             * Tableau associatif pour éviter
             * les doublons.
             */
            $urls[$url] = $url;
        }

        return array_values(
            $urls
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Extraction d'une fiche
    |--------------------------------------------------------------------------
    */

    private function extractJobDetail(
        string $url,
        string $html
    ): array {
        $xpath = $this->makeXPath(
            $html
        );

        /*
        |--------------------------------------------------------------------------
        | Titre
        |--------------------------------------------------------------------------
        */

        $titre = $this->firstNodeText(
            $xpath,
            '//h1'
        );

        $titre = preg_replace(
            "/^Offre d'emploi\s+/iu",
            '',
            $titre
        ) ?? $titre;

        $titre = trim(
            $titre
        );

        /*
        |--------------------------------------------------------------------------
        | Localisation
        |--------------------------------------------------------------------------
        */

        $localisation = $this->firstNodeText(
            $xpath,
            '//h2'
        );

        /*
        |--------------------------------------------------------------------------
        | Entreprise
        |--------------------------------------------------------------------------
        */

        $pageTitle = $this->firstNodeText(
            $xpath,
            '//title'
        );

        /*
         * Première tentative :
         * récupérer l'entreprise depuis <title>.
         */
        $entreprise = $this->companyFromPageTitle(
            $pageTitle
        );

        /*
         * Fallback :
         * rechercher l'entreprise dans le contenu
         * visible après la localisation.
         */
        if (! $entreprise) {
            $entreprise = $this->extractCompanyFromPage(
                $xpath,
                $localisation,
                $titre
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Corps complet
        |--------------------------------------------------------------------------
        */

        $body = $this->firstNodeText(
            $xpath,
            '//body'
        );

        /*
        |--------------------------------------------------------------------------
        | Date
        |--------------------------------------------------------------------------
        */

        $datePublication = $this->extractDate(
            $xpath,
            $body
        );

        /*
        |--------------------------------------------------------------------------
        | TJM
        |--------------------------------------------------------------------------
        */

        [
            $tjmMin,
            $tjmMax,
        ] = $this->extractTjm(
            $body
        );

        /*
        |--------------------------------------------------------------------------
        | Durée
        |--------------------------------------------------------------------------
        */

        $dureeMois = $this->extractDuration(
            $body
        );

        /*
        |--------------------------------------------------------------------------
        | Remote
        |--------------------------------------------------------------------------
        */

        $remoteType = $this->extractRemoteType(
            $body
        );

        /*
        |--------------------------------------------------------------------------
        | Description
        |--------------------------------------------------------------------------
        */

        $description = $this->extractDescription(
            $body
        );

        return [
            'titre' => $titre,

            'description' => $description,

            'entreprise' => $entreprise,

            'tjm_min' => $tjmMin,

            'tjm_max' => $tjmMax,

            'remote_type' => $remoteType,

            'localisation' => $localisation,

            'secteur' => null,

            'duree_mois' => $dureeMois,

            'date_publication' =>
                $datePublication,

            'url_origine' => $url,

            /*
             * HTML original conservé pour audit.
             */
            'raw_html' => $html,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Date
    |--------------------------------------------------------------------------
    */

    private function extractDate(
        DOMXPath $xpath,
        string $body
    ): ?string {
        /*
         * Priorité :
         *
         * <time datetime="2026-08-12">
         */
        $nodes = $xpath->query(
            '//time[@datetime]'
        );

        if (
            $nodes &&
            $nodes->length > 0
        ) {
            $node = $nodes->item(0);

            if ($node instanceof DOMElement) {
                $datetime = trim(
                    $node->getAttribute(
                        'datetime'
                    )
                );

                if (
                    preg_match(
                        '/^\d{4}-\d{2}-\d{2}/',
                        $datetime,
                        $match
                    )
                ) {
                    return $match[0];
                }
            }
        }

        /*
         * Fallback texte :
         *
         * Publiée le 12/08/2026
         */
        if (
            preg_match(
                '/Publi[ée]e?\s+le\s+(\d{1,2}\/\d{1,2}\/\d{4})/iu',
                $body,
                $match
            )
        ) {
            return $this->normaliserDate(
                $match[1]
            );
        }

        return null;
    }

    private function normaliserDate(
        string $value
    ): ?string {
        $value = trim(
            $value
        );

        /*
         * Format français :
         * d/m/Y
         */
        $date =
            \DateTimeImmutable::createFromFormat(
                '!d/m/Y',
                $value
            );

        if ($date !== false) {
            $tomorrow =
                new \DateTimeImmutable(
                    'tomorrow'
                );

            if ($date <= $tomorrow) {
                return $date->format(
                    'Y-m-d'
                );
            }
        }

        /*
         * Fallback au cas où la source
         * utiliserait m/d/Y.
         */
        $dateUs =
            \DateTimeImmutable::createFromFormat(
                '!m/d/Y',
                $value
            );

        if ($dateUs !== false) {
            return $dateUs->format(
                'Y-m-d'
            );
        }

        return $date !== false
            ? $date->format(
                'Y-m-d'
            )
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | TJM
    |--------------------------------------------------------------------------
    */

    private function extractTjm(
        string $text
    ): array {
        /*
         * Exemple :
         *
         * TJM 400-530 €/j
         * TJM 400 – 530 €/j
         * TJM 400 à 530 €/j
         */
        if (
            preg_match(
                '/TJM\s*([0-9][0-9\s]*)\s*(?:-|–|à)\s*([0-9][0-9\s]*)\s*€\s*(?:\/|⁄)?\s*j/iu',
                $text,
                $match
            )
        ) {
            return [
                $this->number(
                    $match[1]
                ),

                $this->number(
                    $match[2]
                ),
            ];
        }

        /*
         * Exemple :
         *
         * TJM 625 €/j
         */
        if (
            preg_match(
                '/TJM\s*([0-9][0-9\s]*)\s*€\s*(?:\/|⁄)?\s*j/iu',
                $text,
                $match
            )
        ) {
            $value = $this->number(
                $match[1]
            );

            return [
                $value,
                $value,
            ];
        }

        /*
         * Fallback possible :
         * 400 - 530 € / jour
         */
        if (
            preg_match(
                '/([0-9]{2,4})\s*(?:-|–|à)\s*([0-9]{2,4})\s*€\s*(?:\/\s*)?(?:jour|j)/iu',
                $text,
                $match
            )
        ) {
            return [
                $this->number(
                    $match[1]
                ),

                $this->number(
                    $match[2]
                ),
            ];
        }

        return [
            null,
            null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Durée
    |--------------------------------------------------------------------------
    */

    private function extractDuration(
        string $text
    ): ?int {
        /*
         * Durée 6 mois
         */
        if (
            preg_match(
                '/Dur[ée]e\s*:?\s*(\d+)\s*mois/iu',
                $text,
                $match
            )
        ) {
            return (int) $match[1];
        }

        /*
         * 6 mois
         * si précédé d'un contexte de durée.
         */
        if (
            preg_match(
                '/(?:mission|contrat)[^\d]{0,40}(\d+)\s*mois/iu',
                $text,
                $match
            )
        ) {
            return (int) $match[1];
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Télétravail
    |--------------------------------------------------------------------------
    */

    private function extractRemoteType(
        string $text
    ): ?string {
        $lower = Str::lower(
            $text
        );

        /*
         * Important :
         * tester "pas de télétravail"
         * avant "télétravail" générique.
         */
        if (
            str_contains(
                $lower,
                'pas de télétravail'
            ) ||
            str_contains(
                $lower,
                'pas de teletravail'
            ) ||
            str_contains(
                $lower,
                '100% sur site'
            ) ||
            str_contains(
                $lower,
                'sur site uniquement'
            ) ||
            str_contains(
                $lower,
                'on-site'
            ) ||
            str_contains(
                $lower,
                'onsite'
            )
        ) {
            return 'onsite';
        }

        if (
            str_contains(
                $lower,
                'télétravail partiel'
            ) ||
            str_contains(
                $lower,
                'teletravail partiel'
            ) ||
            str_contains(
                $lower,
                'hybride'
            ) ||
            str_contains(
                $lower,
                'hybrid'
            )
        ) {
            return 'hybrid';
        }

        if (
            str_contains(
                $lower,
                'télétravail total'
            ) ||
            str_contains(
                $lower,
                'teletravail total'
            ) ||
            str_contains(
                $lower,
                '100% télétravail'
            ) ||
            str_contains(
                $lower,
                '100% teletravail'
            ) ||
            str_contains(
                $lower,
                'full remote'
            ) ||
            str_contains(
                $lower,
                'full-remote'
            )
        ) {
            return 'full_remote';
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Description
    |--------------------------------------------------------------------------
    */

    private function extractDescription(
        string $body
    ): ?string {
        $description = $body;

        /*
         * On commence après :
         *
         * Publiée le 12/08/2026
         */
        if (
            preg_match(
                '/Publi[ée]e?\s+le\s+\d{1,2}\/\d{1,2}\/\d{4}(.*)$/isu',
                $description,
                $match
            )
        ) {
            $description =
                $match[1];
        }

        /*
         * Nettoyage des éléments d'interface
         * placés avant la vraie description.
         *
         * Exemple observé :
         *
         * "Partager cette offreTEKsystems recherche..."
         */
        $description = preg_replace(
            '/^\s*(?:Partager cette offre|Partager l[\'’]offre|Signaler l[\'’]offre|Signaler cette offre)\s*/iu',
            '',
            $description
        ) ?? $description;

        /*
         * Certaines pages répètent éventuellement
         * les boutons plusieurs fois.
         */
        $description = preg_replace(
            '/(?:Partager cette offre|Partager l[\'’]offre|Signaler l[\'’]offre|Signaler cette offre)\s*/iu',
            '',
            $description
        ) ?? $description;

        /*
         * On coupe avant les sections qui
         * n'appartiennent plus à la mission.
         */
        $endMarkers = [
            'Découvrir ',
            'Postulez à cette offre',
            'Postuler à cette offre',
            'Trouvez votre prochaine mission',
            'Autres offres',
            'Offres similaires',
        ];

        foreach (
            $endMarkers as $marker
        ) {
            $position = mb_stripos(
                $description,
                $marker
            );

            if ($position !== false) {
                $description = mb_substr(
                    $description,
                    0,
                    $position
                );
            }
        }

        $description = $this->cleanText(
            $description
        );

        /*
         * Evite de stocker une page entière
         * si la structure HTML change.
         */
        if (
            mb_strlen(
                $description
            ) > 15000
        ) {
            $description = mb_substr(
                $description,
                0,
                15000
            );
        }

        return $description !== ''
            ? $description
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Entreprise depuis la page visible
    |--------------------------------------------------------------------------
    */

    private function extractCompanyFromPage(
        DOMXPath $xpath,
        string $localisation,
        string $titre
    ): ?string {
        /*
         * On récupère les éléments textuels
         * raisonnablement petits de la page.
         */
        $nodes = $xpath->query(
            '//body//*[not(self::script) and not(self::style)]'
        );

        if (! $nodes) {
            return null;
        }

        $texts = [];

        foreach ($nodes as $node) {
            /*
             * On privilégie les éléments qui
             * n'ont pas d'éléments enfants HTML.
             *
             * Cela évite de récupérer le texte
             * complet d'une grande div.
             */
            if (
                $node->hasChildNodes()
            ) {
                $hasElementChild = false;

                foreach (
                    $node->childNodes
                    as $child
                ) {
                    if (
                        $child instanceof
                        DOMElement
                    ) {
                        $hasElementChild = true;

                        break;
                    }
                }

                if ($hasElementChild) {
                    continue;
                }
            }

            $text = $this->cleanText(
                $node->textContent
                ?? ''
            );

            if ($text === '') {
                continue;
            }

            if (
                mb_strlen($text) > 120
            ) {
                continue;
            }

            $texts[] = $text;
        }

        /*
         * Cherche d'abord la localisation.
         */
        $locationIndex = null;

        foreach (
            $texts as $index => $text
        ) {
            if (
                $localisation !== '' &&
                Str::lower($text) ===
                    Str::lower($localisation)
            ) {
                $locationIndex =
                    $index;

                break;
            }
        }

        /*
         * Si la localisation est trouvée,
         * on inspecte les quelques lignes suivantes.
         */
        if (
            $locationIndex !== null
        ) {
            $candidates = array_slice(
                $texts,
                $locationIndex + 1,
                12
            );

            foreach (
                $candidates as $candidate
            ) {
                if (
                    $this->looksLikeCompany(
                        $candidate,
                        $titre,
                        $localisation
                    )
                ) {
                    return $candidate;
                }
            }
        }

        /*
         * Fallback global :
         * recherche un texte court pouvant être
         * une société autour du titre.
         */
        $titleIndex = null;

        foreach (
            $texts as $index => $text
        ) {
            if (
                Str::lower($text) ===
                    Str::lower($titre)
            ) {
                $titleIndex =
                    $index;

                break;
            }
        }

        if (
            $titleIndex !== null
        ) {
            $candidates = array_slice(
                $texts,
                $titleIndex + 1,
                20
            );

            foreach (
                $candidates as $candidate
            ) {
                if (
                    $this->looksLikeCompany(
                        $candidate,
                        $titre,
                        $localisation
                    )
                ) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation d'un candidat entreprise
    |--------------------------------------------------------------------------
    */

    private function looksLikeCompany(
        string $value,
        string $titre,
        string $localisation
    ): bool {
        $value = trim(
            $value
        );

        if (
            $value === '' ||
            mb_strlen($value) < 2 ||
            mb_strlen($value) > 100
        ) {
            return false;
        }

        $lower = Str::lower(
            $value
        );

        /*
         * Exclure le titre.
         */
        if (
            $titre !== '' &&
            Str::lower($titre) ===
                $lower
        ) {
            return false;
        }

        /*
         * Exclure la localisation.
         */
        if (
            $localisation !== '' &&
            Str::lower($localisation) ===
                $lower
        ) {
            return false;
        }

        /*
         * Exclure les informations métier
         * qui ne sont pas des entreprises.
         */
        $ignored = [
            'publiée le',
            'publiee le',
            'partager',
            'signaler',
            'télétravail',
            'teletravail',
            'tjm',
            'durée',
            'duree',
            'mission freelance',
            'postuler',
            'candidature',
            'voir l’offre',
            'voir l\'offre',
            'offres similaires',
            'compétences',
            'competences',
            'description',
            'profil recherché',
            'profil recherche',
            'freelance',
        ];

        foreach (
            $ignored as $ignoredText
        ) {
            if (
                str_contains(
                    $lower,
                    $ignoredText
                )
            ) {
                return false;
            }
        }

        /*
         * Une ligne purement numérique
         * n'est pas une entreprise.
         */
        if (
            preg_match(
                '/^[\d\s€\-–,.\/]+$/u',
                $value
            )
        ) {
            return false;
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Entreprise depuis <title>
    |--------------------------------------------------------------------------
    */

    private function companyFromPageTitle(
        string $pageTitle
    ): ?string {
        $pageTitle = trim(
            $pageTitle
        );

        if ($pageTitle === '') {
            return null;
        }

        /*
         * Exemple :
         *
         * TEKsystems — Offre d'emploi ...
         */
        if (
            preg_match(
                '/^(.*?)\s+[—–-]\s+Offre\s+d[\'’]emploi/iu',
                $pageTitle,
                $match
            )
        ) {
            $company = trim(
                $match[1]
            );

            if (
                $company !== ''
            ) {
                return $company;
            }
        }

        /*
         * Variante :
         *
         * TEKsystems | Mission freelance ...
         */
        if (
            preg_match(
                '/^(.*?)\s*\|\s*(?:Mission|Offre)/iu',
                $pageTitle,
                $match
            )
        ) {
            $company = trim(
                $match[1]
            );

            if (
                $company !== ''
            ) {
                return $company;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Détection des stacks
    |--------------------------------------------------------------------------
    */

    private function detectStacks(
        string $text
    ): array {
        $technologies = [
            'PHP',
            'Laravel',
            'Symfony',

            'JavaScript',
            'TypeScript',

            'Vue',
            'React',
            'Angular',

            'Node.js',
            'Node',

            'Python',
            'Django',
            'Flask',
            'FastAPI',

            'Java',
            'Spring',

            'C#',
            '.NET',

            'Go',
            'Golang',
            'Rust',

            'Ruby',
            'Rails',

            'SQL',
            'MySQL',
            'PostgreSQL',
            'MongoDB',
            'Redis',

            'AWS',
            'Azure',
            'GCP',

            'Docker',
            'Kubernetes',
            'Terraform',
            'Ansible',

            'Linux',
            'Git',

            'Jenkins',
            'GitLab',
            'GitHub',

            'Kotlin',
            'Swift',

            'React Native',
            'Flutter',

            'Spark',
            'Kafka',

            'Elasticsearch',

            'Cybersecurity',
            'Cybersécurité',

            'SIEM',
            'SOC',

            'IAM',
            'PAM',
            'PKI',

            'OpenSSL',
        ];

        $lower = Str::lower(
            html_entity_decode(
                strip_tags(
                    $text
                ),
                ENT_QUOTES
                | ENT_HTML5,
                'UTF-8'
            )
        );

        $found = [];

        foreach (
            $technologies as $technology
        ) {
            $needle = Str::lower(
                $technology
            );

            $pattern =
                '/(?<![\pL\pN])'
                . preg_quote(
                    $needle,
                    '/'
                )
                . '(?![\pL\pN])/iu';

            if (
                preg_match(
                    $pattern,
                    $lower
                ) === 1
            ) {
                $found[] =
                    $technology;
            }
        }

        return array_values(
            array_unique(
                $found
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DOM
    |--------------------------------------------------------------------------
    */

    private function makeXPath(
        string $html
    ): DOMXPath {
        $document =
            new DOMDocument();

        $previous =
            libxml_use_internal_errors(
                true
            );

        $document->loadHTML(
            '<?xml encoding="UTF-8">'
            . $html,
            LIBXML_NOWARNING
            | LIBXML_NOERROR
        );

        libxml_clear_errors();

        libxml_use_internal_errors(
            $previous
        );

        return new DOMXPath(
            $document
        );
    }

    private function firstNodeText(
        DOMXPath $xpath,
        string $query
    ): string {
        $nodes = $xpath->query(
            $query
        );

        if (
            ! $nodes ||
            $nodes->length === 0
        ) {
            return '';
        }

        $node = $nodes->item(0);

        if ($node === null) {
            return '';
        }

        return $this->cleanText(
            $node->textContent
            ?? ''
        );
    }

    /*
    |--------------------------------------------------------------------------
    | URL absolue
    |--------------------------------------------------------------------------
    */

    private function absoluteUrl(
        string $href
    ): ?string {
        $href = html_entity_decode(
            trim($href),
            ENT_QUOTES
            | ENT_HTML5,
            'UTF-8'
        );

        if (
            str_starts_with(
                $href,
                '//'
            )
        ) {
            $href =
                'https:' . $href;
        }

        if (
            str_starts_with(
                $href,
                '/'
            )
        ) {
            $href =
                'https://www.free-work.com'
                . $href;
        }

        if (
            ! str_starts_with(
                $href,
                'https://www.free-work.com/'
            ) &&
            ! str_starts_with(
                $href,
                'https://free-work.com/'
            )
        ) {
            return null;
        }

        $parts = parse_url(
            $href
        );

        if (
            ! isset(
                $parts['path']
            )
        ) {
            return null;
        }

        return 'https://www.free-work.com'
            . $parts['path'];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function cleanText(
        string $text
    ): string {
        $text = html_entity_decode(
            strip_tags(
                $text
            ),
            ENT_QUOTES
            | ENT_HTML5,
            'UTF-8'
        );

        return trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $text
            ) ?? ''
        );
    }

    private function number(
        string $value
    ): float {
        return (float) (
            preg_replace(
                '/\s+/u',
                '',
                $value
            ) ?? '0'
        );
    }
}