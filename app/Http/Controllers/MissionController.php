<?php

namespace App\Http\Controllers;

use App\Models\Mission;
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
                (int) $request->input('per_page', 15),
                5
            ),
            100
        );

        /*
         * IMPORTANT :
         *
         * On ne récupère pas raw_data ni la description
         * dans la liste principale.
         *
         * raw_data Free-Work peut contenir beaucoup de HTML
         * et provoquer une consommation mémoire importante
         * lors du tri MySQL.
         *
         * Ces informations restent disponibles
         * dans show() lorsqu'on ouvre une mission.
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
                'scoresProfils',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Recherche
        |--------------------------------------------------------------------------
        |
        | On peut rechercher dans description
        | sans retourner description dans le SELECT.
        |
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
        | Pagination + tri
        |--------------------------------------------------------------------------
        */

        $missions = $query
            ->orderByDesc('date_publication')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json(
            $missions
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Détail d'une mission
    |--------------------------------------------------------------------------
    |
    | Ici, Laravel charge la mission complète.
    | raw_data et description restent disponibles.
    |
    */

    public function show(
        Mission $mission
    ): JsonResponse {
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