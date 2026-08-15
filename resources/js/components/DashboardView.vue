<script setup>
import {
    computed,
    onMounted,
    ref,
} from 'vue';

import axios from 'axios';

const emit = defineEmits([
    'navigate',
]);

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const loading = ref(true);
const error = ref(null);

const stats = ref({
    missions_total: 0,
    missions_nouvelles: 0,
    missions_aujourdhui: 0,
    missions_7_jours: 0,
    tendance_7_jours: 0,

    sources_total: 0,
    sources_actives: 0,
    sante_sources: 0,

    profils_actifs: 0,

    candidatures_total: 0,

    evolution: [],
    statuts: [],
    missions_par_source: [],
    technologies: [],
    meilleures_missions: [],
    candidatures_recentes: [],
});

/*
|--------------------------------------------------------------------------
| Load
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
            'Erreur dashboard :',
            err
        );

        error.value =
            'Impossible de charger le dashboard.';
    } finally {
        loading.value =
            false;
    }
};

/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

const naviguer = (
    page
) => {
    emit(
        'navigate',
        page
    );
};

/*
|--------------------------------------------------------------------------
| Line chart
|--------------------------------------------------------------------------
*/

const evolutionMax =
    computed(() => {
        const valeurs =
            stats.value
                .evolution
                ?.map(
                    (item) =>
                        Number(
                            item.total
                            ?? 0
                        )
                )
            ?? [];

        return Math.max(
            ...valeurs,
            1
        );
    });

const pointsEvolution =
    computed(() => {
        const data =
            stats.value.evolution
            ?? [];

        if (
            data.length === 0
        ) {
            return [];
        }

        const width = 800;
        const height = 230;

        return data.map(
            (
                item,
                index
            ) => {
                const x =
                    data.length === 1
                        ? width / 2
                        :
                        (
                            index
                            /
                            (
                                data.length
                                - 1
                            )
                        )
                        * width;

                const ratio =
                    Number(
                        item.total
                        ?? 0
                    )
                    /
                    evolutionMax.value;

                const y =
                    height
                    -
                    ratio
                    * 185
                    -
                    20;

                return {
                    x,
                    y,
                    total:
                        Number(
                            item.total
                            ?? 0
                        ),

                    label:
                        item.label,
                };
            }
        );
    });

const ligneEvolution =
    computed(() => {
        const points =
            pointsEvolution.value;

        if (
            points.length === 0
        ) {
            return '';
        }

        return points
            .map(
                (
                    point,
                    index
                ) => {
                    return `${
                        index === 0
                            ? 'M'
                            : 'L'
                    } ${point.x} ${point.y}`;
                }
            )
            .join(' ');
    });

const aireEvolution =
    computed(() => {
        const points =
            pointsEvolution.value;

        if (
            points.length === 0
        ) {
            return '';
        }

        const premier =
            points[0];

        const dernier =
            points[
                points.length - 1
            ];

        return `
            ${ligneEvolution.value}
            L ${dernier.x} 230
            L ${premier.x} 230
            Z
        `;
    });

/*
|--------------------------------------------------------------------------
| Chart helpers
|--------------------------------------------------------------------------
*/

const maxSource =
    computed(() => {
        return Math.max(
            ...(
                stats.value
                    .missions_par_source
                    ?.map(
                        (source) =>
                            Number(
                                source.total
                                ?? 0
                            )
                    )
                ?? []
            ),
            1
        );
    });

const maxTechnologie =
    computed(() => {
        return Math.max(
            ...(
                stats.value
                    .technologies
                    ?.map(
                        (tech) =>
                            Number(
                                tech.total
                                ?? 0
                            )
                    )
                ?? []
            ),
            1
        );
    });

const totalStatuts =
    computed(() => {
        return (
            stats.value
                .statuts
                ?.reduce(
                    (
                        total,
                        statut
                    ) =>
                        total
                        +
                        Number(
                            statut.total
                            ?? 0
                        ),
                    0
                )
            ?? 0
        );
    });

const pourcentageStatut =
    (statut) => {
        if (
            totalStatuts.value === 0
        ) {
            return 0;
        }

        return Math.round(
            (
                Number(
                    statut.total
                    ?? 0
                )
                /
                totalStatuts.value
            )
            * 100
        );
    };

/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

const classeStatut =
    (statut) => {
        const classes = {
            nouveau:
                'status-blue',

            vu:
                'status-slate',

            interessant:
                'status-green',

            postule:
                'status-violet',

            ecarte:
                'status-red',
        };

        return (
            classes[statut]
            ??
            'status-slate'
        );
    };

/*
|--------------------------------------------------------------------------
| Remote
|--------------------------------------------------------------------------
*/

const labelRemote =
    (remote) => {
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
            'Non renseigné'
        );
    };

/*
|--------------------------------------------------------------------------
| Date
|--------------------------------------------------------------------------
*/

const formaterDate =
    (date) => {
        if (!date) {
            return 'Non renseignée';
        }

        return new Intl
            .DateTimeFormat(
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
            )
            .format(
                new Date(date)
            );
    };

