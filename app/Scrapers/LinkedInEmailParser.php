<?php

namespace App\Scrapers;

use App\Models\Source;
use App\Scrapers\Contracts\SourceAwareParserInterface;
use DateTimeInterface;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Carbon\Carbon;


class LinkedInEmailParser implements SourceAwareParserInterface
{
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
    |
    | Une entrée retournée correspond à UNE offre LinkedIn extraite
    | d'un email, pas à l'email entier.
    |
    */

    public function fetch(): array
    {
        if (! $this->source) {
            throw new RuntimeException(
                'LinkedInEmailParser nécessite une Source.'
            );
        }

        $credentials =
            $this->source->credentials ?? [];

        $username = trim(
            (string) (
                $credentials['username']
                ?? ''
            )
        );

        $password = (string) (
            $credentials['password']
            ?? ''
        );

        if (
            $username === '' ||
            $password === ''
        ) {
            throw new RuntimeException(
                'Les credentials IMAP username/password sont manquants.'
            );
        }

        $host = trim(
            (string) (
                $credentials['host']
                ?? 'imap.gmail.com'
            )
        );

        $port = (int) (
            $credentials['port']
            ?? 993
        );

        $encryption =
            $credentials['encryption']
            ?? 'ssl';

        $folderName = trim(
            (string) (
                $credentials['folder']
                ?? 'INBOX'
            )
        );

        $fromContains = trim(
            (string) (
                $credentials['from_contains']
                ?? 'linkedin.com'
            )
        );

       /*
 * Une collecte email doit rester légère.
 *
 * On commence volontairement avec
 * maximum 5 emails par polling.
 */
$maxMessages = max(
    1,
    min(
        5,
        (int) (
            $credentials['max_messages']
            ?? 3
        )
    )
);

$lookbackDays = max(
    1,
    min(
        30,
        (int) (
            $credentials['lookback_days']
            ?? 14
        )
    )
);

        $validateCert =
            array_key_exists(
                'validate_cert',
                $credentials
            )
                ? (bool) $credentials[
                    'validate_cert'
                ]
                : true;

        $client = null;

        try {
            /*
            |--------------------------------------------------------------------------
            | IMAP client
            |--------------------------------------------------------------------------
            */

            $manager =
                new ClientManager([]);

            $client =
                $manager->make([
                    'host' =>
                        $host,

                    'port' =>
                        $port,

                    'encryption' =>
                        $encryption,

                    'validate_cert' =>
                        $validateCert,

                    'username' =>
                        $username,

                    'password' =>
                        $password,

                    'protocol' =>
                        'imap',
                ]);

            $client->connect();

            if (
                ! $client->isConnected()
            ) {
                throw new RuntimeException(
                    'Impossible de se connecter au serveur IMAP.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Folder
            |--------------------------------------------------------------------------
            */

            $folder =
                $client->getFolder(
                    $folderName
                );

            if (! $folder) {
                throw new RuntimeException(
                    "Le dossier IMAP {$folderName} est introuvable."
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Query
            |--------------------------------------------------------------------------
            |
            | leaveUnread():
            | le test ou le polling MissionFinder ne doit pas changer
            | l'état lu/non lu dans la boîte Gmail.
            |
            */

           $query =
    $folder
        ->messages()
        ->all();

/*
 * MissionFinder ne modifie pas
 * l'état lu/non lu des emails.
 */
$query->leaveUnread();

/*
 * Plus récent en premier.
 */
$query->setFetchOrderDesc();

/*
 * Ne pas parcourir toute la boîte Gmail.
 *
 * Seulement les emails récents.
 */
$query->since(
    Carbon::now()
        ->subDays(
            $lookbackDays
        )
);

            /*
             * Filtre optionnel.
             *
             * Par défaut on cherche les emails provenant
             * du domaine LinkedIn.
             */
            if ($fromContains !== '') {
                $query->from(
                    $fromContains
                );
            }

            $messages =
                $query
                    ->limit(
                        1,
                        1
                    )
                    ->get();

            $jobs = [];

            foreach (
                $messages as $message
            ) {
                $subject =
                    $this->attributeString(
                        $message->getSubject()
                    );

                $date =
                    $this->messageDate(
                        $message
                    );

                $uid = null;

                try {
                    $uid =
                        $message->getUid();
                } catch (Throwable) {
                    $uid = null;
                }

                $html =
                    (string) (
                        $message
                            ->getHTMLBody()
                        ?? ''
                    );

                $text =
                    (string) (
                        $message
                            ->getTextBody()
                        ?? ''
                    );

                /*
                 * HTML est prioritaire car les cards
                 * LinkedIn y sont mieux structurées.
                 */
                $itemsHtml =
                    $this
                        ->extractJobsFromHtml(
                            $html,
                            [
                                'email_uid' =>
                                    $uid,

                                'email_subject' =>
                                    $subject,

                                'date_publication' =>
                                    $date,
                            ]
                        );

                foreach (
                    $itemsHtml as $item
                ) {
                    $jobs[
                        $item[
                            'url_origine'
                        ]
                    ] = $item;
                }

                /*
                 * Fallback texte.
                 */
                if (
                    count(
                        $itemsHtml
                    ) === 0 &&
                    $text !== ''
                ) {
                    foreach (
                        $this
                            ->extractJobsFromText(
                                $text,
                                [
                                    'email_uid' =>
                                        $uid,

                                    'email_subject' =>
                                        $subject,

                                    'date_publication' =>
                                        $date,
                                ]
                            )
                        as $item
                    ) {
                        $jobs[
                            $item[
                                'url_origine'
                            ]
                        ] = $item;
                    }
                }
            }

            return array_values(
                $jobs
            );
        } finally {
            /*
             * Toujours fermer la connexion IMAP.
             */
            if (
                $client instanceof Client
            ) {
                try {
                    if (
                        $client
                            ->isConnected()
                    ) {
                        $client
                            ->disconnect();
                    }
                } catch (Throwable) {
                    // Rien à faire ici.
                }
            }
        }
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

        $context = trim(
            (string) (
                $rawItem['context']
                ?? ''
            )
        );

        $date =
            $rawItem[
                'date_publication'
            ] ?? null;

        if ($date) {
            $timestamp =
                strtotime(
                    (string) $date
                );

            $date =
                $timestamp !== false
                    ? date(
                        'Y-m-d',
                        $timestamp
                    )
                    : null;
        }

        return [
            'titre' =>
                $titre,

            /*
             * Un email d'alerte ne contient généralement
             * pas la description complète de l'annonce.
             *
             * On évite donc de remplacer une description
             * plus riche venant d'une autre source.
             */
            'description' =>
                null,

            'entreprise' =>
                $entreprise !== ''
                    ? $entreprise
                    : null,

            'tjm_min' =>
                null,

            'tjm_max' =>
                null,

            'remote_type' =>
                $this->detectRemoteType(
                    $titre
                    . ' '
                    . $localisation
                    . ' '
                    . $context
                ),

            'localisation' =>
                $localisation !== ''
                    ? $localisation
                    : null,

            'secteur' =>
                null,

            'duree_mois' =>
                null,

            'date_publication' =>
                $date,

            'url_origine' =>
                (string) (
                    $rawItem[
                        'url_origine'
                    ] ?? ''
                ),

            /*
             * On conserve le payload brut de la card extraite,
             * pas l'intégralité de la boîte email.
             */
            'raw_data' =>
                $rawItem,

            'stacks' =>
                $this->detectStacks(
                    $titre
                    . ' '
                    . $context
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Extract LinkedIn jobs from HTML
    |--------------------------------------------------------------------------
    */

    private function extractJobsFromHtml(
        string $html,
        array $emailMeta
    ): array {
        if ($html === '') {
            return [];
        }

        preg_match_all(
            '/<a\b[^>]*href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is',
            $html,
            $matches,
            PREG_SET_ORDER
            | PREG_OFFSET_CAPTURE
        );

        $jobs = [];

        foreach (
            $matches as $match
        ) {
            $href =
                html_entity_decode(
                    $match[2][0],
                    ENT_QUOTES
                    | ENT_HTML5,
                    'UTF-8'
                );

            $canonicalUrl =
                $this
                    ->canonicalLinkedInJobUrl(
                        $href
                    );

            if (
                $canonicalUrl ===
                null
            ) {
                continue;
            }

            $anchorHtml =
                $match[3][0];

            $titre =
                $this->cleanText(
                    $anchorHtml
                );

            $offset =
                (int) $match[0][1];

            $context =
                $this
                    ->extractHtmlContext(
                        $html,
                        $offset
                    );

            /*
             * Certains boutons ont comme texte
             * "View job". Dans ce cas on tente
             * de récupérer le titre depuis le contexte.
             */
            if (
                $titre === '' ||
                $this->isGenericLinkText(
                    $titre
                )
            ) {
                $titre =
                    $this
                        ->findTitleInContext(
                            $context
                        );
            }

            if (
                $titre === '' ||
                $this->isGenericLinkText(
                    $titre
                )
            ) {
                continue;
            }

            [
                $entreprise,
                $localisation,
            ] =
                $this->inferCompanyAndLocation(
                    $context,
                    $titre
                );

            $jobs[
                $canonicalUrl
            ] = array_merge(
                $emailMeta,
                [
                    'titre' =>
                        $titre,

                    'entreprise' =>
                        $entreprise,

                    'localisation' =>
                        $localisation,

                    'url_origine' =>
                        $canonicalUrl,

                    'original_url' =>
                        $href,

                    'context' =>
                        $context,
                ]
            );
        }

        return array_values(
            $jobs
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Text fallback
    |--------------------------------------------------------------------------
    */

    private function extractJobsFromText(
        string $text,
        array $emailMeta
    ): array {
        $lines =
            preg_split(
                '/\R+/u',
                $text
            ) ?: [];

        $lines =
            array_values(
                array_filter(
                    array_map(
                        fn ($line) =>
                            trim(
                                preg_replace(
                                    '/\s+/u',
                                    ' ',
                                    $line
                                )
                            ),
                        $lines
                    ),
                    fn ($line) =>
                        $line !== ''
                )
            );

        $jobs = [];

        foreach (
            $lines as $index =>
                $line
        ) {
            if (
                ! preg_match(
                    '~https?://[^\s<>"]*linkedin\.com/[^\s<>"]*jobs/view/[^\s<>"]+~i',
                    $line,
                    $urlMatch
                )
            ) {
                continue;
            }

            $url =
                $this
                    ->canonicalLinkedInJobUrl(
                        $urlMatch[0]
                    );

            if (! $url) {
                continue;
            }

            $titre =
                trim(
                    $lines[
                        $index - 1
                    ] ?? ''
                );

            if (
                $titre === '' ||
                $this->isGenericLinkText(
                    $titre
                )
            ) {
                continue;
            }

            $contextLines =
                array_slice(
                    $lines,
                    max(
                        0,
                        $index - 4
                    ),
                    9
                );

            $context =
                implode(
                    "\n",
                    $contextLines
                );

            [
                $entreprise,
                $localisation,
            ] =
                $this->inferCompanyAndLocation(
                    $context,
                    $titre
                );

            $jobs[$url] =
                array_merge(
                    $emailMeta,
                    [
                        'titre' =>
                            $titre,

                        'entreprise' =>
                            $entreprise,

                        'localisation' =>
                            $localisation,

                        'url_origine' =>
                            $url,

                        'original_url' =>
                            $urlMatch[0],

                        'context' =>
                            $context,
                    ]
                );
        }

        return array_values(
            $jobs
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Canonical LinkedIn URL
    |--------------------------------------------------------------------------
    */

    private function canonicalLinkedInJobUrl(
        string $url
    ): ?string {
        /*
         * LinkedIn encode parfois l'URL
         * dans un paramètre de tracking.
         */
        $decoded =
            html_entity_decode(
                $url,
                ENT_QUOTES
                | ENT_HTML5,
                'UTF-8'
            );

        for (
            $i = 0;
            $i < 2;
            $i++
        ) {
            $new =
                rawurldecode(
                    $decoded
                );

            if ($new === $decoded) {
                break;
            }

            $decoded = $new;
        }

        /*
         * Exemples acceptés :
         *
         * linkedin.com/jobs/view/1234567890
         * linkedin.com/jobs/view/software-engineer-1234567890
         * linkedin.com/comm/jobs/view/1234567890
         */
        if (
            ! preg_match(
                '~linkedin\.com/(?:comm/)?jobs/view/(?:[^/?#]*-)?(\d{6,})(?:[/?#&]|$)~i',
                $decoded,
                $match
            )
        ) {
            return null;
        }

        return sprintf(
            'https://www.linkedin.com/jobs/view/%s',
            $match[1]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Context
    |--------------------------------------------------------------------------
    */

    private function extractHtmlContext(
        string $html,
        int $offset
    ): string {
        $start =
            max(
                0,
                $offset - 1000
            );

        $chunk =
            substr(
                $html,
                $start,
                2500
            );

        /*
         * Les tags bloc deviennent des sauts
         * de ligne avant strip_tags().
         */
        $chunk =
            preg_replace(
                '~<(?:br|/p|/div|/td|/tr|/li|/h[1-6])\b[^>]*>~i',
                "\n",
                $chunk
            );

        $text =
            html_entity_decode(
                strip_tags(
                    $chunk
                ),
                ENT_QUOTES
                | ENT_HTML5,
                'UTF-8'
            );

        $lines =
            preg_split(
                '/\R+/u',
                $text
            ) ?: [];

        $cleanLines = [];

        foreach (
            $lines as $line
        ) {
            $line =
                trim(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        $line
                    )
                );

            if (
                $line === '' ||
                in_array(
                    $line,
                    $cleanLines,
                    true
                )
            ) {
                continue;
            }

            $cleanLines[] =
                $line;
        }

        return implode(
            "\n",
            array_slice(
                $cleanLines,
                0,
                20
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Title fallback
    |--------------------------------------------------------------------------
    */

    private function findTitleInContext(
        string $context
    ): string {
        $lines =
            preg_split(
                '/\R+/u',
                $context
            ) ?: [];

        foreach (
            array_reverse(
                $lines
            )
            as $line
        ) {
            $line =
                trim($line);

            if (
                $line === '' ||
                $this
                    ->isGenericLinkText(
                        $line
                    ) ||
                $this
                    ->isIgnoredContextLine(
                        $line
                    ) ||
                mb_strlen(
                    $line
                ) > 160
            ) {
                continue;
            }

            return $line;
        }

        return '';
    }

    /*
    |--------------------------------------------------------------------------
    | Company / location heuristics
    |--------------------------------------------------------------------------
    */

    private function inferCompanyAndLocation(
        string $context,
        string $titre
    ): array {
        $lines =
            preg_split(
                '/\R+/u',
                $context
            ) ?: [];

        $candidates = [];

        $titleIndex = null;

        foreach (
            $lines as $index =>
                $line
        ) {
            $line =
                trim($line);

            if (
                $line !== '' &&
                Str::lower($line) ===
                    Str::lower(
                        $titre
                    )
            ) {
                $titleIndex =
                    $index;

                break;
            }
        }

        /*
         * Priorité aux lignes situées
         * après le titre.
         */
        if (
            $titleIndex !== null
        ) {
            $lines =
                array_slice(
                    $lines,
                    $titleIndex + 1
                );
        }

        foreach (
            $lines as $line
        ) {
            $line =
                trim(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        $line
                    )
                );

            if (
                $line === '' ||
                Str::lower($line) ===
                    Str::lower(
                        $titre
                    ) ||
                $this
                    ->isIgnoredContextLine(
                        $line
                    ) ||
                mb_strlen(
                    $line
                ) > 150
            ) {
                continue;
            }

            $candidates[] =
                $line;

            if (
                count(
                    $candidates
                ) >= 2
            ) {
                break;
            }
        }

        return [
            $candidates[0]
                ?? null,

            $candidates[1]
                ?? null,
        ];
    }

    private function isGenericLinkText(
        string $text
    ): bool {
        $text =
            Str::lower(
                trim($text)
            );

        return in_array(
            $text,
            [
                'view job',
                'view jobs',
                'see job',
                'see jobs',
                'apply',
                'apply now',
                'learn more',
                'view',
                'voir l’offre',
                'voir l\'offre',
                'voir le poste',
            ],
            true
        );
    }

    private function isIgnoredContextLine(
        string $line
    ): bool {
        $lower =
            Str::lower(
                $line
            );

        if (
            preg_match(
                '~https?://~i',
                $line
            )
        ) {
            return true;
        }

        $ignored = [
            'linkedin',
            'view job',
            'view jobs',
            'apply now',
            'see all jobs',
            'job alert',
            'jobs you may be interested',
            'recommended jobs',
            'unsubscribe',
            'manage your email',
            'privacy policy',
        ];

        foreach (
            $ignored as $value
        ) {
            if (
                str_contains(
                    $lower,
                    $value
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Remote type
    |--------------------------------------------------------------------------
    */

    private function detectRemoteType(
        string $text
    ): ?string {
        $text =
            Str::lower(
                $text
            );

        if (
            str_contains(
                $text,
                'hybrid'
            ) ||
            str_contains(
                $text,
                'hybride'
            )
        ) {
            return 'hybrid';
        }

        if (
            str_contains(
                $text,
                'on-site'
            ) ||
            str_contains(
                $text,
                'onsite'
            ) ||
            str_contains(
                $text,
                'on site'
            )
        ) {
            return 'onsite';
        }

        if (
            str_contains(
                $text,
                'remote'
            ) ||
            str_contains(
                $text,
                'télétravail'
            )
        ) {
            return 'full_remote';
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Stack detection
    |--------------------------------------------------------------------------
    |
    | Whitelist volontaire afin d'éviter les faux tags
    | observés sur certaines plateformes.
    |
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
            'Linux',
            'Git',
            'Cybersecurity',
            'SIEM',
            'SOC',
        ];

        $lower =
            Str::lower(
                $text
            );

        $found = [];

        foreach (
            $technologies as $technology
        ) {
            $needle =
                Str::lower(
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
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function cleanText(
        string $html
    ): string {
        return trim(
            preg_replace(
                '/\s+/u',
                ' ',
                html_entity_decode(
                    strip_tags(
                        $html
                    ),
                    ENT_QUOTES
                    | ENT_HTML5,
                    'UTF-8'
                )
            )
        );
    }

    private function attributeString(
        mixed $attribute
    ): string {
        if ($attribute === null) {
            return '';
        }

        try {
            return trim(
                (string) $attribute
            );
        } catch (Throwable) {
            return '';
        }
    }

    private function messageDate(
        mixed $message
    ): ?string {
        try {
            $attribute =
                $message->getDate();

            $value =
                $attribute
                    ?->first();

            if (
                $value instanceof
                    DateTimeInterface
            ) {
                return $value->format(
                    'Y-m-d'
                );
            }

            if ($value !== null) {
                $timestamp =
                    strtotime(
                        (string) $value
                    );

                if (
                    $timestamp !== false
                ) {
                    return date(
                        'Y-m-d',
                        $timestamp
                    );
                }
            }
        } catch (Throwable) {
            //
        }

        return null;
    }
}