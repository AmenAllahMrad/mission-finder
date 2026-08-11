<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';

import MissionsView from './components/MissionsView.vue';
import SourcesView from './components/SourcesView.vue';

/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

const page = ref('dashboard');

/*
|--------------------------------------------------------------------------
| Dashboard data
|--------------------------------------------------------------------------
*/

const stats = ref({
    missions_total: 0,
    missions_nouvelles: 0,

    sources_total: 0,
    sources_actives: 0,

    profils_actifs: 0,

    candidatures_total: 0,
    candidatures_recentes: [],
});

const loading = ref(true);
const error = ref(null);

/*
|--------------------------------------------------------------------------
| Load dashboard
|--------------------------------------------------------------------------
*/

const chargerDashboard = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response =
            await axios.get(
                '/api/dashboard'
            );

        stats.value =
            response.data;
    } catch (err) {
        console.error(
            'Erreur chargement dashboard :',
            err
        );

        error.value =
            'Impossible de charger les données du dashboard.';
    } finally {
        loading.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| Date
|--------------------------------------------------------------------------
*/

const formaterDateCandidature = (
    date
) => {
    if (!date) {
        return (
            'Date non renseignée'
        );
    }

    return new Intl.DateTimeFormat(
        'fr-FR',
        {
            timeZone:
                'Africa/Tunis',

            day: '2-digit',
            month: '2-digit',
            year: 'numeric',

            hour: '2-digit',
            minute: '2-digit',
        }
    ).format(
        new Date(date)
    );
};

/*
|--------------------------------------------------------------------------
| Navigation actions
|--------------------------------------------------------------------------
*/

const ouvrirDashboard = () => {
    page.value =
        'dashboard';

    /*
     * Recharge les statistiques afin
     * de refléter les changements
     * effectués dans Missions/Sources.
     */
    chargerDashboard();
};

const ouvrirMissions = () => {
    page.value =
        'missions';
};

const ouvrirSources = () => {
    page.value =
        'sources';
};

/*
|--------------------------------------------------------------------------
| Mounted
|--------------------------------------------------------------------------
*/

onMounted(() => {
    chargerDashboard();
});
</script>

<template>
    <div
        class="min-h-screen bg-slate-50"
    >

        <!-- ========================================================= -->
        <!-- HEADER -->
        <!-- ========================================================= -->

        <header
            class="border-b border-slate-200 bg-white"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4"
            >
                <div>
                    <h1
                        class="text-2xl font-bold text-slate-900"
                    >
                        MissionFinder
                    </h1>

                    <p
                        class="text-sm text-slate-500"
                    >
                        Freelance Mission Intelligence Platform
                    </p>
                </div>

                <div
                    class="rounded-full bg-green-100 px-4 py-2 text-sm font-medium text-green-700"
                >
                    ● System operational
                </div>
            </div>
        </header>

        <!-- ========================================================= -->
        <!-- NAVIGATION -->
        <!-- ========================================================= -->

        <nav
            class="border-b border-slate-200 bg-white"
        >
            <div
                class="mx-auto flex max-w-7xl gap-2 px-6"
            >

                <!-- Dashboard -->
                <button
                    type="button"
                    class="border-b-2 px-4 py-4 text-sm font-semibold transition"
                    :class="
                        page === 'dashboard'
                            ? 'border-slate-900 text-slate-900'
                            : 'border-transparent text-slate-500 hover:text-slate-900'
                    "
                    @click="
                        ouvrirDashboard
                    "
                >
                    Dashboard
                </button>

                <!-- Missions -->
                <button
                    type="button"
                    class="border-b-2 px-4 py-4 text-sm font-semibold transition"
                    :class="
                        page === 'missions'
                            ? 'border-slate-900 text-slate-900'
                            : 'border-transparent text-slate-500 hover:text-slate-900'
                    "
                    @click="
                        ouvrirMissions
                    "
                >
                    Missions
                </button>

                <!-- Sources -->
                <button
                    type="button"
                    class="border-b-2 px-4 py-4 text-sm font-semibold transition"
                    :class="
                        page === 'sources'
                            ? 'border-slate-900 text-slate-900'
                            : 'border-transparent text-slate-500 hover:text-slate-900'
                    "
                    @click="
                        ouvrirSources
                    "
                >
                    Sources
                </button>

            </div>
        </nav>

        <!-- ========================================================= -->
        <!-- DASHBOARD -->
        <!-- ========================================================= -->

        <main
            v-if="
                page === 'dashboard'
            "
            class="mx-auto max-w-7xl px-6 py-10"
        >

            <!-- Header -->
            <div class="mb-8">
                <h2
                    class="text-3xl font-bold text-slate-900"
                >
                    Dashboard
                </h2>

                <p
                    class="mt-2 text-slate-500"
                >
                    Vue d'ensemble de MissionFinder.
                </p>
            </div>

            <!-- Loading -->
            <div
                v-if="loading"
                class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
            >
                Chargement des données...
            </div>

            <!-- Error -->
            <div
                v-else-if="error"
                class="rounded-xl border border-red-200 bg-red-50 p-6 text-red-700"
            >
                {{ error }}
            </div>

            <!-- Dashboard -->
            <template v-else>

                <!-- Stats -->
                <div
                    class="grid gap-5 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
                >

                    <!-- Missions -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div
                            class="flex items-center justify-between"
                        >
                            <p
                                class="text-sm font-medium text-slate-500"
                            >
                                Missions
                            </p>

                            <span class="text-xl">
                                📋
                            </span>
                        </div>

                        <p
                            class="mt-3 text-3xl font-bold text-slate-900"
                        >
                            {{
                                stats.missions_total
                            }}
                        </p>

                        <p
                            class="mt-2 text-xs text-slate-400"
                        >
                            Total collecté
                        </p>
                    </div>

                    <!-- New -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div
                            class="flex items-center justify-between"
                        >
                            <p
                                class="text-sm font-medium text-slate-500"
                            >
                                New missions
                            </p>

                            <span class="text-xl">
                                ✨
                            </span>
                        </div>

                        <p
                            class="mt-3 text-3xl font-bold text-slate-900"
                        >
                            {{
                                stats.missions_nouvelles
                            }}
                        </p>

                        <p
                            class="mt-2 text-xs text-slate-400"
                        >
                            Statut nouveau
                        </p>
                    </div>

                    <!-- Sources -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div
                            class="flex items-center justify-between"
                        >
                            <p
                                class="text-sm font-medium text-slate-500"
                            >
                                Sources
                            </p>

                            <span class="text-xl">
                                🌐
                            </span>
                        </div>

                        <p
                            class="mt-3 text-3xl font-bold text-slate-900"
                        >
                            {{
                                stats.sources_total
                            }}
                        </p>

                        <p
                            class="mt-2 text-xs text-slate-400"
                        >
                            {{
                                stats.sources_actives
                            }}
                            active(s)
                        </p>
                    </div>

                    <!-- Profiles -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div
                            class="flex items-center justify-between"
                        >
                            <p
                                class="text-sm font-medium text-slate-500"
                            >
                                Active profiles
                            </p>

                            <span class="text-xl">
                                🎯
                            </span>
                        </div>

                        <p
                            class="mt-3 text-3xl font-bold text-slate-900"
                        >
                            {{
                                stats.profils_actifs
                            }}
                        </p>

                        <p
                            class="mt-2 text-xs text-slate-400"
                        >
                            Profils de recherche
                        </p>
                    </div>

                    <!-- Applications -->
                    <div
                        class="rounded-xl border border-purple-200 bg-white p-6 shadow-sm"
                    >
                        <div
                            class="flex items-center justify-between"
                        >
                            <p
                                class="text-sm font-medium text-purple-700"
                            >
                                Candidatures
                            </p>

                            <span class="text-xl">
                                🚀
                            </span>
                        </div>

                        <p
                            class="mt-3 text-3xl font-bold text-slate-900"
                        >
                            {{
                                stats.candidatures_total
                            }}
                        </p>

                        <p
                            class="mt-2 text-xs text-slate-400"
                        >
                            Missions postulées
                        </p>
                    </div>

                </div>

                <!-- ================================================= -->
                <!-- RECENT APPLICATIONS -->
                <!-- ================================================= -->

                <div
                    class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-200 px-6 py-5"
                    >
                        <div>
                            <h3
                                class="text-xl font-semibold text-slate-900"
                            >
                                Recent applications
                            </h3>

                            <p
                                class="mt-1 text-sm text-slate-500"
                            >
                                Dernières missions
                                auxquelles vous avez postulé.
                            </p>
                        </div>

                        <div
                            class="rounded-full bg-purple-100 px-3 py-1 text-xs font-semibold text-purple-700"
                        >
                            {{
                                stats.candidatures_total
                            }}
                            candidature(s)
                        </div>
                    </div>

                    <!-- No applications -->
                    <div
                        v-if="
                            !stats.candidatures_recentes ||
                            stats.candidatures_recentes.length === 0
                        "
                        class="px-6 py-12 text-center"
                    >
                        <div class="text-3xl">
                            📭
                        </div>

                        <p
                            class="mt-3 font-medium text-slate-700"
                        >
                            Aucune candidature
                        </p>

                        <p
                            class="mt-1 text-sm text-slate-500"
                        >
                            Les missions marquées
                            comme "Postulé"
                            apparaîtront ici.
                        </p>
                    </div>

                    <!-- Applications -->
                    <div v-else>
                        <div
                            v-for="
                                application in
                                stats.candidatures_recentes
                            "
                            :key="application.id"
                            class="flex flex-col gap-4 border-b border-slate-100 px-6 py-5 last:border-b-0 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div
                                class="flex items-center gap-3"
                            >
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-purple-100 text-purple-700"
                                >
                                    ✓
                                </div>

                                <div>
                                    <h4
                                        class="font-semibold text-slate-900"
                                    >
                                        {{
                                            application.titre
                                        }}
                                    </h4>

                                    <p
                                        class="mt-1 text-sm text-slate-500"
                                    >
                                        {{
                                            application.entreprise
                                                ||
                                            'Entreprise non renseignée'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="flex items-center gap-5"
                            >
                                <div
                                    class="text-right"
                                >
                                    <p
                                        class="text-xs uppercase tracking-wide text-slate-400"
                                    >
                                        Candidature
                                    </p>

                                    <p
                                        class="mt-1 text-sm font-medium text-slate-700"
                                    >
                                        {{
                                            formaterDateCandidature(
                                                application.date_candidature
                                            )
                                        }}
                                    </p>
                                </div>

                                <a
                                    v-if="
                                        application.url_origine
                                    "
                                    :href="
                                        application.url_origine
                                    "
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                                >
                                    Open ↗
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================================================= -->
                <!-- QUICK ACTIONS -->
                <!-- ================================================= -->

                <div
                    class="mt-8 grid gap-6 md:grid-cols-3"
                >

                    <!-- Missions -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <h3
                            class="text-lg font-semibold text-slate-900"
                        >
                            Mission collection
                        </h3>

                        <p
                            class="mt-2 text-sm leading-6 text-slate-500"
                        >
                            Consultez les opportunités,
                            leurs scores et leur statut.
                        </p>

                        <button
                            type="button"
                            class="mt-5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                            @click="
                                ouvrirMissions
                            "
                        >
                            Voir les missions →
                        </button>
                    </div>

                    <!-- Sources -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <h3
                            class="text-lg font-semibold text-slate-900"
                        >
                            Sources
                        </h3>

                        <p
                            class="mt-2 text-sm leading-6 text-slate-500"
                        >
                            {{
                                stats.sources_actives
                            }}
                            source(s) active(s)
                            sur
                            {{
                                stats.sources_total
                            }}.
                        </p>

                        <button
                            type="button"
                            class="mt-5 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="
                                ouvrirSources
                            "
                        >
                            Gérer les sources →
                        </button>
                    </div>

                    <!-- Backend -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div
                            class="flex items-start justify-between gap-4"
                        >
                            <div>
                                <h3
                                    class="text-lg font-semibold text-slate-900"
                                >
                                    Backend
                                </h3>

                                <p
                                    class="mt-2 text-sm leading-6 text-slate-500"
                                >
                                    Laravel, Vue et MySQL
                                    communiquent correctement.
                                </p>
                            </div>

                            <span
                                class="whitespace-nowrap rounded-lg bg-green-50 px-3 py-2 text-xs font-semibold text-green-700"
                            >
                                Connected ✓
                            </span>
                        </div>
                    </div>

                </div>

            </template>
        </main>

        <!-- ========================================================= -->
        <!-- MISSIONS -->
        <!-- ========================================================= -->

        <MissionsView
            v-else-if="
                page === 'missions'
            "
        />

        <!-- ========================================================= -->
        <!-- SOURCES -->
        <!-- ========================================================= -->

        <SourcesView
            v-else-if="
                page === 'sources'
            "
        />

    </div>
</template>