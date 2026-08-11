<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use App\Models\ProfilRecherche;
use App\Models\Source;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            /*
            |--------------------------------------------------------------------------
            | Missions
            |--------------------------------------------------------------------------
            */

            'missions_total' => Mission::count(),

            'missions_nouvelles' => Mission::where(
                'statut',
                'nouveau'
            )->count(),

            /*
            |--------------------------------------------------------------------------
            | Sources
            |--------------------------------------------------------------------------
            */

            'sources_total' => Source::count(),

            'sources_actives' => Source::where(
                'actif',
                true
            )->count(),

            /*
            |--------------------------------------------------------------------------
            | Profils
            |--------------------------------------------------------------------------
            */

            'profils_actifs' => ProfilRecherche::where(
                'actif',
                true
            )->count(),

            /*
            |--------------------------------------------------------------------------
            | Candidatures
            |--------------------------------------------------------------------------
            */

            'candidatures_total' => Mission::where(
                'statut',
                'postule'
            )->count(),

            /*
            |--------------------------------------------------------------------------
            | Candidatures récentes
            |--------------------------------------------------------------------------
            */

            'candidatures_recentes' => Mission::query()
                ->where('statut', 'postule')
                ->whereNotNull('date_candidature')
                ->select([
                    'id',
                    'titre',
                    'entreprise',
                    'date_candidature',
                    'url_origine',
                ])
                ->orderByDesc('date_candidature')
                ->limit(5)
                ->get(),
        ]);
    }
}