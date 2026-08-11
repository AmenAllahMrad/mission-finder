<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const profils = ref([]);
const profilSelectionne = ref(null);

const loading = ref(true);
const saving = ref(false);

const error = ref(null);
const successMessage = ref(null);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const clone = (value) => {
    return JSON.parse(JSON.stringify(value));
};

const scoreMaximum = computed(() => {
    if (!profilSelectionne.value?.regles_scoring) {
        return 0;
    }

    return profilSelectionne.value.regles_scoring.reduce(
        (total, regle) => {
            return total + Number(regle.poids || 0);
        },
        0
    );
});

const nombreFiltres = computed(() => {
    return (
        profilSelectionne.value?.regles_filtrage?.length ?? 0
    );
});

const nombreAlertesActives = computed(() => {
    if (!profilSelectionne.value?.alertes) {
        return 0;
    }

    return profilSelectionne.value.alertes.filter(
        (alerte) => alerte.actif
    ).length;
});

/*
|--------------------------------------------------------------------------
| Load profiles
|--------------------------------------------------------------------------
*/

const chargerProfils = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get('/api/profils');

        /*
         * Axios reçoit directement le tableau JSON Laravel.
         */
        profils.value = response.data;

        if (
            profils.value.length > 0 &&
            !profilSelectionne.value
        ) {
            selectionnerProfil(profils.value[0]);
        }
    } catch (err) {
        console.error(
            'Erreur chargement profils :',
            err
        );

        error.value =
            'Impossible de charger les profils de recherche.';
    } finally {
        loading.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| Select profile
|--------------------------------------------------------------------------
*/

const selectionnerProfil = (profil) => {
    profilSelectionne.value = clone(profil);

    error.value = null;
    successMessage.value = null;
};

/*
|--------------------------------------------------------------------------
| Save profile
|--------------------------------------------------------------------------
*/

const sauvegarderProfil = async () => {
    if (!profilSelectionne.value) {
        return;
    }

    saving.value = true;
    error.value = null;
    successMessage.value = null;

    try {
        /*
         * On envoie uniquement les propriétés autorisées
         * par ProfilRechercheController.
         */
        const payload = {
            nom: profilSelectionne.value.nom,

            actif: Boolean(
                profilSelectionne.value.actif
            ),

            regles_filtrage:
                profilSelectionne.value.regles_filtrage.map(
                    (regle) => ({
                        id: regle.id,
                        operateur: regle.operateur,
                        valeur:
                            regle.valeur === null ||
                            regle.valeur === undefined
                                ? null
                                : String(regle.valeur),
                    })
                ),

            regles_scoring:
                profilSelectionne.value.regles_scoring.map(
                    (regle) => ({
                        id: regle.id,
                        poids: Number(regle.poids),
                    })
                ),

            alertes:
                profilSelectionne.value.alertes.map(
                    (alerte) => ({
                        id: alerte.id,
                        canal: alerte.canal,
                        destination:
                            alerte.destination,
                        frequence:
                            alerte.frequence,
                        seuil_score_min:
                            Number(
                                alerte.seuil_score_min
                            ),
                        actif:
                            Boolean(alerte.actif),
                    })
                ),
        };

        const response = await axios.patch(
            `/api/profils/${profilSelectionne.value.id}`,
            payload
        );

        const profilMisAJour =
            response.data.profil;

        profilSelectionne.value =
            clone(profilMisAJour);

        /*
         * Met à jour aussi la liste située à gauche.
         */
        const index = profils.value.findIndex(
            (profil) =>
                profil.id === profilMisAJour.id
        );

        if (index !== -1) {
            profils.value[index] =
                clone(profilMisAJour);
        }

        successMessage.value =
            'Profil sauvegardé et scores recalculés avec succès.';

        setTimeout(() => {
            successMessage.value = null;
        }, 4000);
    } catch (err) {
        console.error(
            'Erreur sauvegarde profil :',
            err
        );

        if (err.response?.status === 422) {
            const errors =
                err.response.data?.errors;

            if (errors) {
                const premierChamp =
                    Object.keys(errors)[0];

                error.value =
                    errors[premierChamp]?.[0] ??
                    'La configuration du profil est invalide.';
            } else {
                error.value =
                    'La configuration du profil est invalide.';
            }
        } else {
            error.value =
                'Impossible de sauvegarder ce profil.';
        }
    } finally {
        saving.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| Filter helpers
|--------------------------------------------------------------------------
*/

const labelOperateur = (operateur) => {
    const labels = {
        egal: '=',
        contient: 'Contient',
        superieur_egal: '≥',
        inferieur_egal: '≤',
        dans: 'Dans',
    };

    return labels[operateur] ?? operateur;
};

const descriptionCritere = (code) => {
    const descriptions = {
        stack:
            'Technologie ou compétence recherchée.',

        tjm_min:
            'TJM minimum accepté pour la mission.',

        remote:
            'Mode de travail attendu.',

        duree_min:
            'Durée minimum de la mission.',

        localisation:
            'Localisation recherchée.',

        secteur:
            'Secteur d’activité recherché.',
    };

    return (
        descriptions[code] ??
        'Critère du profil de recherche.'
    );
};

const valeurAffichee = (regle) => {
    if (
        regle.critere?.code === 'remote'
    ) {
        const labels = {
            full_remote: 'Full remote',
            hybrid: 'Hybrid',
            onsite: 'On-site',
        };

        return (
            labels[regle.valeur] ??
            regle.valeur
        );
    }

    return regle.valeur;
};

/*
|--------------------------------------------------------------------------
| Alert helpers
|--------------------------------------------------------------------------
*/

const labelFrequence = (frequence) => {
    const labels = {
        immediate: 'Immediate',
        daily: 'Daily Digest',
        weekly: 'Weekly Digest',
    };

    return (
        labels[frequence] ??
        frequence
    );
};

const labelCanal = (canal) => {
    const labels = {
        email: 'Email',
        telegram: 'Telegram',
        webhook: 'Webhook',
    };

    return labels[canal] ?? canal;
};

const classeFrequence = (frequence) => {
    const classes = {
        immediate:
            'bg-blue-100 text-blue-700',

        daily:
            'bg-amber-100 text-amber-700',

        weekly:
            'bg-purple-100 text-purple-700',
    };

    return (
        classes[frequence] ??
        'bg-slate-100 text-slate-700'
    );
};

/*
|--------------------------------------------------------------------------
| Mounted
|--------------------------------------------------------------------------
*/

onMounted(() => {
    chargerProfils();
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
                    Profils
                </h2>

                <p
                    class="mt-2 text-slate-500"
                >
                    Gérez les critères de recherche,
                    le scoring et les alertes MissionFinder.
                </p>
            </div>

            <div
                class="self-start rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white sm:self-auto"
            >
                {{ profils.length }}
                profil(s)
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- SUCCESS -->
        <!-- ========================================================= -->

        <div
            v-if="successMessage"
            class="mb-6 rounded-xl border border-green-200 bg-green-50 px-5 py-4 text-green-700"
        >
            ✓ {{ successMessage }}
        </div>

        <!-- ========================================================= -->
        <!-- ERROR -->
        <!-- ========================================================= -->

        <div
            v-if="error"
            class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-red-700"
        >
            {{ error }}
        </div>

        <!-- ========================================================= -->
        <!-- LOADING -->
        <!-- ========================================================= -->

        <div
            v-if="loading"
            class="rounded-xl border border-slate-200 bg-white p-12 text-center text-slate-500 shadow-sm"
        >
            Chargement des profils...
        </div>

        <!-- ========================================================= -->
        <!-- EMPTY -->
        <!-- ========================================================= -->

        <div
            v-else-if="profils.length === 0"
            class="rounded-xl border border-slate-200 bg-white p-12 text-center shadow-sm"
        >
            <div class="text-4xl">
                🎯
            </div>

            <h3
                class="mt-4 font-semibold text-slate-900"
            >
                Aucun profil
            </h3>

            <p
                class="mt-2 text-sm text-slate-500"
            >
                Aucun profil de recherche
                n'est configuré.
            </p>
        </div>

        <!-- ========================================================= -->
        <!-- PROFILES -->
        <!-- ========================================================= -->

        <div
            v-else
            class="grid gap-6 lg:grid-cols-[280px_1fr]"
        >
            <!-- ===================================================== -->
            <!-- LEFT SIDEBAR -->
            <!-- ===================================================== -->

            <aside>
                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="border-b border-slate-200 px-5 py-4"
                    >
                        <h3
                            class="font-semibold text-slate-900"
                        >
                            Profils de recherche
                        </h3>
                    </div>

                    <button
                        v-for="profil in profils"
                        :key="profil.id"
                        type="button"
                        class="w-full border-b border-slate-100 px-5 py-4 text-left transition last:border-b-0"
                        :class="
                            profilSelectionne?.id
                                === profil.id
                                ? 'bg-slate-900 text-white'
                                : 'bg-white hover:bg-slate-50'
                        "
                        @click="
                            selectionnerProfil(
                                profil
                            )
                        "
                    >
                        <div
                            class="flex items-center justify-between gap-3"
                        >
                            <span
                                class="font-semibold"
                            >
                                {{ profil.nom }}
                            </span>

                            <span
                                v-if="profil.actif"
                                class="h-2.5 w-2.5 rounded-full bg-green-500"
                            ></span>

                            <span
                                v-else
                                class="h-2.5 w-2.5 rounded-full bg-slate-300"
                            ></span>
                        </div>

                        <p
                            class="mt-2 text-xs"
                            :class="
                                profilSelectionne?.id
                                    === profil.id
                                    ? 'text-slate-300'
                                    : 'text-slate-500'
                            "
                        >
                            {{
                                profil.scores_missions_count
                                ?? 0
                            }}
                            mission(s) scorée(s)
                        </p>
                    </button>
                </div>
            </aside>

            <!-- ===================================================== -->
            <!-- PROFILE CONFIGURATION -->
            <!-- ===================================================== -->

            <section
                v-if="profilSelectionne"
                class="space-y-6"
            >
                <!-- ================================================= -->
                <!-- PROFILE HEADER -->
                <!-- ================================================= -->

                <div
                    class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div
                        class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex-1">
                            <label
                                class="block text-xs font-semibold uppercase tracking-wide text-slate-400"
                            >
                                Nom du profil
                            </label>

                            <input
                                v-model="
                                    profilSelectionne.nom
                                "
                                type="text"
                                class="mt-2 w-full max-w-md rounded-lg border border-slate-300 px-4 py-2.5 text-lg font-semibold text-slate-900 outline-none focus:border-slate-500"
                            >
                        </div>

                        <!-- Active -->
                        <div
                            class="flex items-center gap-3"
                        >
                            <div class="text-right">
                                <p
                                    class="text-sm font-semibold text-slate-800"
                                >
                                    Profil actif
                                </p>

                                <p
                                    class="text-xs text-slate-500"
                                >
                                    Utilisé pour le filtrage
                                    et les alertes
                                </p>
                            </div>

                            <button
                                type="button"
                                role="switch"
                                :aria-checked="
                                    profilSelectionne.actif
                                "
                                class="relative inline-flex h-7 w-12 flex-shrink-0 rounded-full transition"
                                :class="
                                    profilSelectionne.actif
                                        ? 'bg-green-500'
                                        : 'bg-slate-300'
                                "
                                @click="
                                    profilSelectionne.actif =
                                        !profilSelectionne.actif
                                "
                            >
                                <span
                                    class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transition"
                                    :class="
                                        profilSelectionne.actif
                                            ? 'translate-x-6 translate-y-1'
                                            : 'translate-x-1 translate-y-1'
                                    "
                                ></span>
                            </button>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div
                        class="mt-6 grid gap-4 sm:grid-cols-3"
                    >
                        <div
                            class="rounded-lg bg-slate-50 p-4"
                        >
                            <p
                                class="text-xs font-semibold uppercase text-slate-400"
                            >
                                Filtres
                            </p>

                            <p
                                class="mt-1 text-2xl font-bold text-slate-900"
                            >
                                {{ nombreFiltres }}
                            </p>
                        </div>

                        <div
                            class="rounded-lg bg-slate-50 p-4"
                        >
                            <p
                                class="text-xs font-semibold uppercase text-slate-400"
                            >
                                Score maximum
                            </p>

                            <p
                                class="mt-1 text-2xl font-bold text-slate-900"
                            >
                                {{ scoreMaximum }}
                            </p>
                        </div>

                        <div
                            class="rounded-lg bg-slate-50 p-4"
                        >
                            <p
                                class="text-xs font-semibold uppercase text-slate-400"
                            >
                                Alertes actives
                            </p>

                            <p
                                class="mt-1 text-2xl font-bold text-slate-900"
                            >
                                {{
                                    nombreAlertesActives
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ================================================= -->
                <!-- FILTER RULES -->
                <!-- ================================================= -->

                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="border-b border-slate-200 px-6 py-5"
                    >
                        <div
                            class="flex items-center gap-3"
                        >
                            <span class="text-xl">
                                🔎
                            </span>

                            <div>
                                <h3
                                    class="text-lg font-semibold text-slate-900"
                                >
                                    Règles de filtrage
                                </h3>

                                <p
                                    class="mt-1 text-sm text-slate-500"
                                >
                                    Toutes les règles doivent
                                    correspondre pour qu'une mission
                                    soit retenue.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="divide-y divide-slate-100"
                    >
                        <div
                            v-for="
                                regle in
                                profilSelectionne.regles_filtrage
                            "
                            :key="regle.id"
                            class="grid gap-4 px-6 py-5 lg:grid-cols-[1.2fr_180px_1fr]"
                        >
                            <!-- Criterion -->
                            <div>
                                <p
                                    class="font-semibold text-slate-900"
                                >
                                    {{
                                        regle.critere?.label
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    {{
                                        descriptionCritere(
                                            regle.critere?.code
                                        )
                                    }}
                                </p>

                                <span
                                    class="mt-2 inline-flex rounded bg-slate-100 px-2 py-1 font-mono text-xs text-slate-600"
                                >
                                    {{
                                        regle.critere?.code
                                    }}
                                </span>
                            </div>

                            <!-- Operator -->
                            <div>
                                <label
                                    class="mb-2 block text-xs font-semibold uppercase text-slate-400"
                                >
                                    Opérateur
                                </label>

                                <select
                                    v-model="
                                        regle.operateur
                                    "
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                                >
                                    <option
                                        value="egal"
                                    >
                                        =
                                    </option>

                                    <option
                                        value="contient"
                                    >
                                        Contient
                                    </option>

                                    <option
                                        value="superieur_egal"
                                    >
                                        ≥
                                    </option>

                                    <option
                                        value="inferieur_egal"
                                    >
                                        ≤
                                    </option>

                                    <option
                                        value="dans"
                                    >
                                        Dans
                                    </option>
                                </select>
                            </div>

                            <!-- Value -->
                            <div>
                                <label
                                    class="mb-2 block text-xs font-semibold uppercase text-slate-400"
                                >
                                    Valeur
                                </label>

                                <!-- Remote -->
                                <select
                                    v-if="
                                        regle.critere?.code
                                            === 'remote'
                                    "
                                    v-model="regle.valeur"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                                >
                                    <option
                                        value="full_remote"
                                    >
                                        Full remote
                                    </option>

                                    <option
                                        value="hybrid"
                                    >
                                        Hybrid
                                    </option>

                                    <option
                                        value="onsite"
                                    >
                                        On-site
                                    </option>
                                </select>

                                <!-- Number -->
                                <input
                                    v-else-if="
                                        regle.critere?.type
                                            === 'nombre'
                                    "
                                    v-model="
                                        regle.valeur
                                    "
                                    type="number"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-500"
                                >

                                <!-- Text -->
                                <input
                                    v-else
                                    v-model="
                                        regle.valeur
                                    "
                                    type="text"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-500"
                                >

                                <p
                                    class="mt-2 text-xs text-slate-400"
                                >
                                    {{
                                        labelOperateur(
                                            regle.operateur
                                        )
                                    }}
                                    {{
                                        valeurAffichee(
                                            regle
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================================================= -->
                <!-- SCORING -->
                <!-- ================================================= -->

                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-200 px-6 py-5"
                    >
                        <div
                            class="flex items-center gap-3"
                        >
                            <span class="text-xl">
                                ⭐
                            </span>

                            <div>
                                <h3
                                    class="text-lg font-semibold text-slate-900"
                                >
                                    Scoring
                                </h3>

                                <p
                                    class="mt-1 text-sm text-slate-500"
                                >
                                    Définissez le poids de chaque
                                    critère.
                                </p>
                            </div>
                        </div>

                        <div
                            class="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
                        >
                            Max {{ scoreMaximum }}
                        </div>
                    </div>

                    <div
                        class="divide-y divide-slate-100"
                    >
                        <div
                            v-for="
                                regle in
                                profilSelectionne.regles_scoring
                            "
                            :key="regle.id"
                            class="flex items-center justify-between gap-5 px-6 py-5"
                        >
                            <div>
                                <p
                                    class="font-semibold text-slate-900"
                                >
                                    {{
                                        regle.critere?.label
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    {{
                                        regle.critere?.code
                                    }}
                                </p>
                            </div>

                            <div
                                class="flex items-center gap-3"
                            >
                                <span
                                    class="text-sm font-medium text-slate-500"
                                >
                                    Poids
                                </span>

                                <input
                                    v-model.number="
                                        regle.poids
                                    "
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="w-20 rounded-lg border border-slate-300 px-3 py-2 text-center font-bold outline-none focus:border-slate-500"
                                >

                                <span
                                    class="text-lg font-bold text-green-600"
                                >
                                    +{{ regle.poids }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================================================= -->
                <!-- ALERTS -->
                <!-- ================================================= -->

                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="border-b border-slate-200 px-6 py-5"
                    >
                        <div
                            class="flex items-center gap-3"
                        >
                            <span class="text-xl">
                                🔔
                            </span>

                            <div>
                                <h3
                                    class="text-lg font-semibold text-slate-900"
                                >
                                    Alertes
                                </h3>

                                <p
                                    class="mt-1 text-sm text-slate-500"
                                >
                                    Configuration des notifications
                                    associées au profil.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="grid gap-5 p-6 xl:grid-cols-3"
                    >
                        <article
                            v-for="
                                alerte in
                                profilSelectionne.alertes
                            "
                            :key="alerte.id"
                            class="rounded-xl border border-slate-200 p-5"
                        >
                            <!-- Alert header -->
                            <div
                                class="flex items-start justify-between gap-3"
                            >
                                <div>
                                    <span
                                        :class="
                                            classeFrequence(
                                                alerte.frequence
                                            )
                                        "
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                    >
                                        {{
                                            labelFrequence(
                                                alerte.frequence
                                            )
                                        }}
                                    </span>

                                    <p
                                        class="mt-3 font-semibold text-slate-900"
                                    >
                                        {{
                                            labelCanal(
                                                alerte.canal
                                            )
                                        }}
                                    </p>
                                </div>

                                <!-- Alert active switch -->
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="
                                        alerte.actif
                                    "
                                    class="relative inline-flex h-7 w-12 rounded-full transition"
                                    :class="
                                        alerte.actif
                                            ? 'bg-green-500'
                                            : 'bg-slate-300'
                                    "
                                    @click="
                                        alerte.actif =
                                            !alerte.actif
                                    "
                                >
                                    <span
                                        class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transition"
                                        :class="
                                            alerte.actif
                                                ? 'translate-x-6 translate-y-1'
                                                : 'translate-x-1 translate-y-1'
                                        "
                                    ></span>
                                </button>
                            </div>

                            <!-- Destination -->
                            <div class="mt-5">
                                <label
                                    class="block text-xs font-semibold uppercase text-slate-400"
                                >
                                    Destination
                                </label>

                                <input
                                    v-model="
                                        alerte.destination
                                    "
                                    :type="
                                        alerte.canal
                                            === 'email'
                                            ? 'email'
                                            : 'text'
                                    "
                                    class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
                                >
                            </div>

                            <!-- Threshold -->
                            <div class="mt-4">
                                <label
                                    class="block text-xs font-semibold uppercase text-slate-400"
                                >
                                    Score minimum
                                </label>

                                <input
                                    v-model.number="
                                        alerte.seuil_score_min
                                    "
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 font-semibold outline-none focus:border-slate-500"
                                >
                            </div>

                            <!-- Status -->
                            <div
                                class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4"
                            >
                                <span
                                    class="text-xs text-slate-500"
                                >
                                    {{
                                        alerte.frequence
                                    }}
                                </span>

                                <span
                                    v-if="alerte.actif"
                                    class="text-xs font-semibold text-green-600"
                                >
                                    ● Active
                                </span>

                                <span
                                    v-else
                                    class="text-xs font-semibold text-slate-400"
                                >
                                    ● Inactive
                                </span>
                            </div>
                        </article>
                    </div>
                </div>

                <!-- ================================================= -->
                <!-- SAVE -->
                <!-- ================================================= -->

                <div
                    class="sticky bottom-4 z-20 rounded-xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p
                                class="font-semibold text-slate-900"
                            >
                                Enregistrer la configuration
                            </p>

                            <p
                                class="mt-1 text-xs text-slate-500"
                            >
                                Les scores des missions seront
                                recalculés après sauvegarde.
                            </p>
                        </div>

                        <button
                            type="button"
                            :disabled="saving"
                            class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-wait disabled:opacity-50"
                            @click="
                                sauvegarderProfil
                            "
                        >
                            <template v-if="saving">
                                Enregistrement...
                            </template>

                            <template v-else>
                                Save profile
                            </template>
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>