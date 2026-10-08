<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const colonnes = [
    {
        key: 'nouveau',
        label: 'Nouveau',
        icon: '✦',
        tone: 'blue',
        description: 'Nouvelles opportunités',
    },
    {
        key: 'vu',
        label: 'Vu',
        icon: '○',
        tone: 'slate',
        description: 'Missions consultées',
    },
    {
        key: 'interessant',
        label: 'Intéressant',
        icon: '★',
        tone: 'emerald',
        description: 'À considérer',
    },
    {
        key: 'postule',
        label: 'Postulé',
        icon: '✓',
        tone: 'violet',
        description: 'Candidatures envoyées',
    },
    {
        key: 'ecarte',
        label: 'Écarté',
        icon: '×',
        tone: 'rose',
        description: 'Missions écartées',
    },
];

const missions = ref([]);
const profils = ref([]);
const sources = ref([]);

const loading = ref(false);
const error = ref(null);
const updatingMissionId = ref(null);
const draggingMissionId = ref(null);
const draggedOverColumn = ref(null);

const search = ref('');
const profilId = ref('');
const sourceId = ref('');
const remote = ref('');
const scoreMin = ref('');
const sort = ref('recent');

const chargerDonnees = async () => {
    loading.value = true;
    error.value = null;

    try {
        const firstResponse = await axios.get('/api/missions', {
            params: {
                page: 1,
                per_page: 100,
                sort: sort.value,
                ...(profilId.value
                    ? { profil_id: profilId.value }
                    : {}),
            },
        });

        const firstPayload = firstResponse.data;

        profils.value = firstPayload.profils ?? [];

        const pages = [firstPayload.data ?? []];

        for (
            let page = 2;
            page <= Number(firstPayload.last_page ?? 1);
            page += 1
        ) {
            const response = await axios.get('/api/missions', {
                params: {
                    page,
                    per_page: 100,
                    sort: sort.value,
                    ...(profilId.value
                        ? { profil_id: profilId.value }
                        : {}),
                },
            });

            pages.push(response.data.data ?? []);
        }

        missions.value = pages.flat();

        const sourceResponse = await axios.get('/api/sources');

        sources.value = Array.isArray(sourceResponse.data)
            ? sourceResponse.data
            : sourceResponse.data.data ?? [];
    } catch (err) {
        console.error(
            'Erreur chargement candidatures :',
            err
        );

        error.value =
            'Impossible de charger le suivi des missions.';
    } finally {
        loading.value = false;
    }
};

const scoreMission = (mission) => {
    const scores = mission?.scores_profils ?? [];

    if (scores.length > 0) {
        if (profilId.value) {
            const score = scores.find(
                (item) =>
                    Number(
                        item.profil_recherche_id
                    ) === Number(profilId.value)
            );

            return score
                ? Number(score.score ?? 0)
                : 0;
        }

        return Math.max(
            ...scores.map(
                (item) =>
                    Number(item.score ?? 0)
            )
        );
    }

    return Number(
        mission?.score ?? 0
    );
};

const missionsFiltrees = computed(() => {
    const query =
        search.value
            .trim()
            .toLowerCase();

    const minimum =
        scoreMin.value === ''
            ? null
            : Number(scoreMin.value);

    return missions.value.filter(
        (mission) => {
            const matchesSearch =
                !query ||
                String(
                    mission.titre ?? ''
                )
                    .toLowerCase()
                    .includes(query) ||
                String(
                    mission.entreprise ?? ''
                )
                    .toLowerCase()
                    .includes(query) ||
                String(
                    mission.localisation ?? ''
                )
                    .toLowerCase()
                    .includes(query);

            const matchesSource =
                !sourceId.value ||
                Number(
                    mission.source_id
                ) === Number(
                    sourceId.value
                );

            const matchesRemote =
                !remote.value ||
                mission.remote_type ===
                    remote.value;

            const matchesScore =
                minimum === null ||
                scoreMission(mission) >=
                    minimum;

            return (
                matchesSearch &&
                matchesSource &&
                matchesRemote &&
                matchesScore
            );
        }
    );
});

const missionsParStatut = computed(() => {
    const result = {};

    for (const colonne of colonnes) {
        result[colonne.key] =
            missionsFiltrees.value.filter(
                (mission) =>
                    mission.statut ===
                    colonne.key
            );
    }

    return result;
});

