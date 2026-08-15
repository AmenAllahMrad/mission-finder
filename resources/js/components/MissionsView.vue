<script setup>
import {
    computed,
    onMounted,
    ref,
    watch,
} from 'vue';

import axios from 'axios';

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const missions = ref([]);
const sources = ref([]);
const profils = ref([]);

const loading = ref(false);
const error = ref(null);

const updatingMissionId = ref(null);

/*
|--------------------------------------------------------------------------
| Mission detail
|--------------------------------------------------------------------------
*/

const missionSelectionnee = ref(null);
const detailOuvert = ref(false);
const detailLoading = ref(false);
const detailError = ref(null);

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const from = ref(0);
const to = ref(0);

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

const filters = ref({
    search: '',
    statut: '',
    remote: '',
    source_id: '',
    profil_id: '',
    score_min: '',
});

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const filtresActifs = computed(() => {
    return Boolean(
        filters.value.search.trim() ||
        filters.value.statut ||
        filters.value.remote ||
        filters.value.source_id ||
        filters.value.profil_id ||
        filters.value.score_min !== ''
    );
});

const profilSelectionne = computed(() => {
    if (!filters.value.profil_id) {
        return null;
    }

    return (
        profils.value.find(
            (profil) =>
                Number(profil.id) ===
                Number(
                    filters.value.profil_id
                )
        )
        ?? null
    );
});

/*
|--------------------------------------------------------------------------
| Load sources
|--------------------------------------------------------------------------
*/

const chargerSources = async () => {
    try {
        const response =
            await axios.get(
                '/api/sources'
            );

        sources.value =
            response.data;
    } catch (err) {
        console.error(
            'Erreur chargement sources :',
            err
        );
    }
};

/*
|--------------------------------------------------------------------------
| Load missions
|--------------------------------------------------------------------------
*/

const chargerMissions =
    async (page = 1) => {
        loading.value = true;
        error.value = null;

        try {
            const params = {
                page,
                per_page: 10,
            };

            if (
                filters.value.search.trim()
            ) {
                params.search =
                    filters.value
                        .search
                        .trim();
            }

            if (
                filters.value.statut
            ) {
                params.statut =
                    filters.value.statut;
            }

            if (
                filters.value.remote
            ) {
                params.remote =
                    filters.value.remote;
            }

            if (
                filters.value.source_id
            ) {
                params.source_id =
                    filters.value.source_id;
            }

            if (
                filters.value.profil_id
            ) {
                params.profil_id =
                    filters.value.profil_id;
            }

            if (
                filters.value.score_min !== '' &&
                filters.value.score_min !== null
            ) {
                params.score_min =
                    Number(
                        filters.value
                            .score_min
                    );
            }

            const response =
                await axios.get(
                    '/api/missions',
                    {
                        params,
                    }
                );

            missions.value =
                response.data.data;

            currentPage.value =
                response.data.current_page;

            lastPage.value =
                response.data.last_page;

            total.value =
                response.data.total;

            from.value =
                response.data.from ?? 0;

            to.value =
                response.data.to ?? 0;

            profils.value =
                response.data.profils
                ?? [];
        } catch (err) {
            console.error(
                'Erreur chargement missions :',
                err
            );

            error.value =
                'Impossible de charger les missions.';
        } finally {
            loading.value =
                false;
        }
    };

/*
|--------------------------------------------------------------------------
| Reset filters
|--------------------------------------------------------------------------
*/

const resetFiltres = () => {
    filters.value.search = '';
    filters.value.statut = '';
    filters.value.remote = '';
    filters.value.source_id = '';
    filters.value.profil_id = '';
    filters.value.score_min = '';
};

const supprimerFiltreActif = (
    key
) => {
    if (
        Object.prototype.hasOwnProperty.call(
            filters.value,
            key
        )
    ) {
        filters.value[key] = '';
    }
};

/*
|--------------------------------------------------------------------------
| Open mission detail
|--------------------------------------------------------------------------
*/

const ouvrirMission =
    async (missionId) => {
        detailOuvert.value = true;
        detailLoading.value = true;
        detailError.value = null;

        missionSelectionnee.value =
            null;

        try {
            const response =
                await axios.get(
                    `/api/missions/${missionId}`
                );

            const missionDetail =
                response.data.mission;

            /*
             * Mission affichée dans le drawer.
             */
            missionSelectionnee.value =
                missionDetail;

            /*
             * Synchronise immédiatement la carte
             * déjà présente dans la liste.
             *
             * Si le backend vient de transformer
             * "nouveau" en "vu", la carte change
             * donc elle aussi sans recharger la page.
             */
            const missionListe =
                missions.value.find(
                    (mission) =>
                        Number(
                            mission.id
                        ) ===
                        Number(
                            missionDetail.id
                        )
                );

            if (missionListe) {
                missionListe.statut =
                    missionDetail.statut;

                missionListe.date_candidature =
                    missionDetail
                        .date_candidature;
            }

            /*
             * Si un filtre de statut est actif
             * et que l'ouverture de la mission
             * a modifié son statut, on recharge
             * la page courante afin de conserver
             * une liste cohérente.
             *
             * Exemple :
             * filtre = "Nouveau"
             * mission ouverte => devient "Vu"
             * elle ne doit plus rester dans la
             * liste des missions "Nouveau".
             */
            if (
                filters.value.statut &&
                missionDetail.statut !==
                    filters.value.statut
            ) {
                await chargerMissions(
                    currentPage.value
                );
            }
        } catch (err) {
            console.error(
                'Erreur chargement détail mission :',
                err
            );

            detailError.value =
                'Impossible de charger cette mission.';
        } finally {
            detailLoading.value =
                false;
        }
    };

/*
|--------------------------------------------------------------------------
| Close mission detail
|--------------------------------------------------------------------------
*/

const fermerMission = () => {
    detailOuvert.value = false;
    missionSelectionnee.value = null;
    detailError.value = null;
};

/*
|--------------------------------------------------------------------------
| Score
|--------------------------------------------------------------------------
*/

const scoreMission = (
    mission
) => {
    const scores =
        mission?.scores_profils
        ?? [];

    if (
        scores.length === 0
    ) {
        return 0;
    }

    if (
        filters.value.profil_id
    ) {
        const profilId =
            Number(
                filters.value.profil_id
            );

        const score =
            scores.find(
                (item) =>
                    Number(
                        item.profil_recherche_id
                    ) === profilId
            );

        return score
            ? Number(score.score)
            : 0;
    }

    return Math.max(
        ...scores.map(
            (item) =>
                Number(
                    item.score ?? 0
                )
        )
    );
};

const classeScore = (
    mission
) => {
    const score =
        scoreMission(
            mission
        );

    if (score >= 5) {
        return 'score-premium';
    }

    if (score >= 3) {
        return 'score-good';
    }

    if (score > 0) {
        return 'score-medium';
    }

    return 'score-neutral';
};

const labelScore = (
    mission
) => {
    const score =
        scoreMission(
            mission
        );

    if (score >= 5) {
        return 'Excellent match';
    }

    if (score >= 3) {
        return 'Bon match';
    }

    if (score > 0) {
        return 'À considérer';
    }

    return 'Non scoré';
};

/*
|--------------------------------------------------------------------------
| TJM
|--------------------------------------------------------------------------
*/

const afficherTjm = (
    mission
) => {
    if (
        mission?.tjm_min === null ||
        mission?.tjm_min === undefined
    ) {
        return 'Non renseigné';
    }

    if (
        mission.tjm_max !== null &&
        mission.tjm_max !== undefined &&
        Number(
            mission.tjm_max
        ) !==
        Number(
            mission.tjm_min
        )
    ) {
        return `${mission.tjm_min} - ${mission.tjm_max} €/j`;
    }

    return `${mission.tjm_min} €/j`;
};

/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

const classeStatut = (
    statut
) => {
    const classes = {
        nouveau:
            'border-blue-200 bg-blue-50 text-blue-700',

        vu:
            'border-slate-200 bg-slate-50 text-slate-700',

        interessant:
            'border-emerald-200 bg-emerald-50 text-emerald-700',

        postule:
            'border-violet-200 bg-violet-50 text-violet-700',

        ecarte:
            'border-rose-200 bg-rose-50 text-rose-700',
    };

    return (
        classes[statut]
        ??
        'border-slate-200 bg-slate-50 text-slate-700'
    );
};

const labelStatut = (
    statut
) => {
    const labels = {
        nouveau: 'Nouveau',
        vu: 'Vu',
        interessant: 'Intéressant',
        postule: 'Postulé',
        ecarte: 'Écarté',
    };

    return (
        labels[statut]
        ??
        statut
    );
};

const iconeStatut = (
    statut
) => {
    const icons = {
        nouveau: '✦',
        vu: '○',
        interessant: '★',
        postule: '✓',
        ecarte: '×',
    };

    return (
        icons[statut]
        ??
        '•'
    );
};

/*
|--------------------------------------------------------------------------
| Remote
|--------------------------------------------------------------------------
*/

const labelRemote = (
    remote
) => {
    const labels = {
        full_remote:
            'Full remote',

        hybrid:
            'Hybride',

        onsite:
            'Sur site',
    };

    return (
        labels[remote]
        ??
        remote
        ??
        'Non renseigné'
    );
};

const iconeRemote = (
    remote
) => {
    const icons = {
        full_remote: '◎',
        hybrid: '◐',
        onsite: '⌂',
    };

    return (
        icons[remote]
        ??
        '◌'
    );
};

/*
|--------------------------------------------------------------------------
| Date helpers
|--------------------------------------------------------------------------
*/

