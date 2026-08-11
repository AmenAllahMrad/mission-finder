<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';

import MissionsView from './components/MissionsView.vue';
import SourcesView from './components/SourcesView.vue';
import ProfilsView from './components/ProfilsView.vue';

const page = ref('dashboard');

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

const chargerDashboard = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response =
            await axios.get('/api/dashboard');

        stats.value = response.data;
    } catch (err) {
        console.error(
            'Erreur dashboard :',
            err
        );

        error.value =
            'Impossible de charger le dashboard.';
    } finally {
        loading.value = false;
    }
};

const formaterDateCandidature = (date) => {
    if (!date) {
        return 'Date non renseignée';
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

const ouvrirDashboard = () => {
    page.value = 'dashboard';
    chargerDashboard();
};

onMounted(() => {
    chargerDashboard();
});
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <!-- Header -->
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

        <!-- Navigation -->
        <nav
            class="border-b border-slate-200 bg-white"
        >
            <div
                class="mx-auto flex max-w-7xl gap-2 overflow-x-auto px-6"
            >
                <button
                    type="button"
                    class="whitespace-nowrap border-b-2 px-4 py-4 text-sm font-semibold transition"
                    :class="
                        page === 'dashboard'
                            ? 'border-slate-900 text-slate-900'
                            : 'border-transparent text-slate-500 hover:text-slate-900'
                    "
                    @click="ouvrirDashboard"
                >
                    Dashboard
                </button>

                <button
                    type="button"
                    class="whitespace-nowrap border-b-2 px-4 py-4 text-sm font-semibold transition"
                    :class="
                        page === 'missions'
                            ? 'border-slate-900 text-slate-900'
                            : 'border-transparent text-slate-500 hover:text-slate-900'
                    "
                    @click="page = 'missions'"
                >
                    Missions
                </button>

                <button
                    type="button"
                    class="whitespace-nowrap border-b-2 px-4 py-4 text-sm font-semibold transition"
                    :class="
                        page === 'sources'
                            ? 'border-slate-900 text-slate-900'
                            : 'border-transparent text-slate-500 hover:text-slate-900'
                    "
                    @click="page = 'sources'"
                >
                    Sources
                </button>

                <button
                    type="button"
                    class="whitespace-nowrap border-b-2 px-4 py-4 text-sm font-semibold transition"
                    :class="
                        page === 'profils'
                            ? 'border-slate-900 text-slate-900'
                            : 'border-transparent text-slate-500 hover:text-slate-900'
                    "
                    @click="page = 'profils'"
                >
                    Profils
                </button>
            </div>
        </nav>

        <!-- Dashboard -->
        <main
            v-if="page === 'dashboard'"
            class="mx-auto max-w-7xl px-6 py-10"
        >
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

            <div
                v-if="loading"
                class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
            >
                Chargement des données...
            </div>

            <div
                v-else-if="error"
                class="rounded-xl border border-red-200 bg-red-50 p-6 text-red-700"
            >
                {{ error }}
            </div>

            <template v-else>
                <!-- Statistics -->
                <div
                    class="grid gap-5 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
                >
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
                            {{ stats.missions_total }}
                        </p>

                        <p
                            class="mt-2 text-xs text-slate-400"
                        >
                            Total collecté
                        </p>
                    </div>

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
                            {{ stats.sources_total }}
                        </p>

                        <p
                            class="mt-2 text-xs text-slate-400"
                        >
                            {{ stats.sources_actives }}
                            active(s)
                        </p>
                    </div>

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
                            {{ stats.profils_actifs }}
                        </p>

                        <p
                            class="mt-2 text-xs text-slate-400"
                        >
                            Profils de recherche
                        </p>
                    </div>

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

                <!-- Recent applications -->
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
                                Dernières missions auxquelles
                                vous avez postulé.
                            </p>
                        </div>

                        <span
                            class="rounded-full bg-purple-100 px-3 py-1 text-xs font-semibold text-purple-700"
                        >
                            {{
                                stats.candidatures_total
                            }}
                            candidature(s)
                        </span>
                    </div>

                    <div
                        v-if="
                            !stats.candidatures_recentes ||
                            stats.candidatures_recentes.length === 0
                        "
                        class="px-6 py-12 text-center text-slate-500"
                    >
                        Aucune candidature récente.
                    </div>

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
                                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    Open ↗
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick actions -->
                <div
                    class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-4"
                >
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div class="text-2xl">
                            📋
                        </div>

                        <h3
                            class="mt-3 text-lg font-semibold text-slate-900"
                        >
                            Missions
                        </h3>

                        <p
                            class="mt-2 text-sm text-slate-500"
                        >
                            Consultez et suivez les
                            opportunités collectées.
                        </p>

                        <button
                            type="button"
                            class="mt-5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
                            @click="page = 'missions'"
                        >
                            Voir les missions →
                        </button>
                    </div>

                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div class="text-2xl">
                            🌐
                        </div>

                        <h3
                            class="mt-3 text-lg font-semibold text-slate-900"
                        >
                            Sources
                        </h3>

                        <p
                            class="mt-2 text-sm text-slate-500"
                        >
                            Gérez la collecte RemoteOK
                            et We Work Remotely.
                        </p>

                        <button
                            type="button"
                            class="mt-5 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700"
                            @click="page = 'sources'"
                        >
                            Gérer les sources →
                        </button>
                    </div>

                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div class="text-2xl">
                            🎯
                        </div>

                        <h3
                            class="mt-3 text-lg font-semibold text-slate-900"
                        >
                            Profils
                        </h3>

                        <p
                            class="mt-2 text-sm text-slate-500"
                        >
                            Configurez filtres, scoring
                            et alertes.
                        </p>

                        <button
                            type="button"
                            class="mt-5 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700"
                            @click="page = 'profils'"
                        >
                            Gérer les profils →
                        </button>
                    </div>

                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div
                            class="flex items-start justify-between"
                        >
                            <div>
                                <div class="text-2xl">
                                    ⚙️
                                </div>

                                <h3
                                    class="mt-3 text-lg font-semibold text-slate-900"
                                >
                                    Backend
                                </h3>

                                <p
                                    class="mt-2 text-sm text-slate-500"
                                >
                                    Laravel, Vue et MySQL
                                    fonctionnent correctement.
                                </p>
                            </div>

                            <span
                                class="rounded-lg bg-green-50 px-3 py-2 text-xs font-semibold text-green-700"
                            >
                                Connected ✓
                            </span>
                        </div>
                    </div>
                </div>
            </template>
        </main>

        <MissionsView
            v-else-if="page === 'missions'"
        />

        <SourcesView
            v-else-if="page === 'sources'"
        />

        <ProfilsView
            v-else-if="page === 'profils'"
        />
    </div>
</template>