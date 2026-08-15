<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use App\Models\ProfilRecherche;
use App\Models\Source;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Statistiques principales
        |--------------------------------------------------------------------------
        */

        $missionsTotal = Mission::count();

        $missionsNouvelles = Mission::where(
            'statut',
            'nouveau'
        )->count();

        $missionsAujourdHui = Mission::query()
            ->whereDate(
                'date_publication',
                today()
            )
            ->count();

        $sourcesTotal = Source::count();

        $sourcesActives = Source::where(
            'actif',
            true
        )->count();

        $profilsActifs = ProfilRecherche::where(
            'actif',
            true
        )->count();

        $candidaturesTotal = Mission::where(
            'statut',
            'postule'
        )->count();

        /*
        |--------------------------------------------------------------------------
        | Évolution sur les 14 derniers jours
        |--------------------------------------------------------------------------
        */

        $debutEvolution = Carbon::today()
            ->subDays(13);

        $finEvolution = Carbon::today()
            ->endOfDay();

        $missionsParJour = Mission::query()
            ->whereBetween(
                'date_publication',
                [
                    $debutEvolution,
                    $finEvolution,
                ]
            )
            ->selectRaw(
                'DATE(date_publication) as jour, COUNT(*) as total'
            )
            ->groupByRaw(
                'DATE(date_publication)'
            )
            ->orderByRaw(
                'DATE(date_publication)'
            )
            ->get()
            ->keyBy('jour');

        $evolution = [];

        for (
            $i = 0;
            $i < 14;
            $i++
        ) {
            $date = $debutEvolution
                ->copy()
                ->addDays($i);

            $cle = $date->format(
                'Y-m-d'
            );

            $evolution[] = [
                'date' => $cle,

                'label' => $date->format(
                    'd/m'
                ),

                'total' => (int) (
                    $missionsParJour[$cle]
                        ->total
                    ?? 0
                ),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Tendance 7 jours
        |--------------------------------------------------------------------------
        */

        $septJours = Mission::query()
            ->whereBetween(
                'date_publication',
                [
                    Carbon::today()
                        ->subDays(6),

                    Carbon::today()
                        ->endOfDay(),
                ]
            )
            ->count();

        $septJoursPrecedents = Mission::query()
            ->whereBetween(
                'date_publication',
                [
                    Carbon::today()
                        ->subDays(13),

                    Carbon::today()
                        ->subDays(7)
                        ->endOfDay(),
                ]
            )
            ->count();

        if (
            $septJoursPrecedents > 0
        ) {
            $tendance = round(
                (
                    (
                        $septJours
                        -
                        $septJoursPrecedents
                    )
                    /
                    $septJoursPrecedents
                )
                * 100,
                1
            );
        } elseif (
            $septJours > 0
        ) {
            $tendance = 100;
        } else {
            $tendance = 0;
        }

        /*
        |--------------------------------------------------------------------------
        | Répartition des statuts
        |--------------------------------------------------------------------------
        */

        $statutsBruts = Mission::query()
            ->selectRaw(
                'statut, COUNT(*) as total'
            )
            ->groupBy(
                'statut'
            )
            ->pluck(
                'total',
                'statut'
            );

        $statuts = [
            [
                'statut' => 'nouveau',
                'label' => 'Nouveau',
                'total' => (int) (
                    $statutsBruts['nouveau']
                    ?? 0
                ),
            ],

            [
                'statut' => 'vu',
                'label' => 'Vu',
                'total' => (int) (
                    $statutsBruts['vu']
                    ?? 0
                ),
            ],

            [
                'statut' => 'interessant',
                'label' => 'Intéressant',
                'total' => (int) (
                    $statutsBruts['interessant']
                    ?? 0
                ),
            ],

            [
                'statut' => 'postule',
                'label' => 'Postulé',
                'total' => (int) (
                    $statutsBruts['postule']
                    ?? 0
                ),
            ],

            [
                'statut' => 'ecarte',
                'label' => 'Écarté',
                'total' => (int) (
                    $statutsBruts['ecarte']
                    ?? 0
                ),
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Missions par source
        |--------------------------------------------------------------------------
        */

        $missionsParSource = Source::query()
            ->select([
                'id',
                'nom',
                'actif',
                'dernier_statut',
            ])
            ->withCount(
                'missions'
            )
            ->orderByDesc(
                'missions_count'
            )
            ->get()
            ->map(
                function ($source) {
                    return [
                        'id' =>
                            $source->id,

                        'nom' =>
                            $source->nom,

                        'total' =>
                            (int) $source
                                ->missions_count,

                        'actif' =>
                            (bool) $source
                                ->actif,

                        'dernier_statut' =>
                            $source
                                ->dernier_statut,
                    ];
                }
            )
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Top technologies
        |--------------------------------------------------------------------------
        */

        $technologies = DB::table(
            'mission_stack'
        )
            ->join(
                'stacks',
                'stacks.id',
                '=',
                'mission_stack.stack_id'
            )
            ->select(
                'stacks.id',
                'stacks.nom'
            )
            ->selectRaw(
                'COUNT(*) as total'
            )
            ->groupBy(
                'stacks.id',
                'stacks.nom'
            )
            ->orderByDesc(
                'total'
            )
            ->limit(7)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Meilleures opportunités
        |--------------------------------------------------------------------------
        */

        $meilleuresMissions =
            Mission::query()
                ->select([
                    'id',
                    'source_id',
                    'titre',
                    'entreprise',
                    'remote_type',
                    'localisation',
                    'date_publication',
                    'url_origine',
                    'statut',
                ])
                ->addSelect([
                    'meilleur_score' =>
                        DB::table(
                            'scores_missions_profils'
                        )
                            ->selectRaw(
                                'MAX(score)'
                            )
                            ->whereColumn(
                                'mission_id',
                                'missions.id'
                            ),
                ])
                ->with([
                    'source:id,nom',
                ])
                ->orderByDesc(
                    'meilleur_score'
                )
                ->orderByDesc(
                    'date_publication'
                )
                ->limit(5)
                ->get();

        /*
        |--------------------------------------------------------------------------
        | Candidatures récentes
        |--------------------------------------------------------------------------
        */

        $candidaturesRecentes =
            Mission::query()
                ->where(
                    'statut',
                    'postule'
                )
                ->whereNotNull(
                    'date_candidature'
                )
                ->select([
                    'id',
                    'titre',
                    'entreprise',
                    'date_candidature',
                    'url_origine',
                ])
                ->orderByDesc(
                    'date_candidature'
                )
                ->limit(5)
                ->get();

        /*
        |--------------------------------------------------------------------------
        | Santé des sources
        |--------------------------------------------------------------------------
        */

        $santeSources =
            $sourcesTotal > 0
                ? round(
                    (
                        $sourcesActives
                        /
                        $sourcesTotal
                    )
                    * 100
                )
                : 0;

        /*
        |--------------------------------------------------------------------------
        | Réponse
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'missions_total' =>
                $missionsTotal,

            'missions_nouvelles' =>
                $missionsNouvelles,

            'missions_aujourdhui' =>
                $missionsAujourdHui,

            'missions_7_jours' =>
                $septJours,

            'tendance_7_jours' =>
                $tendance,

            'sources_total' =>
                $sourcesTotal,

            'sources_actives' =>
                $sourcesActives,

            'sante_sources' =>
                $santeSources,

            'profils_actifs' =>
                $profilsActifs,

            'candidatures_total' =>
                $candidaturesTotal,

            'evolution' =>
                $evolution,

            'statuts' =>
                $statuts,

            'missions_par_source' =>
                $missionsParSource,

            'technologies' =>
                $technologies,

            'meilleures_missions' =>
                $meilleuresMissions,

            'candidatures_recentes' =>
                $candidaturesRecentes,
        ]);
    }
}