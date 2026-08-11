<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">

    <title>Nouvelle mission MissionFinder</title>
</head>

<body>

    <h2>Nouvelle mission détectée</h2>

    <p>
        Une mission correspondant au profil
        <strong>{{ $profil->nom }}</strong>
        vient d'être détectée.
    </p>

    <hr>

    <p>
        <strong>Titre :</strong>
        {{ $mission->titre }}
    </p>

    <p>
        <strong>Entreprise :</strong>
        {{ $mission->entreprise ?? 'Non renseignée' }}
    </p>

    <p>
        <strong>Localisation :</strong>
        {{ $mission->localisation ?? 'Non renseignée' }}
    </p>

    <p>
        <strong>Remote :</strong>
        {{ $mission->remote_type ?? 'Non renseigné' }}
    </p>

    <p>
        <strong>TJM minimum :</strong>

        @if ($mission->tjm_min !== null)
            {{ $mission->tjm_min }}
        @else
            Non renseigné
        @endif
    </p>

    <p>
        <strong>Score :</strong>
        {{ $score }}
    </p>

    @if ($mission->stacks->isNotEmpty())
        <p>
            <strong>Technologies :</strong>
            {{ $mission->stacks->pluck('nom')->implode(', ') }}
        </p>
    @endif

    <p>
        <a href="{{ $mission->url_origine }}">
            Voir la mission
        </a>
    </p>

    <hr>

    <small>
        Email généré automatiquement par MissionFinder.
    </small>

</body>
</html>