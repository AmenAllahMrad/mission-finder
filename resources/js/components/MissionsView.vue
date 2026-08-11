<script setup>
import { onMounted, ref, watch } from 'vue';
import axios from 'axios';

/*
|--------------------------------------------------------------------------
| Missions / Sources
|--------------------------------------------------------------------------
*/

const missions = ref([]);
const sources = ref([]);

const loading = ref(false);
const error = ref(null);

const updatingMissionId = ref(null);

/*
|--------------------------------------------------------------------------
| Mission detail drawer
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
});

/*
|--------------------------------------------------------------------------
| Load sources
|--------------------------------------------------------------------------
*/

const chargerSources = async () => {
    try {
        const response = await axios.get('/api/sources');

        sources.value = response.data;
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

const chargerMissions = async (page = 1) => {
    loading.value = true;
    error.value = null;

    try {
        const params = {
            page,
            per_page: 10,
        };

        if (filters.value.search.trim()) {
            params.search =
                filters.value.search.trim();
        }

        if (filters.value.statut) {
            params.statut =
                filters.value.statut;
        }

        if (filters.value.remote) {
            params.remote =
                filters.value.remote;
        }

        if (filters.value.source_id) {
            params.source_id =
                filters.value.source_id;
        }

        const response = await axios.get(
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
    } catch (err) {
        console.error(
            'Erreur chargement missions :',
            err
        );

        error.value =
            'Impossible de charger les missions.';
    } finally {
        loading.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| Open mission detail
|--------------------------------------------------------------------------
*/

const ouvrirMission = async (missionId) => {
    detailOuvert.value = true;
    detailLoading.value = true;
    detailError.value = null;
    missionSelectionnee.value = null;

    try {
        const response = await axios.get(
            `/api/missions/${missionId}`
        );

        missionSelectionnee.value =
            response.data.mission;
    } catch (err) {
        console.error(
            'Erreur chargement détail mission :',
            err
        );

        detailError.value =
            'Impossible de charger cette mission.';
    } finally {
        detailLoading.value = false;
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

const scoreMission = (mission) => {
    if (
        !mission?.scores_profils ||
        mission.scores_profils.length === 0
    ) {
        return 0;
    }

    return mission.scores_profils[0].score;
};

/*
|--------------------------------------------------------------------------
| TJM
|--------------------------------------------------------------------------
*/

const afficherTjm = (mission) => {
    if (
        mission?.tjm_min === null ||
        mission?.tjm_min === undefined
    ) {
        return 'Non renseigné';
    }

    if (
        mission.tjm_max !== null &&
        mission.tjm_max !== undefined &&
        Number(mission.tjm_max) !==
            Number(mission.tjm_min)
    ) {
        return `${mission.tjm_min} - ${mission.tjm_max}`;
    }

    return mission.tjm_min;
};

/*
|--------------------------------------------------------------------------
| Status classes
|--------------------------------------------------------------------------
*/

const classeStatut = (statut) => {
    const classes = {
        nouveau:
            'bg-blue-100 text-blue-700',

        vu:
            'bg-slate-100 text-slate-700',

        interessant:
            'bg-green-100 text-green-700',

        postule:
            'bg-purple-100 text-purple-700',

        ecarte:
            'bg-red-100 text-red-700',
    };

    return (
        classes[statut] ??
        'bg-slate-100 text-slate-700'
    );
};

/*
|--------------------------------------------------------------------------
| Status label
|--------------------------------------------------------------------------
*/

const labelStatut = (statut) => {
    const labels = {
        nouveau: 'Nouveau',
        vu: 'Vu',
        interessant: 'Intéressant',
        postule: 'Postulé',
        ecarte: 'Écarté',
    };

    return labels[statut] ?? statut;
};

/*
|--------------------------------------------------------------------------
| Remote label
|--------------------------------------------------------------------------
*/

const labelRemote = (remote) => {
    const labels = {
        full_remote: 'Full remote',
        hybrid: 'Hybrid',
        onsite: 'On-site',
    };

    return (
        labels[remote] ??
        remote ??
        'Non renseigné'
    );
};

/*
|--------------------------------------------------------------------------
| Date
|--------------------------------------------------------------------------
*/

const formaterDate = (date) => {
    if (!date) {
        return 'Non renseignée';
    }

    return new Intl.DateTimeFormat(
        'fr-FR',
        {
            timeZone: 'Africa/Tunis',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }
    ).format(new Date(date));
};

/*
|--------------------------------------------------------------------------
| Clean external HTML description
|--------------------------------------------------------------------------
|
| Certaines sources comme We Work Remotely retournent une description
| contenant du HTML.
|
| On extrait uniquement le texte au lieu d'utiliser v-html.
|--------------------------------------------------------------------------
*/

const nettoyerDescription = (html) => {
    if (!html) {
        return 'Description non renseignée.';
    }

    const parser = new DOMParser();

    const documentHtml =
        parser.parseFromString(
            html,
            'text/html'
        );

    /*
     * Ajoute des retours à la ligne après certains
     * éléments de bloc pour garder une description lisible.
     */
    documentHtml
        .querySelectorAll(
            'p, div, li, br, h1, h2, h3, h4, h5, h6'
        )
        .forEach((element) => {
            if (
                element.tagName.toLowerCase()
                === 'br'
            ) {
                element.replaceWith('\n');
            } else {
                element.append('\n\n');
            }
        });

    const texte =
        documentHtml.body.textContent ?? '';

    return (
        texte
            .replace(/\u00a0/g, ' ')
            .replace(/[ \t]+/g, ' ')
            .replace(/\n[ \t]+/g, '\n')
            .replace(/\n{3,}/g, '\n\n')
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

const changerStatut = async (
    mission,
    nouveauStatut
) => {
    if (
        !mission ||
        nouveauStatut === mission.statut
    ) {
        return;
    }

    const ancienStatut =
        mission.statut;

    updatingMissionId.value =
        mission.id;

    /*
     * Mise à jour immédiate dans l'interface.
     */
    mission.statut =
        nouveauStatut;

    try {
        const response =
            await axios.patch(
                `/api/missions/${mission.id}/statut`,
                {
                    statut: nouveauStatut,
                }
            );

        const missionMiseAJour =
            response.data.mission;

        /*
         * Synchronise la ligne du tableau.
         */
        const missionListe =
            missions.value.find(
                (item) =>
                    item.id === mission.id
            );

        if (missionListe) {
            missionListe.statut =
                missionMiseAJour.statut;

            missionListe.date_candidature =
                missionMiseAJour.date_candidature;
        }

        /*
         * Synchronise le panneau de détail.
         */
        if (
            missionSelectionnee.value &&
            missionSelectionnee.value.id
                === mission.id
        ) {
            missionSelectionnee.value.statut =
                missionMiseAJour.statut;

            missionSelectionnee.value.date_candidature =
                missionMiseAJour.date_candidature;
        }
    } catch (err) {
        console.error(
            'Erreur modification statut :',
            err
        );

        /*
         * Rollback en cas d'erreur.
         */
        mission.statut =
            ancienStatut;

        const missionListe =
            missions.value.find(
                (item) =>
                    item.id === mission.id
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
            missionSelectionnee.value.statut =
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
    () => filters.value.search,

    () => {
        clearTimeout(searchTimer);

        searchTimer = setTimeout(
            () => {
                chargerMissions(1);
            },
            350
        );
    }
);

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

watch(
    [
        () => filters.value.statut,
        () => filters.value.remote,
        () => filters.value.source_id,
    ],

    () => {
        chargerMissions(1);
    }
);

/*
|--------------------------------------------------------------------------
| Mounted
|--------------------------------------------------------------------------
*/

onMounted(async () => {
    await chargerSources();

    await chargerMissions();
});
</script>

<template>
    <main
        class="mx-auto max-w-7xl px-6 py-10"
    >

        <!-- ========================================================= -->
        <!-- HEADER -->
        <!-- ========================================================= -->

        <div
            class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h2
                    class="text-3xl font-bold text-slate-900"
                >
                    Missions
                </h2>

                <p
                    class="mt-2 text-slate-500"
                >
                    Explorez et filtrez les missions
                    collectées par MissionFinder.
                </p>
            </div>

            <div
                class="self-start rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white sm:self-auto"
            >
                {{ total }} missions
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- FILTERS -->
        <!-- ========================================================= -->

        <div
            class="mb-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-2 lg:grid-cols-4"
        >

            <!-- Search -->
            <div>
                <label
                    class="mb-2 block text-xs font-semibold uppercase text-slate-500"
                >
                    Search
                </label>

                <input
                    v-model="filters.search"
                    type="text"
                    placeholder="Laravel, company..."
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none transition focus:border-slate-500"
                >
            </div>

            <!-- Status -->
            <div>
                <label
                    class="mb-2 block text-xs font-semibold uppercase text-slate-500"
                >
                    Status
                </label>

                <select
                    v-model="filters.statut"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                >
                    <option value="">
                        All statuses
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
                    class="mb-2 block text-xs font-semibold uppercase text-slate-500"
                >
                    Remote
                </label>

                <select
                    v-model="filters.remote"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                >
                    <option value="">
                        All
                    </option>

                    <option value="full_remote">
                        Full remote
                    </option>

                    <option value="hybrid">
                        Hybrid
                    </option>

                    <option value="onsite">
                        On-site
                    </option>
                </select>
            </div>

            <!-- Source -->
            <div>
                <label
                    class="mb-2 block text-xs font-semibold uppercase text-slate-500"
                >
                    Source
                </label>

                <select
                    v-model="filters.source_id"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                >
                    <option value="">
                        All sources
                    </option>

                    <option
                        v-for="source in sources"
                        :key="source.id"
                        :value="source.id"
                    >
                        {{ source.nom }}
                    </option>
                </select>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- ERROR -->
        <!-- ========================================================= -->

        <div
            v-if="error"
            class="mb-6 rounded-xl border border-red-200 bg-red-50 p-5 text-red-700"
        >
            {{ error }}
        </div>

        <!-- ========================================================= -->
        <!-- TABLE -->
        <!-- ========================================================= -->

        <div
            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
        >
            <div class="overflow-x-auto">

                <table class="w-full">

                    <!-- Header -->
                    <thead class="bg-slate-50">
                        <tr
                            class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500"
                        >
                            <th class="px-5 py-4">
                                Mission
                            </th>

                            <th class="px-5 py-4">
                                Source
                            </th>

                            <th class="px-5 py-4">
                                Remote
                            </th>

                            <th class="px-5 py-4">
                                TJM
                            </th>

                            <th class="px-5 py-4">
                                Score
                            </th>

                            <th class="px-5 py-4">
                                Status
                            </th>

                            <th class="px-5 py-4">
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody>

                        <!-- Loading -->
                        <tr v-if="loading">
                            <td
                                colspan="7"
                                class="px-5 py-12 text-center text-slate-500"
                            >
                                Chargement des missions...
                            </td>
                        </tr>

                        <!-- Empty -->
                        <tr
                            v-else-if="
                                missions.length === 0
                            "
                        >
                            <td
                                colspan="7"
                                class="px-5 py-12 text-center text-slate-500"
                            >
                                Aucune mission trouvée.
                            </td>
                        </tr>

                        <!-- Missions -->
                        <template v-else>

                            <tr
                                v-for="mission in missions"
                                :key="mission.id"
                                class="border-b border-slate-100 transition hover:bg-slate-50"
                            >

                                <!-- Mission -->
                                <td class="px-5 py-4">

                                    <button
                                        type="button"
                                        class="max-w-md text-left font-semibold text-slate-900 transition hover:text-blue-600"
                                        @click="
                                            ouvrirMission(
                                                mission.id
                                            )
                                        "
                                    >
                                        {{ mission.titre }}
                                    </button>

                                    <div
                                        class="mt-1 text-sm text-slate-500"
                                    >
                                        {{
                                            mission.entreprise
                                                ||
                                            'Entreprise non renseignée'
                                        }}
                                    </div>

                                    <div
                                        v-if="
                                            mission.localisation
                                        "
                                        class="mt-1 text-xs text-slate-400"
                                    >
                                        📍
                                        {{
                                            mission.localisation
                                        }}
                                    </div>

                                    <!-- Stacks -->
                                    <div
                                        v-if="
                                            mission.stacks &&
                                            mission.stacks.length > 0
                                        "
                                        class="mt-2 flex max-w-md flex-wrap gap-1"
                                    >
                                        <span
                                            v-for="stack in mission.stacks"
                                            :key="stack.id"
                                            class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-600"
                                        >
                                            {{ stack.nom }}
                                        </span>
                                    </div>

                                </td>

                                <!-- Source -->
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-block max-w-28 rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700"
                                    >
                                        {{
                                            mission.source
                                                ?.nom
                                                || '—'
                                        }}
                                    </span>
                                </td>

                                <!-- Remote -->
                                <td
                                    class="px-5 py-4 text-sm text-slate-600"
                                >
                                    {{
                                        labelRemote(
                                            mission.remote_type
                                        )
                                    }}
                                </td>

                                <!-- TJM -->
                                <td
                                    class="px-5 py-4 text-sm text-slate-600"
                                >
                                    {{
                                        afficherTjm(
                                            mission
                                        )
                                    }}
                                </td>

                                <!-- Score -->
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-slate-900 font-bold text-white"
                                    >
                                        {{
                                            scoreMission(
                                                mission
                                            )
                                        }}
                                    </span>
                                </td>

                                <!-- Status -->
                                <td class="px-5 py-4">

                                    <select
                                        :value="
                                            mission.statut
                                        "
                                        :disabled="
                                            updatingMissionId
                                                === mission.id
                                        "
                                        :class="
                                            classeStatut(
                                                mission.statut
                                            )
                                        "
                                        class="cursor-pointer rounded-full border-0 px-3 py-2 text-xs font-semibold outline-none disabled:cursor-wait disabled:opacity-60"
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

                                </td>

                                <!-- Action -->
                                <td class="px-5 py-4">

                                    <div
                                        class="flex flex-col items-start gap-2"
                                    >
                                        <button
                                            type="button"
                                            class="font-medium text-slate-700 transition hover:text-blue-600"
                                            @click="
                                                ouvrirMission(
                                                    mission.id
                                                )
                                            "
                                        >
                                            Details
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
                                            class="font-medium text-blue-600 hover:underline"
                                        >
                                            Open ↗
                                        </a>
                                    </div>

                                </td>

                            </tr>

                        </template>

                    </tbody>
                </table>

            </div>

            <!-- ===================================================== -->
            <!-- PAGINATION -->
            <!-- ===================================================== -->

            <div
                class="flex flex-col gap-4 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
            >

                <p
                    class="text-sm text-slate-500"
                >
                    Showing
                    {{ from }}
                    -
                    {{ to }}
                    of
                    {{ total }}
                </p>

                <div
                    class="flex items-center gap-3"
                >

                    <!-- Previous -->
                    <button
                        type="button"
                        :disabled="
                            currentPage <= 1 ||
                            loading
                        "
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            chargerMissions(
                                currentPage - 1
                            )
                        "
                    >
                        ← Previous
                    </button>

                    <!-- Page -->
                    <span
                        class="text-sm font-medium text-slate-600"
                    >
                        Page
                        {{ currentPage }}
                        /
                        {{ lastPage }}
                    </span>

                    <!-- Next -->
                    <button
                        type="button"
                        :disabled="
                            currentPage >=
                                lastPage ||
                            loading
                        "
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            chargerMissions(
                                currentPage + 1
                            )
                        "
                    >
                        Next →
                    </button>

                </div>
            </div>

        </div>

        <!-- ========================================================= -->
        <!-- OVERLAY -->
        <!-- ========================================================= -->

        <div
            v-if="detailOuvert"
            class="fixed inset-0 z-40 bg-slate-900/40"
            @click="fermerMission"
        ></div>

        <!-- ========================================================= -->
        <!-- MISSION DETAIL DRAWER -->
        <!-- ========================================================= -->

        <aside
            v-if="detailOuvert"
            class="fixed right-0 top-0 z-50 h-full w-full overflow-y-auto border-l border-slate-200 bg-white shadow-2xl sm:w-[520px]"
        >

            <!-- Drawer header -->
            <div
                class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-6 py-5"
            >
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-slate-400"
                    >
                        Mission details
                    </p>

                    <p
                        class="mt-1 text-sm text-slate-500"
                    >
                        MissionFinder
                    </p>
                </div>

                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xl text-slate-600 transition hover:bg-slate-200"
                    @click="fermerMission"
                >
                    ×
                </button>
            </div>

            <!-- Drawer loading -->
            <div
                v-if="detailLoading"
                class="px-6 py-16 text-center text-slate-500"
            >
                Chargement de la mission...
            </div>

            <!-- Drawer error -->
            <div
                v-else-if="detailError"
                class="m-6 rounded-xl border border-red-200 bg-red-50 p-5 text-red-700"
            >
                {{ detailError }}
            </div>

            <!-- Mission detail -->
            <div
                v-else-if="missionSelectionnee"
                class="px-6 py-6"
            >

                <!-- ================================================= -->
                <!-- TITLE -->
                <!-- ================================================= -->

                <div
                    class="border-b border-slate-100 pb-6"
                >

                    <div
                        class="mb-3 flex flex-wrap items-center gap-2"
                    >

                        <!-- Status -->
                        <span
                            :class="
                                classeStatut(
                                    missionSelectionnee.statut
                                )
                            "
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                        >
                            {{
                                labelStatut(
                                    missionSelectionnee.statut
                                )
                            }}
                        </span>

                        <!-- Score -->
                        <span
                            class="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white"
                        >
                            Score
                            {{
                                scoreMission(
                                    missionSelectionnee
                                )
                            }}
                        </span>

                    </div>

                    <h2
                        class="text-2xl font-bold text-slate-900"
                    >
                        {{
                            missionSelectionnee.titre
                        }}
                    </h2>

                    <p
                        class="mt-2 text-lg text-slate-500"
                    >
                        {{
                            missionSelectionnee.entreprise
                                ||
                            'Entreprise non renseignée'
                        }}
                    </p>

                </div>

                <!-- ================================================= -->
                <!-- INFORMATION -->
                <!-- ================================================= -->

                <div
                    class="grid grid-cols-2 gap-5 border-b border-slate-100 py-6"
                >

                    <!-- Source -->
                    <div>
                        <p
                            class="text-xs font-semibold uppercase text-slate-400"
                        >
                            Source
                        </p>

                        <p
                            class="mt-1 font-medium text-slate-800"
                        >
                            {{
                                missionSelectionnee
                                    .source?.nom
                                    || '—'
                            }}
                        </p>
                    </div>

                    <!-- Remote -->
                    <div>
                        <p
                            class="text-xs font-semibold uppercase text-slate-400"
                        >
                            Remote
                        </p>

                        <p
                            class="mt-1 font-medium text-slate-800"
                        >
                            {{
                                labelRemote(
                                    missionSelectionnee
                                        .remote_type
                                )
                            }}
                        </p>
                    </div>

                    <!-- Location -->
                    <div>
                        <p
                            class="text-xs font-semibold uppercase text-slate-400"
                        >
                            Localisation
                        </p>

                        <p
                            class="mt-1 font-medium text-slate-800"
                        >
                            {{
                                missionSelectionnee
                                    .localisation
                                    ||
                                'Non renseignée'
                            }}
                        </p>
                    </div>

                    <!-- TJM -->
                    <div>
                        <p
                            class="text-xs font-semibold uppercase text-slate-400"
                        >
                            TJM
                        </p>

                        <p
                            class="mt-1 font-medium text-slate-800"
                        >
                            {{
                                afficherTjm(
                                    missionSelectionnee
                                )
                            }}
                        </p>
                    </div>

                    <!-- Sector -->
                    <div>
                        <p
                            class="text-xs font-semibold uppercase text-slate-400"
                        >
                            Secteur
                        </p>

                        <p
                            class="mt-1 font-medium text-slate-800"
                        >
                            {{
                                missionSelectionnee
                                    .secteur
                                    ||
                                'Non renseigné'
                            }}
                        </p>
                    </div>

                    <!-- Duration -->
                    <div>
                        <p
                            class="text-xs font-semibold uppercase text-slate-400"
                        >
                            Durée
                        </p>

                        <p
                            class="mt-1 font-medium text-slate-800"
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

                            <template v-else>
                                Non renseignée
                            </template>
                        </p>
                    </div>

                </div>

                <!-- ================================================= -->
                <!-- TECHNOLOGIES -->
                <!-- ================================================= -->

                <div
                    class="border-b border-slate-100 py-6"
                >
                    <h3
                        class="font-semibold text-slate-900"
                    >
                        Technologies
                    </h3>

                    <div
                        v-if="
                            missionSelectionnee
                                .stacks?.length
                        "
                        class="mt-3 flex flex-wrap gap-2"
                    >
                        <span
                            v-for="stack in missionSelectionnee.stacks"
                            :key="stack.id"
                            class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700"
                        >
                            {{ stack.nom }}
                        </span>
                    </div>

                    <p
                        v-else
                        class="mt-2 text-sm text-slate-500"
                    >
                        Aucune technologie fiable détectée.
                    </p>

                </div>

                <!-- ================================================= -->
                <!-- APPLICATION -->
                <!-- ================================================= -->

                <div
                    v-if="
                        missionSelectionnee.statut
                            === 'postule'
                    "
                    class="border-b border-slate-100 py-6"
                >
                    <h3
                        class="font-semibold text-slate-900"
                    >
                        Candidature
                    </h3>

                    <div
                        class="mt-3 rounded-xl bg-purple-50 p-4"
                    >
                        <p
                            class="text-sm font-semibold text-purple-700"
                        >
                            ✓ Candidature enregistrée
                        </p>

                        <p
                            class="mt-1 text-sm text-purple-600"
                        >
                            {{
                                formaterDate(
                                    missionSelectionnee
                                        .date_candidature
                                )
                            }}
                        </p>
                    </div>
                </div>

                <!-- ================================================= -->
                <!-- STATUS -->
                <!-- ================================================= -->

                <div
                    class="border-b border-slate-100 py-6"
                >
                    <h3
                        class="font-semibold text-slate-900"
                    >
                        Suivi de la mission
                    </h3>

                    <p
                        class="mt-1 text-sm text-slate-500"
                    >
                        Modifiez directement le statut de cette opportunité.
                    </p>

                    <select
                        :value="
                            missionSelectionnee.statut
                        "
                        :disabled="
                            updatingMissionId
                                === missionSelectionnee.id
                        "
                        :class="
                            classeStatut(
                                missionSelectionnee
                                    .statut
                            )
                        "
                        class="mt-4 w-full cursor-pointer rounded-lg border-0 px-4 py-3 font-semibold outline-none disabled:cursor-wait disabled:opacity-60"
                        @change="
                            changerStatut(
                                missionSelectionnee,
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

                    <p
                        v-if="
                            updatingMissionId
                                === missionSelectionnee.id
                        "
                        class="mt-2 text-xs text-slate-400"
                    >
                        Enregistrement...
                    </p>
                </div>

                <!-- ================================================= -->
                <!-- DESCRIPTION -->
                <!-- ================================================= -->

                <div
                    class="border-b border-slate-100 py-6"
                >
                    <h3
                        class="font-semibold text-slate-900"
                    >
                        Description
                    </h3>

                    <p
                        class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-600"
                    >
                        {{
                            nettoyerDescription(
                                missionSelectionnee.description
                            )
                        }}
                    </p>
                </div>

                <!-- ================================================= -->
                <!-- SOURCE OCCURRENCES -->
                <!-- ================================================= -->

                <div
                    v-if="
                        missionSelectionnee
                            .source_occurrences
                            ?.length
                    "
                    class="border-b border-slate-100 py-6"
                >
                    <h3
                        class="font-semibold text-slate-900"
                    >
                        Sources d'origine
                    </h3>

                    <p
                        class="mt-1 text-sm text-slate-500"
                    >
                        Plateformes sur lesquelles cette mission a été détectée.
                    </p>

                    <div
                        class="mt-3 space-y-3"
                    >
                        <div
                            v-for="
                                occurrence in
                                missionSelectionnee
                                    .source_occurrences
                            "
                            :key="occurrence.id"
                            class="flex items-center justify-between rounded-lg bg-slate-50 p-3"
                        >
                            <span
                                class="text-sm font-medium text-slate-700"
                            >
                                {{
                                    occurrence.source
                                        ?.nom
                                        ||
                                    'Source'
                                }}
                            </span>

                            <a
                                v-if="
                                    occurrence.url_origine
                                "
                                :href="
                                    occurrence.url_origine
                                "
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm font-semibold text-blue-600 hover:underline"
                            >
                                Open ↗
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ================================================= -->
                <!-- ORIGINAL LINK -->
                <!-- ================================================= -->

                <div class="py-6">

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
                        class="flex w-full items-center justify-center rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white transition hover:bg-slate-700"
                    >
                        Open original mission ↗
                    </a>

                    <p
                        v-else
                        class="text-center text-sm text-slate-400"
                    >
                        Aucun lien original disponible.
                    </p>

                </div>

            </div>

        </aside>

    </main>
</template>