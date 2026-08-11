<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\ProfilRechercheController;

Route::get('/api/dashboard', [
    DashboardController::class,
    'index',
]);

Route::get('/', function () {
    return view('app');
});

Route::get('/api/missions', [
    MissionController::class,
    'index',
]);

Route::get('/api/missions/{mission}', [
    MissionController::class,
    'show',
]);



Route::get('/api/sources', [
    SourceController::class,
    'index',
]);

Route::patch('/api/sources/{source}', [
    SourceController::class,
    'update',
]);


Route::patch('/api/missions/{mission}/statut', [
    MissionController::class,
    'updateStatut',
]);


Route::get('/api/profils', [
    ProfilRechercheController::class,
    'index',
]);

Route::get('/api/profils/{profil}', [
    ProfilRechercheController::class,
    'show',
]);

Route::patch('/api/profils/{profil}', [
    ProfilRechercheController::class,
    'update',
]);