const datePartsTunis = (
    date
) => {
    if (!date) {
        return null;
    }

    const formatter =
        new Intl.DateTimeFormat(
            'fr-FR',
            {
                timeZone:
                    'Africa/Tunis',

                year:
                    'numeric',

                month:
                    '2-digit',

                day:
                    '2-digit',
            }
        );

    const parts =
        formatter.formatToParts(
            new Date(date)
        );

    const get =
        (type) =>
            Number(
                parts.find(
                    (part) =>
                        part.type === type
                )?.value
            );

    return {
        year:
            get('year'),

        month:
            get('month'),

        day:
            get('day'),
    };
};

const serialJourTunis = (
    date
) => {
    const parts =
        datePartsTunis(
            date
        );

    if (!parts) {
        return null;
    }

    return Date.UTC(
        parts.year,
        parts.month - 1,
        parts.day
    );
};

const differenceJours = (
    date
) => {
    if (!date) {
        return null;
    }

    const cible =
        serialJourTunis(
            date
        );

    const aujourdHui =
        serialJourTunis(
            new Date()
        );

    if (
        cible === null ||
        aujourdHui === null
    ) {
        return null;
    }

    return Math.round(
        (
            aujourdHui -
            cible
        )
        /
        86400000
    );
};

const formaterDate = (
    date
) => {
    if (!date) {
        return 'Non renseignée';
    }

    return new Intl.DateTimeFormat(
        'fr-FR',
        {
            timeZone:
                'Africa/Tunis',

            day:
                '2-digit',

            month:
                'short',

            year:
                'numeric',
        }
    ).format(
        new Date(date)
    );
};

const dateRelative = (
    date
) => {
    if (!date) {
        return 'Date inconnue';
    }

    const difference =
        differenceJours(
            date
        );

    if (difference === 0) {
        return 'Aujourd’hui';
    }

    if (difference === 1) {
        return 'Hier';
    }

    if (
        difference !== null &&
        difference > 1 &&
        difference <= 7
    ) {
        return `Il y a ${difference} j`;
    }

    return formaterDate(
        date
    );
};

const classeDateRelative = (
    date
) => {
    const difference =
        differenceJours(
            date
        );

    if (difference === 0) {
        return 'date-today';
    }

    if (difference === 1) {
        return 'date-yesterday';
    }

    return 'date-default';
};

const formaterDateComplete = (
    date
) => {
    if (!date) {
        return 'Non renseignée';
    }

    return new Intl.DateTimeFormat(
        'fr-FR',
        {
            timeZone:
                'Africa/Tunis',

            day:
                '2-digit',

            month:
                '2-digit',

            year:
                'numeric',

            hour:
                '2-digit',

            minute:
                '2-digit',
        }
    ).format(
        new Date(date)
    );
};

/*
|--------------------------------------------------------------------------
| Active filters chips
|--------------------------------------------------------------------------
*/

const filtresActifsListe =
    computed(() => {
        const liste = [];

        if (
            filters.value.search.trim()
        ) {
            liste.push({
                key:
                    'search',

                prefix:
                    'Recherche',

                value:
                    filters.value
                        .search
                        .trim(),
            });
        }

        if (
            filters.value.statut
        ) {
            liste.push({
                key:
                    'statut',

                prefix:
                    'Statut',

                value:
                    labelStatut(
                        filters.value
                            .statut
                    ),
            });
        }

        if (
            filters.value.remote
        ) {
            liste.push({
                key:
                    'remote',

                prefix:
                    'Remote',

                value:
                    labelRemote(
                        filters.value
                            .remote
                    ),
            });
        }

        if (
            filters.value.source_id
        ) {
            const source =
                sources.value.find(
                    (item) =>
                        Number(
                            item.id
                        ) ===
                        Number(
                            filters.value
                                .source_id
                        )
                );

            liste.push({
                key:
                    'source_id',

                prefix:
                    'Source',

                value:
                    source?.nom
                    ??
                    'Source',
            });
        }

        if (
            filters.value.profil_id
        ) {
            liste.push({
                key:
                    'profil_id',

                prefix:
                    'Profil',

                value:
                    profilSelectionne
                        .value
                        ?.nom
                    ??
                    'Profil',
            });
        }

        if (
            filters.value.score_min !== ''
        ) {
            liste.push({
                key:
                    'score_min',

                prefix:
                    'Score',

                value:
                    `≥ ${filters.value.score_min}`,
            });
        }

        return liste;
    });

/*
|--------------------------------------------------------------------------
| Description
|--------------------------------------------------------------------------
*/

const nettoyerDescription = (
    html
) => {
    if (!html) {
        return (
            'Description non renseignée.'
        );
    }

    const parser =
        new DOMParser();

    const documentHtml =
        parser.parseFromString(
            html,
            'text/html'
        );

    documentHtml
        .querySelectorAll(
            'p, div, li, br, h1, h2, h3, h4, h5, h6'
        )
        .forEach(
            (element) => {
                if (
                    element.tagName
                        .toLowerCase()
                    === 'br'
                ) {
                    element.replaceWith(
                        '\n'
                    );
                } else {
                    element.append(
                        '\n\n'
                    );
                }
            }
        );

    const texte =
        documentHtml.body
            .textContent
        ?? '';

    return (
        texte
            .replace(
                /\u00a0/g,
                ' '
            )
            .replace(
                /[ \t]+/g,
                ' '
            )
            .replace(
                /\n[ \t]+/g,
                '\n'
            )
            .replace(
                /\n{3,}/g,
                '\n\n'
            )
            .trim()
        ||
        'Description non renseignée.'
    );
};

/*
|--------------------------------------------------------------------------
| Update status
|--------------------------------------------------------------------------
*/

const changerStatut =
    async (
        mission,
        nouveauStatut
    ) => {
        if (
            !mission ||
            nouveauStatut ===
                mission.statut
        ) {
            return;
        }

        const ancienStatut =
            mission.statut;

        updatingMissionId.value =
            mission.id;

        mission.statut =
            nouveauStatut;

        try {
            const response =
                await axios.patch(
                    `/api/missions/${mission.id}/statut`,
                    {
                        statut:
                            nouveauStatut,
                    }
                );

            const missionMiseAJour =
                response.data.mission;

            const missionListe =
                missions.value.find(
                    (item) =>
                        item.id ===
                        mission.id
                );

            if (missionListe) {
                missionListe.statut =
                    missionMiseAJour
                        .statut;

                missionListe
                    .date_candidature =
                    missionMiseAJour
                        .date_candidature;
            }

            if (
                missionSelectionnee.value &&
                missionSelectionnee.value.id
                    === mission.id
            ) {
                missionSelectionnee
                    .value.statut =
                    missionMiseAJour
                        .statut;

                missionSelectionnee
                    .value
                    .date_candidature =
                    missionMiseAJour
                        .date_candidature;
            }
        } catch (err) {
            console.error(
                'Erreur modification statut :',
                err
            );

            mission.statut =
                ancienStatut;

            const missionListe =
                missions.value.find(
                    (item) =>
                        item.id ===
                        mission.id
                );

            if (missionListe) {
                missionListe.statut =
                    ancienStatut;
            }

            if (
                missionSelectionnee.value &&
                missionSelectionnee.value.id
                    === mission.id
            ) {
                missionSelectionnee
                    .value.statut =
                    ancienStatut;
            }

            alert(
                'Impossible de modifier le statut.'
            );
        } finally {
            updatingMissionId.value =
                null;
        }
    };

/*
|--------------------------------------------------------------------------
| Search debounce
|--------------------------------------------------------------------------
*/

let searchTimer = null;

watch(
    () =>
        filters.value.search,

    () => {
        clearTimeout(
            searchTimer
        );

        searchTimer =
            setTimeout(
                () => {
                    chargerMissions(
                        1
                    );
                },
                350
            );
    }
);

/*
|--------------------------------------------------------------------------
| Other filters
|--------------------------------------------------------------------------
*/

watch(
    [
        () =>
            filters.value.statut,

        () =>
            filters.value.remote,

        () =>
            filters.value.source_id,

        () =>
            filters.value.profil_id,

        () =>
            filters.value.score_min,
    ],

    () => {
        chargerMissions(
            1
        );
    }
);

/*
|--------------------------------------------------------------------------
| Mounted
|--------------------------------------------------------------------------
*/

onMounted(
    async () => {
        await chargerSources();

        await chargerMissions();
    }
);
</script>

