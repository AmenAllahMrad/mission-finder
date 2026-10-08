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
        /*
         * Validation des nouveaux paramètres :
         * - date_from
         * - date_to
         * - sort
         */
        $validated = $request->validate([
            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],

            'sort' => [
                'nullable',
                'in:recent,oldest,score_desc,score_asc',
            ],
        ]);

        $perPage = min(
            max(
                (int) $request->input('per_page', 15),
                5
            ),
            100
        );

        /*
        |--------------------------------------------------------------------------
        | Profil sélectionné
        |--------------------------------------------------------------------------
        */

        $profilId = null;

        if ($request->filled('profil_id')) {
            $value = (int) $request->input('profil_id');

            if ($value > 0) {
                $profilId = $value;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Score minimum
        |--------------------------------------------------------------------------
        */

        $scoreMin = null;

        if (
            $request->filled('score_min') &&
            is_numeric($request->input('score_min'))
        ) {
            $scoreMin = max(
                0,
                (int) $request->input('score_min')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Dates
        |--------------------------------------------------------------------------
        */

        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        /*
        |--------------------------------------------------------------------------
        | Tri
        |--------------------------------------------------------------------------
        */

        $sort = $validated['sort'] ?? 'recent';

        /*
         * IMPORTANT :
         *
         * On ne récupère volontairement PAS :
         * - description
         * - raw_data
         *
         * dans la liste principale.
         *
         * raw_data peut contenir énormément de HTML,
         * notamment avec Free-Work.
         *
         * Ces données restent disponibles dans show().
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
                 * Si un profil est sélectionné,
                 * on ne charge que son score.
                 */
                'scoresProfils' => function ($scoreQuery) use ($profilId) {
                    if ($profilId !== null) {
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

        if ($request->filled('search')) {
            $search = trim(
                $request
                    ->string('search')
                    ->toString()
            );

            if ($search !== '') {
                $query->where(
                    function ($q) use ($search) {
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

        if ($request->filled('statut')) {
            $query->where(
                'statut',
                $request
                    ->string('statut')
                    ->toString()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Filtre remote
        |--------------------------------------------------------------------------
        */

        if ($request->filled('remote')) {
            $query->where(
                'remote_type',
                $request
                    ->string('remote')
                    ->toString()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Filtre source
        |--------------------------------------------------------------------------
        */

        if ($request->filled('source_id')) {
            $sourceId = (int) $request->input(
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
        | Filtre dates
        |--------------------------------------------------------------------------
        */

        if ($dateFrom !== null) {
            $query->whereDate(
                'date_publication',
                '>=',
                $dateFrom
            );
        }

        if ($dateTo !== null) {
            $query->whereDate(
                'date_publication',
                '<=',
                $dateTo
            );
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

        if ($profilId !== null) {
            $query->whereHas(
                'scoresProfils',
                function ($scoreQuery) use (
                    $profilId,
                    $scoreMin
                ) {
                    $scoreQuery->where(
                        'profil_recherche_id',
                        $profilId
                    );

                    if ($scoreMin !== null) {
                        $scoreQuery->where(
                            'score',
                            '>=',
                            $scoreMin
                        );
                    }
                }
            );
        } elseif ($scoreMin !== null) {
            $query->whereHas(
                'scoresProfils',
                function ($scoreQuery) use ($scoreMin) {
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
        | Tri
        |--------------------------------------------------------------------------
        */

        if (
            $sort === 'score_desc' ||
            $sort === 'score_asc'
        ) {
            /*
             * Si un profil est sélectionné :
             * on trie sur son score.
             *
             * Sinon :
             * on prend le meilleur score parmi les profils.
             */
            $scoreSubquery = '
                (
                    SELECT COALESCE(MAX(smp.score), 0)
                    FROM scores_missions_profils AS smp
                    WHERE smp.mission_id = missions.id
            ';

            if ($profilId !== null) {
                $scoreSubquery .= sprintf(
                    ' AND smp.profil_recherche_id = %d',
                    $profilId
                );
            }

            $scoreSubquery .= '
                )
            ';

            $direction = $sort === 'score_asc'
                ? 'ASC'
                : 'DESC';

            $query
                ->orderByRaw(
                    $scoreSubquery . $direction
                )
                ->orderByDesc('date_publication')
                ->orderByDesc('id');
        } else {
            /*
             * Tri chronologique.
             */
            $direction = $sort === 'oldest'
                ? 'asc'
                : 'desc';

            /*
             * Les dates nulles restent en dernier.
             */
            $query
                ->orderByRaw(
                    'date_publication IS NULL ASC'
                )
                ->orderBy(
                    'date_publication',
                    $direction
                )
                ->orderBy(
                    'id',
                    $direction
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $missions = $query->paginate(
            $perPage
        );

        /*
        |--------------------------------------------------------------------------
        | Profils disponibles pour Vue
        |--------------------------------------------------------------------------
        */

        $profils = ProfilRecherche::query()
            ->select([
                'id',
                'nom',
                'actif',
            ])
            ->where(
                'actif',
                true
            )
            ->orderBy('nom')
            ->get();

        /*
         * Structure paginator Laravel
         * + profils.
         */
        $payload = $missions->toArray();

        $payload['profils'] = $profils;

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
         * Une mission nouvellement découverte
         * devient automatiquement "vue"
         * lorsqu'on ouvre son détail.
         */
        if ($mission->statut === 'nouveau') {
            $mission->statut = 'vu';
            $mission->save();
        }

        /*
         * Ici seulement, on charge les informations
         * complètes de la mission.
         */
        $mission->load([
            'source:id,nom',
            'stacks:id,nom',
            'scoresProfils',
            'sourceOccurrences.source:id,nom',
        ]);

        return response()->json([
            'mission' => $mission,
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
        $validated = $request->validate([
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
            $validated['statut'] ===
            'postule'
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