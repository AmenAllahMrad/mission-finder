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

        $query = Mission::query()
            ->with([
                'source:id,nom',
                'stacks:id,nom',
                'scoresProfils',
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

            $query->where(
                function ($q) use ($search) {
                    $q->where(
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

        /*
        |--------------------------------------------------------------------------
        | Statut
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
        | Remote
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
        | Source
        |--------------------------------------------------------------------------
        */

        if ($request->filled('source_id')) {
            $query->where(
                'source_id',
                (int) $request->input(
                    'source_id'
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
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
         * on enregistre la date.
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