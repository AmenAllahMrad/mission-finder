<?php

namespace Database\Seeders;

use App\Models\Critere;
use Illuminate\Database\Seeder;

class CritereSeeder extends Seeder
{
    public function run(): void
    {
        $criteres = [
            [
                'code' => 'stack',
                'label' => 'Stack technique',
                'type' => 'texte',
            ],
            [
                'code' => 'tjm_min',
                'label' => 'TJM minimum',
                'type' => 'nombre',
            ],
            [
                'code' => 'remote',
                'label' => 'Type de remote',
                'type' => 'liste',
            ],
            [
                'code' => 'duree_min',
                'label' => 'Durée minimum',
                'type' => 'nombre',
            ],
            [
                'code' => 'localisation',
                'label' => 'Localisation',
                'type' => 'texte',
            ],
            [
                'code' => 'secteur',
                'label' => 'Secteur',
                'type' => 'texte',
            ],
        ];

        foreach ($criteres as $critere) {
            Critere::updateOrCreate(
                ['code' => $critere['code']],
                $critere
            );
        }
    }
}