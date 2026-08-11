<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>MissionFinder Digest</title>
</head>

<body>

    <h2>
        MissionFinder -
        {{ $alerte->frequence === 'daily'
            ? 'Daily Digest'
            : 'Weekly Digest' }}
    </h2>

    <p>
        Profil :
        <strong>
            {{ $alerte->profilRecherche->nom }}
        </strong>
    </p>

    <p>
        {{ $missions->count() }}
        nouvelle(s) mission(s) correspondent à votre profil.
    </p>

    <hr>

    @foreach ($missions as $mission)

        <h3>
            {{ $mission->titre }}
        </h3>

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
            {{ $scores[$mission->id] ?? 0 }}
        </p>

        @if ($mission->stacks->isNotEmpty())
            <p>
                <strong>Technologies :</strong>
                {{ $mission->stacks
                    ->pluck('nom')
                    ->implode(', ') }}
            </p>
        @endif

        <p>
            <a href="{{ $mission->url_origine }}">
                Voir la mission
            </a>
        </p>

        <hr>

    @endforeach

    <small>
        Email généré automatiquement par MissionFinder.
    </small>

</body>

</html>
