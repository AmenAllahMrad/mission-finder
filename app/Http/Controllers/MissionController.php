<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use App\Models\ProfilRecherche;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MissionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Liste des missions
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max(
                (int) $request->input(
                    'per_page',
                    15
                ),
                5
            ),
            100
        );

        /*
         * Profil éventuellement sélectionné.
         */
        $profilId = null;

        if (
            $request->filled(
                'profil_id'
            )
        ) {
            $value = (int) $request->input(
                'profil_id'
            );

            if ($value > 0) {
                $profilId = $value;
            }
        }

        /*
         * Score minimum éventuellement sélectionné.
         */
        $scoreMin = null;

        if (
            $request->filled(
                'score_min'
            )
            &&
            is_numeric(
                $request->input(
                    'score_min'
                )
            )
        ) {
            $scoreMin = max(
                0,
                (int) $request->input(
                    'score_min'
                )
            );
        }

        /*
         * IMPORTANT :
         *
         * On ne récupère volontairement PAS
         * description ni raw_data dans la liste.
         *
         * raw_data Free-Work peut contenir beaucoup
         * de HTML et rendre les tris MySQL trop lourds.
         *
         * Ces champs restent disponibles dans show().
         */
        $query = Mission::query()
            ->select([
                'id',
                'source_id',
                'titre',
                'entreprise',
                'tjm_min',
                'tjm_max',
                'remote_type',
                'localisation',
                'secteur',
                'duree_mois',
                'date_publication',
                'url_origine',
                'statut',
                'date_candidature',
            ])
            ->with([
                'source:id,nom',
                'stacks:id,nom',

                /*
                 * Si un profil est choisi,
                 * inutile de renvoyer les scores
                 * des autres profils.
                 */
                'scoresProfils' => function (
                    $scoreQuery
                ) use (
                    $profilId
                ) {
                    if (
                        $profilId !== null
                    ) {
                        $scoreQuery->where(
                            'profil_recherche_id',
                            $profilId
                        );
                    }
                },
            ]);

        /*
        |--------------------------------------------------------------------------
        | Recherche
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled(
                'search'
            )
        ) {
            $search = trim(
                $request
                    ->string(
                        'search'
                    )
                    ->toString()
            );

            if ($search !== '') {
                $query->where(
                    function (
                        $q
                    ) use (
                        $search
                    ) {
                        $q
                            ->where(
                                'titre',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'entreprise',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'description',
                                'like',
                                "%{$search}%"
                            );
                    }
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Filtre statut
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled(
                'statut'
            )
        ) {
            $query->where(
                'statut',
                $request
                    ->string(
                        'statut'
                    )
                    ->toString()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Filtre remote
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled(
                'remote'
            )
        ) {
            $query->where(
                'remote_type',
                $request
                    ->string(
                        'remote'
                    )
                    ->toString()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Filtre source
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled(
                'source_id'
            )
        ) {
            $sourceId =
                (int) $request->input(
                    'source_id'
                );

            if ($sourceId > 0) {
                $query->where(
                    'source_id',
                    $sourceId
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Filtre profil + score
        |--------------------------------------------------------------------------
        |
        | Cas 1 :
        | profil choisi + score minimum
        |
        | Cas 2 :
        | profil choisi sans score minimum
        |
        | Cas 3 :
        | score minimum sans profil
        | => au moins un profil doit atteindre ce score.
        |
        */

        if (
            $profilId !== null
        ) {
            $query->whereHas(
                'scoresProfils',
                function (
                    $scoreQuery
                ) use (
                    $profilId,
                    $scoreMin
                ) {
                    $scoreQuery->where(
                        'profil_recherche_id',
                        $profilId
                    );

                    if (
                        $scoreMin !== null
                    ) {
                        $scoreQuery->where(
                            'score',
                            '>=',
                            $scoreMin
                        );
                    }
                }
            );
        } elseif (
            $scoreMin !== null
        ) {
            $query->whereHas(
                'scoresProfils',
                function (
                    $scoreQuery
                ) use (
                    $scoreMin
                ) {
                    $scoreQuery->where(
                        'score',
                        '>=',
                        $scoreMin
                    );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $missions = $query
            ->orderByDesc(
                'date_publication'
            )
            ->orderByDesc(
                'id'
            )
            ->paginate(
                $perPage
            );

        /*
        |--------------------------------------------------------------------------
        | Profils disponibles pour le filtre Vue
        |--------------------------------------------------------------------------
        */

        $profils =
            ProfilRecherche::query()
                ->select([
                    'id',
                    'nom',
                    'actif',
                ])
                ->where(
                    'actif',
                    true
                )
                ->orderBy(
                    'nom'
                )
                ->get();

        /*
         * On conserve exactement la structure
         * habituelle du paginator Laravel et
         * on ajoute simplement "profils".
         */
        $payload =
            $missions->toArray();

        $payload['profils'] =
            $profils;

        return response()->json(
            $payload
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Détail d'une mission
    |--------------------------------------------------------------------------
    */

    public function show(
        Mission $mission
    ): JsonResponse {
        /*
         * Une mission ouverte pour la première fois
         * n'est plus considérée comme "nouvelle".
         *
         * On ne modifie automatiquement QUE
         * le statut "nouveau".
         *
         * Les statuts :
         * - vu
         * - interessant
         * - postule
         * - ecarte
         *
         * restent donc toujours sous contrôle
         * de l'utilisateur.
         */
        if (
            $mission->statut ===
            'nouveau'
        ) {
            $mission->statut =
                'vu';

            $mission->save();
        }

        /*
         * Le détail charge volontairement
         * les données complètes de la mission.
         */
        $mission->load([
            'source:id,nom',
            'stacks:id,nom',
            'scoresProfils',
            'sourceOccurrences.source:id,nom',
        ]);

        return response()->json([
            'mission' =>
                $mission,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Modifier le statut
    |--------------------------------------------------------------------------
    */

    public function updateStatut(
        Request $request,
        Mission $mission
    ): JsonResponse {
        $validated =
            $request->validate([
                'statut' => [
                    'required',

                    'in:nouveau,vu,interessant,ecarte,postule',
                ],
            ]);

        $mission->statut =
            $validated['statut'];

        /*
         * Lors du premier passage à "postule",
         * on conserve la date de candidature.
         */
        if (
            $validated['statut']
            === 'postule'
        ) {
            $mission->date_candidature =
                $mission->date_candidature
                ?? now();
        }

        $mission->save();

        return response()->json([
            'message' =>
                'Statut mis à jour.',

            'mission' =>
                $mission,
        ]);
    }
}