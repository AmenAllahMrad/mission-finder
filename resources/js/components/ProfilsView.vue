<script setup>
import {
    computed,
    onMounted,
    ref,
} from 'vue';

import axios from 'axios';

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const profils = ref([]);
const criteres = ref([]);

const profilSelectionne = ref(null);

const loading = ref(true);
const saving = ref(false);
const creating = ref(false);
const deleting = ref(false);

const error = ref(null);
const successMessage = ref(null);

/*
|--------------------------------------------------------------------------
| New profile modal
|--------------------------------------------------------------------------
*/

const modalCreationOuvert = ref(false);

const nouveauProfil = ref({
    nom: '',
    actif: true,
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const clone = (value) => {
    return JSON.parse(
        JSON.stringify(value)
    );
};

const critereParId = (id) => {
    return criteres.value.find(
        (critere) =>
            Number(critere.id) === Number(id)
    ) ?? null;
};

const hydrateRegleCritere = (regle) => {
    regle.critere =
        critereParId(
            regle.critere_id
        );
};

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const scoreMaximum = computed(() => {
    if (
        !profilSelectionne.value
            ?.regles_scoring
    ) {
        return 0;
    }

    return profilSelectionne.value
        .regles_scoring
        .reduce(
            (total, regle) =>
                total +
                Number(
                    regle.poids || 0
                ),
            0
        );
});

const nombreFiltres = computed(() => {
    return (
        profilSelectionne.value
            ?.regles_filtrage
            ?.length ?? 0
    );
});

const nombreAlertesActives =
    computed(() => {
        if (
            !profilSelectionne.value
                ?.alertes
        ) {
            return 0;
        }

        return profilSelectionne.value
            .alertes
            .filter(
                (alerte) =>
                    alerte.actif
            )
            .length;
    });

/*
|--------------------------------------------------------------------------
| Load data
|--------------------------------------------------------------------------
*/

const chargerCriteres = async () => {
    const response =
        await axios.get(
            '/api/criteres'
        );

    criteres.value =
        response.data;
};

const chargerProfils = async () => {
    const response =
        await axios.get(
            '/api/profils'
        );

    profils.value =
        response.data;

    /*
     * Conserve le profil sélectionné
     * si possible.
     */
    if (
        profilSelectionne.value
    ) {
        const profilRecharge =
            profils.value.find(
                (profil) =>
                    profil.id ===
                    profilSelectionne
                        .value.id
            );

        if (profilRecharge) {
            selectionnerProfil(
                profilRecharge
            );

            return;
        }
    }

    if (
        profils.value.length > 0
    ) {
        selectionnerProfil(
            profils.value[0]
        );
    } else {
        profilSelectionne.value =
            null;
    }
};

const chargerDonnees = async () => {
    loading.value = true;
    error.value = null;

    try {
        await Promise.all([
            chargerCriteres(),
            chargerProfils(),
        ]);
    } catch (err) {
        console.error(
            'Erreur chargement profils :',
            err
        );

        error.value =
            'Impossible de charger les profils.';
    } finally {
        loading.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| Select profile
|--------------------------------------------------------------------------
*/

const selectionnerProfil = (
    profil
) => {
    profilSelectionne.value =
        clone(profil);

    error.value = null;
    successMessage.value = null;
};

/*
|--------------------------------------------------------------------------
| Create profile
|--------------------------------------------------------------------------
*/

const ouvrirCreationProfil =
    () => {
        nouveauProfil.value = {
            nom: '',
            actif: true,
        };

        modalCreationOuvert.value =
            true;

        error.value = null;
    };

const fermerCreationProfil =
    () => {
        modalCreationOuvert.value =
            false;
    };

const creerProfil = async () => {
    const nom =
        nouveauProfil.value.nom
            .trim();

    if (!nom) {
        error.value =
            'Le nom du profil est obligatoire.';

        return;
    }

    creating.value = true;
    error.value = null;

    try {
        const response =
            await axios.post(
                '/api/profils',
                {
                    nom,
                    actif:
                        Boolean(
                            nouveauProfil
                                .value
                                .actif
                        ),
                }
            );

        const profilCree =
            response.data.profil;

        profils.value.push(
            profilCree
        );

        profils.value.sort(
            (a, b) =>
                a.nom.localeCompare(
                    b.nom
                )
        );

        selectionnerProfil(
            profilCree
        );

        modalCreationOuvert.value =
            false;

        successMessage.value =
            'Profil créé avec succès. Vous pouvez maintenant ajouter ses filtres, son scoring et ses alertes.';
    } catch (err) {
        console.error(
            'Erreur création profil :',
            err
        );

        if (
            err.response?.status ===
            422
        ) {
            const errors =
                err.response.data
                    ?.errors;

            if (errors?.nom?.[0]) {
                error.value =
                    errors.nom[0];
            } else {
                error.value =
                    'Impossible de créer le profil.';
            }
        } else {
            error.value =
                'Impossible de créer le profil.';
        }
    } finally {
        creating.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| Delete profile
|--------------------------------------------------------------------------
*/

const supprimerProfil =
    async () => {
        if (
            !profilSelectionne.value
        ) {
            return;
        }

        const confirmation =
            window.confirm(
                `Supprimer définitivement le profil "${profilSelectionne.value.nom}" ?\n\nSes filtres, scores et alertes seront également supprimés.`
            );

        if (!confirmation) {
            return;
        }

        deleting.value = true;
        error.value = null;

        const profilId =
            profilSelectionne.value.id;

        try {
            await axios.delete(
                `/api/profils/${profilId}`
            );

            profils.value =
                profils.value.filter(
                    (profil) =>
                        profil.id !==
                        profilId
                );

            if (
                profils.value.length >
                0
            ) {
                selectionnerProfil(
                    profils.value[0]
                );
            } else {
                profilSelectionne.value =
                    null;
            }

            successMessage.value =
                'Profil supprimé avec succès.';
        } catch (err) {
            console.error(
                'Erreur suppression profil :',
                err
            );

            error.value =
                'Impossible de supprimer ce profil.';
        } finally {
            deleting.value = false;
        }
    };

/*
|--------------------------------------------------------------------------
| Filter rules
|--------------------------------------------------------------------------
*/

const critereDejaDansFiltres = (
    critereId
) => {
    return profilSelectionne.value
        .regles_filtrage
        .some(
            (regle) =>
                Number(
                    regle.critere_id
                ) ===
                Number(
                    critereId
                )
        );
};

const criteresFiltresDisponibles =
    () => {
        return criteres.value.filter(
            (critere) =>
                !critereDejaDansFiltres(
                    critere.id
                )
        );
    };

const operateurParDefaut = (
    critere
) => {
    if (
        critere?.type ===
        'nombre'
    ) {
        return 'superieur_egal';
    }

    if (
        critere?.code ===
        'remote'
    ) {
        return 'egal';
    }

    return 'contient';
};

const valeurParDefaut = (
    critere
) => {
    if (
        critere?.code ===
        'remote'
    ) {
        return 'full_remote';
    }

    return '';
};

const ajouterFiltre = () => {
    if (
        !profilSelectionne.value
    ) {
        return;
    }

    const disponibles =
        criteresFiltresDisponibles();

    if (
        disponibles.length === 0
    ) {
        window.alert(
            'Tous les critères disponibles sont déjà utilisés dans les filtres.'
        );

        return;
    }

    const critere =
        disponibles[0];

    profilSelectionne.value
        .regles_filtrage
        .push({
            id: null,

            profil_recherche_id:
                profilSelectionne
                    .value.id,

            critere_id:
                critere.id,

            critere:
                clone(critere),

            operateur:
                operateurParDefaut(
                    critere
                ),

            valeur:
                valeurParDefaut(
                    critere
                ),
        });
};

const changerCritereFiltre = (
    regle
) => {
    const critere =
        critereParId(
            regle.critere_id
        );

    regle.critere =
        critere
            ? clone(critere)
            : null;

    regle.operateur =
        operateurParDefaut(
            critere
        );

    regle.valeur =
        valeurParDefaut(
            critere
        );
};

const supprimerFiltre = (
    index
) => {
    const regle =
        profilSelectionne.value
            .regles_filtrage[
                index
            ];

    const critereId =
        Number(
            regle.critere_id
        );

    /*
     * Le scoring dépend d'une règle
     * de filtrage du même critère.
     * Si le filtre disparaît, on retire
     * également le scoring correspondant.
     */
    profilSelectionne.value
        .regles_scoring =
        profilSelectionne.value
            .regles_scoring
            .filter(
                (scoring) =>
                    Number(
                        scoring
                            .critere_id
                    ) !==
                    critereId
            );

    profilSelectionne.value
        .regles_filtrage
        .splice(
            index,
            1
        );
};

/*
|--------------------------------------------------------------------------
| Scoring rules
|--------------------------------------------------------------------------
*/

const criteresScoringDisponibles =
    () => {
        const idsScoring =
            profilSelectionne.value
                .regles_scoring
                .map(
                    (regle) =>
                        Number(
                            regle
                                .critere_id
                        )
                );

        /*
         * Le moteur de scoring actuel
         * évalue un critère grâce à sa
         * règle de filtrage correspondante.
         *
         * On propose donc uniquement
         * les critères déjà présents
         * dans les filtres.
         */
        return profilSelectionne.value
            .regles_filtrage
            .map(
                (filtre) =>
                    filtre.critere ??
                    critereParId(
                        filtre
                            .critere_id
                    )
            )
            .filter(Boolean)
            .filter(
                (critere) =>
                    !idsScoring.includes(
                        Number(
                            critere.id
                        )
                    )
            );
    };

const ajouterScoring = () => {
    const disponibles =
        criteresScoringDisponibles();

    if (
        disponibles.length === 0
    ) {
        window.alert(
            'Ajoutez d’abord un filtre qui ne possède pas encore de règle de scoring.'
        );

        return;
    }

    const critere =
        disponibles[0];

    profilSelectionne.value
        .regles_scoring
        .push({
            id: null,

            profil_recherche_id:
                profilSelectionne
                    .value.id,

            critere_id:
                critere.id,

            critere:
                clone(critere),

            poids: 1,
        });
};

const changerCritereScoring = (
    regle
) => {
    const critere =
        critereParId(
            regle.critere_id
        );

    regle.critere =
        critere
            ? clone(critere)
            : null;
};

const supprimerScoring = (
    index
) => {
    profilSelectionne.value
        .regles_scoring
        .splice(
            index,
            1
        );
};

/*
|--------------------------------------------------------------------------
| Alerts
|--------------------------------------------------------------------------
*/

const ajouterAlerte = () => {
    profilSelectionne.value
        .alertes
        .push({
            id: null,

            profil_recherche_id:
                profilSelectionne
                    .value.id,

            canal:
                'email',

            destination:
                '',

            frequence:
                'immediate',

            seuil_score_min:
                scoreMaximum.value,

            actif:
                true,
        });
};

const supprimerAlerte = (
    index
) => {
    profilSelectionne.value
        .alertes
        .splice(
            index,
            1
        );
};

/*
|--------------------------------------------------------------------------
| Save profile
|--------------------------------------------------------------------------
*/

const sauvegarderProfil =
    async () => {
        if (
            !profilSelectionne.value
        ) {
            return;
        }

        saving.value = true;
        error.value = null;
        successMessage.value = null;

        try {
            const payload = {
                /*
                 * Profil
                 */
                nom:
                    profilSelectionne
                        .value.nom,

                actif:
                    Boolean(
                        profilSelectionne
                            .value.actif
                    ),

                /*
                 * Filtres
                 */
                regles_filtrage:
                    profilSelectionne
                        .value
                        .regles_filtrage
                        .map(
                            (regle) => ({
                                id:
                                    regle.id ??
                                    null,

                                critere_id:
                                    Number(
                                        regle
                                            .critere_id
                                    ),

                                operateur:
                                    regle
                                        .operateur,

                                valeur:
                                    regle
                                        .valeur ===
                                        null ||
                                    regle
                                        .valeur ===
                                        undefined
                                        ? null
                                        : String(
                                              regle
                                                  .valeur
                                          ),
                            })
                        ),

                /*
                 * Scoring
                 */
                regles_scoring:
                    profilSelectionne
                        .value
                        .regles_scoring
                        .map(
                            (regle) => ({
                                id:
                                    regle.id ??
                                    null,

                                critere_id:
                                    Number(
                                        regle
                                            .critere_id
                                    ),

                                poids:
                                    Number(
                                        regle
                                            .poids
                                    ),
                            })
                        ),

                /*
                 * Alertes
                 */
                alertes:
                    profilSelectionne
                        .value
                        .alertes
                        .map(
                            (alerte) => ({
                                id:
                                    alerte.id ??
                                    null,

                                canal:
                                    alerte
                                        .canal,

                                destination:
                                    alerte
                                        .destination,

                                frequence:
                                    alerte
                                        .frequence,

                                seuil_score_min:
                                    Number(
                                        alerte
                                            .seuil_score_min
                                    ),

                                actif:
                                    Boolean(
                                        alerte
                                            .actif
                                    ),
                            })
                        ),
            };

            const response =
                await axios.patch(
                    `/api/profils/${profilSelectionne.value.id}`,
                    payload
                );

            const profilMisAJour =
                response.data
                    .profil;

            profilSelectionne.value =
                clone(
                    profilMisAJour
                );

            const index =
                profils.value.findIndex(
                    (profil) =>
                        profil.id ===
                        profilMisAJour.id
                );

            if (index !== -1) {
                profils.value[index] =
                    clone(
                        profilMisAJour
                    );
            }

            successMessage.value =
                'Profil sauvegardé et scores recalculés avec succès.';

            setTimeout(() => {
                successMessage.value =
                    null;
            }, 4000);
        } catch (err) {
            console.error(
                'Erreur sauvegarde profil :',
                err
            );

            if (
                err.response?.status ===
                422
            ) {
                const errors =
                    err.response.data
                        ?.errors;

                if (
                    errors &&
                    Object.keys(errors)
                        .length > 0
                ) {
                    const premierChamp =
                        Object.keys(
                            errors
                        )[0];

                    error.value =
                        errors[
                            premierChamp
                        ]?.[0] ??
                        'Configuration invalide.';
                } else {
                    error.value =
                        err.response.data
                            ?.message ??
                        'Configuration invalide.';
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
| Labels
|--------------------------------------------------------------------------
*/

const labelOperateur = (
    operateur
) => {
    const labels = {
        egal: '=',
        contient: 'Contient',
        superieur_egal: '≥',
        inferieur_egal: '≤',
        dans: 'Dans',
    };

    return (
        labels[operateur] ??
        operateur
    );
};

const descriptionCritere = (
    code
) => {
    const descriptions = {
        stack:
            'Technologie ou compétence recherchée.',

        tjm_min:
            'TJM minimum accepté.',

        remote:
            'Mode de travail recherché.',

        duree_min:
            'Durée minimum de la mission.',

        localisation:
            'Localisation recherchée.',

        secteur:
            'Secteur recherché.',
    };

    return (
        descriptions[code] ??
        'Critère de recherche.'
    );
};

const labelFrequence = (
    frequence
) => {
    const labels = {
        immediate:
            'Immediate',

        daily:
            'Daily Digest',

        weekly:
            'Weekly Digest',
    };

    return (
        labels[frequence] ??
        frequence
    );
};

const classeFrequence = (
    frequence
) => {
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
    chargerDonnees();
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
                    Gérez les profils de recherche,
                    leurs filtres, scoring et alertes.
                </p>
            </div>

            <div
                class="flex items-center gap-3"
            >
                <div
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
                >
                    {{ profils.length }}
                    profil(s)
                </div>

                <button
                    type="button"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                    @click="
                        ouvrirCreationProfil
                    "
                >
                    + Nouveau profil
                </button>
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
        <!-- NO PROFILE -->
        <!-- ========================================================= -->

        <div
            v-else-if="
                profils.length === 0
            "
            class="rounded-xl border border-slate-200 bg-white p-12 text-center shadow-sm"
        >
            <div class="text-5xl">
                🎯
            </div>

            <h3
                class="mt-4 text-xl font-semibold text-slate-900"
            >
                Aucun profil de recherche
            </h3>

            <p
                class="mt-2 text-slate-500"
            >
                Créez votre premier profil
                pour commencer à filtrer
                et scorer les missions.
            </p>

            <button
                type="button"
                class="mt-6 rounded-lg bg-slate-900 px-5 py-3 font-semibold text-white"
                @click="
                    ouvrirCreationProfil
                "
            >
                + Créer un profil
            </button>
        </div>

        <!-- ========================================================= -->
        <!-- CRUD -->
        <!-- ========================================================= -->

        <div
            v-else
            class="grid gap-6 lg:grid-cols-[280px_1fr]"
        >
            <!-- ===================================================== -->
            <!-- PROFILES LIST -->
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
                        v-for="
                            profil in profils
                        "
                        :key="profil.id"
                        type="button"
                        class="w-full border-b border-slate-100 px-5 py-4 text-left transition last:border-b-0"
                        :class="
                            profilSelectionne?.id
                                === profil.id
                                ? 'bg-slate-900 text-white'
                                : 'hover:bg-slate-50'
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
                                class="h-2.5 w-2.5 rounded-full"
                                :class="
                                    profil.actif
                                        ? 'bg-green-500'
                                        : 'bg-slate-300'
                                "
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
            <!-- PROFILE -->
            <!-- ===================================================== -->

            <section
                v-if="
                    profilSelectionne
                "
                class="space-y-6"
            >
                <!-- ================================================= -->
                <!-- GENERAL -->
                <!-- ================================================= -->

                <div
                    class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div
                        class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="flex-1">
                            <label
                                class="text-xs font-semibold uppercase tracking-wide text-slate-400"
                            >
                                Nom
                            </label>

                            <input
                                v-model="
                                    profilSelectionne.nom
                                "
                                type="text"
                                class="mt-2 w-full max-w-md rounded-lg border border-slate-300 px-4 py-2.5 text-lg font-semibold outline-none focus:border-slate-500"
                            >
                        </div>

                        <div
                            class="flex items-center gap-4"
                        >
                            <div
                                class="text-right"
                            >
                                <p
                                    class="text-sm font-semibold text-slate-800"
                                >
                                    Profil actif
                                </p>

                                <p
                                    class="text-xs text-slate-500"
                                >
                                    Filtrage et alertes
                                </p>
                            </div>

                            <button
                                type="button"
                                role="switch"
                                class="relative inline-flex h-7 w-12 rounded-full transition"
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

                    <!-- Stats -->
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
                                class="mt-1 text-2xl font-bold"
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
                                class="mt-1 text-2xl font-bold"
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
                                class="mt-1 text-2xl font-bold"
                            >
                                {{
                                    nombreAlertesActives
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ================================================= -->
                <!-- FILTERS -->
                <!-- ================================================= -->

                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-200 px-6 py-5"
                    >
                        <div>
                            <h3
                                class="text-lg font-semibold text-slate-900"
                            >
                                🔎 Règles de filtrage
                            </h3>

                            <p
                                class="mt-1 text-sm text-slate-500"
                            >
                                Toutes les règles
                                utilisent une logique AND.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold hover:bg-slate-50"
                            @click="
                                ajouterFiltre
                            "
                        >
                            + Ajouter un filtre
                        </button>
                    </div>

                    <div
                        v-if="
                            profilSelectionne
                                .regles_filtrage
                                .length === 0
                        "
                        class="px-6 py-10 text-center text-sm text-slate-500"
                    >
                        Aucun filtre configuré.
                    </div>

                    <div
                        v-else
                        class="divide-y divide-slate-100"
                    >
                        <div
                            v-for="
                                (
                                    regle,
                                    index
                                ) in
                                profilSelectionne.regles_filtrage
                            "
                            :key="
                                regle.id ??
                                `new-filter-${index}`
                            "
                            class="grid gap-4 px-6 py-5 xl:grid-cols-[1.2fr_170px_1fr_auto]"
                        >
                            <!-- Criterion -->
                            <div>
                                <label
                                    class="mb-2 block text-xs font-semibold uppercase text-slate-400"
                                >
                                    Critère
                                </label>

                                <select
                                    v-model.number="
                                        regle.critere_id
                                    "
                                    :disabled="
                                        Boolean(
                                            regle.id
                                        )
                                    "
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 disabled:bg-slate-100"
                                    @change="
                                        changerCritereFiltre(
                                            regle
                                        )
                                    "
                                >
                                    <option
                                        v-for="
                                            critere in criteres
                                        "
                                        :key="
                                            critere.id
                                        "
                                        :value="
                                            critere.id
                                        "
                                        :disabled="
                                            critereDejaDansFiltres(
                                                critere.id
                                            ) &&
                                            Number(
                                                regle.critere_id
                                            ) !==
                                                Number(
                                                    critere.id
                                                )
                                        "
                                    >
                                        {{
                                            critere.label
                                        }}
                                    </option>
                                </select>

                                <p
                                    class="mt-2 text-xs text-slate-500"
                                >
                                    {{
                                        descriptionCritere(
                                            regle.critere
                                                ?.code
                                        )
                                    }}
                                </p>
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

                                <p
                                    class="mt-2 text-xs text-slate-400"
                                >
                                    {{
                                        labelOperateur(
                                            regle.operateur
                                        )
                                    }}
                                </p>
                            </div>

                            <!-- Value -->
                            <div>
                                <label
                                    class="mb-2 block text-xs font-semibold uppercase text-slate-400"
                                >
                                    Valeur
                                </label>

                                <select
                                    v-if="
                                        regle.critere
                                            ?.code ===
                                        'remote'
                                    "
                                    v-model="
                                        regle.valeur
                                    "
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

                                <input
                                    v-else-if="
                                        regle.critere
                                            ?.type ===
                                        'nombre'
                                    "
                                    v-model="
                                        regle.valeur
                                    "
                                    type="number"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2"
                                >

                                <input
                                    v-else
                                    v-model="
                                        regle.valeur
                                    "
                                    type="text"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2"
                                >
                            </div>

                            <!-- Delete -->
                            <div
                                class="flex items-end"
                            >
                                <button
                                    type="button"
                                    class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"
                                    @click="
                                        supprimerFiltre(
                                            index
                                        )
                                    "
                                >
                                    🗑
                                </button>
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
                        <div>
                            <h3
                                class="text-lg font-semibold"
                            >
                                ⭐ Scoring
                            </h3>

                            <p
                                class="mt-1 text-sm text-slate-500"
                            >
                                Score maximum :
                                {{ scoreMaximum }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold hover:bg-slate-50"
                            @click="
                                ajouterScoring
                            "
                        >
                            + Ajouter un scoring
                        </button>
                    </div>

                    <div
                        v-if="
                            profilSelectionne
                                .regles_scoring
                                .length === 0
                        "
                        class="px-6 py-10 text-center text-sm text-slate-500"
                    >
                        Aucun scoring configuré.
                    </div>

                    <div
                        v-else
                        class="divide-y divide-slate-100"
                    >
                        <div
                            v-for="
                                (
                                    regle,
                                    index
                                ) in
                                profilSelectionne.regles_scoring
                            "
                            :key="
                                regle.id ??
                                `new-score-${index}`
                            "
                            class="grid gap-4 px-6 py-5 sm:grid-cols-[1fr_150px_auto]"
                        >
                            <div>
                                <p
                                    class="font-semibold text-slate-900"
                                >
                                    {{
                                        regle.critere
                                            ?.label
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    {{
                                        regle.critere
                                            ?.code
                                    }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-2 block text-xs font-semibold uppercase text-slate-400"
                                >
                                    Poids
                                </label>

                                <input
                                    v-model.number="
                                        regle.poids
                                    "
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 font-bold"
                                >
                            </div>

                            <div
                                class="flex items-end"
                            >
                                <button
                                    type="button"
                                    class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"
                                    @click="
                                        supprimerScoring(
                                            index
                                        )
                                    "
                                >
                                    🗑
                                </button>
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
                        class="flex items-center justify-between border-b border-slate-200 px-6 py-5"
                    >
                        <div>
                            <h3
                                class="text-lg font-semibold"
                            >
                                🔔 Alertes
                            </h3>

                            <p
                                class="mt-1 text-sm text-slate-500"
                            >
                                Email, Telegram ou Webhook.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold hover:bg-slate-50"
                            @click="
                                ajouterAlerte
                            "
                        >
                            + Ajouter une alerte
                        </button>
                    </div>

                    <div
                        v-if="
                            profilSelectionne
                                .alertes
                                .length === 0
                        "
                        class="px-6 py-10 text-center text-sm text-slate-500"
                    >
                        Aucune alerte configurée.
                    </div>

                    <div
                        v-else
                        class="grid gap-5 p-6 xl:grid-cols-2"
                    >
                        <article
                            v-for="
                                (
                                    alerte,
                                    index
                                ) in
                                profilSelectionne.alertes
                            "
                            :key="
                                alerte.id ??
                                `new-alert-${index}`
                            "
                            class="rounded-xl border border-slate-200 p-5"
                        >
                            <!-- Header -->
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span
                                    :class="
                                        classeFrequence(
                                            alerte.frequence
                                        )
                                    "
                                    class="rounded-full px-3 py-1 text-xs font-semibold"
                                >
                                    {{
                                        labelFrequence(
                                            alerte.frequence
                                        )
                                    }}
                                </span>

                                <button
                                    type="button"
                                    class="text-sm font-semibold text-red-600"
                                    @click="
                                        supprimerAlerte(
                                            index
                                        )
                                    "
                                >
                                    🗑 Supprimer
                                </button>
                            </div>

                            <div
                                class="mt-5 grid gap-4 sm:grid-cols-2"
                            >
                                <!-- Channel -->
                                <div>
                                    <label
                                        class="mb-2 block text-xs font-semibold uppercase text-slate-400"
                                    >
                                        Canal
                                    </label>

                                    <select
                                        v-model="
                                            alerte.canal
                                        "
                                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                                    >
                                        <option
                                            value="email"
                                        >
                                            Email
                                        </option>

                                        <option
                                            value="telegram"
                                        >
                                            Telegram
                                        </option>

                                        <option
                                            value="webhook"
                                        >
                                            Webhook
                                        </option>
                                    </select>
                                </div>

                                <!-- Frequency -->
                                <div>
                                    <label
                                        class="mb-2 block text-xs font-semibold uppercase text-slate-400"
                                    >
                                        Fréquence
                                    </label>

                                    <select
                                        v-model="
                                            alerte.frequence
                                        "
                                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                                    >
                                        <option
                                            value="immediate"
                                        >
                                            Immediate
                                        </option>

                                        <option
                                            value="daily"
                                        >
                                            Daily
                                        </option>

                                        <option
                                            value="weekly"
                                        >
                                            Weekly
                                        </option>
                                    </select>
                                </div>

                                <!-- Destination -->
                                <div
                                    class="sm:col-span-2"
                                >
                                    <label
                                        class="mb-2 block text-xs font-semibold uppercase text-slate-400"
                                    >
                                        Destination
                                    </label>

                                    <input
                                        v-model="
                                            alerte.destination
                                        "
                                        :type="
                                            alerte.canal ===
                                            'email'
                                                ? 'email'
                                                : 'text'
                                        "
                                        placeholder="Destination..."
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                                    >
                                </div>

                                <!-- Threshold -->
                                <div>
                                    <label
                                        class="mb-2 block text-xs font-semibold uppercase text-slate-400"
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
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                                    >
                                </div>

                                <!-- Active -->
                                <div
                                    class="flex items-end"
                                >
                                    <label
                                        class="flex w-full cursor-pointer items-center justify-between rounded-lg bg-slate-50 px-4 py-3"
                                    >
                                        <span
                                            class="text-sm font-medium"
                                        >
                                            Active
                                        </span>

                                        <input
                                            v-model="
                                                alerte.actif
                                            "
                                            type="checkbox"
                                            class="h-5 w-5"
                                        >
                                    </label>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <!-- ================================================= -->
                <!-- ACTIONS -->
                <!-- ================================================= -->

                <div
                    class="sticky bottom-4 z-20 rounded-xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <button
                            type="button"
                            :disabled="
                                deleting
                            "
                            class="rounded-xl border border-red-200 px-5 py-3 text-sm font-semibold text-red-600 hover:bg-red-50 disabled:opacity-50"
                            @click="
                                supprimerProfil
                            "
                        >
                            🗑 Supprimer le profil
                        </button>

                        <button
                            type="button"
                            :disabled="saving"
                            class="rounded-xl bg-slate-900 px-7 py-3 text-sm font-semibold text-white hover:bg-slate-700 disabled:cursor-wait disabled:opacity-50"
                            @click="
                                sauvegarderProfil
                            "
                        >
                            <template
                                v-if="saving"
                            >
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

        <!-- ========================================================= -->
        <!-- CREATE PROFILE MODAL -->
        <!-- ========================================================= -->

        <div
            v-if="
                modalCreationOuvert
            "
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
            @click.self="
                fermerCreationProfil
            "
        >
            <div
                class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl"
            >
                <div
                    class="flex items-start justify-between"
                >
                    <div>
                        <h3
                            class="text-xl font-bold text-slate-900"
                        >
                            Nouveau profil
                        </h3>

                        <p
                            class="mt-1 text-sm text-slate-500"
                        >
                            Créez un nouveau profil
                            de recherche.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-lg"
                        @click="
                            fermerCreationProfil
                        "
                    >
                        ×
                    </button>
                </div>

                <div class="mt-6">
                    <label
                        class="block text-sm font-semibold text-slate-700"
                    >
                        Nom du profil
                    </label>

                    <input
                        v-model="
                            nouveauProfil.nom
                        "
                        type="text"
                        placeholder="Ex: PHP Europe"
                        class="mt-2 w-full rounded-lg border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
                        @keyup.enter="
                            creerProfil
                        "
                    >
                </div>

                <label
                    class="mt-5 flex cursor-pointer items-center justify-between rounded-xl bg-slate-50 p-4"
                >
                    <div>
                        <p
                            class="font-medium text-slate-800"
                        >
                            Profil actif
                        </p>

                        <p
                            class="mt-1 text-xs text-slate-500"
                        >
                            Active immédiatement
                            le profil.
                        </p>
                    </div>

                    <input
                        v-model="
                            nouveauProfil.actif
                        "
                        type="checkbox"
                        class="h-5 w-5"
                    >
                </label>

                <div
                    class="mt-6 flex gap-3"
                >
                    <button
                        type="button"
                        class="flex-1 rounded-xl border border-slate-300 px-4 py-3 font-semibold text-slate-700"
                        @click="
                            fermerCreationProfil
                        "
                    >
                        Annuler
                    </button>

                    <button
                        type="button"
                        :disabled="
                            creating
                        "
                        class="flex-1 rounded-xl bg-slate-900 px-4 py-3 font-semibold text-white disabled:opacity-50"
                        @click="
                            creerProfil
                        "
                    >
                        <template
                            v-if="
                                creating
                            "
                        >
                            Création...
                        </template>

                        <template v-else>
                            Créer
                        </template>
                    </button>
                </div>
            </div>
        </div>
    </main>
</template>