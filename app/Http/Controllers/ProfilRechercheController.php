<?php

namespace App\Http\Controllers;

use App\Models\Alerte;
use App\Models\Mission;
use App\Models\ProfilRecherche;
use App\Models\RegleFiltrage;
use App\Models\RegleScoring;
use App\Services\MissionScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfilRechercheController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Liste des profils
    |--------------------------------------------------------------------------
    */

    public function index(): JsonResponse
    {
        $profils = ProfilRecherche::query()
            ->with([
                'reglesFiltrage.critere:id,code,label,type',
                'reglesScoring.critere:id,code,label,type',
                'alertes',
            ])
            ->withCount('scoresMissions')
            ->orderBy('nom')
            ->get();

        return response()->json(
            $profils
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Détail d'un profil
    |--------------------------------------------------------------------------
    */

    public function show(
        ProfilRecherche $profil
    ): JsonResponse {
        $profil->load([
            'reglesFiltrage.critere:id,code,label,type',
            'reglesScoring.critere:id,code,label,type',
            'alertes',
        ]);

        $profil->loadCount(
            'scoresMissions'
        );

        return response()->json([
            'profil' => $profil,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Modifier un profil
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        ProfilRecherche $profil,
        MissionScoringService $scoringService
    ): JsonResponse {
        $validated = $request->validate([
            /*
             * Profil
             */
            'nom' => [
                'required',
                'string',
                'max:255',
            ],

            'actif' => [
                'required',
                'boolean',
            ],

            /*
             * Règles de filtrage
             */
            'regles_filtrage' => [
                'sometimes',
                'array',
            ],

            'regles_filtrage.*.id' => [
                'required',
                'integer',
                'exists:regles_filtrage,id',
            ],

            'regles_filtrage.*.operateur' => [
                'required',
                'string',
                'in:egal,contient,superieur_egal,inferieur_egal,dans',
            ],

            'regles_filtrage.*.valeur' => [
                'nullable',
                'string',
                'max:1000',
            ],

            /*
             * Règles de scoring
             */
            'regles_scoring' => [
                'sometimes',
                'array',
            ],

            'regles_scoring.*.id' => [
                'required',
                'integer',
                'exists:regles_scoring,id',
            ],

            'regles_scoring.*.poids' => [
                'required',
                'integer',
                'min:0',
                'max:100',
            ],

            /*
             * Alertes
             */
            'alertes' => [
                'sometimes',
                'array',
            ],

            'alertes.*.id' => [
                'required',
                'integer',
                'exists:alertes,id',
            ],

            'alertes.*.canal' => [
                'required',
                'string',
                'in:email,telegram,webhook',
            ],

            'alertes.*.frequence' => [
                'required',
                'string',
                'in:immediate,daily,weekly',
            ],

            'alertes.*.destination' => [
                'required',
                'string',
                'max:500',
            ],

            'alertes.*.seuil_score_min' => [
                'required',
                'integer',
                'min:0',
                'max:100',
            ],

            'alertes.*.actif' => [
                'required',
                'boolean',
            ],
        ]);

        DB::transaction(
            function () use (
                $profil,
                $validated
            ) {
                /*
                |--------------------------------------------------------------------------
                | Profil
                |--------------------------------------------------------------------------
                */

                $profil->update([
                    'nom' =>
                        $validated['nom'],

                    'actif' =>
                        $validated['actif'],
                ]);

                /*
                |--------------------------------------------------------------------------
                | Filtres
                |--------------------------------------------------------------------------
                */

                foreach (
                    $validated['regles_filtrage']
                        ?? []
                    as $regleData
                ) {
                    /*
                     * Important :
                     * on vérifie que la règle appartient
                     * réellement au profil modifié.
                     */
                    $regle =
                        RegleFiltrage::query()
                            ->where(
                                'profil_recherche_id',
                                $profil->id
                            )
                            ->findOrFail(
                                $regleData['id']
                            );

                    $regle->update([
                        'operateur' =>
                            $regleData[
                                'operateur'
                            ],

                        'valeur' =>
                            $regleData[
                                'valeur'
                            ] ?? null,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Scoring
                |--------------------------------------------------------------------------
                */

                foreach (
                    $validated['regles_scoring']
                        ?? []
                    as $regleData
                ) {
                    $regle =
                        RegleScoring::query()
                            ->where(
                                'profil_recherche_id',
                                $profil->id
                            )
                            ->findOrFail(
                                $regleData['id']
                            );

                    $regle->update([
                        'poids' =>
                            $regleData[
                                'poids'
                            ],
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Alertes
                |--------------------------------------------------------------------------
                */

                foreach (
                    $validated['alertes']
                        ?? []
                    as $alerteData
                ) {
                    $alerte =
                        Alerte::query()
                            ->where(
                                'profil_recherche_id',
                                $profil->id
                            )
                            ->findOrFail(
                                $alerteData['id']
                            );

                    $alerte->update([
                        'canal' =>
                            $alerteData[
                                'canal'
                            ],

                        'destination' =>
                            $alerteData[
                                'destination'
                            ],

                        'frequence' =>
                            $alerteData[
                                'frequence'
                            ],

                        'seuil_score_min' =>
                            $alerteData[
                                'seuil_score_min'
                            ],

                        'actif' =>
                            $alerteData[
                                'actif'
                            ],
                    ]);
                }
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Recalcul des scores
        |--------------------------------------------------------------------------
        |
        | Si le poids ou les critères changent,
        | les scores existants ne doivent pas rester obsolètes.
        |
        */

        Mission::query()
            ->with('stacks')
            ->chunkById(
                100,
                function ($missions) use (
                    $profil,
                    $scoringService
                ) {
                    foreach (
                        $missions as $mission
                    ) {
                        $scoringService
                            ->calculer(
                                $mission,
                                $profil
                            );
                    }
                }
            );

        /*
        |--------------------------------------------------------------------------
        | Retourner la configuration actualisée
        |--------------------------------------------------------------------------
        */

        $profil->refresh();

        $profil->load([
            'reglesFiltrage.critere:id,code,label,type',
            'reglesScoring.critere:id,code,label,type',
            'alertes',
        ]);

        $profil->loadCount(
            'scoresMissions'
        );

        return response()->json([
            'message' =>
                'Profil mis à jour avec succès.',

            'profil' =>
                $profil,
        ]);
    }
}