const formaterDateComplete =
    (date) => {
        if (!date) {
            return 'Date non renseignée';
        }

        return new Intl
            .DateTimeFormat(
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
            )
            .format(
                new Date(date)
            );
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
    <main
        class="dashboard-page relative min-h-screen overflow-hidden"
    >
        <!-- Background atmosphere -->

        <div
            class="pointer-events-none absolute -left-32 top-16 h-[420px] w-[420px] rounded-full bg-indigo-400/10 blur-3xl"
        ></div>

        <div
            class="pointer-events-none absolute -right-40 top-[500px] h-[520px] w-[520px] rounded-full bg-violet-400/10 blur-3xl"
        ></div>

        <div
            class="relative mx-auto max-w-7xl px-6 py-10 lg:py-12"
        >
            <!-- ===================================================== -->
            <!-- LOADING -->
            <!-- ===================================================== -->

            <div
                v-if="loading"
                class="space-y-6"
            >
                <div
                    class="h-52 animate-pulse rounded-[30px] bg-white shadow-sm"
                ></div>

                <div
                    class="grid gap-5 md:grid-cols-2 xl:grid-cols-4"
                >
                    <div
                        v-for="index in 4"
                        :key="index"
                        class="h-36 animate-pulse rounded-[24px] bg-white shadow-sm"
                    ></div>
                </div>
            </div>

            <!-- ===================================================== -->
            <!-- ERROR -->
            <!-- ===================================================== -->

            <div
                v-else-if="error"
                class="rounded-[24px] border border-rose-200 bg-rose-50 p-6 text-rose-700 shadow-lg"
            >
                <div
                    class="flex items-center gap-3"
                >
                    <span
                        class="text-2xl"
                    >
                        ⚠️
                    </span>

                    <div>
                        <p
                            class="font-bold"
                        >
                            Erreur Dashboard
                        </p>

                        <p
                            class="mt-1 text-sm"
                        >
                            {{ error }}
                        </p>
                    </div>
                </div>
            </div>

            <template v-else>
                <!-- ================================================= -->
                <!-- HERO -->
                <!-- ================================================= -->

                <section
                    class="dashboard-hero relative overflow-hidden rounded-[30px] border border-white/70 bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 p-7 text-white shadow-2xl shadow-indigo-200/40 lg:p-9"
                >
                    <div
                        class="absolute -right-16 -top-24 h-72 w-72 rounded-full bg-indigo-400/20 blur-3xl"
                    ></div>

                    <div
                        class="absolute -bottom-24 left-1/3 h-64 w-64 rounded-full bg-violet-500/10 blur-3xl"
                    ></div>

                    <div
                        class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-indigo-300/60 to-transparent"
                    ></div>

                    <div
                        class="relative flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div
                            class="max-w-2xl"
                        >
                            <div
                                class="mb-5 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.16em] text-indigo-100 backdrop-blur"
                            >
                                <span
                                    class="h-2 w-2 animate-pulse rounded-full bg-emerald-400"
                                ></span>

                                Mission Intelligence Live
                            </div>

                            <h1
                                class="text-3xl font-black tracking-tight sm:text-4xl lg:text-5xl"
                            >
                                Pilotez vos
                                <span
                                    class="hero-gradient-text"
                                >
                                    opportunités
                                </span>
                            </h1>

                            <p
                                class="mt-4 max-w-xl text-sm leading-7 text-slate-300 sm:text-base"
                            >
                                Visualisez la performance de vos sources, identifiez les meilleures missions et suivez vos candidatures depuis une seule interface.
                            </p>

                            <div
                                class="mt-6 flex flex-wrap gap-3"
                            >
                                <button
                                    type="button"
                                    class="hero-primary-button"
                                    @click="
                                        naviguer(
                                            'missions'
                                        )
                                    "
                                >
                                    Explorer les missions

                                    <span>
                                        →
                                    </span>
                                </button>

                                <button
                                    type="button"
                                    class="hero-secondary-button"
                                    @click="
                                        naviguer(
                                            'profils'
                                        )
                                    "
                                >
                                    Gérer les profils
                                </button>
                            </div>
                        </div>

                        <!-- Hero intelligence card -->

                        <div
                            class="hero-intelligence-card"
                        >
                            <div
                                class="flex items-start justify-between"
                            >
                                <div>
                                    <p
                                        class="text-[10px] font-bold uppercase tracking-[0.17em] text-slate-400"
                                    >
                                        Activité 7 jours
                                    </p>

                                    <p
                                        class="mt-2 text-4xl font-black"
                                    >
                                        {{
                                            stats.missions_7_jours
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-slate-400"
                                    >
                                        nouvelles opportunités détectées
                                    </p>
                                </div>

                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/10 text-xl"
                                >
                                    ⚡
                                </div>
                            </div>

                            <div
                                class="mt-6 flex items-center justify-between border-t border-white/10 pt-4"
                            >
                                <span
                                    class="text-xs text-slate-400"
                                >
                                    Tendance
                                </span>

                                <span
                                    class="rounded-full px-3 py-1 text-xs font-bold"
                                    :class="
                                        stats.tendance_7_jours >= 0
                                            ? 'bg-emerald-400/15 text-emerald-300'
                                            : 'bg-rose-400/15 text-rose-300'
                                    "
                                >
                                    {{
                                        stats.tendance_7_jours >= 0
                                            ? '↗'
                                            : '↘'
                                    }}

                                    {{
                                        Math.abs(
                                            stats.tendance_7_jours
                                        )
                                    }}%
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ================================================= -->
                <!-- KPI -->
                <!-- ================================================= -->

                <section
                    class="mt-7 grid gap-5 md:grid-cols-2 xl:grid-cols-4"
                >
                    <!-- Missions -->

                    <article
                        class="kpi-card"
                    >
                        <div
                            class="kpi-icon kpi-indigo"
                        >
                            ◈
                        </div>

                        <div
                            class="mt-5 flex items-end justify-between"
                        >
                            <div>
                                <p
                                    class="kpi-label"
                                >
                                    Missions
                                </p>

                                <p
                                    class="kpi-value"
                                >
                                    {{
                                        stats.missions_total
                                    }}
                                </p>
                            </div>

                            <span
                                class="kpi-mini-badge"
                            >
                                +{{
                                    stats.missions_aujourdhui
                                }}
                                aujourd'hui
                            </span>
                        </div>

                        <p
                            class="mt-3 text-xs text-slate-400"
                        >
                            Opportunités collectées
                        </p>
                    </article>

                    <!-- Nouvelles -->

                    <article
                        class="kpi-card"
                    >
                        <div
                            class="kpi-icon kpi-blue"
                        >
                            ✦
                        </div>

                        <div
                            class="mt-5"
                        >
                            <p
                                class="kpi-label"
                            >
                                Nouvelles
                            </p>

                            <p
                                class="kpi-value"
                            >
                                {{
                                    stats.missions_nouvelles
                                }}
                            </p>
                        </div>

                        <p
                            class="mt-3 text-xs text-slate-400"
                        >
                            Missions encore à qualifier
                        </p>
                    </article>

                    <!-- Candidatures -->

                    <article
                        class="kpi-card"
                    >
                        <div
                            class="kpi-icon kpi-violet"
                        >
                            ◎
                        </div>

                        <div
                            class="mt-5"
                        >
                            <p
                                class="kpi-label"
                            >
                                Candidatures
                            </p>

                            <p
                                class="kpi-value"
                            >
                                {{
                                    stats.candidatures_total
                                }}
                            </p>
                        </div>

                        <p
                            class="mt-3 text-xs text-slate-400"
                        >
                            Opportunités déjà postulées
                        </p>
                    </article>

                    <!-- Sources -->

                    <article
                        class="kpi-card"
                    >
                        <div
                            class="kpi-icon kpi-green"
                        >
                            ◉
                        </div>

                        <div
                            class="mt-5 flex items-end justify-between"
                        >
                            <div>
                                <p
                                    class="kpi-label"
                                >
                                    Sources actives
                                </p>

                                <p
                                    class="kpi-value"
                                >
                                    {{
                                        stats.sources_actives
                                    }}
                                </p>
                            </div>

                            <span
                                class="text-sm font-black text-emerald-600"
                            >
                                {{
                                    stats.sante_sources
                                }}%
                            </span>
                        </div>

                        <div
                            class="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-100"
                        >
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-500 transition-all duration-700"
                                :style="{
                                    width: `${stats.sante_sources}%`,
                                }"
                            ></div>
                        </div>
                    </article>
                </section>

                <!-- ================================================= -->
                <!-- MAIN CHARTS -->
                <!-- ================================================= -->

                <section
                    class="mt-7 grid gap-6 xl:grid-cols-[1.55fr_0.85fr]"
                >
                    <!-- Evolution -->

                    <article
                        class="dashboard-card"
                    >
                        <div
                            class="card-header"
                        >
                            <div>
                                <p
                                    class="eyebrow"
                                >
                                    Activité
                                </p>

                                <h2
                                    class="card-title"
                                >
                                    Évolution des missions
                                </h2>

                                <p
                                    class="card-description"
                                >
                                    Missions publiées sur les 14 derniers jours.
                                </p>
                            </div>

                            <div
                                class="rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-600"
                            >
                                14 jours
                            </div>
                        </div>

                        <div
                            class="mt-7"
                        >
                            <svg
                                viewBox="0 0 800 230"
                                class="h-[260px] w-full overflow-visible"
                                preserveAspectRatio="none"
                            >
                                <defs>
                                    <linearGradient
                                        id="areaGradient"
                                        x1="0"
                                        y1="0"
                                        x2="0"
                                        y2="1"
                                    >
                                        <stop
                                            offset="0%"
                                            stop-color="#6366f1"
                                            stop-opacity="0.22"
                                        />

                                        <stop
                                            offset="100%"
                                            stop-color="#6366f1"
                                            stop-opacity="0"
                                        />
                                    </linearGradient>

                                    <linearGradient
                                        id="lineGradient"
                                        x1="0"
                                        y1="0"
                                        x2="1"
                                        y2="0"
                                    >
                                        <stop
                                            offset="0%"
                                            stop-color="#4f46e5"
                                        />

                                        <stop
                                            offset="50%"
                                            stop-color="#7c3aed"
                                        />

                                        <stop
                                            offset="100%"
                                            stop-color="#2563eb"
                                        />
                                    </linearGradient>
                                </defs>

                                <!-- Grid -->

                                <line
                                    v-for="position in [45, 90, 135, 180, 225]"
                                    :key="position"
                                    x1="0"
                                    :y1="position"
                                    x2="800"
                                    :y2="position"
                                    stroke="#e2e8f0"
                                    stroke-width="1"
                                    stroke-dasharray="4 7"
                                />

                                <!-- Area -->

                                <path
                                    v-if="aireEvolution"
                                    :d="aireEvolution"
                                    fill="url(#areaGradient)"
                                />

                                <!-- Line -->

                                <path
                                    v-if="ligneEvolution"
                                    :d="ligneEvolution"
                                    fill="none"
                                    stroke="url(#lineGradient)"
                                    stroke-width="4"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="chart-line"
                                />

                                <!-- Points -->

                                <g
                                    v-for="point in pointsEvolution"
                                    :key="`${point.x}-${point.label}`"
                                >
                                    <circle
                                        :cx="point.x"
                                        :cy="point.y"
                                        r="7"
                                        fill="white"
                                        stroke="#6366f1"
                                        stroke-width="3"
                                        class="chart-point"
                                    >
                                        <title>
                                            {{ point.label }} — {{ point.total }} mission(s)
                                        </title>
                                    </circle>
                                </g>
                            </svg>

                            <div
                                class="mt-2 grid grid-cols-7 gap-1 text-center"
                            >
                                <span
                                    v-for="
                                        item in stats.evolution.filter(
                                            (_, index) =>
                                                index % 2 === 0
                                        )
                                    "
                                    :key="item.date"
                                    class="text-[10px] font-semibold text-slate-400"
                                >
                                    {{ item.label }}
                                </span>
                            </div>
                        </div>
                    </article>

                    <!-- Status distribution -->

                    <article
                        class="dashboard-card"
                    >
                        <div>
                            <p
                                class="eyebrow"
                            >
                                Pipeline
                            </p>

                            <h2
                                class="card-title"
                            >
                                Répartition des statuts
                            </h2>

                            <p
                                class="card-description"
                            >
                                Où se trouvent vos opportunités.
                            </p>
                        </div>

                        <!-- Ring -->

                        <div
                            class="relative mx-auto mt-8 flex h-44 w-44 items-center justify-center rounded-full"
                            :style="{
                                background:
                                    `conic-gradient(
                                        #3b82f6 0 ${pourcentageStatut(stats.statuts[0] ?? {})}%,
                                        #94a3b8 ${pourcentageStatut(stats.statuts[0] ?? {})}% ${pourcentageStatut(stats.statuts[0] ?? {}) + pourcentageStatut(stats.statuts[1] ?? {})}%,
                                        #10b981 ${pourcentageStatut(stats.statuts[0] ?? {}) + pourcentageStatut(stats.statuts[1] ?? {})}% ${pourcentageStatut(stats.statuts[0] ?? {}) + pourcentageStatut(stats.statuts[1] ?? {}) + pourcentageStatut(stats.statuts[2] ?? {})}%,
                                        #8b5cf6 ${pourcentageStatut(stats.statuts[0] ?? {}) + pourcentageStatut(stats.statuts[1] ?? {}) + pourcentageStatut(stats.statuts[2] ?? {})}% ${pourcentageStatut(stats.statuts[0] ?? {}) + pourcentageStatut(stats.statuts[1] ?? {}) + pourcentageStatut(stats.statuts[2] ?? {}) + pourcentageStatut(stats.statuts[3] ?? {})}%,
                                        #f43f5e ${pourcentageStatut(stats.statuts[0] ?? {}) + pourcentageStatut(stats.statuts[1] ?? {}) + pourcentageStatut(stats.statuts[2] ?? {}) + pourcentageStatut(stats.statuts[3] ?? {})}% 100%
                                    )`,
                            }"
                        >
                            <div
                                class="flex h-[122px] w-[122px] flex-col items-center justify-center rounded-full bg-white shadow-inner"
                            >
                                <strong
                                    class="text-3xl font-black text-slate-900"
                                >
                                    {{
                                        totalStatuts
                                    }}
                                </strong>

                                <span
                                    class="mt-1 text-[10px] font-bold uppercase tracking-wider text-slate-400"
                                >
                                    Missions
                                </span>
                            </div>
                        </div>

                        <div
                            class="mt-7 space-y-3"
                        >
                            <div
                                v-for="
                                    statut in stats.statuts
                                "
                                :key="
                                    statut.statut
                                "
                                class="flex items-center justify-between"
                            >
                                <div
                                    class="flex items-center gap-2.5"
                                >
                                    <span
                                        class="h-2.5 w-2.5 rounded-full"
                                        :class="
                                            classeStatut(
                                                statut.statut
                                            )
                                        "
                                    ></span>

                                    <span
                                        class="text-xs font-semibold text-slate-600"
                                    >
                                        {{
                                            statut.label
                                        }}
                                    </span>
                                </div>

                                <div
                                    class="flex items-center gap-3"
                                >
                                    <span
                                        class="text-xs text-slate-400"
                                    >
                                        {{
                                            pourcentageStatut(
                                                statut
                                            )
                                        }}%
                                    </span>

                                    <strong
                                        class="min-w-6 text-right text-xs text-slate-800"
                                    >
                                        {{
                                            statut.total
                                        }}
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </article>
                </section>

                <!-- ================================================= -->
                <!-- SECONDARY CHARTS -->
                <!-- ================================================= -->

                <section
                    class="mt-7 grid gap-6 lg:grid-cols-2"
                >
                    <!-- Sources -->

                    <article
                        class="dashboard-card"
                    >
                        <div
                            class="card-header"
                        >
                            <div>
                                <p
                                    class="eyebrow"
                                >
                                    Acquisition
                                </p>

                                <h2
                                    class="card-title"
                                >
                                    Missions par source
                                </h2>

                                <p
                                    class="card-description"
                                >
                                    Contribution des différentes plateformes.
                                </p>
                            </div>

                            <button
                                type="button"
                                class="small-action"
                                @click="
                                    naviguer(
                                        'sources'
                                    )
                                "
                            >
                                Voir sources →
                            </button>
                        </div>

                        <div
                            class="mt-7 space-y-5"
                        >
                            <div
                                v-for="
                                    source in stats.missions_par_source
                                "
                                :key="
                                    source.id
                                "
                            >
                                <div
                                    class="mb-2 flex items-center justify-between"
                                >
                                    <div
                                        class="flex items-center gap-2"
                                    >
                                        <span
                                            class="h-2 w-2 rounded-full"
                                            :class="
                                                source.actif
                                                    ? 'bg-emerald-400'
                                                    : 'bg-slate-300'
                                            "
                                        ></span>

                                        <span
                                            class="text-sm font-bold text-slate-700"
                                        >
                                            {{
                                                source.nom
                                            }}
                                        </span>
                                    </div>

                                    <strong
                                        class="text-sm text-slate-900"
                                    >
                                        {{
                                            source.total
                                        }}
                                    </strong>
                                </div>

                                <div
                                    class="h-2 overflow-hidden rounded-full bg-slate-100"
                                >
                                    <div
                                        class="source-bar h-full rounded-full"
                                        :style="{
                                            width:
                                                `${
                                                    (
                                                        source.total
                                                        /
                                                        maxSource
                                                    )
                                                    * 100
                                                }%`,
                                        }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <!-- Technologies -->

                    <article
                        class="dashboard-card"
                    >
                        <div>
                            <p
                                class="eyebrow"
                            >
                                Technologies
                            </p>

                            <h2
                                class="card-title"
                            >
                                Stacks les plus demandées
                            </h2>

                            <p
                                class="card-description"
                            >
                                Technologies les plus présentes dans les missions.
                            </p>
                        </div>

                        <div
                            class="mt-7 space-y-4"
                        >
                            <div
                                v-for="
                                    (
                                        technologie,
                                        index
                                    ) in stats.technologies
                                "
                                :key="
                                    technologie.id
                                "
                                class="tech-row"
                            >
                                <div
                                    class="flex min-w-0 items-center gap-3"
                                >
                                    <span
                                        class="tech-rank"
                                    >
                                        {{
                                            index + 1
                                        }}
                                    </span>

                                    <span
                                        class="truncate text-sm font-bold text-slate-700"
                                    >
                                        {{
                                            technologie.nom
                                        }}
                                    </span>
                                </div>

                                <div
                                    class="flex flex-1 items-center gap-3"
                                >
                                    <div
                                        class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100"
                                    >
                                        <div
                                            class="tech-bar h-full rounded-full"
                                            :style="{
                                                width:
                                                    `${
                                                        (
                                                            technologie.total
                                                            /
                                                            maxTechnologie
                                                        )
                                                        * 100
                                                    }%`,
                                            }"
                                        ></div>
                                    </div>

                                    <strong
                                        class="w-8 text-right text-xs text-slate-600"
                                    >
                                        {{
                                            technologie.total
                                        }}
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </article>
                </section>

                <!-- ================================================= -->
                <!-- TOP OPPORTUNITIES -->
                <!-- ================================================= -->

                <section
                    class="mt-7 dashboard-card"
                >
                    <div
                        class="card-header"
                    >
                        <div>
                            <p
                                class="eyebrow"
                            >
                                Intelligence
                            </p>

                            <h2
                                class="card-title"
                            >
                                🔥 Opportunités à fort potentiel
                            </h2>

                            <p
                                class="card-description"
                            >
                                Missions ayant les meilleurs scores de matching.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="small-action"
                            @click="
                                naviguer(
                                    'missions'
                                )
                            "
                        >
                            Toutes les missions →
                        </button>
                    </div>

                    <div
                        class="mt-6 divide-y divide-slate-100"
                    >
                        <article
                            v-for="
                                mission in stats.meilleures_missions
                            "
                            :key="
                                mission.id
                            "
                            class="opportunity-row group"
                        >
                            <div
                                class="min-w-0 flex-1"
                            >
                                <div
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <span
                                        class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-500"
                                    >
                                        {{
                                            mission.source
                                                ?.nom
                                            ||
                                            'Source'
                                        }}
                                    </span>

                                    <span
                                        class="text-[11px] text-slate-400"
                                    >
                                        {{
                                            formaterDate(
                                                mission.date_publication
                                            )
                                        }}
                                    </span>
                                </div>

                                <h3
                                    class="mt-2 truncate text-sm font-extrabold text-slate-800 transition-colors group-hover:text-indigo-600 sm:text-base"
                                >
                                    {{
                                        mission.titre
                                    }}
                                </h3>

                                <p
                                    class="mt-1 text-xs text-slate-400"
                                >
                                    {{
                                        mission.entreprise
                                        ||
                                        'Entreprise non renseignée'
                                    }}

                                    ·

                                    {{
                                        labelRemote(
                                            mission.remote_type
                                        )
                                    }}
                                </p>
                            </div>

                            <div
                                class="flex items-center gap-3"
                            >
                                <div
                                    class="score-box"
                                >
                                    <span
                                        class="text-[9px] font-bold uppercase tracking-wide text-indigo-300"
                                    >
                                        Score
                                    </span>

                                    <strong
                                        class="block text-lg font-black text-white"
                                    >
                                        {{
                                            mission.meilleur_score
                                            ?? 0
                                        }}
                                    </strong>
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
                                    class="external-button"
                                >
                                    ↗
                                </a>
                            </div>
                        </article>
                    </div>
                </section>

                <!-- ================================================= -->
                <!-- APPLICATIONS / PROFILE -->
                <!-- ================================================= -->

                <section
                    class="mt-7 grid gap-6 xl:grid-cols-[1.35fr_0.65fr]"
                >
                    <!-- Applications -->

                    <article
                        class="dashboard-card"
                    >
                        <div>
                            <p
                                class="eyebrow"
                            >
                                Suivi
                            </p>

                            <h2
                                class="card-title"
                            >
                                Candidatures récentes
                            </h2>

                            <p
                                class="card-description"
                            >
                                Dernières opportunités auxquelles vous avez postulé.
                            </p>
                        </div>

                        <div
                            v-if="
                                !stats.candidatures_recentes
                                    ?.length
                            "
                            class="mt-7 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center"
                        >
                            <div
                                class="text-3xl"
                            >
                                🎯
                            </div>

                            <p
                                class="mt-3 text-sm font-bold text-slate-700"
                            >
                                Aucune candidature récente
                            </p>
                        </div>

                        <div
                            v-else
                            class="mt-5 space-y-2"
                        >
                            <div
                                v-for="
                                    candidature in stats.candidatures_recentes
                                "
                                :key="
                                    candidature.id
                                "
                                class="application-row"
                            >
                                <div
                                    class="flex min-w-0 items-center gap-3"
                                >
                                    <div
                                        class="application-check"
                                    >
                                        ✓
                                    </div>

                                    <div
                                        class="min-w-0"
                                    >
                                        <p
                                            class="truncate text-sm font-bold text-slate-800"
                                        >
                                            {{
                                                candidature.titre
                                            }}
                                        </p>

                                        <p
                                            class="mt-1 truncate text-xs text-slate-400"
                                        >
                                            {{
                                                candidature.entreprise
                                                ||
                                                'Entreprise non renseignée'
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="shrink-0 text-right"
                                >
                                    <p
                                        class="text-[10px] font-bold uppercase tracking-wide text-slate-400"
                                    >
                                        Candidature
                                    </p>

                                    <p
                                        class="mt-1 text-xs font-semibold text-slate-600"
                                    >
                                        {{
                                            formaterDateComplete(
                                                candidature.date_candidature
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>

                    <!-- Profiles -->

                    <article
                        class="profile-spotlight"
                    >
                        <div
                            class="relative"
                        >
                            <div
                                class="profile-icon"
                            >
                                ◎
                            </div>

                            <p
                                class="mt-6 text-[10px] font-bold uppercase tracking-[0.16em] text-indigo-200"
                            >
                                Recherche intelligente
                            </p>

                            <p
                                class="mt-3 text-5xl font-black text-white"
                            >
                                {{
                                    stats.profils_actifs
                                }}
                            </p>

                            <p
                                class="mt-2 text-sm font-semibold text-indigo-100"
                            >
                                profil(s) actif(s)
                            </p>

                            <p
                                class="mt-5 text-sm leading-6 text-indigo-200/80"
                            >
                                Vos profils pilotent automatiquement le filtrage et le scoring des nouvelles missions.
                            </p>

                            <button
                                type="button"
                                class="mt-7 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm font-bold text-white backdrop-blur transition-all duration-300 hover:-translate-y-0.5 hover:bg-white/15"
                                @click="
                                    naviguer(
                                        'profils'
                                    )
                                "
                            >
                                Configurer les profils →
                            </button>
                        </div>
                    </article>
                </section>
            </template>
        </div>
    </main>
</template>

<style scoped>
.dashboard-page {
    background:
        linear-gradient(
            135deg,
            #f8fafc 0%,
            #ffffff 45%,
            #f5f3ff 100%
        );
}

.dashboard-hero {
    animation:
        heroReveal 0.6s
        cubic-bezier(
            0.22,
            1,
            0.36,
            1
        )
        both;
}

.hero-gradient-text {
    background:
        linear-gradient(
            90deg,
            #a5b4fc,
            #c4b5fd,
            #93c5fd
        );
    background-clip: text;
    -webkit-background-clip: text;
    color: transparent;
}

.hero-primary-button {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    border-radius: 0.9rem;
    background: white;
    padding: 0.8rem 1.1rem;
    font-size: 0.8rem;
    font-weight: 800;
    color: #0f172a;
    box-shadow:
        0 12px 30px -18px
        rgba(255, 255, 255, 0.6);
    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease;
}

.hero-primary-button:hover {
    transform: translateY(-2px);
    box-shadow:
        0 18px 35px -18px
        rgba(255, 255, 255, 0.7);
}

.hero-secondary-button {
    border: 1px solid
        rgba(255, 255, 255, 0.12);
    border-radius: 0.9rem;
    background:
        rgba(255, 255, 255, 0.07);
    padding: 0.8rem 1.1rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: #e2e8f0;
    backdrop-filter: blur(12px);
    transition:
        transform 0.25s ease,
        background 0.25s ease;
}

.hero-secondary-button:hover {
    transform: translateY(-2px);
    background:
        rgba(255, 255, 255, 0.11);
}

.hero-intelligence-card {
    width: 100%;
    border: 1px solid
        rgba(255, 255, 255, 0.1);
    border-radius: 1.4rem;
    background:
        rgba(255, 255, 255, 0.07);
    padding: 1.3rem;
    backdrop-filter: blur(18px);
}

@media (min-width: 1024px) {
    .hero-intelligence-card {
        width: 290px;
    }
}

.kpi-card {
    position: relative;
    overflow: hidden;
    border: 1px solid
        rgba(226, 232, 240, 0.8);
    border-radius: 1.35rem;
    background:
        rgba(255, 255, 255, 0.86);
    padding: 1.25rem;
    box-shadow:
        0 14px 36px -28px
        rgba(15, 23, 42, 0.36);
    backdrop-filter: blur(16px);
    transition:
        transform 0.3s ease,
        box-shadow 0.3s ease,
        border-color 0.3s ease;
}

.kpi-card:hover {
    transform: translateY(-4px);
    border-color:
        rgba(129, 140, 248, 0.24);
    box-shadow:
        0 25px 48px -30px
        rgba(79, 70, 229, 0.35);
}

.kpi-icon {
    display: flex;
    height: 2.8rem;
    width: 2.8rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.9rem;
    font-size: 1.2rem;
    font-weight: 900;
}

.kpi-indigo {
    background: #eef2ff;
    color: #4f46e5;
}

.kpi-blue {
    background: #eff6ff;
    color: #2563eb;
}

.kpi-violet {
    background: #f5f3ff;
    color: #7c3aed;
}

.kpi-green {
    background: #ecfdf5;
    color: #059669;
}

.kpi-label {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #94a3b8;
}

.kpi-value {
    margin-top: 0.25rem;
    font-size: 2.15rem;
    font-weight: 900;
    line-height: 1;
    color: #0f172a;
}

.kpi-mini-badge {
    border-radius: 9999px;
    background: #eef2ff;
    padding: 0.35rem 0.55rem;
    font-size: 0.62rem;
    font-weight: 800;
    color: #4f46e5;
}

.dashboard-card {
    border: 1px solid
        rgba(226, 232, 240, 0.82);
    border-radius: 1.5rem;
    background:
        rgba(255, 255, 255, 0.88);
    padding: 1.4rem;
    box-shadow:
        0 18px 46px -35px
        rgba(15, 23, 42, 0.38);
    backdrop-filter: blur(18px);
}

.card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.eyebrow {
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.13em;
    text-transform: uppercase;
    color: #6366f1;
}

.card-title {
    margin-top: 0.35rem;
    font-size: 1.05rem;
    font-weight: 900;
    color: #0f172a;
}

.card-description {
    margin-top: 0.35rem;
    font-size: 0.72rem;
    line-height: 1.4rem;
    color: #94a3b8;
}

.chart-line {
    stroke-dasharray: 1200;
    stroke-dashoffset: 1200;
    animation:
        drawLine 1.3s
        cubic-bezier(
            0.22,
            1,
            0.36,
            1
        )
        forwards;
}

.chart-point {
    transition:
        r 0.2s ease,
        filter 0.2s ease;
}

.chart-point:hover {
    r: 10;
    filter:
        drop-shadow(
            0 5px 5px
            rgba(99, 102, 241, 0.28)
        );
}

.status-blue {
    background: #3b82f6;
}

.status-slate {
    background: #94a3b8;
}

.status-green {
    background: #10b981;
}

.status-violet {
    background: #8b5cf6;
}

.status-red {
    background: #f43f5e;
}

.source-bar {
    background:
        linear-gradient(
            90deg,
            #4f46e5,
            #7c3aed,
            #3b82f6
        );
    animation:
        expandBar 0.8s ease-out both;
}

.tech-row {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.tech-rank {
    display: flex;
    height: 1.8rem;
    width: 1.8rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.6rem;
    background: #f1f5f9;
    font-size: 0.65rem;
    font-weight: 900;
    color: #64748b;
}

.tech-bar {
    background:
        linear-gradient(
            90deg,
            #8b5cf6,
            #6366f1
        );
    animation:
        expandBar 0.9s ease-out both;
}

.small-action {
    flex-shrink: 0;
    border-radius: 0.8rem;
    border: 1px solid #e2e8f0;
    background: white;
    padding: 0.6rem 0.8rem;
    font-size: 0.65rem;
    font-weight: 800;
    color: #64748b;
    transition:
        transform 0.2s ease,
        border-color 0.2s ease,
        color 0.2s ease;
}

.small-action:hover {
    transform: translateY(-2px);
    border-color: #c7d2fe;
    color: #4f46e5;
}

.opportunity-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 0;
}

.score-box {
    min-width: 3.4rem;
    border-radius: 0.9rem;
    background:
        linear-gradient(
            135deg,
            #312e81,
            #6d28d9
        );
    padding: 0.55rem 0.75rem;
    text-align: center;
    box-shadow:
        0 12px 25px -17px
        rgba(79, 70, 229, 0.7);
}

.external-button {
    display: flex;
    height: 2.4rem;
    width: 2.4rem;
    align-items: center;
    justify-content: center;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    background: white;
    font-weight: 900;
    color: #64748b;
    transition:
        transform 0.2s ease,
        color 0.2s ease,
        border-color 0.2s ease;
}

.external-button:hover {
    transform: translateY(-2px);
    border-color: #c7d2fe;
    color: #4f46e5;
}

.application-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-radius: 1rem;
    padding: 0.8rem;
    transition:
        background 0.2s ease;
}

.application-row:hover {
    background: #f8fafc;
}

.application-check {
    display: flex;
    height: 2.4rem;
    width: 2.4rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.8rem;
    background:
        linear-gradient(
            135deg,
            #ede9fe,
            #eef2ff
        );
    font-size: 0.8rem;
    font-weight: 900;
    color: #7c3aed;
}

.profile-spotlight {
    position: relative;
    overflow: hidden;
    border-radius: 1.5rem;
    background:
        linear-gradient(
            145deg,
            #312e81,
            #4338ca 55%,
            #6d28d9
        );
    padding: 1.6rem;
    box-shadow:
        0 25px 50px -28px
        rgba(79, 70, 229, 0.6);
}

.profile-spotlight::after {
    content: '';
    position: absolute;
    right: -80px;
    top: -70px;
    height: 220px;
    width: 220px;
    border-radius: 9999px;
    background:
        rgba(196, 181, 253, 0.18);
    filter: blur(40px);
}

.profile-icon {
    display: flex;
    height: 3rem;
    width: 3rem;
    align-items: center;
    justify-content: center;
    border: 1px solid
        rgba(255, 255, 255, 0.12);
    border-radius: 1rem;
    background:
        rgba(255, 255, 255, 0.1);
    color: white;
    font-size: 1.25rem;
    backdrop-filter: blur(12px);
}

@keyframes heroReveal {
    from {
        opacity: 0;
        transform: translateY(14px)
            scale(0.99);
    }

    to {
        opacity: 1;
        transform: translateY(0)
            scale(1);
    }
}

@keyframes drawLine {
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes expandBar {
    from {
        max-width: 0;
        opacity: 0.35;
    }

    to {
        max-width: 100%;
        opacity: 1;
    }
}

@media (
    prefers-reduced-motion:
    reduce
) {
    .dashboard-hero,
    .chart-line,
    .source-bar,
    .tech-bar {
        animation: none;
    }

    .kpi-card,
    .small-action,
    .external-button {
        transition: none;
    }
}
</style>