const totalFiltre = computed(
    () =>
        missionsFiltrees.value.length
);

const totalPostulees = computed(
    () =>
        missions.value.filter(
            (mission) =>
                mission.statut ===
                'postule'
        ).length
);

const totalInteressantes = computed(
    () =>
        missions.value.filter(
            (mission) =>
                mission.statut ===
                'interessant'
        ).length
);

const totalNouvelles = computed(
    () =>
        missions.value.filter(
            (mission) =>
                mission.statut ===
                'nouveau'
        ).length
);

const classeScore = (score) => {
    if (score >= 5) {
        return 'from-emerald-500 to-teal-500';
    }

    if (score >= 3) {
        return 'from-violet-500 to-indigo-500';
    }

    if (score > 0) {
        return 'from-amber-400 to-orange-500';
    }

    return 'from-slate-300 to-slate-400';
};

const labelScore = (score) => {
    if (score >= 5) {
        return 'Excellent';
    }

    if (score >= 3) {
        return 'Bon match';
    }

    if (score > 0) {
        return 'À considérer';
    }

    return 'Non scoré';
};

const labelRemote = (value) => {
    const labels = {
        full_remote: 'Full remote',
        hybrid: 'Hybride',
        onsite: 'Sur site',
    };

    return (
        labels[value] ??
        value ??
        'Non renseigné'
    );
};

const afficherTjm = (mission) => {
    if (
        mission?.tjm_min === null ||
        mission?.tjm_min === undefined
    ) {
        return 'TJM non renseigné';
    }

    if (
        mission.tjm_max !== null &&
        mission.tjm_max !== undefined &&
        Number(
            mission.tjm_max
        ) !== Number(
            mission.tjm_min
        )
    ) {
        return `${mission.tjm_min}–${mission.tjm_max} €/j`;
    }

    return `${mission.tjm_min} €/j`;
};

const formaterDate = (date) => {
    if (!date) {
        return '';
    }

    return new Intl.DateTimeFormat(
        'fr-FR',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }
    ).format(
        new Date(date)
    );
};

const formaterDateRelative = (date) => {
    if (!date) {
        return '';
    }

    const value =
        new Date(date);

    const now =
        new Date();

    const diff =
        Math.round(
            (
                now.getTime() -
                value.getTime()
            ) /
            86400000
        );

    if (diff === 0) {
        return "Aujourd'hui";
    }

    if (diff === 1) {
        return 'Hier';
    }

    if (
        diff > 1 &&
        diff < 7
    ) {
        return `Il y a ${diff} j`;
    }

    return formaterDate(date);
};

const sourceNom = (mission) => {
    if (mission?.source?.nom) {
        return mission.source.nom;
    }

    const source =
        sources.value.find(
            (item) =>
                Number(item.id) ===
                Number(
                    mission.source_id
                )
        );

    return (
        source?.nom ??
        'Source inconnue'
    );
};

const changerStatut = async (
    mission,
    nouveauStatut
) => {
    if (
        !mission ||
        nouveauStatut ===
            mission.statut ||
        updatingMissionId.value ===
            mission.id
    ) {
        return;
    }

    const ancienStatut =
        mission.statut;

    const ancienneDate =
        mission.date_candidature;

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

        mission.statut =
            missionMiseAJour.statut;

        mission.date_candidature =
            missionMiseAJour
                .date_candidature;
    } catch (err) {
        console.error(
            'Erreur modification statut :',
            err
        );

        mission.statut =
            ancienStatut;

        mission.date_candidature =
            ancienneDate;

        window.alert(
            'Impossible de modifier le statut.'
        );
    } finally {
        updatingMissionId.value =
            null;
    }
};

const commencerDrag = (
    mission
) => {
    draggingMissionId.value =
        mission.id;
};

const terminerDrag = () => {
    draggingMissionId.value =
        null;

    draggedOverColumn.value =
        null;
};

const survolerColonne = (
    statut
) => {
    draggedOverColumn.value =
        statut;
};

