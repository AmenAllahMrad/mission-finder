<?php

namespace App\Http\Controllers;

use App\Models\Source;
use App\Scrapers\Contracts\SourceParserInterface;
use App\Scrapers\RemoteOkParser;
use App\Scrapers\WeWorkRemotelyParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;
use App\Scrapers\Contracts\SourceAwareParserInterface;
use App\Scrapers\LinkedInEmailParser;


class SourceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Parsers actuellement supportés
    |--------------------------------------------------------------------------
    */

    private const PARSERS = [
        RemoteOkParser::class,
        WeWorkRemotelyParser::class,
        LinkedInEmailParser::class,

    ];

    /*
    |--------------------------------------------------------------------------
    | Liste des sources
    |--------------------------------------------------------------------------
    */

    public function index(): JsonResponse
    {
        $sources = Source::query()
            ->withCount([
                'missions',
                'missionOccurrences',
            ])
            ->orderBy('nom')
            ->get()
            ->map(
                fn (Source $source) =>
                    $this->serializeSource($source)
            );

        return response()->json(
            $sources
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Parsers disponibles
    |--------------------------------------------------------------------------
    */

    public function parsers(): JsonResponse
    {
        return response()->json([
            [
                'class' =>
                    RemoteOkParser::class,

                'label' =>
                    'RemoteOK',

                'type' =>
                    'api',
            ],

            [
                'class' =>
                    WeWorkRemotelyParser::class,

                'label' =>
                    'We Work Remotely',

                'type' =>
                    'rss',
            ],

            [
    'class' =>
        LinkedInEmailParser::class,

    'label' =>
        'LinkedIn Email',

    'type' =>
        'email',
],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Créer une source
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request
    ): JsonResponse {
        $validated =
            $this->validateSource(
                $request
            );

        $source = Source::create([
            'nom' =>
                $validated['nom'],

            'type' =>
                $validated['type'],

            'url_base' =>
                $validated['url_base']
                ?? null,

            'parser_class' =>
                $validated[
                    'parser_class'
                ],

            'frequence_polling_minutes' =>
                $validated[
                    'frequence_polling_minutes'
                ],

            'credentials' =>
                $validated[
                    'credentials'
                ] ?? null,

            'actif' =>
                $validated['actif'],

            'derniere_execution' =>
                null,

            'dernier_statut' =>
                null,
        ]);

        $source->loadCount([
            'missions',
            'missionOccurrences',
        ]);

        return response()->json([
            'message' =>
                'Source créée avec succès.',

            'source' =>
                $this->serializeSource(
                    $source
                ),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Modifier une source
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Source $source
    ): JsonResponse {
        $validated =
            $this->validateSource(
                $request
            );

        $data = [
            'nom' =>
                $validated['nom'],

            'type' =>
                $validated['type'],

            'url_base' =>
                $validated['url_base']
                ?? null,

            'parser_class' =>
                $validated[
                    'parser_class'
                ],

            'frequence_polling_minutes' =>
                $validated[
                    'frequence_polling_minutes'
                ],

            'actif' =>
                $validated['actif'],
        ];

        /*
         * Les credentials existants sont conservés
         * lorsque le frontend n'en fournit pas.
         */
        if (
            array_key_exists(
                'credentials',
                $validated
            )
        ) {
            $data['credentials'] =
                $validated[
                    'credentials'
                ];
        }

        $source->update($data);

        $source->refresh();

        $source->loadCount([
            'missions',
            'missionOccurrences',
        ]);

        return response()->json([
            'message' =>
                'Source mise à jour avec succès.',

            'source' =>
                $this->serializeSource(
                    $source
                ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Tester une source
    |--------------------------------------------------------------------------
    |
    | Le test :
    | - résout le parser ;
    | - lance fetch();
    | - normalise un exemple ;
    | - n'importe RIEN dans MySQL.
    |
    */

    public function tester(
        Source $source
    ): JsonResponse {
        $startedAt =
            microtime(true);

        try {
            $parserClass =
                $source->parser_class;

            if (
                ! in_array(
                    $parserClass,
                    self::PARSERS,
                    true
                )
            ) {
                return response()->json([
                    'success' => false,

                    'message' =>
                        'Parser non autorisé.',
                ], 422);
            }

            if (
                ! class_exists(
                    $parserClass
                )
            ) {
                return response()->json([
                    'success' => false,

                    'message' =>
                        'La classe du parser est introuvable.',
                ], 422);
            }

            /*
             * Résolution par le container Laravel.
             */
            $parser =
                app($parserClass);

            if (
                ! $parser instanceof
                    SourceParserInterface
            ) {
                return response()->json([
                    'success' => false,

                    'message' =>
                        'Le parser ne respecte pas SourceParserInterface.',
                ], 422);
            }

            $parser = app($parserClass);

if (
    ! $parser instanceof
        SourceParserInterface
) {
    return response()->json([
        'success' => false,

        'message' =>
            'Le parser ne respecte pas SourceParserInterface.',
    ], 422);
}

if (
    $parser instanceof
    SourceAwareParserInterface
) {
    $parser->setSource(
        $source
    );
}

$items =
    $parser->fetch();

            /*
             * Récupération réelle.
             */
            $items =
                $parser->fetch();

            if (
                count($items) === 0
            ) {
                return response()->json([
                    'success' => true,

                    'message' =>
                        'Connexion réussie, mais aucune mission n’a été retournée.',

                    'items_count' => 0,

                    'duration_ms' =>
                        $this->durationMs(
                            $startedAt
                        ),

                    'sample' =>
                        null,
                ]);
            }

            /*
             * On normalise seulement
             * la première mission pour le test.
             */
            $sample =
                $parser->normaliser(
                    $items[0]
                );

            /*
             * Ne jamais retourner raw_data
             * dans l'interface de test.
             */
            unset(
                $sample['raw_data']
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Source testée avec succès.',

                'items_count' =>
                    count($items),

                'duration_ms' =>
                    $this->durationMs(
                        $startedAt
                    ),

                'sample' =>
                    $sample,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    $exception->getMessage(),

                'items_count' => 0,

                'duration_ms' =>
                    $this->durationMs(
                        $startedAt
                    ),

                'sample' =>
                    null,
            ], 422);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Supprimer une source
    |--------------------------------------------------------------------------
    |
    | On interdit la suppression si la source possède
    | déjà des missions ou des occurrences.
    |
    | Dans ce cas, il faut simplement la désactiver.
    |
    */

    public function destroy(
        Source $source
    ): JsonResponse {
        $missionsCount =
            $source->missions()
                ->count();

        $occurrencesCount =
            $source
                ->missionOccurrences()
                ->count();

        if (
            $missionsCount > 0 ||
            $occurrencesCount > 0
        ) {
            return response()->json([
                'message' =>
                    'Cette source possède déjà des données collectées. Désactivez-la au lieu de la supprimer.',

                'missions_count' =>
                    $missionsCount,

                'occurrences_count' =>
                    $occurrencesCount,
            ], 422);
        }

        $source->delete();

        return response()->json([
            'message' =>
                'Source supprimée avec succès.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Validation commune
    |--------------------------------------------------------------------------
    */

    private function validateSource(
        Request $request
    ): array {
        return $request->validate([
            'nom' => [
                'required',
                'string',
                'max:255',
            ],

            'type' => [
                'required',

                Rule::in([
                    'api',
                    'rss',
                    'html',
                    'email',
                ]),
            ],

            'url_base' => [
                'nullable',
                'url',
                'max:1000',
            ],

            'parser_class' => [
                'required',

                Rule::in(
                    self::PARSERS
                ),
            ],

            'frequence_polling_minutes' => [
                'required',
                'integer',
                'min:1',
                'max:10080',
            ],

            'actif' => [
                'required',
                'boolean',
            ],

            /*
             * Optionnel.
             *
             * Le modèle Source chiffre automatiquement
             * ces données grâce à encrypted:array.
             */
            'credentials' => [
                'sometimes',
                'nullable',
                'array',
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Sérialisation sécurisée
    |--------------------------------------------------------------------------
    |
    | IMPORTANT :
    | credentials n'est jamais retourné.
    |
    */

    private function serializeSource(
        Source $source
    ): array {
        return [
            'id' =>
                $source->id,

            'nom' =>
                $source->nom,

            'type' =>
                $source->type,

            'url_base' =>
                $source->url_base,

            'parser_class' =>
                $source->parser_class,

            'frequence_polling_minutes' =>
                (int)
                $source
                    ->frequence_polling_minutes,

            'actif' =>
                (bool)
                $source->actif,

            'derniere_execution' =>
                $source
                    ->derniere_execution,

            'dernier_statut' =>
                $source
                    ->dernier_statut,

            /*
             * Seulement oui/non.
             * Jamais le secret.
             */
            'credentials_configured' =>
                ! empty(
                    $source->credentials
                ),

            'missions_count' =>
                (int)
                (
                    $source
                        ->missions_count
                    ?? 0
                ),

            'mission_occurrences_count' =>
                (int)
                (
                    $source
                        ->mission_occurrences_count
                    ?? 0
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Duration
    |--------------------------------------------------------------------------
    */

    private function durationMs(
        float $startedAt
    ): int {
        return (int) round(
            (
                microtime(true)
                - $startedAt
            ) * 1000
        );
    }
}