<template>
    <main
        class="missions-page relative min-h-screen overflow-hidden"
    >
        <!-- Background atmosphere -->

        <div
            class="pointer-events-none absolute -left-32 top-20 h-96 w-96 rounded-full bg-indigo-300/10 blur-3xl"
        ></div>

        <div
            class="pointer-events-none absolute -right-32 top-72 h-96 w-96 rounded-full bg-violet-300/10 blur-3xl"
        ></div>

        <div
            class="relative mx-auto max-w-7xl px-6 py-10 lg:py-12"
        >
            <!-- ===================================================== -->
            <!-- HERO -->
            <!-- ===================================================== -->

            <section
                class="hero-panel relative mb-7 overflow-hidden"
            >
                <div
                    class="hero-glow hero-glow-one"
                ></div>

                <div
                    class="hero-glow hero-glow-two"
                ></div>

                <div
                    class="relative flex flex-col gap-7 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div
                        class="max-w-2xl"
                    >
                        <div
                            class="hero-eyebrow"
                        >
                            <span
                                class="relative flex h-2 w-2"
                            >
                                <span
                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-indigo-300 opacity-40"
                                ></span>

                                <span
                                    class="relative h-2 w-2 rounded-full bg-indigo-300"
                                ></span>
                            </span>

                            Opportunity Intelligence
                        </div>

                        <h1
                            class="mt-5 text-3xl font-black tracking-tight text-white sm:text-4xl"
                        >
                            Explorez les
                            <span
                                class="hero-title-gradient"
                            >
                                meilleures missions
                            </span>
                        </h1>

                        <p
                            class="mt-4 max-w-xl text-sm leading-7 text-slate-300 sm:text-base"
                        >
                            Analysez, filtrez et priorisez les opportunités détectées automatiquement par MissionFinder.
                        </p>

                        <div
                            v-if="
                                profilSelectionne
                            "
                            class="mt-5 inline-flex items-center gap-2 rounded-xl border border-violet-300/20 bg-violet-300/10 px-3 py-2 text-xs font-bold text-violet-100 backdrop-blur"
                        >
                            <span>
                                ✦
                            </span>

                            Profil de matching

                            <strong
                                class="text-white"
                            >
                                {{
                                    profilSelectionne.nom
                                }}
                            </strong>
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-3"
                    >
                        <div
                            class="hero-stat"
                        >
                            <span>
                                Missions
                            </span>

                            <strong>
                                {{ total }}
                            </strong>

                            <small>
                                disponibles
                            </small>
                        </div>

                        <div
                            class="hero-stat"
                        >
                            <span>
                                Navigation
                            </span>

                            <strong
                                class="text-indigo-300"
                            >
                                {{ currentPage }}
                            </strong>

                            <small>
                                / {{ lastPage }} pages
                            </small>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===================================================== -->
            <!-- FILTERS -->
            <!-- ===================================================== -->

            <section
                class="filter-panel"
            >
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div>
                        <div
                            class="flex items-center gap-2"
                        >
                            <div
                                class="filter-heading-icon"
                            >
                                ⌕
                            </div>

                            <div>
                                <h2
                                    class="text-sm font-black text-slate-900"
                                >
                                    Filtres intelligents
                                </h2>

                                <p
                                    class="mt-0.5 text-[11px] text-slate-400"
                                >
                                    Affinez instantanément la sélection.
                                </p>
                            </div>
                        </div>
                    </div>

                    <button
                        v-if="
                            filtresActifs
                        "
                        type="button"
                        class="reset-button"
                        @click="
                            resetFiltres
                        "
                    >
                        <span>
                            ↻
                        </span>

                        Tout réinitialiser
                    </button>
                </div>

                <div
                    class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-6"
                >
                    <!-- Search -->

                    <div
                        class="xl:col-span-2"
                    >
                        <label
                            class="filter-label"
                        >
                            Recherche
                        </label>

                        <div
                            class="relative"
                        >
                            <span
                                class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-slate-400"
                            >
                                ⌕
                            </span>

                            <input
                                v-model="
                                    filters.search
                                "
                                type="text"
                                placeholder="Laravel, entreprise, mot-clé..."
                                class="filter-control pl-10"
                            >
                        </div>
                    </div>

                    <!-- Status -->

                    <div>
                        <label
                            class="filter-label"
                        >
                            Statut
                        </label>

                        <select
                            v-model="
                                filters.statut
                            "
                            class="filter-control"
                        >
                            <option value="">
                                Tous
                            </option>

                            <option value="nouveau">
                                Nouveau
                            </option>

                            <option value="vu">
                                Vu
                            </option>

                            <option value="interessant">
                                Intéressant
                            </option>

                            <option value="postule">
                                Postulé
                            </option>

                            <option value="ecarte">
                                Écarté
                            </option>
                        </select>
                    </div>

                    <!-- Remote -->

                    <div>
                        <label
                            class="filter-label"
                        >
                            Remote
                        </label>

                        <select
                            v-model="
                                filters.remote
                            "
                            class="filter-control"
                        >
                            <option value="">
                                Tous
                            </option>

                            <option value="full_remote">
                                Full remote
                            </option>

                            <option value="hybrid">
                                Hybride
                            </option>

                            <option value="onsite">
                                Sur site
                            </option>
                        </select>
                    </div>

                    <!-- Source -->

                    <div>
                        <label
                            class="filter-label"
                        >
                            Source
                        </label>

                        <select
                            v-model="
                                filters.source_id
                            "
                            class="filter-control"
                        >
                            <option value="">
                                Toutes
                            </option>

                            <option
                                v-for="
                                    source in sources
                                "
                                :key="
                                    source.id
                                "
                                :value="
                                    source.id
                                "
                            >
                                {{
                                    source.nom
                                }}
                            </option>
                        </select>
                    </div>

                    <!-- Profile -->

                    <div>
                        <label
                            class="filter-label"
                        >
                            Profil
                        </label>

                        <select
                            v-model="
                                filters.profil_id
                            "
                            class="filter-control"
                        >
                            <option value="">
                                Tous
                            </option>

                            <option
                                v-for="
                                    profil in profils
                                "
                                :key="
                                    profil.id
                                "
                                :value="
                                    profil.id
                                "
                            >
                                {{
                                    profil.nom
                                }}
                            </option>
                        </select>
                    </div>

                    <!-- Score -->

                    <div>
                        <label
                            class="filter-label"
                        >
                            Score minimum
                        </label>

                        <input
                            v-model.number="
                                filters.score_min
                            "
                            type="number"
                            min="0"
                            step="1"
                            placeholder="Ex. 3"
                            class="filter-control"
                        >
                    </div>
                </div>

                <!-- ACTIVE FILTER CHIPS -->

                <transition
                    name="chips"
                >
                    <div
                        v-if="
                            filtresActifsListe.length
                        "
                        class="active-filters"
                    >
                        <div
                            class="flex items-center gap-2"
                        >
                            <span
                                class="active-filter-indicator"
                            ></span>

                            <span
                                class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400"
                            >
                                Filtres actifs
                            </span>
                        </div>

                        <div
                            class="flex flex-wrap gap-2"
                        >
                            <button
                                v-for="
                                    filtre in filtresActifsListe
                                "
                                :key="
                                    filtre.key
                                "
                                type="button"
                                class="active-filter-chip group"
                                :title="`Supprimer le filtre ${filtre.prefix}`"
                                @click="
                                    supprimerFiltreActif(
                                        filtre.key
                                    )
                                "
                            >
                                <span
                                    class="text-slate-400"
                                >
                                    {{
                                        filtre.prefix
                                    }}
                                </span>

                                <strong>
                                    {{
                                        filtre.value
                                    }}
                                </strong>

                                <span
                                    class="chip-close"
                                >
                                    ×
                                </span>
                            </button>
                        </div>
                    </div>
                </transition>
            </section>

            <!-- ===================================================== -->
            <!-- RESULTS TOOLBAR -->
            <!-- ===================================================== -->

            <div
                v-if="
                    !loading &&
                    missions.length > 0
                "
                class="results-toolbar"
            >
                <div>
                    <span
                        class="text-xs font-black text-slate-700"
                    >
                        {{
                            total
                        }}
                        opportunité<span v-if="total > 1">s</span>
                    </span>

                    <span
                        v-if="
                            filtresActifs
                        "
                        class="ml-2 text-[10px] text-slate-400"
                    >
                        selon vos critères
                    </span>
                </div>

                <div
                    class="flex items-center gap-2 text-[10px] font-bold text-slate-400"
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full bg-emerald-400"
                    ></span>

                    Données synchronisées
                </div>
            </div>

            <!-- ===================================================== -->
            <!-- ERROR -->
            <!-- ===================================================== -->

            <div
                v-if="error"
                class="mb-6 rounded-2xl border border-rose-200 bg-rose-50/90 p-5 text-sm font-medium text-rose-700 shadow-sm"
            >
                ⚠ {{ error }}
            </div>

            <!-- ===================================================== -->
            <!-- LOADING -->
            <!-- ===================================================== -->

            <div
                v-if="loading"
                class="space-y-4"
            >
                <div
                    v-for="
                        index in 4
                    "
                    :key="
                        index
                    "
                    class="animate-pulse rounded-[24px] border border-slate-200 bg-white p-6"
                >
                    <div
                        class="h-5 w-2/5 rounded bg-slate-200"
                    ></div>

                    <div
                        class="mt-3 h-4 w-1/4 rounded bg-slate-100"
                    ></div>

                    <div
                        class="mt-5 flex gap-2"
                    >
                        <div
                            class="h-7 w-24 rounded-full bg-slate-100"
                        ></div>

                        <div
                            class="h-7 w-24 rounded-full bg-slate-100"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- ===================================================== -->
            <!-- EMPTY -->
            <!-- ===================================================== -->

            <div
                v-else-if="
                    missions.length === 0
                "
                class="empty-state"
            >
                <div
                    class="empty-state-icon"
                >
                    ⌕
                </div>

                <h3
                    class="mt-5 text-lg font-black text-slate-900"
                >
                    Aucune mission trouvée
                </h3>

                <p
                    class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500"
                >
                    Aucun résultat ne correspond actuellement à cette combinaison de filtres.
                </p>

                <button
                    v-if="
                        filtresActifs
                    "
                    type="button"
                    class="mt-5 rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-slate-300 transition-all duration-300 hover:-translate-y-0.5 hover:bg-indigo-950"
                    @click="
                        resetFiltres
                    "
                >
                    Réinitialiser les filtres
                </button>
            </div>

            <!-- ===================================================== -->
            <!-- MISSIONS -->
            <!-- ===================================================== -->

            <section
                v-else
                class="space-y-4"
            >
                <article
                    v-for="
                        (
                            mission,
                            index
                        ) in missions
                    "
                    :key="
                        mission.id
                    "
                    class="mission-card group"
                    :style="{
                        '--mission-delay':
                            `${Math.min(index, 8) * 45}ms`
                    }"
                >
                    <!-- TOP ACCENT -->

                    <div
                        class="mission-card-accent"
                        :class="
                            classeScore(
                                mission
                            )
                        "
                    ></div>

                    <div
                        class="p-5 sm:p-6"
                    >
                        <div
                            class="flex flex-col gap-6 xl:flex-row xl:items-center xl:justify-between"
                        >
                            <!-- LEFT -->

                            <div
                                class="min-w-0 flex-1"
                            >
                                <div
                                    class="mb-3 flex flex-wrap items-center gap-2"
                                >
                                    <!-- STATUS -->

                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-black"
                                        :class="
                                            classeStatut(
                                                mission.statut
                                            )
                                        "
                                    >
                                        <span>
                                            {{
                                                iconeStatut(
                                                    mission.statut
                                                )
                                            }}
                                        </span>

                                        {{
                                            labelStatut(
                                                mission.statut
                                            )
                                        }}
                                    </span>

                                    <!-- SOURCE -->

                                    <span
                                        class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-bold text-slate-500 shadow-sm"
                                    >
                                        {{
                                            mission.source
                                                ?.nom
                                            ||
                                            'Source inconnue'
                                        }}
                                    </span>

                                    <!-- DATE RELATIVE -->

                                    <span
                                        v-if="
                                            mission.date_publication
                                        "
                                        class="date-badge"
                                        :class="
                                            classeDateRelative(
                                                mission.date_publication
                                            )
                                        "
                                        :title="
                                            formaterDate(
                                                mission.date_publication
                                            )
                                        "
                                    >
                                        <span
                                            v-if="
                                                differenceJours(
                                                    mission.date_publication
                                                ) === 0
                                            "
                                            class="date-live-dot"
                                        ></span>

                                        {{
                                            dateRelative(
                                                mission.date_publication
                                            )
                                        }}
                                    </span>
                                </div>

                                <!-- TITLE -->

                                <button
                                    type="button"
                                    class="mission-title"
                                    @click="
                                        ouvrirMission(
                                            mission.id
                                        )
                                    "
                                >
                                    {{
                                        mission.titre
                                    }}
                                </button>

                                <div
                                    class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1"
                                >
                                    <p
                                        class="text-sm font-bold text-slate-500"
                                    >
                                        {{
                                            mission.entreprise
                                            ||
                                            'Entreprise non renseignée'
                                        }}
                                    </p>

                                    <span
                                        v-if="
                                            mission.secteur
                                        "
                                        class="hidden h-1 w-1 rounded-full bg-slate-300 sm:block"
                                    ></span>

                                    <span
                                        v-if="
                                            mission.secteur
                                        "
                                        class="text-xs text-slate-400"
                                    >
                                        {{
                                            mission.secteur
                                        }}
                                    </span>
                                </div>

                                <!-- INFORMATION -->

                                <div
                                    class="mt-4 flex flex-wrap gap-2"
                                >
                                    <span
                                        class="info-chip"
                                    >
                                        <span
                                            class="info-chip-icon"
                                        >
                                            {{
                                                iconeRemote(
                                                    mission.remote_type
                                                )
                                            }}
                                        </span>

                                        {{
                                            labelRemote(
                                                mission.remote_type
                                            )
                                        }}
                                    </span>

                                    <span
                                        v-if="
                                            mission.localisation
                                        "
                                        class="info-chip"
                                    >
                                        <span
                                            class="info-chip-icon"
                                        >
                                            ⌖
                                        </span>

                                        {{
                                            mission.localisation
                                        }}
                                    </span>

                                    <span
                                        class="info-chip info-chip-money"
                                    >
                                        <span
                                            class="info-chip-icon"
                                        >
                                            €
                                        </span>

                                        {{
                                            afficherTjm(
                                                mission
                                            )
                                        }}
                                    </span>

                                    <span
                                        v-if="
                                            mission.duree_mois
                                        "
                                        class="info-chip"
                                    >
                                        <span
                                            class="info-chip-icon"
                                        >
                                            ◷
                                        </span>

                                        {{
                                            mission.duree_mois
                                        }}
                                        mois
                                    </span>
                                </div>

                                <!-- STACKS -->

                                <div
                                    v-if="
                                        mission.stacks
                                            ?.length
                                    "
                                    class="mt-4 flex flex-wrap gap-1.5"
                                >
                                    <span
                                        v-for="
                                            stack in mission.stacks
                                        "
                                        :key="
                                            stack.id
                                        "
                                        class="stack-chip"
                                    >
                                        {{
                                            stack.nom
                                        }}
                                    </span>
                                </div>
                            </div>

                            <!-- RIGHT -->

                            <div
                                class="mission-side"
                            >
                                <!-- SCORE -->

                                <div
                                    class="flex items-center justify-between xl:block"
                                >
                                    <div>
                                        <p
                                            class="text-[9px] font-black uppercase tracking-[0.15em] text-slate-400"
                                        >
                                            Matching
                                        </p>

                                        <div
                                            class="mt-2 flex items-center gap-3"
                                        >
                                            <div
                                                class="score-display"
                                                :class="
                                                    classeScore(
                                                        mission
                                                    )
                                                "
                                            >
                                                <div
                                                    class="score-shine"
                                                ></div>

                                                <span
                                                    class="score-number"
                                                >
                                                    {{
                                                        scoreMission(
                                                            mission
                                                        )
                                                    }}
                                                </span>

                                                <span
                                                    class="score-spark"
                                                >
                                                    ✦
                                                </span>
                                            </div>

                                            <div>
                                                <p
                                                    class="text-[11px] font-black text-slate-700"
                                                >
                                                    {{
                                                        labelScore(
                                                            mission
                                                        )
                                                    }}
                                                </p>

                                                <p
                                                    class="mt-1 text-[9px] text-slate-400"
                                                >
                                                    MissionFinder Score
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- STATUS SELECT -->

                                    <div
                                        class="mt-0 xl:mt-4"
                                    >
                                        <label
                                            class="mb-1.5 hidden text-[9px] font-black uppercase tracking-[0.12em] text-slate-400 xl:block"
                                        >
                                            Suivi
                                        </label>

                                        <select
                                            :value="
                                                mission.statut
                                            "
                                            :disabled="
                                                updatingMissionId ===
                                                mission.id
                                            "
                                            :class="
                                                classeStatut(
                                                    mission.statut
                                                )
                                            "
                                            class="status-select"
                                            @change="
                                                changerStatut(
                                                    mission,
                                                    $event.target.value
                                                )
                                            "
                                        >
                                            <option value="nouveau">
                                                Nouveau
                                            </option>

                                            <option value="vu">
                                                Vu
                                            </option>

                                            <option value="interessant">
                                                Intéressant
                                            </option>

                                            <option value="postule">
                                                Postulé
                                            </option>

                                            <option value="ecarte">
                                                Écarté
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <!-- ACTIONS -->

                                <div
                                    class="mt-4 flex gap-2"
                                >
                                    <button
                                        type="button"
                                        class="details-button"
                                        @click="
                                            ouvrirMission(
                                                mission.id
                                            )
                                        "
                                    >
                                        Voir détails

                                        <span>
                                            →
                                        </span>
                                    </button>

                                    <a
                                        v-if="
                                            mission.url_origine
                                        "
                                        :href="
                                            mission.url_origine
                                        "
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="external-button"
                                        title="Ouvrir la mission originale"
                                    >
                                        ↗
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            <!-- ===================================================== -->
            <!-- PAGINATION -->
            <!-- ===================================================== -->

            <section
                v-if="
                    !loading &&
                    missions.length > 0
                "
                class="pagination-panel"
            >
                <p
                    class="text-xs text-slate-500"
                >
                    Affichage

                    <strong
                        class="text-slate-800"
                    >
                        {{ from }} – {{ to }}
                    </strong>

                    sur

                    <strong
                        class="text-slate-800"
                    >
                        {{ total }}
                    </strong>
                </p>

                <div
                    class="flex items-center gap-2"
                >
                    <button
                        type="button"
                        :disabled="
                            currentPage <= 1 ||
                            loading
                        "
                        class="pagination-button"
                        @click="
                            chargerMissions(
                                currentPage - 1
                            )
                        "
                    >
                        ←
                    </button>

                    <div
                        class="pagination-current"
                    >
                        <span>
                            Page
                        </span>

                        <strong>
                            {{ currentPage }}
                        </strong>

                        <span>
                            / {{ lastPage }}
                        </span>
                    </div>

                    <button
                        type="button"
                        :disabled="
                            currentPage >= lastPage ||
                            loading
                        "
                        class="pagination-button"
                        @click="
                            chargerMissions(
                                currentPage + 1
                            )
                        "
                    >
                        →
                    </button>
                </div>
            </section>
        </div>

        <!-- ========================================================= -->
        <!-- DETAIL OVERLAY -->
        <!-- ========================================================= -->

        <transition
            name="fade"
        >
            <div
                v-if="
                    detailOuvert
                "
                class="fixed inset-0 z-40 bg-slate-950/45 backdrop-blur-sm"
                @click="
                    fermerMission
                "
            ></div>
        </transition>

        <!-- ========================================================= -->
        <!-- DETAIL DRAWER -->
        <!-- ========================================================= -->

        <transition
            name="drawer"
        >
            <aside
                v-if="
                    detailOuvert
                "
                class="mission-drawer"
            >
                <!-- HEADER -->

                <div
                    class="drawer-header"
                >
                    <div>
                        <div
                            class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.15em] text-indigo-500"
                        >
                            <span
                                class="h-2 w-2 rounded-full bg-indigo-500"
                            ></span>

                            Mission Intelligence
                        </div>

                        <p
                            class="mt-1 text-[10px] text-slate-400"
                        >
                            Analyse détaillée de l'opportunité
                        </p>
                    </div>

                    <button
                        type="button"
                        class="drawer-close"
                        @click="
                            fermerMission
                        "
                    >
                        ×
                    </button>
                </div>

                <!-- LOADING -->

                <div
                    v-if="
                        detailLoading
                    "
                    class="px-6 py-16"
                >
                    <div
                        class="animate-pulse space-y-5"
                    >
                        <div
                            class="h-7 w-4/5 rounded bg-slate-200"
                        ></div>

                        <div
                            class="h-5 w-2/5 rounded bg-slate-100"
                        ></div>

                        <div
                            class="grid grid-cols-2 gap-3"
                        >
                            <div
                                class="h-24 rounded-2xl bg-slate-100"
                            ></div>

                            <div
                                class="h-24 rounded-2xl bg-slate-100"
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- ERROR -->

                <div
                    v-else-if="
                        detailError
                    "
                    class="m-6 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm font-medium text-rose-700"
                >
                    ⚠
                    {{
                        detailError
                    }}
                </div>

                <!-- CONTENT -->

                <div
                    v-else-if="
                        missionSelectionnee
                    "
                    class="px-6 py-6"
                >
                    <!-- HERO -->

                    <div
                        class="drawer-hero"
                    >
                        <div
                            class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-indigo-400/20 blur-3xl"
                        ></div>

                        <div
                            class="relative"
                        >
                            <div
                                class="flex flex-wrap items-center gap-2"
                            >
                                <span
                                    class="rounded-full border border-white/10 bg-white/10 px-3 py-1 text-[10px] font-bold backdrop-blur"
                                >
                                    {{
                                        iconeStatut(
                                            missionSelectionnee.statut
                                        )
                                    }}

                                    {{
                                        labelStatut(
                                            missionSelectionnee.statut
                                        )
                                    }}
                                </span>

                                <span
                                    v-if="
                                        missionSelectionnee.date_publication
                                    "
                                    class="rounded-full border border-white/10 bg-white/10 px-3 py-1 text-[10px] font-bold text-slate-200"
                                >
                                    {{
                                        dateRelative(
                                            missionSelectionnee.date_publication
                                        )
                                    }}
                                </span>
                            </div>

                            <h2
                                class="mt-5 text-2xl font-black leading-tight"
                            >
                                {{
                                    missionSelectionnee.titre
                                }}
                            </h2>

                            <p
                                class="mt-2 text-sm font-medium text-slate-300"
                            >
                                {{
                                    missionSelectionnee.entreprise
                                    ||
                                    'Entreprise non renseignée'
                                }}
                            </p>

                            <!-- LARGE SCORE -->

                            <div
                                class="mt-6 flex items-center gap-4"
                            >
                                <div
                                    class="drawer-score"
                                    :class="
                                        classeScore(
                                            missionSelectionnee
                                        )
                                    "
                                >
                                    <span
                                        class="text-[9px] font-black uppercase tracking-[0.1em] opacity-70"
                                    >
                                        Score
                                    </span>

                                    <strong>
                                        {{
                                            scoreMission(
                                                missionSelectionnee
                                            )
                                        }}
                                    </strong>
                                </div>

                                <div>
                                    <p
                                        class="text-sm font-black text-white"
                                    >
                                        {{
                                            labelScore(
                                                missionSelectionnee
                                            )
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-[10px] leading-5 text-slate-400"
                                    >
                                        Pertinence calculée selon vos profils de recherche.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- INFO GRID -->

                    <div
                        class="mt-5 grid grid-cols-2 gap-3"
                    >
                        <div
                            class="detail-stat"
                        >
                            <span
                                class="detail-icon"
                            >
                                ◉
                            </span>

                            <div>
                                <p
                                    class="detail-label"
                                >
                                    Source
                                </p>

                                <p
                                    class="detail-value"
                                >
                                    {{
                                        missionSelectionnee
                                            .source
                                            ?.nom
                                        ||
                                        '—'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="detail-stat"
                        >
                            <span
                                class="detail-icon"
                            >
                                {{
                                    iconeRemote(
                                        missionSelectionnee
                                            .remote_type
                                    )
                                }}
                            </span>

                            <div>
                                <p
                                    class="detail-label"
                                >
                                    Remote
                                </p>

                                <p
                                    class="detail-value"
                                >
                                    {{
                                        labelRemote(
                                            missionSelectionnee
                                                .remote_type
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="detail-stat"
                        >
                            <span
                                class="detail-icon"
                            >
                                ⌖
                            </span>

                            <div>
                                <p
                                    class="detail-label"
                                >
                                    Localisation
                                </p>

                                <p
                                    class="detail-value"
                                >
                                    {{
                                        missionSelectionnee
                                            .localisation
                                        ||
                                        'Non renseignée'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="detail-stat"
                        >
                            <span
                                class="detail-icon"
                            >
                                €
                            </span>

                            <div>
                                <p
                                    class="detail-label"
                                >
                                    TJM
                                </p>

                                <p
                                    class="detail-value"
                                >
                                    {{
                                        afficherTjm(
                                            missionSelectionnee
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="detail-stat"
                        >
                            <span
                                class="detail-icon"
                            >
                                ◇
                            </span>

                            <div>
                                <p
                                    class="detail-label"
                                >
                                    Secteur
                                </p>

                                <p
                                    class="detail-value"
                                >
                                    {{
                                        missionSelectionnee
                                            .secteur
                                        ||
                                        'Non renseigné'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="detail-stat"
                        >
                            <span
                                class="detail-icon"
                            >
                                ◷
                            </span>

                            <div>
                                <p
                                    class="detail-label"
                                >
                                    Durée
                                </p>

                                <p
                                    class="detail-value"
                                >
                                    <template
                                        v-if="
                                            missionSelectionnee
                                                .duree_mois
                                        "
                                    >
                                        {{
                                            missionSelectionnee
                                                .duree_mois
                                        }}
                                        mois
                                    </template>

                                    <template
                                        v-else
                                    >
                                        Non renseignée
                                    </template>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- TECHNOLOGIES -->

                    <section
                        class="detail-section"
                    >
                        <div
                            class="section-heading"
                        >
                            <span
                                class="section-heading-icon"
                            >
                                ⌘
                            </span>

                            Technologies
                        </div>

                        <div
                            v-if="
                                missionSelectionnee
                                    .stacks?.length
                            "
                            class="mt-4 flex flex-wrap gap-2"
                        >
                            <span
                                v-for="
                                    stack in missionSelectionnee.stacks
                                "
                                :key="
                                    stack.id
                                "
                                class="drawer-stack"
                            >
                                {{
                                    stack.nom
                                }}
                            </span>
                        </div>

                        <p
                            v-else
                            class="mt-3 text-xs text-slate-400"
                        >
                            Aucune technologie fiable détectée.
                        </p>
                    </section>

                    <!-- STATUS ACTIONS -->

                    <section
                        class="detail-section"
                    >
                        <div
                            class="section-heading"
                        >
                            <span
                                class="section-heading-icon"
                            >
                                ◎
                            </span>

                            Suivi de la mission
                        </div>

                        <p
                            class="mt-2 text-xs leading-6 text-slate-500"
                        >
                            Mettez à jour rapidement l'état de cette opportunité.
                        </p>

                        <div
                            class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-5"
                        >
                            <button
                                v-for="
                                    statut in [
                                        'nouveau',
                                        'vu',
                                        'interessant',
                                        'postule',
                                        'ecarte'
                                    ]
                                "
                                :key="
                                    statut
                                "
                                type="button"
                                :disabled="
                                    updatingMissionId ===
                                    missionSelectionnee.id
                                "
                                class="status-action"
                                :class="[
                                    classeStatut(
                                        statut
                                    ),
                                    missionSelectionnee.statut === statut
                                        ? 'status-action-selected'
                                        : ''
                                ]"
                                @click="
                                    changerStatut(
                                        missionSelectionnee,
                                        statut
                                    )
                                "
                            >
                                <span
                                    class="text-sm"
                                >
                                    {{
                                        iconeStatut(
                                            statut
                                        )
                                    }}
                                </span>

                                <span>
                                    {{
                                        labelStatut(
                                            statut
                                        )
                                    }}
                                </span>
                            </button>
                        </div>
                    </section>

                    <!-- APPLICATION -->

                    <section
                        v-if="
                            missionSelectionnee.statut ===
                            'postule'
                        "
                        class="detail-section"
                    >
                        <div
                            class="section-heading"
                        >
                            <span
                                class="section-heading-icon section-heading-success"
                            >
                                ✓
                            </span>

                            Candidature
                        </div>

                        <div
                            class="application-card"
                        >
                            <div
                                class="application-check"
                            >
                                ✓
                            </div>

                            <div>
                                <p
                                    class="text-sm font-black text-violet-700"
                                >
                                    Candidature enregistrée
                                </p>

                                <p
                                    class="mt-1 text-xs text-violet-500"
                                >
                                    {{
                                        formaterDateComplete(
                                            missionSelectionnee
                                                .date_candidature
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- DESCRIPTION -->

                    <section
                        class="detail-section"
                    >
                        <div
                            class="section-heading"
                        >
                            <span
                                class="section-heading-icon"
                            >
                                ≡
                            </span>

                            Description
                        </div>

                        <p
                            class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-600"
                        >
                            {{
                                nettoyerDescription(
                                    missionSelectionnee
                                        .description
                                )
                            }}
                        </p>
                    </section>

                    <!-- SOURCES -->

                    <section
                        v-if="
                            missionSelectionnee
                                .source_occurrences
                                ?.length
                        "
                        class="detail-section"
                    >
                        <div
                            class="section-heading"
                        >
                            <span
                                class="section-heading-icon"
                            >
                                ⛓
                            </span>

                            Sources d'origine
                        </div>

                        <div
                            class="mt-4 space-y-2"
                        >
                            <div
                                v-for="
                                    occurrence in
                                    missionSelectionnee.source_occurrences
                                "
                                :key="
                                    occurrence.id
                                "
                                class="source-occurrence"
                            >
                                <span
                                    class="flex items-center gap-2 text-xs font-bold text-slate-700"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full bg-indigo-400"
                                    ></span>

                                    {{
                                        occurrence
                                            .source
                                            ?.nom
                                        ||
                                        'Source'
                                    }}
                                </span>

                                <a
                                    v-if="
                                        occurrence
                                            .url_origine
                                    "
                                    :href="
                                        occurrence
                                            .url_origine
                                    "
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-[10px] font-black text-indigo-600 transition hover:text-indigo-800"
                                >
                                    Ouvrir ↗
                                </a>
                            </div>
                        </div>
                    </section>

                    <!-- ORIGINAL LINK -->

                    <div
                        class="py-6"
                    >
                        <a
                            v-if="
                                missionSelectionnee
                                    .url_origine
                            "
                            :href="
                                missionSelectionnee
                                    .url_origine
                            "
                            target="_blank"
                            rel="noopener noreferrer"
                            class="original-button group"
                        >
                            <span>
                                Ouvrir la mission originale
                            </span>

                            <span
                                class="transition-transform duration-300 group-hover:translate-x-1"
                            >
                                ↗
                            </span>
                        </a>
                    </div>
                </div>
            </aside>
        </transition>
    </main>
</template>

<style scoped>
/* ================================================================
   PAGE
================================================================ */

.missions-page {
    background:
        linear-gradient(
            135deg,
            #f8fafc 0%,
            #ffffff 45%,
            #eef2ff 100%
        );
}

/* ================================================================
   HERO
================================================================ */

.hero-panel {
    position: relative;

    overflow: hidden;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.08
        );

    border-radius: 1.8rem;

    background:
        linear-gradient(
            135deg,
            #020617,
            #0f172a 44%,
            #312e81 100%
        );

    padding: 2rem;

    color: white;

    box-shadow:
        0 30px 70px -40px
        rgba(
            49,
            46,
            129,
            0.75
        );

    animation:
        heroReveal
        0.55s
        cubic-bezier(
            0.22,
            1,
            0.36,
            1
        )
        both;
}

.hero-glow {
    position: absolute;

    border-radius:
        9999px;

    filter:
        blur(55px);
}

.hero-glow-one {
    right: -4rem;
    top: -6rem;

    height: 18rem;
    width: 18rem;

    background:
        rgba(
            129,
            140,
            248,
            0.22
        );
}

.hero-glow-two {
    bottom: -8rem;
    left: 25%;

    height: 15rem;
    width: 15rem;

    background:
        rgba(
            139,
            92,
            246,
            0.13
        );
}

.hero-eyebrow {
    display: inline-flex;

    align-items: center;

    gap: 0.5rem;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.1
        );

    border-radius: 9999px;

    background:
        rgba(
            255,
            255,
            255,
            0.08
        );

    padding:
        0.4rem
        0.7rem;

    font-size: 0.58rem;

    font-weight: 900;

    letter-spacing:
        0.14em;

    text-transform:
        uppercase;

    color: #c7d2fe;

    backdrop-filter:
        blur(12px);
}

.hero-title-gradient {
    background:
        linear-gradient(
            90deg,
            #c7d2fe,
            #ddd6fe,
            #bae6fd
        );

    background-clip:
        text;

    -webkit-background-clip:
        text;

    color: transparent;
}

.hero-stat {
    min-width: 120px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.1
        );

    border-radius: 1rem;

    background:
        rgba(
            255,
            255,
            255,
            0.07
        );

    padding: 1rem;

    backdrop-filter:
        blur(16px);
}

.hero-stat span {
    display: block;

    font-size: 0.55rem;

    font-weight: 900;

    letter-spacing:
        0.12em;

    text-transform:
        uppercase;

    color: #94a3b8;
}

.hero-stat strong {
    display: block;

    margin-top: 0.3rem;

    font-size: 1.7rem;

    font-weight: 900;
}

.hero-stat small {
    display: block;

    margin-top: 0.1rem;

    font-size: 0.58rem;

    color: #94a3b8;
}

/* ================================================================
   FILTER PANEL
================================================================ */

.filter-panel {
    margin-bottom: 1.5rem;

    border:
        1px solid
        rgba(
            226,
            232,
            240,
            0.8
        );

    border-radius: 1.5rem;

    background:
        rgba(
            255,
            255,
            255,
            0.9
        );

    padding: 1.4rem;

    box-shadow:
        0 20px 45px -38px
        rgba(
            15,
            23,
            42,
            0.4
        );

    backdrop-filter:
        blur(18px);
}

.filter-heading-icon {
    display: flex;

    height: 2.3rem;
    width: 2.3rem;

    align-items: center;
    justify-content: center;

    border-radius: 0.75rem;

    background:
        linear-gradient(
            135deg,
            #eef2ff,
            #ede9fe
        );

    font-size: 1rem;

    font-weight: 900;

    color: #6366f1;
}

.filter-label {
    margin-bottom: 0.45rem;

    display: block;

    font-size: 0.58rem;

    font-weight: 900;

    letter-spacing:
        0.08em;

    text-transform:
        uppercase;

    color: #64748b;
}

.filter-control {
    width: 100%;

    border:
        1px solid
        #e2e8f0;

    border-radius: 0.8rem;

    background:
        rgba(
            248,
            250,
            252,
            0.8
        );

    padding:
        0.72rem
        0.85rem;

    font-size: 0.75rem;

    color: #334155;

    outline: none;

    transition:
        transform 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        background 0.2s ease;
}

.filter-control:hover {
    border-color: #cbd5e1;

    background: white;
}

.filter-control:focus {
    border-color: #818cf8;

    background: white;

    box-shadow:
        0 0 0 4px
        rgba(
            99,
            102,
            241,
            0.08
        );
}

.reset-button {
    display: inline-flex;

    align-items: center;

    gap: 0.45rem;

    border:
        1px solid
        #e2e8f0;

    border-radius: 0.8rem;

    background: white;

    padding:
        0.65rem
        0.8rem;

    font-size: 0.65rem;

    font-weight: 900;

    color: #64748b;

    box-shadow:
        0 6px 15px -14px
        rgba(
            15,
            23,
            42,
            0.4
        );

    transition:
        transform 0.25s ease,
        color 0.25s ease,
        border-color 0.25s ease,
        box-shadow 0.25s ease;
}

.reset-button:hover {
    transform:
        translateY(-2px);

    border-color: #c7d2fe;

    color: #4f46e5;

    box-shadow:
        0 12px 22px -17px
        rgba(
            79,
            70,
            229,
            0.5
        );
}

/* ================================================================
   ACTIVE FILTERS
================================================================ */

.active-filters {
    display: flex;

    flex-direction: column;

    gap: 0.75rem;

    margin-top: 1rem;

    border-top:
        1px solid #f1f5f9;

    padding-top: 1rem;
}

.active-filter-indicator {
    height: 0.45rem;
    width: 0.45rem;

    border-radius: 9999px;

    background: #6366f1;

    box-shadow:
        0 0 0 4px
        rgba(
            99,
            102,
            241,
            0.08
        );
}

.active-filter-chip {
    display: inline-flex;

    align-items: center;

    gap: 0.35rem;

    border:
        1px solid #e0e7ff;

    border-radius: 0.7rem;

    background:
        linear-gradient(
            135deg,
            #eef2ff,
            #f5f3ff
        );

    padding:
        0.4rem
        0.45rem
        0.4rem
        0.65rem;

    font-size: 0.62rem;

    color: #6366f1;

    transition:
        transform 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.active-filter-chip strong {
    font-weight: 900;
}

.active-filter-chip:hover {
    transform:
        translateY(-1px);

    border-color: #c7d2fe;

    box-shadow:
        0 7px 16px -14px
        rgba(
            79,
            70,
            229,
            0.55
        );
}

.chip-close {
    display: flex;

    height: 1.25rem;
    width: 1.25rem;

    align-items: center;
    justify-content: center;

    border-radius: 0.4rem;

    background:
        rgba(
            255,
            255,
            255,
            0.7
        );

    font-size: 0.7rem;

    font-weight: 900;

    color: #818cf8;

    transition:
        transform 0.2s ease,
        background 0.2s ease,
        color 0.2s ease;
}

.active-filter-chip:hover
.chip-close {
    transform:
        rotate(90deg);

    background: white;

    color: #e11d48;
}

/* ================================================================
   RESULTS TOOLBAR
================================================================ */

.results-toolbar {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 1rem;

    margin-bottom: 0.8rem;

    padding:
        0
        0.25rem;
}

/* ================================================================
   MISSION CARD
================================================================ */

.mission-card {
    position: relative;

    overflow: hidden;

    border:
        1px solid
        rgba(
            226,
            232,
            240,
            0.86
        );

    border-radius: 1.45rem;

    background:
        rgba(
            255,
            255,
            255,
            0.93
        );

    box-shadow:
        0 16px 40px -34px
        rgba(
            15,
            23,
            42,
            0.42
        );

    backdrop-filter:
        blur(18px);

    animation:
        missionReveal
        0.45s
        cubic-bezier(
            0.22,
            1,
            0.36,
            1
        )
        both;

    animation-delay:
        var(
            --mission-delay,
            0ms
        );

    transition:
        transform 0.3s ease,
        box-shadow 0.3s ease,
        border-color 0.3s ease;
}

.mission-card:hover {
    transform:
        translateY(-4px);

    border-color:
        rgba(
            129,
            140,
            248,
            0.28
        );

    box-shadow:
        0 26px 60px -38px
        rgba(
            79,
            70,
            229,
            0.35
        );
}

.mission-card-accent {
    position: absolute;

    left: 0;
    top: 0;

    height: 3px;

    width: 100%;

    opacity: 0.82;
}

.mission-card-accent.score-premium {
    background:
        linear-gradient(
            90deg,
            #4f46e5,
            #8b5cf6,
            #6366f1
        );
}

.mission-card-accent.score-good {
    background:
        linear-gradient(
            90deg,
            #059669,
            #34d399
        );
}

.mission-card-accent.score-medium {
    background:
        linear-gradient(
            90deg,
            #d97706,
            #fbbf24
        );
}

.mission-card-accent.score-neutral {
    background:
        linear-gradient(
            90deg,
            #64748b,
            #cbd5e1
        );
}

.mission-title {
    display: block;

    max-width: 50rem;

    text-align: left;

    font-size: 1.1rem;

    font-weight: 900;

    line-height: 1.4;

    letter-spacing: -0.02em;

    color: #0f172a;

    transition:
        color 0.25s ease,
        transform 0.25s ease;
}

.mission-title:hover {
    color: #4f46e5;

    transform:
        translateX(2px);
}

/* ================================================================
   DATE BADGES
================================================================ */

.date-badge {
    display: inline-flex;

    align-items: center;

    gap: 0.35rem;

    border-radius: 9999px;

    padding:
        0.28rem
        0.55rem;

    font-size: 0.58rem;

    font-weight: 900;
}

.date-today {
    border:
        1px solid #a7f3d0;

    background: #ecfdf5;

    color: #047857;
}

.date-yesterday {
    border:
        1px solid #bfdbfe;

    background: #eff6ff;

    color: #2563eb;
}

.date-default {
    border:
        1px solid #f1f5f9;

    background: #f8fafc;

    color: #94a3b8;
}

.date-live-dot {
    height: 0.4rem;
    width: 0.4rem;

    border-radius: 9999px;

    background: #10b981;

    box-shadow:
        0 0 0 3px
        rgba(
            16,
            185,
            129,
            0.12
        );
}

/* ================================================================
   INFORMATION
================================================================ */

.info-chip {
    display: inline-flex;

    align-items: center;

    gap: 0.4rem;

    border:
        1px solid #e2e8f0;

    border-radius: 0.7rem;

    background:
        rgba(
            248,
            250,
            252,
            0.85
        );

    padding:
        0.4rem
        0.62rem;

    font-size: 0.66rem;

    font-weight: 700;

    color: #475569;
}

.info-chip-money {
    border-color: #d1fae5;

    background: #f0fdf4;

    color: #047857;
}

.info-chip-icon {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    min-width: 0.8rem;

    font-size: 0.68rem;

    font-weight: 900;
}

.stack-chip {
    border:
        1px solid #e0e7ff;

    border-radius: 0.55rem;

    background:
        linear-gradient(
            135deg,
            #eef2ff,
            #f8fafc
        );

    padding:
        0.3rem
        0.5rem;

    font-size: 0.58rem;

    font-weight: 900;

    color: #6366f1;

    transition:
        transform 0.2s ease,
        border-color 0.2s ease;
}

.stack-chip:hover {
    transform:
        translateY(-1px);

    border-color: #c7d2fe;
}

/* ================================================================
   RIGHT SIDE
================================================================ */

.mission-side {
    flex-shrink: 0;

    border-top:
        1px solid #f1f5f9;

    padding-top: 1.25rem;
}

@media (
    min-width: 1280px
) {
    .mission-side {
        width: 17rem;

        border-left:
            1px solid #f1f5f9;

        border-top: 0;

        padding-left: 1.5rem;
        padding-top: 0;
    }
}

/* ================================================================
   SCORE
================================================================ */

.score-display {
    position: relative;

    display: flex;

    height: 3.6rem;
    width: 3.6rem;

    flex-shrink: 0;

    align-items: center;
    justify-content: center;

    overflow: hidden;

    border-radius: 1.15rem;

    color: white;

    box-shadow:
        inset
        0
        1px
        0
        rgba(
            255,
            255,
            255,
            0.28
        ),
        0
        15px
        28px
        -20px
        rgba(
            15,
            23,
            42,
            0.7
        );

    transition:
        transform 0.3s
        cubic-bezier(
            0.22,
            1,
            0.36,
            1
        ),
        box-shadow 0.3s ease;
}

.mission-card:hover
.score-display {
    transform:
        scale(1.06)
        rotate(-2deg);
}

.score-premium {
    background:
        linear-gradient(
            135deg,
            #4338ca,
            #7c3aed
        );

    color: white;
}

.score-good {
    background:
        linear-gradient(
            135deg,
            #047857,
            #10b981
        );

    color: white;
}

.score-medium {
    background:
        linear-gradient(
            135deg,
            #d97706,
            #f59e0b
        );

    color: white;
}

.score-neutral {
    background:
        linear-gradient(
            135deg,
            #334155,
            #64748b
        );

    color: white;
}

.score-number {
    position: relative;

    z-index: 2;

    font-size: 1.25rem;

    font-weight: 900;
}

.score-spark {
    position: absolute;

    right: 0.35rem;
    top: 0.25rem;

    z-index: 2;

    font-size: 0.45rem;

    opacity: 0.7;
}

.score-shine {
    position: absolute;

    left: -100%;
    top: 0;

    height: 100%;
    width: 65%;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(
                255,
                255,
                255,
                0.18
            ),
            transparent
        );

    transform:
        skewX(-20deg);

    transition:
        left 0.6s ease;
}

.mission-card:hover
.score-shine {
    left: 130%;
}

/* ================================================================
   STATUS SELECT
================================================================ */

.status-select {
    width: 100%;

    border-radius: 0.75rem;

    border-width: 1px;

    padding:
        0.55rem
        0.65rem;

    font-size: 0.62rem;

    font-weight: 900;

    outline: none;

    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.status-select:hover:not(:disabled) {
    transform:
        translateY(-1px);

    box-shadow:
        0 5px 12px -10px
        rgba(
            15,
            23,
            42,
            0.4
        );
}

/* ================================================================
   ACTION BUTTONS
================================================================ */

.details-button {
    display: flex;

    flex: 1;

    align-items: center;
    justify-content: center;

    gap: 0.45rem;

    border-radius: 0.8rem;

    background:
        linear-gradient(
            135deg,
            #0f172a,
            #312e81
        );

    padding:
        0.68rem
        0.85rem;

    font-size: 0.65rem;

    font-weight: 900;

    color: white;

    box-shadow:
        0 10px 24px -20px
        rgba(
            49,
            46,
            129,
            0.8
        );

    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease;
}

.details-button:hover {
    transform:
        translateY(-2px);

    box-shadow:
        0 15px 27px -19px
        rgba(
            79,
            70,
            229,
            0.8
        );
}

.details-button span {
    transition:
        transform 0.25s ease;
}

.details-button:hover span {
    transform:
        translateX(3px);
}

.external-button {
    display: flex;

    height: 2.45rem;
    width: 2.45rem;

    flex-shrink: 0;

    align-items: center;
    justify-content: center;

    border:
        1px solid #e2e8f0;

    border-radius: 0.8rem;

    background: white;

    font-size: 0.75rem;

    font-weight: 900;

    color: #64748b;

    transition:
        transform 0.25s ease,
        border-color 0.25s ease,
        color 0.25s ease;
}

.external-button:hover {
    transform:
        translateY(-2px)
        rotate(3deg);

    border-color: #c7d2fe;

    color: #4f46e5;
}

/* ================================================================
   EMPTY
================================================================ */

.empty-state {
    border:
        1px dashed #cbd5e1;

    border-radius: 1.7rem;

    background:
        rgba(
            255,
            255,
            255,
            0.85
        );

    padding:
        4rem
        1.5rem;

    text-align: center;

    box-shadow:
        0 15px 35px -32px
        rgba(
            15,
            23,
            42,
            0.4
        );
}

.empty-state-icon {
    display: flex;

    height: 4rem;
    width: 4rem;

    margin: auto;

    align-items: center;
    justify-content: center;

    border-radius: 1.15rem;

    background:
        linear-gradient(
            135deg,
            #eef2ff,
            #f5f3ff
        );

    font-size: 1.7rem;

    font-weight: 900;

    color: #6366f1;
}

/* ================================================================
   PAGINATION
================================================================ */

.pagination-panel {
    display: flex;

    flex-direction: column;

    gap: 1rem;

    margin-top: 1.5rem;

    border:
        1px solid
        rgba(
            226,
            232,
            240,
            0.8
        );

    border-radius: 1.3rem;

    background:
        rgba(
            255,
            255,
            255,
            0.9
        );

    padding:
        0.9rem
        1.1rem;

    box-shadow:
        0 15px 35px -32px
        rgba(
            15,
            23,
            42,
            0.4
        );

    backdrop-filter:
        blur(16px);
}

@media (
    min-width: 640px
) {
    .pagination-panel {
        flex-direction: row;

        align-items: center;

        justify-content: space-between;
    }
}

.pagination-button {
    display: inline-flex;

    height: 2.45rem;
    width: 2.45rem;

    align-items: center;
    justify-content: center;

    border:
        1px solid #e2e8f0;

    border-radius: 0.75rem;

    background: white;

    font-size: 0.7rem;

    font-weight: 900;

    color: #475569;

    transition:
        transform 0.2s ease,
        color 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.pagination-button:hover:not(:disabled) {
    transform:
        translateY(-2px);

    border-color: #c7d2fe;

    color: #4f46e5;

    box-shadow:
        0 10px 20px -16px
        rgba(
            79,
            70,
            229,
            0.55
        );
}

.pagination-button:disabled {
    cursor: not-allowed;

    opacity: 0.3;
}

.pagination-current {
    display: flex;

    align-items: center;

    gap: 0.35rem;

    border:
        1px solid #e2e8f0;

    border-radius: 0.75rem;

    background: #f8fafc;

    padding:
        0.6rem
        0.8rem;

    font-size: 0.62rem;

    color: #64748b;
}

.pagination-current strong {
    font-weight: 900;

    color: #4f46e5;
}

/* ================================================================
   DRAWER
================================================================ */

.mission-drawer {
    position: fixed;

    right: 0;
    top: 0;

    z-index: 50;

    height: 100%;

    width: 100%;

    overflow-y: auto;

    border-left:
        1px solid
        rgba(
            255,
            255,
            255,
            0.7
        );

    background:
        rgba(
            255,
            255,
            255,
            0.97
        );

    box-shadow:
        -30px
        0
        70px
        -35px
        rgba(
            15,
            23,
            42,
            0.55
        );

    backdrop-filter:
        blur(24px);
}

@media (
    min-width: 640px
) {
    .mission-drawer {
        width: 590px;
    }
}

.drawer-header {
    position: sticky;

    top: 0;

    z-index: 20;

    display: flex;

    align-items: center;

    justify-content: space-between;

    border-bottom:
        1px solid
        rgba(
            226,
            232,
            240,
            0.75
        );

    background:
        rgba(
            255,
            255,
            255,
            0.9
        );

    padding:
        1.1rem
        1.5rem;

    backdrop-filter:
        blur(18px);
}

.drawer-close {
    display: flex;

    height: 2.5rem;
    width: 2.5rem;

    align-items: center;
    justify-content: center;

    border:
        1px solid #e2e8f0;

    border-radius: 0.8rem;

    background: white;

    font-size: 1.1rem;

    color: #64748b;

    transition:
        transform 0.3s ease,
        color 0.3s ease,
        background 0.3s ease,
        border-color 0.3s ease;
}

.drawer-close:hover {
    transform:
        rotate(90deg);

    border-color: #fecdd3;

    background: #fff1f2;

    color: #e11d48;
}

.drawer-hero {
    position: relative;

    overflow: hidden;

    border-radius: 1.45rem;

    background:
        linear-gradient(
            135deg,
            #020617,
            #0f172a,
            #312e81
        );

    padding: 1.5rem;

    color: white;

    box-shadow:
        0 20px 45px -30px
        rgba(
            49,
            46,
            129,
            0.75
        );
}

.drawer-score {
    display: flex;

    height: 4.5rem;
    width: 4.5rem;

    flex-shrink: 0;

    flex-direction: column;

    align-items: center;
    justify-content: center;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.13
        );

    border-radius: 1rem;

    box-shadow:
        inset
        0
        1px
        0
        rgba(
            255,
            255,
            255,
            0.2
        );
}

.drawer-score strong {
    font-size: 1.55rem;

    font-weight: 900;

    line-height: 1.4rem;
}

/* ================================================================
   DETAIL STATS
================================================================ */

.detail-stat {
    display: flex;

    min-height: 5rem;

    align-items: center;

    gap: 0.75rem;

    border:
        1px solid #e2e8f0;

    border-radius: 1rem;

    background:
        linear-gradient(
            135deg,
            #f8fafc,
            #ffffff
        );

    padding: 0.85rem;

    transition:
        transform 0.2s ease,
        border-color 0.2s ease;
}

.detail-stat:hover {
    transform:
        translateY(-1px);

    border-color: #e0e7ff;
}

.detail-icon {
    display: flex;

    height: 2.25rem;
    width: 2.25rem;

    flex-shrink: 0;

    align-items: center;
    justify-content: center;

    border-radius: 0.7rem;

    background:
        linear-gradient(
            135deg,
            #eef2ff,
            #f5f3ff
        );

    font-size: 0.75rem;

    font-weight: 900;

    color: #6366f1;
}

.detail-label {
    font-size: 0.55rem;

    font-weight: 900;

    letter-spacing:
        0.07em;

    text-transform:
        uppercase;

    color: #94a3b8;
}

.detail-value {
    margin-top: 0.2rem;

    font-size: 0.7rem;

    font-weight: 800;

    color: #334155;
}

/* ================================================================
   DETAIL SECTIONS
================================================================ */

.detail-section {
    margin-top: 1.1rem;

    border:
        1px solid #f1f5f9;

    border-radius: 1.15rem;

    background:
        rgba(
            255,
            255,
            255,
            0.9
        );

    padding: 1.1rem;

    box-shadow:
        0 8px 20px -20px
        rgba(
            15,
            23,
            42,
            0.4
        );
}

.section-heading {
    display: flex;

    align-items: center;

    gap: 0.55rem;

    font-size: 0.82rem;

    font-weight: 900;

    color: #0f172a;
}

.section-heading-icon {
    display: flex;

    height: 1.8rem;
    width: 1.8rem;

    align-items: center;
    justify-content: center;

    border-radius: 0.55rem;

    background: #eef2ff;

    font-size: 0.65rem;

    color: #6366f1;
}

.section-heading-success {
    background: #ecfdf5;

    color: #059669;
}

.drawer-stack {
    border:
        1px solid #e0e7ff;

    border-radius: 0.65rem;

    background: #eef2ff;

    padding:
        0.45rem
        0.65rem;

    font-size: 0.62rem;

    font-weight: 900;

    color: #6366f1;
}

/* ================================================================
   STATUS ACTIONS
================================================================ */

.status-action {
    display: flex;

    min-height: 3.8rem;

    flex-direction: column;

    align-items: center;
    justify-content: center;

    gap: 0.2rem;

    border-width: 1px;

    border-radius: 0.75rem;

    padding:
        0.45rem
        0.25rem;

    font-size: 0.52rem;

    font-weight: 900;

    opacity: 0.72;

    transition:
        transform 0.2s ease,
        opacity 0.2s ease,
        box-shadow 0.2s ease;
}

.status-action:hover:not(:disabled) {
    transform:
        translateY(-2px);

    opacity: 1;
}

.status-action-selected {
    opacity: 1;

    box-shadow:
        0 0 0 2px white,
        0 0 0 4px
        rgba(
            99,
            102,
            241,
            0.16
        );
}

/* ================================================================
   APPLICATION
================================================================ */

.application-card {
    display: flex;

    align-items: center;

    gap: 0.8rem;

    margin-top: 1rem;

    border:
        1px solid #ddd6fe;

    border-radius: 1rem;

    background:
        linear-gradient(
            135deg,
            #f5f3ff,
            #eef2ff
        );

    padding: 0.9rem;
}

.application-check {
    display: flex;

    height: 2.5rem;
    width: 2.5rem;

    flex-shrink: 0;

    align-items: center;
    justify-content: center;

    border-radius: 0.8rem;

    background:
        linear-gradient(
            135deg,
            #7c3aed,
            #4f46e5
        );

    font-size: 0.8rem;

    font-weight: 900;

    color: white;

    box-shadow:
        0 10px 20px -15px
        rgba(
            79,
            70,
            229,
            0.8
        );
}

/* ================================================================
   SOURCE OCCURRENCES
================================================================ */

.source-occurrence {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 1rem;

    border:
        1px solid #e2e8f0;

    border-radius: 0.8rem;

    background: #f8fafc;

    padding:
        0.7rem
        0.8rem;
}

/* ================================================================
   ORIGINAL BUTTON
================================================================ */

.original-button {
    display: flex;

    width: 100%;

    align-items: center;

    justify-content: center;

    gap: 0.6rem;

    border-radius: 1rem;

    background:
        linear-gradient(
            135deg,
            #020617,
            #312e81
        );

    padding:
        0.9rem
        1rem;

    font-size: 0.72rem;

    font-weight: 900;

    color: white;

    box-shadow:
        0 18px 35px -25px
        rgba(
            49,
            46,
            129,
            0.75
        );

    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease;
}

.original-button:hover {
    transform:
        translateY(-2px);

    box-shadow:
        0 22px 40px -24px
        rgba(
            79,
            70,
            229,
            0.8
        );
}

/* ================================================================
   TRANSITIONS
================================================================ */

.fade-enter-active,
.fade-leave-active {
    transition:
        opacity 0.22s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.drawer-enter-active,
.drawer-leave-active {
    transition:
        transform
        0.34s
        cubic-bezier(
            0.22,
            1,
            0.36,
            1
        ),
        opacity
        0.25s
        ease;
}

.drawer-enter-from,
.drawer-leave-to {
    transform:
        translateX(100%);

    opacity: 0;
}

.chips-enter-active,
.chips-leave-active {
    transition:
        opacity 0.25s ease,
        transform 0.25s ease;
}

.chips-enter-from,
.chips-leave-to {
    opacity: 0;

    transform:
        translateY(-5px);
}

/* ================================================================
   ANIMATIONS
================================================================ */

@keyframes heroReveal {
    from {
        opacity: 0;

        transform:
            translateY(10px)
            scale(0.995);
    }

    to {
        opacity: 1;

        transform:
            translateY(0)
            scale(1);
    }
}

@keyframes missionReveal {
    from {
        opacity: 0;

        transform:
            translateY(8px);
    }

    to {
        opacity: 1;

        transform:
            translateY(0);
    }
}

/* ================================================================
   RESPONSIVE
================================================================ */

@media (
    min-width: 640px
) {
    .hero-panel {
        padding: 2.2rem;
    }
}

@media (
    max-width: 639px
) {
    .results-toolbar {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .status-action {
        min-height: 3.2rem;
    }
}

/* ================================================================
   REDUCED MOTION
================================================================ */

@media (
    prefers-reduced-motion:
    reduce
) {
    .hero-panel,
    .mission-card {
        animation: none;
    }

    .mission-card,
    .score-display,
    .drawer-enter-active,
    .drawer-leave-active,
    .active-filter-chip,
    .details-button {
        transition: none;
    }
}
</style>