const deposerMission = async (
    statut
) => {
    const mission =
        missions.value.find(
            (item) =>
                Number(item.id) ===
                Number(
                    draggingMissionId.value
                )
        );

    draggedOverColumn.value =
        null;

    if (!mission) {
        draggingMissionId.value =
            null;

        return;
    }

    await changerStatut(
        mission,
        statut
    );

    draggingMissionId.value =
        null;
};

const reinitialiserFiltres = () => {
    search.value = '';
    profilId.value = '';
    sourceId.value = '';
    remote.value = '';
    scoreMin.value = '';
};

const profilChange = async () => {
    await chargerDonnees();
};

onMounted(
    chargerDonnees
);
</script>

<template>
    <section
        class="min-h-screen bg-slate-50"
    >
        <!-- Hero -->
        <div
            class="relative overflow-hidden border-b border-white/70 bg-gradient-to-br from-slate-950 via-indigo-950 to-violet-950"
        >
            <div
                class="absolute -left-20 -top-32 h-72 w-72 rounded-full bg-violet-500/20 blur-3xl"
            ></div>

            <div
                class="absolute right-0 top-0 h-80 w-80 rounded-full bg-indigo-400/20 blur-3xl"
            ></div>

            <div
                class="relative mx-auto max-w-[1800px] px-5 py-8 lg:px-8"
            >
                <div
                    class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between"
                >
                    <div>
                        <div
                            class="mb-3 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.2em] text-indigo-100 backdrop-blur"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-emerald-400"
                            ></span>

                            Application Intelligence
                        </div>

                        <h1
                            class="text-3xl font-black tracking-tight text-white md:text-4xl"
                        >
                            Suivi des candidatures
                        </h1>

                        <p
                            class="mt-2 max-w-2xl text-sm leading-6 text-indigo-100/70"
                        >
                            Visualisez chaque opportunité
                            de la découverte à la candidature.
                            Déplacez les missions entre les statuts
                            pour garder votre pipeline à jour.
                        </p>
                    </div>

                    <div
                        class="grid grid-cols-3 gap-3 sm:min-w-[420px]"
                    >
                        <div
                            class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur"
                        >
                            <span
                                class="text-[10px] font-black uppercase tracking-wider text-indigo-200/70"
                            >
                                Nouvelles
                            </span>

                            <strong
                                class="mt-1 block text-2xl font-black text-white"
                            >
                                {{ totalNouvelles }}
                            </strong>
                        </div>

                        <div
                            class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur"
                        >
                            <span
                                class="text-[10px] font-black uppercase tracking-wider text-indigo-200/70"
                            >
                                Intéressantes
                            </span>

                            <strong
                                class="mt-1 block text-2xl font-black text-white"
                            >
                                {{ totalInteressantes }}
                            </strong>
                        </div>

                        <div
                            class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur"
                        >
                            <span
                                class="text-[10px] font-black uppercase tracking-wider text-indigo-200/70"
                            >
                                Postulées
                            </span>

                            <strong
                                class="mt-1 block text-2xl font-black text-white"
                            >
                                {{ totalPostulees }}
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div
            class="mx-auto max-w-[1800px] px-5 py-6 lg:px-8"
        >
            <div
                class="rounded-3xl border border-slate-200/80 bg-white/90 p-4 shadow-sm backdrop-blur md:p-5"
            >
                <div
                    class="grid gap-3 md:grid-cols-2 xl:grid-cols-6"
                >
                    <div
                        class="xl:col-span-2"
                    >
                        <label
                            class="mb-1.5 block text-[10px] font-black uppercase tracking-wider text-slate-500"
                        >
                            Recherche
                        </label>

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Mission, entreprise, localisation..."
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-indigo-300 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                        >
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-[10px] font-black uppercase tracking-wider text-slate-500"
                        >
                            Profil
                        </label>

                        <select
                            v-model="profilId"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none transition focus:border-indigo-300 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                            @change="profilChange"
                        >
                            <option value="">
                                Tous les profils
                            </option>

                            <option
                                v-for="profil in profils"
                                :key="profil.id"
                                :value="profil.id"
                            >
                                {{ profil.nom }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-[10px] font-black uppercase tracking-wider text-slate-500"
                        >
                            Source
                        </label>

                        <select
                            v-model="sourceId"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none transition focus:border-indigo-300 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                        >
                            <option value="">
                                Toutes
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

                    <div>
                        <label
                            class="mb-1.5 block text-[10px] font-black uppercase tracking-wider text-slate-500"
                        >
                            Remote
                        </label>

                        <select
                            v-model="remote"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none transition focus:border-indigo-300 focus:bg-white focus:ring-4 focus:ring-indigo-100"
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

                    <div>
                        <label
                            class="mb-1.5 block text-[10px] font-black uppercase tracking-wider text-slate-500"
                        >
                            Score minimum
                        </label>

                        <input
                            v-model="scoreMin"
                            type="number"
                            min="0"
                            placeholder="0"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none transition focus:border-indigo-300 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                        >
                    </div>
                </div>

                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4"
                >
                    <div
                        class="text-xs font-semibold text-slate-500"
                    >
                        <span
                            class="font-black text-slate-900"
                        >
                            {{ totalFiltre }}
                        </span>

                        mission{{ totalFiltre > 1 ? 's' : '' }}
                        affichée{{ totalFiltre > 1 ? 's' : '' }}
                    </div>

                    <button
                        type="button"
                        class="rounded-xl px-3 py-2 text-xs font-black text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                        @click="reinitialiserFiltres"
                    >
                        Réinitialiser les filtres
                    </button>
                </div>
            </div>

            <!-- Loading -->
            <div
                v-if="loading"
                class="mt-6 rounded-3xl border border-slate-200 bg-white p-12 text-center shadow-sm"
            >
                <div
                    class="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-indigo-100 border-t-indigo-600"
                ></div>

                <p
                    class="mt-4 text-sm font-semibold text-slate-500"
                >
                    Chargement du pipeline...
                </p>
            </div>

            <!-- Error -->
            <div
                v-else-if="error"
                class="mt-6 rounded-3xl border border-rose-200 bg-rose-50 p-6 text-sm font-semibold text-rose-700"
            >
                {{ error }}
            </div>

            <!-- Kanban -->
            <div
                v-else
                class="mt-6 overflow-x-auto pb-5"
            >
                <div
                    class="grid min-w-[1450px] grid-cols-5 gap-4"
                >
                    <section
                        v-for="colonne in colonnes"
                        :key="colonne.key"
                        class="min-h-[520px] rounded-3xl border bg-slate-100/70 p-3 transition-all"
                        :class="[
                            draggedOverColumn === colonne.key
                                ? 'border-indigo-300 bg-indigo-50/80 shadow-lg shadow-indigo-100'
                                : 'border-slate-200/80'
                        ]"
                        @dragover.prevent="
                            survolerColonne(colonne.key)
                        "
                        @drop.prevent="
                            deposerMission(colonne.key)
                        "
                    >
                        <div
                            class="mb-3 flex items-center justify-between rounded-2xl bg-white/80 p-3 shadow-sm"
                        >
                            <div
                                class="flex min-w-0 items-center gap-2.5"
                            >
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-black"
                                    :class="{
                                        'bg-blue-100 text-blue-700':
                                            colonne.tone === 'blue',
                                        'bg-slate-100 text-slate-700':
                                            colonne.tone === 'slate',
                                        'bg-emerald-100 text-emerald-700':
                                            colonne.tone === 'emerald',
                                        'bg-violet-100 text-violet-700':
                                            colonne.tone === 'violet',
                                        'bg-rose-100 text-rose-700':
                                            colonne.tone === 'rose',
                                    }"
                                >
                                    {{ colonne.icon }}
                                </span>

                                <div
                                    class="min-w-0"
                                >
                                    <h2
                                        class="truncate text-sm font-black text-slate-900"
                                    >
                                        {{ colonne.label }}
                                    </h2>

                                    <p
                                        class="truncate text-[10px] font-semibold text-slate-400"
                                    >
                                        {{ colonne.description }}
                                    </p>
                                </div>
                            </div>

                            <span
                                class="rounded-full bg-slate-900 px-2.5 py-1 text-[10px] font-black text-white"
                            >
                                {{
                                    missionsParStatut[
                                        colonne.key
                                    ]?.length ?? 0
                                }}
                            </span>
                        </div>

                        <div
                            class="space-y-3"
                        >
                            <article
                                v-for="mission in missionsParStatut[colonne.key]"
                                :key="mission.id"
                                draggable="true"
                                class="group cursor-grab rounded-2xl border border-white bg-white p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-lg active:cursor-grabbing"
                                :class="{
                                    'opacity-50':
                                        updatingMissionId ===
                                        mission.id,
                                }"
                                @dragstart="
                                    commencerDrag(mission)
                                "
                                @dragend="
                                    terminerDrag
                                "
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div
                                        class="min-w-0"
                                    >
                                        <p
                                            class="line-clamp-2 text-sm font-black leading-5 text-slate-900"
                                        >
                                            {{ mission.titre }}
                                        </p>

                                        <p
                                            class="mt-1 truncate text-xs font-semibold text-slate-500"
                                        >
                                            {{
                                                mission.entreprise ||
                                                'Entreprise non renseignée'
                                            }}
                                        </p>
                                    </div>

                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-xs font-black text-white shadow-md"
                                        :class="
                                            classeScore(
                                                scoreMission(
                                                    mission
                                                )
                                            )
                                        "
                                    >
                                        {{
                                            scoreMission(
                                                mission
                                            )
                                        }}
                                    </div>
                                </div>

                                <div
                                    class="mt-3 flex flex-wrap gap-1.5"
                                >
                                    <span
                                        class="rounded-full bg-slate-100 px-2 py-1 text-[9px] font-black text-slate-600"
                                    >
                                        {{
                                            afficherTjm(
                                                mission
                                            )
                                        }}
                                    </span>

                                    <span
                                        class="rounded-full bg-indigo-50 px-2 py-1 text-[9px] font-black text-indigo-700"
                                    >
                                        {{
                                            labelRemote(
                                                mission.remote_type
                                            )
                                        }}
                                    </span>

                                    <span
                                        class="rounded-full bg-slate-100 px-2 py-1 text-[9px] font-black text-slate-500"
                                    >
                                        {{
                                            sourceNom(
                                                mission
                                            )
                                        }}
                                    </span>
                                </div>

                                <div
                                    class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3"
                                >
                                    <div
                                        class="min-w-0"
                                    >
                                        <p
                                            class="truncate text-[10px] font-black text-slate-500"
                                        >
                                            {{
                                                labelScore(
                                                    scoreMission(
                                                        mission
                                                    )
                                                )
                                            }}
                                        </p>

                                        <p
                                            v-if="
                                                mission.statut ===
                                                    'postule' &&
                                                mission.date_candidature
                                            "
                                            class="mt-1 text-[9px] font-semibold text-violet-600"
                                        >
                                            Candidaté le
                                            {{
                                                formaterDate(
                                                    mission.date_candidature
                                                )
                                            }}
                                        </p>

                                        <p
                                            v-else
                                            class="mt-1 text-[9px] font-semibold text-slate-400"
                                        >
                                            {{
                                                formaterDateRelative(
                                                    mission.date_publication
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <a
                                        v-if="
                                            mission.url_origine
                                        "
                                        :href="
                                            mission.url_origine
                                        "
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="rounded-lg bg-slate-900 px-2.5 py-1.5 text-[9px] font-black text-white transition hover:bg-indigo-700"
                                        @click.stop
                                    >
                                        Offre ↗
                                    </a>
                                </div>

                                <div
                                    class="mt-3"
                                >
                                    <select
                                        :value="
                                            mission.statut
                                        "
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-[10px] font-black text-slate-700 outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                                        @click.stop
                                        @change="
                                            changerStatut(
                                                mission,
                                                $event.target.value
                                            )
                                        "
                                    >
                                        <option
                                            v-for="option in colonnes"
                                            :key="option.key"
                                            :value="option.key"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>
                            </article>

                            <div
                                v-if="
                                    missionsParStatut[
                                        colonne.key
                                    ]?.length === 0
                                "
                                class="rounded-2xl border border-dashed border-slate-300 bg-white/50 p-8 text-center"
                            >
                                <div
                                    class="text-2xl text-slate-300"
                                >
                                    {{ colonne.icon }}
                                </div>

                                <p
                                    class="mt-2 text-xs font-bold text-slate-400"
                                >
                                    Aucune mission
                                </p>

                                <p
                                    class="mt-1 text-[10px] text-slate-400"
                                >
                                    Déposez une mission ici
                                </p>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        scroll-behavior: auto !important;
        transition-duration: 0.01ms !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
    }
}
</style>
