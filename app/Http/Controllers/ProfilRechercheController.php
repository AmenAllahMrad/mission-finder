<?php

namespace App\Http\Controllers;

use App\Models\Alerte;
use App\Models\AlerteMissionEnvoyee;
use App\Models\Critere;
use App\Models\Mission;
use App\Models\ProfilRecherche;
use App\Models\RegleFiltrage;
use App\Models\RegleScoring;
use App\Models\ScoreMissionProfil;
use App\Services\MissionScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

        return response()->json($profils);
    }

    /*
    |--------------------------------------------------------------------------
    | Liste des critères disponibles
    |--------------------------------------------------------------------------
    */

    public function criteres(): JsonResponse
    {
        $criteres = Critere::query()
            ->select([
                'id',
                'code',
                'label',
                'type',
            ])
            ->orderBy('label')
            ->get();

        return response()->json($criteres);
    }

    /*
    |--------------------------------------------------------------------------
    | Créer un profil
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'nom' => [
                'required',
                'string',
                'max:255',
                'unique:profils_recherche,nom',
            ],

            'actif' => [
                'required',
                'boolean',
            ],
        ]);

        $profil = ProfilRecherche::create([
            'nom' => $validated['nom'],
            'actif' => $validated['actif'],
        ]);

        $profil->load([
            'reglesFiltrage.critere',
            'reglesScoring.critere',
            'alertes',
        ]);

        $profil->loadCount(
            'scoresMissions'
        );

        return response()->json([
            'message' =>
                'Profil créé avec succès.',

            'profil' =>
                $profil,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Détail
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
    | Modifier le profil complet
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

                Rule::unique(
                    'profils_recherche',
                    'nom'
                )->ignore($profil->id),
            ],

            'actif' => [
                'required',
                'boolean',
            ],

            /*
             * Filtres
             */
            'regles_filtrage' => [
                'present',
                'array',
            ],

            'regles_filtrage.*.id' => [
                'nullable',
                'integer',
            ],

            'regles_filtrage.*.critere_id' => [
                'nullable',
                'integer',
                'exists:criteres,id',
            ],

            'regles_filtrage.*.operateur' => [
                'required',
                Rule::in([
                    'egal',
                    'contient',
                    'superieur_egal',
                    'inferieur_egal',
                    'dans',
                ]),
            ],

            'regles_filtrage.*.valeur' => [
                'nullable',
                'string',
                'max:1000',
            ],

            /*
             * Scoring
             */
            'regles_scoring' => [
                'present',
                'array',
            ],

            'regles_scoring.*.id' => [
                'nullable',
                'integer',
            ],

            'regles_scoring.*.critere_id' => [
                'nullable',
                'integer',
                'exists:criteres,id',
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
                'present',
                'array',
            ],

            'alertes.*.id' => [
                'nullable',
                'integer',
            ],

            'alertes.*.canal' => [
                'required',
                Rule::in([
                    'email',
                    'telegram',
                    'webhook',
                ]),
            ],

            'alertes.*.destination' => [
                'required',
                'string',
                'max:500',
            ],

            'alertes.*.frequence' => [
                'required',
                Rule::in([
                    'immediate',
                    'daily',
                    'weekly',
                ]),
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

        DB::transaction(function () use (
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
            | Synchronisation des règles de filtrage
            |--------------------------------------------------------------------------
            */

            $idsFiltresConserves = [];

            foreach (
                $validated['regles_filtrage']
                as $data
            ) {
                /*
                 * Modification d'une règle existante.
                 */
                if (! empty($data['id'])) {
                    $regle =
                        RegleFiltrage::query()
                            ->where(
                                'profil_recherche_id',
                                $profil->id
                            )
                            ->findOrFail(
                                $data['id']
                            );

                    $regle->update([
                        'operateur' =>
                            $data['operateur'],

                        'valeur' =>
                            $data['valeur']
                            ?? null,
                    ]);
                } else {
                    /*
                     * Nouvelle règle.
                     */
                    if (
                        empty(
                            $data['critere_id']
                        )
                    ) {
                        abort(
                            422,
                            'Un critère est requis pour une nouvelle règle de filtrage.'
                        );
                    }

                    $regle =
                        RegleFiltrage::updateOrCreate(
                            [
                                'profil_recherche_id' =>
                                    $profil->id,

                                'critere_id' =>
                                    $data[
                                        'critere_id'
                                    ],
                            ],
                            [
                                'operateur' =>
                                    $data[
                                        'operateur'
                                    ],

                                'valeur' =>
                                    $data[
                                        'valeur'
                                    ] ?? null,
                            ]
                        );
                }

                $idsFiltresConserves[] =
                    $regle->id;
            }

            /*
             * Une règle absente du payload
             * est considérée comme supprimée.
             */
            RegleFiltrage::query()
                ->where(
                    'profil_recherche_id',
                    $profil->id
                )
                ->when(
                    count(
                        $idsFiltresConserves
                    ) > 0,
                    fn ($query) =>
                        $query->whereNotIn(
                            'id',
                            $idsFiltresConserves
                        )
                )
                ->when(
                    count(
                        $idsFiltresConserves
                    ) === 0,
                    fn ($query) =>
                        $query
                )
                ->delete();

            /*
            |--------------------------------------------------------------------------
            | Synchronisation scoring
            |--------------------------------------------------------------------------
            */

            $idsScoringConserves = [];

            foreach (
                $validated['regles_scoring']
                as $data
            ) {
                if (! empty($data['id'])) {
                    $regle =
                        RegleScoring::query()
                            ->where(
                                'profil_recherche_id',
                                $profil->id
                            )
                            ->findOrFail(
                                $data['id']
                            );

                    $regle->update([
                        'poids' =>
                            $data['poids'],
                    ]);
                } else {
                    if (
                        empty(
                            $data['critere_id']
                        )
                    ) {
                        abort(
                            422,
                            'Un critère est requis pour une nouvelle règle de scoring.'
                        );
                    }

                    $regle =
                        RegleScoring::updateOrCreate(
                            [
                                'profil_recherche_id' =>
                                    $profil->id,

                                'critere_id' =>
                                    $data[
                                        'critere_id'
                                    ],
                            ],
                            [
                                'poids' =>
                                    $data[
                                        'poids'
                                    ],
                            ]
                        );
                }

                $idsScoringConserves[] =
                    $regle->id;
            }

            RegleScoring::query()
                ->where(
                    'profil_recherche_id',
                    $profil->id
                )
                ->when(
                    count(
                        $idsScoringConserves
                    ) > 0,
                    fn ($query) =>
                        $query->whereNotIn(
                            'id',
                            $idsScoringConserves
                        )
                )
                ->delete();

            if (
                count(
                    $idsScoringConserves
                ) === 0
            ) {
                RegleScoring::query()
                    ->where(
                        'profil_recherche_id',
                        $profil->id
                    )
                    ->delete();
            }

            /*
            |--------------------------------------------------------------------------
            | Synchronisation alertes
            |--------------------------------------------------------------------------
            */

            $idsAlertesConservees = [];

            foreach (
                $validated['alertes']
                as $data
            ) {
                if (! empty($data['id'])) {
                    $alerte =
                        Alerte::query()
                            ->where(
                                'profil_recherche_id',
                                $profil->id
                            )
                            ->findOrFail(
                                $data['id']
                            );

                    $alerte->update([
                        'canal' =>
                            $data['canal'],

                        'destination' =>
                            $data[
                                'destination'
                            ],

                        'frequence' =>
                            $data[
                                'frequence'
                            ],

                        'seuil_score_min' =>
                            $data[
                                'seuil_score_min'
                            ],

                        'actif' =>
                            $data['actif'],
                    ]);
                } else {
                    $alerte =
                        Alerte::create([
                            'profil_recherche_id' =>
                                $profil->id,

                            'canal' =>
                                $data['canal'],

                            'destination' =>
                                $data[
                                    'destination'
                                ],

                            'frequence' =>
                                $data[
                                    'frequence'
                                ],

                            'seuil_score_min' =>
                                $data[
                                    'seuil_score_min'
                                ],

                            'actif' =>
                                $data['actif'],
                        ]);
                }

                $idsAlertesConservees[] =
                    $alerte->id;
            }

            /*
             * Alertes supprimées dans l'interface.
             */
            $alertesSupprimees =
                Alerte::query()
                    ->where(
                        'profil_recherche_id',
                        $profil->id
                    )
                    ->when(
                        count(
                            $idsAlertesConservees
                        ) > 0,
                        fn ($query) =>
                            $query->whereNotIn(
                                'id',
                                $idsAlertesConservees
                            )
                    )
                    ->pluck('id');

            if (
                count(
                    $idsAlertesConservees
                ) === 0
            ) {
                $alertesSupprimees =
                    Alerte::query()
                        ->where(
                            'profil_recherche_id',
                            $profil->id
                        )
                        ->pluck('id');
            }

            if (
                $alertesSupprimees
                    ->isNotEmpty()
            ) {
                AlerteMissionEnvoyee::query()
                    ->whereIn(
                        'alerte_id',
                        $alertesSupprimees
                    )
                    ->delete();

                Alerte::query()
                    ->whereIn(
                        'id',
                        $alertesSupprimees
                    )
                    ->delete();
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Recalcul des scores du profil
        |--------------------------------------------------------------------------
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
                        $missions
                        as $mission
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
        | Retour API
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

    /*
    |--------------------------------------------------------------------------
    | Suppression d'un profil
    |--------------------------------------------------------------------------
    */

    public function destroy(
        ProfilRecherche $profil
    ): JsonResponse {
        DB::transaction(function () use (
            $profil
        ) {
            /*
             * Traces d'alertes.
             */
            $idsAlertes =
                Alerte::query()
                    ->where(
                        'profil_recherche_id',
                        $profil->id
                    )
                    ->pluck('id');

            if (
                $idsAlertes->isNotEmpty()
            ) {
                AlerteMissionEnvoyee::query()
                    ->whereIn(
                        'alerte_id',
                        $idsAlertes
                    )
                    ->delete();
            }

            /*
             * Alertes.
             */
            Alerte::query()
                ->where(
                    'profil_recherche_id',
                    $profil->id
                )
                ->delete();

            /*
             * Scores.
             */
            ScoreMissionProfil::query()
                ->where(
                    'profil_recherche_id',
                    $profil->id
                )
                ->delete();

            /*
             * Scoring.
             */
            RegleScoring::query()
                ->where(
                    'profil_recherche_id',
                    $profil->id
                )
                ->delete();

            /*
             * Filtres.
             */
            RegleFiltrage::query()
                ->where(
                    'profil_recherche_id',
                    $profil->id
                )
                ->delete();

            /*
             * Profil.
             */
            $profil->delete();
        });

        return response()->json([
            'message' =>
                'Profil supprimé avec succès.',
        ]);
    }
}