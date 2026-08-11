<?php

namespace App\Http\Controllers;

use App\Models\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SourceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Liste des sources
    |--------------------------------------------------------------------------
    */

    public function index(): JsonResponse
    {
        $sources = Source::query()
            ->select([
                'id',
                'nom',
                'type',
                'url_base',
                'parser_class',
                'frequence_polling_minutes',
                'actif',
                'derniere_execution',
                'dernier_statut',
            ])
            ->orderBy('nom')
            ->get();

        return response()->json($sources);
    }

    /*
    |--------------------------------------------------------------------------
    | Modifier la configuration d'une source
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Source $source
    ): JsonResponse {
        $validated = $request->validate([
            'actif' => [
                'required',
                'boolean',
            ],

            'frequence_polling_minutes' => [
                'required',
                'integer',
                'min:1',
                'max:10080',
            ],
        ]);

        $source->actif =
            $validated['actif'];

        $source->frequence_polling_minutes =
            $validated['frequence_polling_minutes'];

        $source->save();

        return response()->json([
            'message' =>
                'Source mise à jour avec succès.',

            'source' => [
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
                    $source->frequence_polling_minutes,

                'actif' =>
                    (bool) $source->actif,

                'derniere_execution' =>
                    $source->derniere_execution,

                'dernier_statut' =>
                    $source->dernier_statut,
            ],
        ]);
    }
}