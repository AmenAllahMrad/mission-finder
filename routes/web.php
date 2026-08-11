<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\SourceController;

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
