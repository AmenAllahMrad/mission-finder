<?php

namespace App\Services;

use App\Models\Mission;
use App\Models\ProfilRecherche;
use App\Models\RegleFiltrage;
use Illuminate\Support\Str;

class MissionFilteringService
{
    public function correspond(
        Mission $mission,
        ProfilRecherche $profil
    ): bool {
        $profil->loadMissing('reglesFiltrage.critere');
        $mission->loadMissing('stacks');

        foreach ($profil->reglesFiltrage as $regle) {
            if (! $this->respecteRegle($mission, $regle)) {
                return false;
            }
        }

        return true;
    }

    public function respecteRegle(
        Mission $mission,
        RegleFiltrage $regle
    ): bool {
        $critere = $regle->critere;

        if (! $critere) {
            return false;
        }

        return match ($critere->code) {
            'stack' => $this->testerStack($mission, $regle),

            'tjm_min' => $this->testerValeur(
                $mission->tjm_min,
                $regle->operateur,
                $regle->valeur
            ),

            'remote' => $this->testerValeur(
                $mission->remote_type,
                $regle->operateur,
                $regle->valeur
            ),

            'duree_min' => $this->testerValeur(
                $mission->duree_mois,
                $regle->operateur,
                $regle->valeur
            ),

            'localisation' => $this->testerValeur(
                $mission->localisation,
                $regle->operateur,
                $regle->valeur
            ),

            'secteur' => $this->testerValeur(
                $mission->secteur,
                $regle->operateur,
                $regle->valeur
            ),

            default => false,
        };
    }

    private function testerStack(
        Mission $mission,
        RegleFiltrage $regle
    ): bool {
        $recherche = Str::lower(
            trim((string) $regle->valeur)
        );

        foreach ($mission->stacks as $stack) {
            $nom = Str::lower($stack->nom);

            if (
                $regle->operateur === 'egal'
                && $nom === $recherche
            ) {
                return true;
            }

            if (
                $regle->operateur === 'contient'
                && Str::contains($nom, $recherche)
            ) {
                return true;
            }
        }

        return false;
    }

    private function testerValeur(
        mixed $valeurMission,
        string $operateur,
        mixed $valeurRegle
    ): bool {
        if ($valeurMission === null) {
            return false;
        }

        return match ($operateur) {
            'egal' => $this->egal(
                $valeurMission,
                $valeurRegle
            ),

            'contient' => Str::contains(
                Str::lower((string) $valeurMission),
                Str::lower((string) $valeurRegle)
            ),

            'superieur_egal' =>
                is_numeric($valeurMission)
                && is_numeric($valeurRegle)
                && (float) $valeurMission >= (float) $valeurRegle,

            'inferieur_egal' =>
                is_numeric($valeurMission)
                && is_numeric($valeurRegle)
                && (float) $valeurMission <= (float) $valeurRegle,

            'dans' => $this->estDans(
                $valeurMission,
                $valeurRegle
            ),

            default => false,
        };
    }

    private function egal(
        mixed $valeurMission,
        mixed $valeurRegle
    ): bool {
        if (
            is_numeric($valeurMission)
            && is_numeric($valeurRegle)
        ) {
            return (float) $valeurMission === (float) $valeurRegle;
        }

        return Str::lower(trim((string) $valeurMission))
            === Str::lower(trim((string) $valeurRegle));
    }

    private function estDans(
        mixed $valeurMission,
        mixed $valeurRegle
    ): bool {
        $valeurs = is_array($valeurRegle)
            ? $valeurRegle
            : preg_split(
                '/[,;|]/',
                (string) $valeurRegle
            );

        $valeurMission = Str::lower(
            trim((string) $valeurMission)
        );

        foreach ($valeurs as $valeur) {
            if (
                Str::lower(trim((string) $valeur))
                === $valeurMission
            ) {
                return true;
            }
        }

        return false;
    }
}