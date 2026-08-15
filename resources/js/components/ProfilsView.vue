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
| Create modal
|--------------------------------------------------------------------------
*/

const modalCreationOuvert =
    ref(false);

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
    return (
        criteres.value.find(
            (critere) =>
                Number(critere.id) ===
                Number(id)
        )
        ?? null
    );
};

/*
|--------------------------------------------------------------------------
| Global stats
|--------------------------------------------------------------------------
*/

const profilsActifs = computed(() => {
    return profils.value.filter(
        (profil) =>
            Boolean(
                profil.actif
            )
    ).length;
});

const totalFiltres = computed(() => {
    return profils.value.reduce(
        (total, profil) =>
            total
            +
            (
                profil.regles_filtrage
                    ?.length
                ?? 0
            ),
        0
    );
});

const totalAlertesActives =
    computed(() => {
        return profils.value.reduce(
            (total, profil) =>
                total
                +
                (
                    profil.alertes
                        ?.filter(
                            (alerte) =>
                                Boolean(
                                    alerte.actif
                                )
                        )
                        .length
                    ?? 0
                ),
            0
        );
    });

/*
|--------------------------------------------------------------------------
| Selected profile stats
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
                total
                +
                Number(
                    regle.poids
                    || 0
                ),
            0
        );
});

const nombreFiltres = computed(() => {
    return (
        profilSelectionne.value
            ?.regles_filtrage
            ?.length
        ?? 0
    );
});

const nombreScorings =
    computed(() => {
        return (
            profilSelectionne.value
                ?.regles_scoring
                ?.length
            ?? 0
        );
    });

const nombreAlertes =
    computed(() => {
        return (
            profilSelectionne.value
                ?.alertes
                ?.length
            ?? 0
        );
    });

const nombreAlertesActives =
    computed(() => {
        return (
            profilSelectionne.value
                ?.alertes
                ?.filter(
                    (alerte) =>
                        Boolean(
                            alerte.actif
                        )
                )
                .length
            ?? 0
        );
    });

const modulesConfigures =
    computed(() => {
        let total = 0;

        if (
            nombreFiltres.value > 0
        ) {
            total++;
        }

        if (
            nombreScorings.value > 0
        ) {
            total++;
        }

        if (
            nombreAlertes.value > 0
        ) {
            total++;
        }

        return total;
    });

/*
|--------------------------------------------------------------------------
| Profile list helpers
|--------------------------------------------------------------------------
*/

const scoreMaximumProfil =
    (profil) => {
        return (
            profil.regles_scoring
                ?.reduce(
                    (total, regle) =>
                        total
                        +
                        Number(
                            regle.poids
                            || 0
                        ),
                    0
                )
            ?? 0
        );
    };

const alertesActivesProfil =
    (profil) => {
        return (
            profil.alertes
                ?.filter(
                    (alerte) =>
                        Boolean(
                            alerte.actif
                        )
                )
                .length
            ?? 0
        );
    };

/*
|--------------------------------------------------------------------------
| Load
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
        loading.value =
            false;
    }
};

/*
|--------------------------------------------------------------------------
| Select profile
|--------------------------------------------------------------------------
*/

const selectionnerProfil =
    (profil) => {
        profilSelectionne.value =
            clone(
                profil
            );

        error.value =
            null;

        successMessage.value =
            null;
    };

/*
|--------------------------------------------------------------------------
| Success helper
|--------------------------------------------------------------------------
*/

const afficherSucces =
    (message) => {
        successMessage.value =
            message;

        setTimeout(
            () => {
                successMessage.value =
                    null;
            },
            4000
        );
    };

/*
|--------------------------------------------------------------------------
| Create
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

        error.value =
            null;
    };

const fermerCreationProfil =
    () => {
        modalCreationOuvert.value =
            false;
    };

const creerProfil = async () => {
    const nom =
        nouveauProfil.value
            .nom
            .trim();

    if (!nom) {
        error.value =
            'Le nom du profil est obligatoire.';

        return;
    }

    creating.value =
        true;

    error.value =
        null;

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

        afficherSucces(
            'Profil créé avec succès. Configurez maintenant ses filtres, son scoring et ses alertes.'
        );
    } catch (err) {
        console.error(
            'Erreur création profil :',
            err
        );

        if (
            err.response
                ?.status === 422
        ) {
            const errors =
                err.response
                    .data
                    ?.errors;

            error.value =
                errors?.nom?.[0]
                ??
                'Impossible de créer le profil.';
        } else {
            error.value =
                'Impossible de créer le profil.';
        }
    } finally {
        creating.value =
            false;
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

        deleting.value =
            true;

        error.value =
            null;

        const profilId =
            profilSelectionne
                .value.id;

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
                profils.value.length
                > 0
            ) {
                selectionnerProfil(
                    profils.value[0]
                );
            } else {
                profilSelectionne.value =
                    null;
            }

            afficherSucces(
                'Profil supprimé avec succès.'
            );
        } catch (err) {
            console.error(
                'Erreur suppression profil :',
                err
            );

            error.value =
                'Impossible de supprimer ce profil.';
        } finally {
            deleting.value =
                false;
        }
    };

/*
|--------------------------------------------------------------------------
| Filter rules
|--------------------------------------------------------------------------
*/

const critereDejaDansFiltres =
    (critereId) => {
        return profilSelectionne
            .value
            .regles_filtrage
            .some(
                (regle) =>
                    Number(
                        regle.critere_id
                    )
                    ===
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

const operateurParDefaut =
    (critere) => {
        if (
            critere?.type ===
            'nombre'
        ) {
            return (
                'superieur_egal'
            );
        }

        if (
            critere?.code ===
            'remote'
        ) {
            return 'egal';
        }

        return 'contient';
    };

const valeurParDefaut =
    (critere) => {
        if (
            critere?.code ===
            'remote'
        ) {
            return (
                'full_remote'
            );
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
            'Tous les critères disponibles sont déjà utilisés.'
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
                clone(
                    critere
                ),

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

const changerCritereFiltre =
    (regle) => {
        const critere =
            critereParId(
                regle.critere_id
            );

        regle.critere =
            critere
                ? clone(
                    critere
                )
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

const supprimerFiltre =
    (index) => {
        const regle =
            profilSelectionne
                .value
                .regles_filtrage[
                    index
                ];

        const critereId =
            Number(
                regle.critere_id
            );

        profilSelectionne.value
            .regles_scoring =
            profilSelectionne
                .value
                .regles_scoring
                .filter(
                    (scoring) =>
                        Number(
                            scoring
                                .critere_id
                        )
                        !==
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
| Scoring
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

        return profilSelectionne.value
            .regles_filtrage
            .map(
                (filtre) =>
                    filtre.critere
                    ??
                    critereParId(
                        filtre
                            .critere_id
                    )
            )
            .filter(Boolean)
            .filter(
                (critere) =>
                    !idsScoring
                        .includes(
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
                clone(
                    critere
                ),

            poids: 1,
        });
};

const supprimerScoring =
    (index) => {
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

const supprimerAlerte =
    (index) => {
        profilSelectionne.value
            .alertes
            .splice(
                index,
                1
            );
    };

/*
|--------------------------------------------------------------------------
| Save
|--------------------------------------------------------------------------
*/

const sauvegarderProfil =
    async () => {
        if (
            !profilSelectionne.value
        ) {
            return;
        }

        saving.value =
            true;

        error.value =
            null;

        successMessage.value =
            null;

        try {
            const payload = {
                nom:
                    profilSelectionne
                        .value
                        .nom,

                actif:
                    Boolean(
                        profilSelectionne
                            .value
                            .actif
                    ),

                regles_filtrage:
                    profilSelectionne
                        .value
                        .regles_filtrage
                        .map(
                            (regle) => ({
                                id:
                                    regle.id
                                    ?? null,

                                critere_id:
                                    Number(
                                        regle
                                            .critere_id
                                    ),

                                operateur:
                                    regle
                                        .operateur,

                                valeur:
                                    regle.valeur
                                        === null
                                    ||
                                    regle.valeur
                                        === undefined
                                        ? null
                                        : String(
                                            regle.valeur
                                        ),
                            })
                        ),

                regles_scoring:
                    profilSelectionne
                        .value
                        .regles_scoring
                        .map(
                            (regle) => ({
                                id:
                                    regle.id
                                    ?? null,

                                critere_id:
                                    Number(
                                        regle
                                            .critere_id
                                    ),

                                poids:
                                    Number(
                                        regle.poids
                                    ),
                            })
                        ),

                alertes:
                    profilSelectionne
                        .value
                        .alertes
                        .map(
                            (alerte) => ({
                                id:
                                    alerte.id
                                    ?? null,

                                canal:
                                    alerte.canal,

                                destination:
                                    alerte.destination,

                                frequence:
                                    alerte.frequence,

                                seuil_score_min:
                                    Number(
                                        alerte
                                            .seuil_score_min
                                    ),

                                actif:
                                    Boolean(
                                        alerte.actif
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

            if (
                index !== -1
            ) {
                profils.value[index] =
                    clone(
                        profilMisAJour
                    );
            }

            afficherSucces(
                'Profil sauvegardé et scores recalculés avec succès.'
            );
        } catch (err) {
            console.error(
                'Erreur sauvegarde profil :',
                err
            );

            if (
                err.response
                    ?.status === 422
            ) {
                const errors =
                    err.response
                        .data
                        ?.errors;

                if (
                    errors
                    &&
                    Object.keys(
                        errors
                    ).length > 0
                ) {
                    const premierChamp =
                        Object.keys(
                            errors
                        )[0];

                    error.value =
                        errors[
                            premierChamp
                        ]?.[0]
                        ??
                        'Configuration invalide.';
                } else {
                    error.value =
                        err.response
                            .data
                            ?.message
                        ??
                        'Configuration invalide.';
                }
            } else {
                error.value =
                    'Impossible de sauvegarder ce profil.';
            }
        } finally {
            saving.value =
                false;
        }
    };

/*
|--------------------------------------------------------------------------
| Labels
|--------------------------------------------------------------------------
*/

const labelOperateur =
    (operateur) => {
        const labels = {
            egal: '=',
            contient: 'contient',
            superieur_egal: '≥',
            inferieur_egal: '≤',
            dans: 'dans',
        };

        return (
            labels[operateur]
            ??
            operateur
        );
    };

const descriptionCritere =
    (code) => {
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
            descriptions[code]
            ??
            'Critère de recherche.'
        );
    };

const valeurLisible =
    (regle) => {
        if (
            regle.critere
                ?.code ===
            'remote'
        ) {
            const valeurs = {
                full_remote:
                    'Full remote',

                hybrid:
                    'Hybride',

                onsite:
                    'Sur site',
            };

            return (
                valeurs[
                    regle.valeur
                ]
                ??
                regle.valeur
            );
        }

        return (
            regle.valeur
            ||
            '—'
        );
    };

const labelFrequence =
    (frequence) => {
        const labels = {
            immediate:
                'Immédiate',

            daily:
                'Quotidienne',

            weekly:
                'Hebdomadaire',
        };

        return (
            labels[frequence]
            ??
            frequence
        );
    };

const iconeCanal =
    (canal) => {
        const icons = {
            email: '✉',
            telegram: '➤',
            webhook: '⌁',
        };

        return (
            icons[canal]
            ??
            '◉'
        );
    };

const labelCanal =
    (canal) => {
        const labels = {
            email:
                'Email',

            telegram:
                'Telegram',

            webhook:
                'Webhook',
        };

        return (
            labels[canal]
            ??
            canal
        );
    };

const placeholderDestination =
    (canal) => {
        if (
            canal === 'email'
        ) {
            return (
                'email@example.com'
            );
        }

        if (
            canal === 'telegram'
        ) {
            return (
                'Chat ID / destination Telegram'
            );
        }

        return (
            'https://example.com/webhook'
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
        class="profiles-page relative min-h-screen overflow-hidden"
    >
        <!-- Background -->

        <div
            class="pointer-events-none absolute -left-40 top-20 h-[450px] w-[450px] rounded-full bg-violet-400/10 blur-3xl"
        ></div>

        <div
            class="pointer-events-none absolute -right-40 top-[550px] h-[500px] w-[500px] rounded-full bg-indigo-400/10 blur-3xl"
        ></div>

        <div
            class="relative mx-auto max-w-7xl px-6 py-10 lg:py-12"
        >
            <!-- ===================================================== -->
            <!-- HERO -->
            <!-- ===================================================== -->

            <section
                class="profile-hero relative mb-7 overflow-hidden rounded-[30px] border border-white/70 bg-gradient-to-br from-slate-950 via-indigo-950 to-violet-950 p-7 text-white shadow-2xl shadow-violet-200/40 lg:p-9"
            >
                <div
                    class="absolute -right-24 -top-28 h-80 w-80 rounded-full bg-violet-400/20 blur-3xl"
                ></div>

                <div
                    class="absolute -bottom-24 left-1/3 h-64 w-64 rounded-full bg-indigo-400/10 blur-3xl"
                ></div>

                <div
                    class="relative flex flex-col gap-7 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div
                        class="max-w-2xl"
                    >
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.16em] text-violet-100 backdrop-blur"
                        >
                            <span
                                class="h-2 w-2 animate-pulse rounded-full bg-violet-300"
                            ></span>

                            Search Intelligence
                        </div>

                        <h1
                            class="mt-5 text-3xl font-black tracking-tight sm:text-4xl"
                        >
                            Profils de
                            <span
                                class="hero-gradient"
                            >
                                recherche
                            </span>
                        </h1>

                        <p
                            class="mt-4 max-w-xl text-sm leading-7 text-slate-300 sm:text-base"
                        >
                            Construisez vos stratégies de recherche, attribuez des scores aux critères importants et automatisez vos alertes.
                        </p>

                        <button
                            type="button"
                            class="hero-button mt-6"
                            @click="
                                ouvrirCreationProfil
                            "
                        >
                            <span>
                                ＋
                            </span>

                            Nouveau profil
                        </button>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-3"
                    >
                        <div
                            class="hero-stat"
                        >
                            <span>
                                Profils
                            </span>

                            <strong>
                                {{
                                    profils.length
                                }}
                            </strong>
                        </div>

                        <div
                            class="hero-stat"
                        >
                            <span>
                                Actifs
                            </span>

                            <strong
                                class="text-emerald-300"
                            >
                                {{
                                    profilsActifs
                                }}
                            </strong>
                        </div>

                        <div
                            class="hero-stat"
                        >
                            <span>
                                Filtres
                            </span>

                            <strong
                                class="text-indigo-300"
                            >
                                {{
                                    totalFiltres
                                }}
                            </strong>
                        </div>

                        <div
                            class="hero-stat"
                        >
                            <span>
                                Alertes
                            </span>

                            <strong
                                class="text-violet-300"
                            >
                                {{
                                    totalAlertesActives
                                }}
                            </strong>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Success -->

            <transition
                name="toast"
            >
                <div
                    v-if="
                        successMessage
                    "
                    class="mb-6 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/90 px-5 py-4 text-sm font-bold text-emerald-700 shadow-lg shadow-emerald-100/50"
                >
                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-100"
                    >
                        ✓
                    </span>

                    {{
                        successMessage
                    }}
                </div>
            </transition>

            <!-- Error -->

            <div
                v-if="error"
                class="mb-6 flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-700"
            >
                <span>
                    ⚠
                </span>

                {{ error }}
            </div>

            <!-- ===================================================== -->
            <!-- LOADING -->
            <!-- ===================================================== -->

            <div
                v-if="loading"
                class="grid gap-6 lg:grid-cols-[290px_1fr]"
            >
                <div
                    class="h-[500px] animate-pulse rounded-[26px] bg-white"
                ></div>

                <div
                    class="space-y-5"
                >
                    <div
                        class="h-64 animate-pulse rounded-[26px] bg-white"
                    ></div>

                    <div
                        class="h-72 animate-pulse rounded-[26px] bg-white"
                    ></div>
                </div>
            </div>

            <!-- ===================================================== -->
            <!-- EMPTY -->
            <!-- ===================================================== -->

            <div
                v-else-if="
                    profils.length === 0
                "
                class="rounded-[28px] border border-dashed border-slate-300 bg-white/85 px-6 py-16 text-center shadow-sm"
            >
                <div
                    class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-violet-50 text-3xl text-violet-600"
                >
                    ◎
                </div>

                <h2
                    class="mt-5 text-xl font-black text-slate-900"
                >
                    Aucun profil de recherche
                </h2>

                <p
                    class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500"
                >
                    Créez votre premier profil pour filtrer, scorer et surveiller automatiquement les nouvelles missions.
                </p>

                <button
                    type="button"
                    class="mt-6 rounded-xl bg-slate-950 px-5 py-3 text-sm font-black text-white"
                    @click="
                        ouvrirCreationProfil
                    "
                >
                    + Créer un profil
                </button>
            </div>

            <!-- ===================================================== -->
            <!-- WORKSPACE -->
            <!-- ===================================================== -->

            <div
                v-else
                class="grid gap-6 lg:grid-cols-[300px_1fr]"
            >
                <!-- PROFILE LIST -->

                <aside>
                    <div
                        class="sticky top-24 overflow-hidden rounded-[24px] border border-slate-200/80 bg-white/90 shadow-lg shadow-slate-200/40 backdrop-blur-xl"
                    >
                        <div
                            class="border-b border-slate-100 px-5 py-5"
                        >
                            <p
                                class="text-[10px] font-black uppercase tracking-[0.15em] text-indigo-500"
                            >
                                Workspace
                            </p>

                            <div
                                class="mt-1 flex items-center justify-between"
                            >
                                <h2
                                    class="text-base font-black text-slate-900"
                                >
                                    Vos profils
                                </h2>

                                <span
                                    class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black text-slate-500"
                                >
                                    {{
                                        profils.length
                                    }}
                                </span>
                            </div>
                        </div>

                        <div
                            class="max-h-[650px] overflow-y-auto p-2"
                        >
                            <button
                                v-for="
                                    profil in profils
                                "
                                :key="
                                    profil.id
                                "
                                type="button"
                                class="profile-selector group"
                                :class="
                                    profilSelectionne?.id === profil.id
                                        ? 'profile-selector-active'
                                        : 'profile-selector-idle'
                                "
                                @click="
                                    selectionnerProfil(
                                        profil
                                    )
                                "
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div
                                        class="min-w-0"
                                    >
                                        <div
                                            class="flex items-center gap-2"
                                        >
                                            <span
                                                class="status-dot"
                                                :class="
                                                    profil.actif
                                                        ? 'bg-emerald-400'
                                                        : 'bg-slate-300'
                                                "
                                            ></span>

                                            <h3
                                                class="truncate text-sm font-black"
                                            >
                                                {{
                                                    profil.nom
                                                }}
                                            </h3>
                                        </div>

                                        <p
                                            class="mt-2 text-[10px] opacity-60"
                                        >
                                            {{
                                                profil.scores_missions_count
                                                ?? 0
                                            }}
                                            missions scorées
                                        </p>
                                    </div>

                                    <span
                                        class="score-mini"
                                    >
                                        {{
                                            scoreMaximumProfil(
                                                profil
                                            )
                                        }}
                                    </span>
                                </div>

                                <div
                                    class="mt-3 flex gap-2"
                                >
                                    <span
                                        class="profile-mini-chip"
                                    >
                                        ◇
                                        {{
                                            profil.regles_filtrage
                                                ?.length
                                            ?? 0
                                        }}
                                    </span>

                                    <span
                                        class="profile-mini-chip"
                                    >
                                        ✦
                                        {{
                                            profil.regles_scoring
                                                ?.length
                                            ?? 0
                                        }}
                                    </span>

                                    <span
                                        class="profile-mini-chip"
                                    >
                                        🔔
                                        {{
                                            alertesActivesProfil(
                                                profil
                                            )
                                        }}
                                    </span>
                                </div>
                            </button>
                        </div>

                        <div
                            class="border-t border-slate-100 p-3"
                        >
                            <button
                                type="button"
                                class="w-full rounded-xl border border-dashed border-indigo-200 bg-indigo-50/50 px-4 py-3 text-xs font-black text-indigo-600 transition-all duration-300 hover:bg-indigo-50"
                                @click="
                                    ouvrirCreationProfil
                                "
                            >
                                + Nouveau profil
                            </button>
                        </div>
                    </div>
                </aside>

                <!-- PROFILE CONTENT -->

                <section
                    v-if="
                        profilSelectionne
                    "
                    class="min-w-0 space-y-6"
                >
                    <!-- SUMMARY -->

                    <article
                        class="profile-summary relative overflow-hidden"
                    >
                        <div
                            class="absolute -right-20 -top-20 h-56 w-56 rounded-full bg-violet-400/10 blur-3xl"
                        ></div>

                        <div
                            class="relative"
                        >
                            <div
                                class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between"
                            >
                                <div
                                    class="min-w-0 flex-1"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            class="profile-state"
                                            :class="
                                                profilSelectionne.actif
                                                    ? 'profile-state-active'
                                                    : 'profile-state-inactive'
                                            "
                                        >
                                            <span
                                                class="h-2 w-2 rounded-full"
                                                :class="
                                                    profilSelectionne.actif
                                                        ? 'bg-emerald-500'
                                                        : 'bg-slate-400'
                                                "
                                            ></span>

                                            {{
                                                profilSelectionne.actif
                                                    ? 'Profil actif'
                                                    : 'Profil inactif'
                                            }}
                                        </span>

                                        <span
                                            class="rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-[10px] font-black text-indigo-600"
                                        >
                                            {{
                                                profilSelectionne
                                                    .scores_missions_count
                                                ?? 0
                                            }}
                                            missions scorées
                                        </span>
                                    </div>

                                    <input
                                        v-model="
                                            profilSelectionne.nom
                                        "
                                        type="text"
                                        class="profile-name-input"
                                    >

                                    <p
                                        class="mt-2 max-w-2xl text-sm leading-6 text-slate-400"
                                    >
                                        Configurez les critères obligatoires, les poids de pertinence et les règles de notification de ce profil.
                                    </p>
                                </div>

                                <div
                                    class="score-panel"
                                >
                                    <div
                                        class="score-orb"
                                    >
                                        <span
                                            class="text-[9px] font-black uppercase tracking-[0.12em] text-indigo-200"
                                        >
                                            Score
                                        </span>

                                        <strong>
                                            {{
                                                scoreMaximum
                                            }}
                                        </strong>

                                        <span
                                            class="text-[9px] font-bold text-indigo-200"
                                        >
                                            maximum
                                        </span>
                                    </div>

                                    <div>
                                        <p
                                            class="text-xs font-black text-slate-700"
                                        >
                                            Matching potentiel
                                        </p>

                                        <p
                                            class="mt-1 text-[10px] leading-5 text-slate-400"
                                        >
                                            Somme des poids de scoring configurés.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- METRICS -->

                            <div
                                class="mt-6 grid gap-3 sm:grid-cols-4"
                            >
                                <div
                                    class="summary-stat"
                                >
                                    <span>
                                        ◇
                                    </span>

                                    <div>
                                        <p>
                                            Filtres
                                        </p>

                                        <strong>
                                            {{
                                                nombreFiltres
                                            }}
                                        </strong>
                                    </div>
                                </div>

                                <div
                                    class="summary-stat"
                                >
                                    <span>
                                        ✦
                                    </span>

                                    <div>
                                        <p>
                                            Scoring
                                        </p>

                                        <strong>
                                            {{
                                                nombreScorings
                                            }}
                                        </strong>
                                    </div>
                                </div>

                                <div
                                    class="summary-stat"
                                >
                                    <span>
                                        🔔
                                    </span>

                                    <div>
                                        <p>
                                            Alertes actives
                                        </p>

                                        <strong>
                                            {{
                                                nombreAlertesActives
                                            }}
                                        </strong>
                                    </div>
                                </div>

                                <div
                                    class="summary-stat"
                                >
                                    <span>
                                        ◎
                                    </span>

                                    <div>
                                        <p>
                                            Modules
                                        </p>

                                        <strong>
                                            {{
                                                modulesConfigures
                                            }}/3
                                        </strong>
                                    </div>
                                </div>
                            </div>

                            <!-- PREVIEW RULES -->

                            <div
                                v-if="
                                    profilSelectionne
                                        .regles_filtrage
                                        .length
                                "
                                class="mt-5 flex flex-wrap gap-2"
                            >
                                <span
                                    v-for="
                                        regle in profilSelectionne.regles_filtrage
                                    "
                                    :key="
                                        `preview-${regle.id ?? regle.critere_id}`
                                    "
                                    class="rule-chip"
                                >
                                    <strong>
                                        {{
                                            regle.critere
                                                ?.label
                                            ??
                                            'Critère'
                                        }}
                                    </strong>

                                    {{
                                        labelOperateur(
                                            regle.operateur
                                        )
                                    }}

                                    {{
                                        valeurLisible(
                                            regle
                                        )
                                    }}
                                </span>
                            </div>

                            <!-- PREMIUM STATUS -->

                            <div
                                class="mt-6 flex flex-col gap-4 rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <p
                                        class="text-sm font-black text-slate-800"
                                    >
                                        Activation du profil
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-slate-400"
                                    >
                                        Contrôle son utilisation pour le matching et les alertes.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="
                                        profilSelectionne.actif
                                    "
                                    class="premium-status-toggle"
                                    :class="
                                        profilSelectionne.actif
                                            ? 'premium-status-active'
                                            : 'premium-status-inactive'
                                    "
                                    @click="
                                        profilSelectionne.actif =
                                            !profilSelectionne.actif
                                    "
                                >
                                    <span
                                        class="premium-status-dot"
                                    >
                                        <span
                                            v-if="
                                                profilSelectionne.actif
                                            "
                                            class="premium-status-pulse"
                                        ></span>

                                        <span
                                            class="premium-status-dot-core"
                                        ></span>
                                    </span>

                                    <span>
                                        {{
                                            profilSelectionne.actif
                                                ? 'Actif'
                                                : 'Inactif'
                                        }}
                                    </span>

                                    <span
                                        class="premium-status-icon"
                                    >
                                        {{
                                            profilSelectionne.actif
                                                ? '✓'
                                                : '—'
                                        }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </article>

                    <!-- FILTER RULES -->

                    <article
                        class="workspace-card"
                    >
                        <div
                            class="workspace-header"
                        >
                            <div
                                class="flex items-start gap-3"
                            >
                                <div
                                    class="section-icon section-indigo"
                                >
                                    ◇
                                </div>

                                <div>
                                    <p
                                        class="section-eyebrow"
                                    >
                                        Filtrage
                                    </p>

                                    <h2
                                        class="section-title"
                                    >
                                        Règles obligatoires
                                    </h2>

                                    <p
                                        class="section-description"
                                    >
                                        Toutes les règles sont combinées avec une logique AND.
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="add-button"
                                @click="
                                    ajouterFiltre
                                "
                            >
                                + Ajouter
                            </button>
                        </div>

                        <div
                            v-if="
                                profilSelectionne
                                    .regles_filtrage
                                    .length === 0
                            "
                            class="empty-section"
                        >
                            <div
                                class="empty-icon"
                            >
                                ◇
                            </div>

                            <p
                                class="mt-3 font-black text-slate-700"
                            >
                                Aucun filtre
                            </p>

                            <p
                                class="mt-1 text-xs text-slate-400"
                            >
                                Ajoutez un critère pour définir les missions acceptées.
                            </p>
                        </div>

                        <div
                            v-else
                            class="space-y-3 p-5"
                        >
                            <div
                                v-for="
                                    (
                                        regle,
                                        index
                                    ) in profilSelectionne.regles_filtrage
                                "
                                :key="
                                    regle.id
                                    ??
                                    `filter-${index}`
                                "
                                class="rule-card"
                            >
                                <div
                                    class="rule-number"
                                >
                                    {{
                                        index + 1
                                    }}
                                </div>

                                <div
                                    class="grid min-w-0 flex-1 gap-4 xl:grid-cols-[1.2fr_180px_1fr]"
                                >
                                    <div>
                                        <label
                                            class="field-label"
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
                                            class="field-control"
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
                                                    )
                                                    &&
                                                    Number(
                                                        regle.critere_id
                                                    )
                                                    !==
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
                                            class="mt-2 text-[10px] leading-4 text-slate-400"
                                        >
                                            {{
                                                descriptionCritere(
                                                    regle
                                                        .critere
                                                        ?.code
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div>
                                        <label
                                            class="field-label"
                                        >
                                            Opérateur
                                        </label>

                                        <select
                                            v-model="
                                                regle.operateur
                                            "
                                            class="field-control"
                                        >
                                            <option value="egal">
                                                =
                                            </option>

                                            <option value="contient">
                                                Contient
                                            </option>

                                            <option value="superieur_egal">
                                                ≥
                                            </option>

                                            <option value="inferieur_egal">
                                                ≤
                                            </option>

                                            <option value="dans">
                                                Dans
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <label
                                            class="field-label"
                                        >
                                            Valeur
                                        </label>

                                        <select
                                            v-if="
                                                regle.critere
                                                    ?.code
                                                ===
                                                'remote'
                                            "
                                            v-model="
                                                regle.valeur
                                            "
                                            class="field-control"
                                        >
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

                                        <input
                                            v-else-if="
                                                regle.critere
                                                    ?.type
                                                ===
                                                'nombre'
                                            "
                                            v-model="
                                                regle.valeur
                                            "
                                            type="number"
                                            class="field-control"
                                        >

                                        <input
                                            v-else
                                            v-model="
                                                regle.valeur
                                            "
                                            type="text"
                                            class="field-control"
                                        >
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="remove-button"
                                    title="Supprimer cette règle"
                                    @click="
                                        supprimerFiltre(
                                            index
                                        )
                                    "
                                >
                                    ×
                                </button>
                            </div>
                        </div>
                    </article>

                    <!-- SCORING -->

                    <article
                        class="workspace-card"
                    >
                        <div
                            class="workspace-header"
                        >
                            <div
                                class="flex items-start gap-3"
                            >
                                <div
                                    class="section-icon section-violet"
                                >
                                    ✦
                                </div>

                                <div>
                                    <p
                                        class="section-eyebrow text-violet-500"
                                    >
                                        Matching
                                    </p>

                                    <h2
                                        class="section-title"
                                    >
                                        Scoring de pertinence
                                    </h2>

                                    <p
                                        class="section-description"
                                    >
                                        Attribuez plus de poids aux critères les plus importants.
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="add-button"
                                @click="
                                    ajouterScoring
                                "
                            >
                                + Ajouter
                            </button>
                        </div>

                        <div
                            v-if="
                                profilSelectionne
                                    .regles_scoring
                                    .length === 0
                            "
                            class="empty-section"
                        >
                            <div
                                class="empty-icon"
                            >
                                ✦
                            </div>

                            <p
                                class="mt-3 font-black text-slate-700"
                            >
                                Aucun scoring
                            </p>

                            <p
                                class="mt-1 text-xs text-slate-400"
                            >
                                Ajoutez d’abord un filtre, puis attribuez-lui un poids.
                            </p>
                        </div>

                        <div
                            v-else
                            class="grid gap-4 p-5 md:grid-cols-2"
                        >
                            <div
                                v-for="
                                    (
                                        regle,
                                        index
                                    ) in profilSelectionne.regles_scoring
                                "
                                :key="
                                    regle.id
                                    ??
                                    `score-${index}`
                                "
                                class="scoring-card"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-3"
                                    >
                                        <div
                                            class="scoring-icon"
                                        >
                                            ✦
                                        </div>

                                        <div
                                            class="min-w-0"
                                        >
                                            <p
                                                class="truncate text-sm font-black text-slate-800"
                                            >
                                                {{
                                                    regle.critere
                                                        ?.label
                                                    ??
                                                    'Critère'
                                                }}
                                            </p>

                                            <p
                                                class="mt-1 text-[10px] uppercase tracking-wide text-slate-400"
                                            >
                                                {{
                                                    regle.critere
                                                        ?.code
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="remove-button"
                                        @click="
                                            supprimerScoring(
                                                index
                                            )
                                        "
                                    >
                                        ×
                                    </button>
                                </div>

                                <div
                                    class="mt-5 flex items-end gap-4"
                                >
                                    <div
                                        class="flex-1"
                                    >
                                        <label
                                            class="field-label"
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
                                            class="field-control text-lg font-black"
                                        >
                                    </div>

                                    <div
                                        class="weight-display"
                                    >
                                        +{{
                                            Number(
                                                regle.poids
                                                || 0
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="border-t border-slate-100 bg-gradient-to-r from-violet-50/70 to-indigo-50/70 px-5 py-4"
                        >
                            <div
                                class="flex items-center justify-between"
                            >
                                <span
                                    class="text-xs font-bold text-slate-500"
                                >
                                    Score maximum du profil
                                </span>

                                <span
                                    class="rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 px-4 py-2 text-lg font-black text-white shadow-lg shadow-indigo-100"
                                >
                                    {{
                                        scoreMaximum
                                    }}
                                </span>
                            </div>
                        </div>
                    </article>

                    <!-- ALERTS -->

                    <article
                        class="workspace-card"
                    >
                        <div
                            class="workspace-header"
                        >
                            <div
                                class="flex items-start gap-3"
                            >
                                <div
                                    class="section-icon section-amber"
                                >
                                    🔔
                                </div>

                                <div>
                                    <p
                                        class="section-eyebrow text-amber-500"
                                    >
                                        Automation
                                    </p>

                                    <h2
                                        class="section-title"
                                    >
                                        Alertes
                                    </h2>

                                    <p
                                        class="section-description"
                                    >
                                        Définissez où, quand et à partir de quel score vous souhaitez être alerté.
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="add-button"
                                @click="
                                    ajouterAlerte
                                "
                            >
                                + Ajouter
                            </button>
                        </div>

                        <div
                            v-if="
                                profilSelectionne
                                    .alertes
                                    .length === 0
                            "
                            class="empty-section"
                        >
                            <div
                                class="empty-icon"
                            >
                                🔔
                            </div>

                            <p
                                class="mt-3 font-black text-slate-700"
                            >
                                Aucune alerte
                            </p>

                            <p
                                class="mt-1 text-xs text-slate-400"
                            >
                                Ajoutez une notification pour les missions les plus pertinentes.
                            </p>
                        </div>

                        <div
                            v-else
                            class="grid gap-4 p-5 xl:grid-cols-2"
                        >
                            <article
                                v-for="
                                    (
                                        alerte,
                                        index
                                    ) in profilSelectionne.alertes
                                "
                                :key="
                                    alerte.id
                                    ??
                                    `alert-${index}`
                                "
                                class="alert-card"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div
                                        class="flex items-center gap-3"
                                    >
                                        <div
                                            class="channel-icon"
                                        >
                                            {{
                                                iconeCanal(
                                                    alerte.canal
                                                )
                                            }}
                                        </div>

                                        <div>
                                            <p
                                                class="text-sm font-black text-slate-800"
                                            >
                                                {{
                                                    labelCanal(
                                                        alerte.canal
                                                    )
                                                }}
                                            </p>

                                            <span
                                                class="mt-1 inline-block rounded-full bg-indigo-50 px-2 py-1 text-[9px] font-black text-indigo-600"
                                            >
                                                {{
                                                    labelFrequence(
                                                        alerte.frequence
                                                    )
                                                }}
                                            </span>
                                        </div>
                                    </div>

                                    <div
                                        class="flex items-center gap-2"
                                    >
                                        <!-- FIXED MINI SWITCH -->

                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="
                                                alerte.actif
                                            "
                                            class="mini-switch"
                                            :class="
                                                alerte.actif
                                                    ? 'mini-switch-on'
                                                    : 'mini-switch-off'
                                            "
                                            @click="
                                                alerte.actif =
                                                    !alerte.actif
                                            "
                                        >
                                            <span
                                                :class="
                                                    alerte.actif
                                                        ? 'mini-thumb-on'
                                                        : 'mini-thumb-off'
                                                "
                                            ></span>
                                        </button>

                                        <button
                                            type="button"
                                            class="remove-button"
                                            @click="
                                                supprimerAlerte(
                                                    index
                                                )
                                            "
                                        >
                                            ×
                                        </button>
                                    </div>
                                </div>

                                <div
                                    class="mt-5 grid gap-4 sm:grid-cols-2"
                                >
                                    <div>
                                        <label
                                            class="field-label"
                                        >
                                            Canal
                                        </label>

                                        <select
                                            v-model="
                                                alerte.canal
                                            "
                                            class="field-control"
                                        >
                                            <option value="email">
                                                Email
                                            </option>

                                            <option value="telegram">
                                                Telegram
                                            </option>

                                            <option value="webhook">
                                                Webhook
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <label
                                            class="field-label"
                                        >
                                            Fréquence
                                        </label>

                                        <select
                                            v-model="
                                                alerte.frequence
                                            "
                                            class="field-control"
                                        >
                                            <option value="immediate">
                                                Immédiate
                                            </option>

                                            <option value="daily">
                                                Quotidienne
                                            </option>

                                            <option value="weekly">
                                                Hebdomadaire
                                            </option>
                                        </select>
                                    </div>

                                    <div
                                        class="sm:col-span-2"
                                    >
                                        <label
                                            class="field-label"
                                        >
                                            Destination
                                        </label>

                                        <input
                                            v-model="
                                                alerte.destination
                                            "
                                            :type="
                                                alerte.canal === 'email'
                                                    ? 'email'
                                                    : 'text'
                                            "
                                            :placeholder="
                                                placeholderDestination(
                                                    alerte.canal
                                                )
                                            "
                                            class="field-control"
                                        >
                                    </div>

                                    <div
                                        class="sm:col-span-2"
                                    >
                                        <div
                                            class="flex items-end justify-between gap-4"
                                        >
                                            <div
                                                class="flex-1"
                                            >
                                                <label
                                                    class="field-label"
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
                                                    class="field-control"
                                                >
                                            </div>

                                            <div
                                                class="threshold-box"
                                            >
                                                ≥
                                                {{
                                                    alerte.seuil_score_min
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </article>

                    <!-- SAVE BAR -->

                    <div
                        class="save-bar"
                    >
                        <div
                            class="hidden sm:block"
                        >
                            <p
                                class="text-xs font-black text-slate-700"
                            >
                                Configuration du profil
                            </p>

                            <p
                                class="mt-1 text-[10px] text-slate-400"
                            >
                                L'enregistrement recalcule les scores des missions.
                            </p>
                        </div>

                        <div
                            class="flex w-full gap-3 sm:w-auto"
                        >
                            <button
                                type="button"
                                :disabled="
                                    deleting
                                "
                                class="delete-profile-button"
                                @click="
                                    supprimerProfil
                                "
                            >
                                {{
                                    deleting
                                        ? 'Suppression...'
                                        : 'Supprimer'
                                }}
                            </button>

                            <button
                                type="button"
                                :disabled="
                                    saving
                                "
                                class="save-profile-button"
                                @click="
                                    sauvegarderProfil
                                "
                            >
                                <span
                                    v-if="
                                        saving
                                    "
                                    class="spinner"
                                ></span>

                                {{
                                    saving
                                        ? 'Recalcul en cours...'
                                        : '✓ Enregistrer le profil'
                                }}
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- CREATE OVERLAY -->
        <!-- ========================================================= -->

        <transition
            name="fade"
        >
            <div
                v-if="
                    modalCreationOuvert
                "
                class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm"
                @click="
                    fermerCreationProfil
                "
            ></div>
        </transition>

        <!-- ========================================================= -->
        <!-- CREATE MODAL -->
        <!-- ========================================================= -->

        <transition
            name="modal"
        >
            <div
                v-if="
                    modalCreationOuvert
                "
                class="fixed inset-0 z-50 flex items-center justify-center px-4 py-8"
            >
                <div
                    class="premium-modal w-full max-w-md"
                    @click.stop
                >
                    <div
                        class="modal-header"
                    >
                        <div>
                            <p
                                class="text-[10px] font-black uppercase tracking-[0.16em] text-violet-500"
                            >
                                Search Intelligence
                            </p>

                            <h2
                                class="mt-2 text-2xl font-black text-slate-900"
                            >
                                Nouveau profil
                            </h2>

                            <p
                                class="mt-1 text-xs text-slate-400"
                            >
                                Créez une nouvelle stratégie de recherche.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="close-button"
                            @click="
                                fermerCreationProfil
                            "
                        >
                            ×
                        </button>
                    </div>

                    <div
                        class="p-6"
                    >
                        <label
                            class="field-label"
                        >
                            Nom du profil
                        </label>

                        <input
                            v-model="
                                nouveauProfil.nom
                            "
                            type="text"
                            placeholder="Ex. Laravel Remote"
                            class="field-control text-base font-bold"
                            @keyup.enter="
                                creerProfil
                            "
                        >

                        <div
                            class="mt-5 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-sm font-black text-slate-800"
                                >
                                    Profil actif
                                </p>

                                <p
                                    class="mt-1 text-xs text-slate-400"
                                >
                                    Utilisable immédiatement après création.
                                </p>
                            </div>

                            <!-- NEW PREMIUM STATUS BUTTON -->

                            <button
                                type="button"
                                role="switch"
                                :aria-checked="
                                    nouveauProfil.actif
                                "
                                class="premium-status-toggle"
                                :class="
                                    nouveauProfil.actif
                                        ? 'premium-status-active'
                                        : 'premium-status-inactive'
                                "
                                @click="
                                    nouveauProfil.actif =
                                        !nouveauProfil.actif
                                "
                            >
                                <span
                                    class="premium-status-dot"
                                >
                                    <span
                                        v-if="
                                            nouveauProfil.actif
                                        "
                                        class="premium-status-pulse"
                                    ></span>

                                    <span
                                        class="premium-status-dot-core"
                                    ></span>
                                </span>

                                <span>
                                    {{
                                        nouveauProfil.actif
                                            ? 'Actif'
                                            : 'Inactif'
                                    }}
                                </span>

                                <span
                                    class="premium-status-icon"
                                >
                                    {{
                                        nouveauProfil.actif
                                            ? '✓'
                                            : '—'
                                    }}
                                </span>
                            </button>
                        </div>

                        <div
                            class="mt-6 flex gap-3"
                        >
                            <button
                                type="button"
                                class="flex-1 rounded-xl border border-slate-200 px-4 py-3 text-sm font-black text-slate-600 transition hover:bg-slate-50"
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
                                class="create-button flex-1"
                                @click="
                                    creerProfil
                                "
                            >
                                {{
                                    creating
                                        ? 'Création...'
                                        : 'Créer le profil'
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </main>
</template>

<style scoped>
.profiles-page {
    background:
        linear-gradient(
            135deg,
            #f8fafc 0%,
            #ffffff 45%,
            #f5f3ff 100%
        );
}

/* ================================================================
   HERO
================================================================ */

.profile-hero {
    animation:
        heroReveal
        0.6s
        cubic-bezier(
            0.22,
            1,
            0.36,
            1
        )
        both;
}

.hero-gradient {
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

.hero-button {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;

    border-radius: 0.9rem;

    background: white;

    padding:
        0.75rem
        1rem;

    font-size: 0.75rem;
    font-weight: 900;

    color: #0f172a;

    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease;
}

.hero-button:hover {
    transform:
        translateY(-2px);

    box-shadow:
        0 15px 30px -18px
        rgba(
            255,
            255,
            255,
            0.7
        );
}

.hero-stat {
    min-width: 110px;

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

    padding: 0.9rem;

    backdrop-filter:
        blur(14px);
}

.hero-stat span {
    display: block;

    font-size: 0.55rem;
    font-weight: 900;

    letter-spacing: 0.12em;

    text-transform: uppercase;

    color: #94a3b8;
}

.hero-stat strong {
    display: block;

    margin-top: 0.3rem;

    font-size: 1.5rem;
    font-weight: 900;
}

/* ================================================================
   PROFILE SIDEBAR
================================================================ */

.profile-selector {
    width: 100%;

    border-radius: 0.95rem;

    padding: 0.9rem;

    text-align: left;

    transition:
        transform 0.25s ease,
        background 0.25s ease,
        color 0.25s ease,
        box-shadow 0.25s ease;
}

.profile-selector
+
.profile-selector {
    margin-top: 0.3rem;
}

.profile-selector-idle {
    color: #475569;
}

.profile-selector-idle:hover {
    background: #f8fafc;

    transform:
        translateX(2px);
}

.profile-selector-active {
    background:
        linear-gradient(
            135deg,
            #0f172a,
            #312e81
        );

    color: white;

    box-shadow:
        0 15px 30px -22px
        rgba(
            49,
            46,
            129,
            0.75
        );
}

.status-dot {
    height: 0.5rem;
    width: 0.5rem;

    flex-shrink: 0;

    border-radius: 9999px;
}

.score-mini {
    display: flex;

    min-width: 2rem;
    height: 2rem;

    align-items: center;
    justify-content: center;

    border-radius: 0.65rem;

    background:
        rgba(
            148,
            163,
            184,
            0.14
        );

    padding:
        0
        0.45rem;

    font-size: 0.7rem;
    font-weight: 900;
}

.profile-mini-chip {
    border-radius: 0.5rem;

    background:
        rgba(
            148,
            163,
            184,
            0.1
        );

    padding:
        0.25rem
        0.4rem;

    font-size: 0.55rem;
    font-weight: 900;
}

/* ================================================================
   SUMMARY
================================================================ */

.profile-summary {
    border:
        1px solid
        rgba(
            226,
            232,
            240,
            0.85
        );

    border-radius: 1.55rem;

    background:
        rgba(
            255,
            255,
            255,
            0.9
        );

    padding: 1.5rem;

    box-shadow:
        0 20px 45px -36px
        rgba(
            15,
            23,
            42,
            0.45
        );

    backdrop-filter:
        blur(18px);
}

.profile-state {
    display: inline-flex;

    align-items: center;

    gap: 0.45rem;

    border: 1px solid;

    border-radius: 9999px;

    padding:
        0.38rem
        0.65rem;

    font-size: 0.62rem;
    font-weight: 900;
}

.profile-state-active {
    border-color: #a7f3d0;

    background: #ecfdf5;

    color: #047857;
}

.profile-state-inactive {
    border-color: #e2e8f0;

    background: #f8fafc;

    color: #64748b;
}

.profile-name-input {
    margin-top: 1rem;

    width: 100%;

    max-width: 620px;

    border: 0;

    background: transparent;

    padding: 0;

    font-size: 1.7rem;
    font-weight: 900;

    letter-spacing: -0.025em;

    color: #0f172a;

    outline: none;
}

.profile-name-input:focus {
    color: #4338ca;
}

/* ================================================================
   SCORE PANEL
================================================================ */

.score-panel {
    display: flex;

    width: 100%;

    align-items: center;

    gap: 1rem;

    border:
        1px solid
        #ede9fe;

    border-radius: 1.25rem;

    background:
        linear-gradient(
            135deg,
            #faf5ff,
            #eef2ff
        );

    padding: 1rem;
}

@media (
    min-width: 1280px
) {
    .score-panel {
        width: 290px;
    }
}

.score-orb {
    display: flex;

    height: 5rem;
    width: 5rem;

    flex-shrink: 0;

    flex-direction: column;

    align-items: center;
    justify-content: center;

    border-radius: 1.25rem;

    background:
        linear-gradient(
            135deg,
            #4338ca,
            #7c3aed
        );

    color: white;

    box-shadow:
        0 16px 32px -20px
        rgba(
            79,
            70,
            229,
            0.75
        );
}

.score-orb strong {
    font-size: 1.65rem;

    font-weight: 900;

    line-height: 1.5rem;
}

/* ================================================================
   SUMMARY STATS
================================================================ */

.summary-stat {
    display: flex;

    align-items: center;

    gap: 0.75rem;

    border:
        1px solid
        #f1f5f9;

    border-radius: 1rem;

    background: #f8fafc;

    padding: 0.8rem;
}

.summary-stat > span {
    display: flex;

    height: 2.25rem;
    width: 2.25rem;

    align-items: center;
    justify-content: center;

    border-radius: 0.75rem;

    background: white;

    font-weight: 900;

    color: #6366f1;

    box-shadow:
        0 5px 15px -12px
        rgba(
            15,
            23,
            42,
            0.45
        );
}

.summary-stat p {
    font-size: 0.55rem;

    font-weight: 900;

    text-transform: uppercase;

    letter-spacing:
        0.08em;

    color: #94a3b8;
}

.summary-stat strong {
    display: block;

    margin-top: 0.15rem;

    font-size: 1rem;

    font-weight: 900;

    color: #1e293b;
}

/* ================================================================
   RULE CHIPS
================================================================ */

.rule-chip {
    display: inline-flex;

    align-items: center;

    gap: 0.3rem;

    border:
        1px solid
        #e0e7ff;

    border-radius: 0.65rem;

    background: #eef2ff;

    padding:
        0.38rem
        0.6rem;

    font-size: 0.62rem;

    color: #6366f1;
}

.rule-chip strong {
    font-weight: 900;
}

/* ================================================================
   NEW PREMIUM ACTIVE / INACTIVE BUTTON
================================================================ */

.premium-status-toggle {
    display: inline-flex;

    align-items: center;

    gap: 0.5rem;

    min-width: 108px;

    flex-shrink: 0;

    border: 1px solid;

    border-radius: 0.85rem;

    padding:
        0.55rem
        0.65rem;

    font-size: 0.68rem;

    font-weight: 900;

    transition:
        transform 0.25s ease,
        background 0.25s ease,
        border-color 0.25s ease,
        color 0.25s ease,
        box-shadow 0.25s ease;
}

.premium-status-toggle:hover {
    transform:
        translateY(-2px);
}

.premium-status-active {
    border-color: #a7f3d0;

    background:
        linear-gradient(
            135deg,
            #ecfdf5,
            #f0fdf4
        );

    color: #047857;

    box-shadow:
        0 10px 25px -20px
        rgba(
            16,
            185,
            129,
            0.7
        );
}

.premium-status-inactive {
    border-color: #e2e8f0;

    background:
        linear-gradient(
            135deg,
            #f8fafc,
            #ffffff
        );

    color: #64748b;
}

.premium-status-dot {
    position: relative;

    display: flex;

    height: 0.65rem;
    width: 0.65rem;

    flex-shrink: 0;

    align-items: center;
    justify-content: center;
}

.premium-status-dot-core {
    position: relative;

    z-index: 2;

    height: 0.5rem;
    width: 0.5rem;

    border-radius: 9999px;

    background: currentColor;
}

.premium-status-pulse {
    position: absolute;

    inset: 0;

    border-radius: 9999px;

    background: #10b981;

    opacity: 0.3;

    animation:
        statusPulse
        1.8s
        ease-out
        infinite;
}

.premium-status-icon {
    display: flex;

    height: 1.4rem;
    width: 1.4rem;

    margin-left: auto;

    align-items: center;
    justify-content: center;

    border-radius: 0.45rem;

    background:
        rgba(
            255,
            255,
            255,
            0.85
        );

    font-size: 0.6rem;

    font-weight: 900;

    box-shadow:
        0 3px 8px -6px
        rgba(
            15,
            23,
            42,
            0.4
        );
}

/* ================================================================
   WORKSPACE
================================================================ */

.workspace-card {
    overflow: hidden;

    border:
        1px solid
        rgba(
            226,
            232,
            240,
            0.85
        );

    border-radius: 1.5rem;

    background:
        rgba(
            255,
            255,
            255,
            0.9
        );

    box-shadow:
        0 18px 45px -36px
        rgba(
            15,
            23,
            42,
            0.42
        );

    backdrop-filter:
        blur(18px);

    transition:
        box-shadow 0.3s ease;
}

.workspace-card:hover {
    box-shadow:
        0 26px 50px -38px
        rgba(
            79,
            70,
            229,
            0.25
        );
}

.workspace-header {
    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 1rem;

    border-bottom:
        1px solid
        #f1f5f9;

    padding: 1.3rem;
}

.section-icon {
    display: flex;

    height: 2.8rem;
    width: 2.8rem;

    flex-shrink: 0;

    align-items: center;
    justify-content: center;

    border-radius: 0.9rem;

    font-size: 1rem;
    font-weight: 900;
}

.section-indigo {
    background: #eef2ff;

    color: #4f46e5;
}

.section-violet {
    background: #f5f3ff;

    color: #7c3aed;
}

.section-amber {
    background: #fffbeb;

    color: #d97706;
}

.section-eyebrow {
    font-size: 0.58rem;

    font-weight: 900;

    text-transform: uppercase;

    letter-spacing:
        0.13em;

    color: #6366f1;
}

.section-title {
    margin-top: 0.25rem;

    font-size: 1rem;

    font-weight: 900;

    color: #0f172a;
}

.section-description {
    margin-top: 0.25rem;

    font-size: 0.7rem;

    line-height: 1.25rem;

    color: #94a3b8;
}

.add-button {
    flex-shrink: 0;

    border:
        1px solid
        #e2e8f0;

    border-radius: 0.8rem;

    background: white;

    padding:
        0.6rem
        0.8rem;

    font-size: 0.68rem;

    font-weight: 900;

    color: #475569;

    transition:
        transform 0.2s ease,
        border-color 0.2s ease,
        color 0.2s ease,
        box-shadow 0.2s ease;
}

.add-button:hover {
    transform:
        translateY(-2px);

    border-color: #c7d2fe;

    color: #4f46e5;

    box-shadow:
        0 10px 20px -18px
        rgba(
            79,
            70,
            229,
            0.55
        );
}

.empty-section {
    padding:
        2.8rem
        1rem;

    text-align: center;
}

.empty-icon {
    display: flex;

    height: 3rem;
    width: 3rem;

    margin: auto;

    align-items: center;
    justify-content: center;

    border-radius: 1rem;

    background: #f8fafc;

    color: #94a3b8;
}

/* ================================================================
   RULES
================================================================ */

.rule-card {
    position: relative;

    display: flex;

    align-items: flex-start;

    gap: 1rem;

    border:
        1px solid
        #f1f5f9;

    border-radius: 1.15rem;

    background:
        linear-gradient(
            135deg,
            #f8fafc,
            white
        );

    padding: 1rem;

    transition:
        transform 0.25s ease,
        border-color 0.25s ease,
        box-shadow 0.25s ease;
}

.rule-card:hover {
    transform:
        translateY(-2px);

    border-color: #e0e7ff;

    box-shadow:
        0 12px 30px -28px
        rgba(
            79,
            70,
            229,
            0.4
        );
}

.rule-number {
    display: flex;

    height: 2rem;
    width: 2rem;

    flex-shrink: 0;

    align-items: center;
    justify-content: center;

    border-radius: 0.7rem;

    background:
        linear-gradient(
            135deg,
            #eef2ff,
            #ede9fe
        );

    font-size: 0.65rem;

    font-weight: 900;

    color: #6366f1;
}

/* ================================================================
   FIELDS
================================================================ */

.field-label {
    margin-bottom: 0.45rem;

    display: block;

    font-size: 0.58rem;

    font-weight: 900;

    text-transform: uppercase;

    letter-spacing:
        0.08em;

    color: #64748b;
}

.field-control {
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
        0.7rem
        0.8rem;

    font-size: 0.75rem;

    color: #334155;

    outline: none;

    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        background 0.2s ease;
}

.field-control:focus {
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

.field-control:disabled {
    cursor: not-allowed;

    opacity: 0.65;
}

.remove-button {
    display: flex;

    height: 2rem;
    width: 2rem;

    flex-shrink: 0;

    align-items: center;
    justify-content: center;

    border:
        1px solid
        #ffe4e6;

    border-radius: 0.65rem;

    background: white;

    color: #fb7185;

    transition:
        transform 0.2s ease,
        background 0.2s ease,
        color 0.2s ease;
}

.remove-button:hover {
    transform:
        rotate(5deg);

    background: #fff1f2;

    color: #e11d48;
}

/* ================================================================
   SCORING
================================================================ */

.scoring-card {
    border:
        1px solid
        #ede9fe;

    border-radius: 1.1rem;

    background:
        linear-gradient(
            135deg,
            #faf5ff,
            #ffffff
        );

    padding: 1rem;
}

.scoring-icon {
    display: flex;

    height: 2.4rem;
    width: 2.4rem;

    flex-shrink: 0;

    align-items: center;
    justify-content: center;

    border-radius: 0.8rem;

    background: #ede9fe;

    font-weight: 900;

    color: #7c3aed;
}

.weight-display {
    display: flex;

    min-width: 3.5rem;

    height: 2.7rem;

    align-items: center;
    justify-content: center;

    border-radius: 0.8rem;

    background:
        linear-gradient(
            135deg,
            #4f46e5,
            #7c3aed
        );

    padding:
        0
        0.7rem;

    font-size: 0.85rem;

    font-weight: 900;

    color: white;
}

/* ================================================================
   ALERTS
================================================================ */

.alert-card {
    border:
        1px solid
        #e2e8f0;

    border-radius: 1.15rem;

    background:
        linear-gradient(
            135deg,
            #ffffff,
            #f8fafc
        );

    padding: 1rem;

    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease,
        border-color 0.25s ease;
}

.alert-card:hover {
    transform:
        translateY(-2px);

    border-color: #ddd6fe;

    box-shadow:
        0 14px 30px -28px
        rgba(
            124,
            58,
            237,
            0.45
        );
}

.channel-icon {
    display: flex;

    height: 2.6rem;
    width: 2.6rem;

    align-items: center;
    justify-content: center;

    border-radius: 0.85rem;

    background:
        linear-gradient(
            135deg,
            #eef2ff,
            #f5f3ff
        );

    font-size: 1rem;

    font-weight: 900;

    color: #6366f1;
}

/* ================================================================
   FIXED MINI SWITCH
================================================================ */

.mini-switch {
    position: relative;

    height: 1.55rem;

    width: 2.8rem;

    flex-shrink: 0;

    overflow: hidden;

    border-radius: 9999px;

    transition:
        background 0.25s ease,
        box-shadow 0.25s ease;
}

.mini-switch-on {
    background:
        linear-gradient(
            135deg,
            #10b981,
            #059669
        );

    box-shadow:
        0 6px 14px -8px
        rgba(
            16,
            185,
            129,
            0.8
        );
}

.mini-switch-off {
    background: #cbd5e1;
}

.mini-switch span {
    position: absolute;

    left: 0.25rem;

    top: 0.25rem;

    height: 1.05rem;

    width: 1.05rem;

    border-radius: 9999px;

    background: white;

    box-shadow:
        0 2px 6px
        rgba(
            15,
            23,
            42,
            0.2
        );

    transition:
        transform 0.25s
        cubic-bezier(
            0.22,
            1,
            0.36,
            1
        );
}

.mini-thumb-off {
    transform:
        translateX(0);
}

.mini-thumb-on {
    transform:
        translateX(1.25rem);
}

.threshold-box {
    display: flex;

    height: 2.65rem;

    min-width: 4rem;

    align-items: center;
    justify-content: center;

    border-radius: 0.8rem;

    background:
        linear-gradient(
            135deg,
            #0f172a,
            #312e81
        );

    padding:
        0
        0.75rem;

    font-size: 0.8rem;

    font-weight: 900;

    color: white;
}

/* ================================================================
   SAVE BAR
================================================================ */

.save-bar {
    position: sticky;

    bottom: 1rem;

    z-index: 20;

    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 1rem;

    border:
        1px solid
        rgba(
            226,
            232,
            240,
            0.85
        );

    border-radius: 1.15rem;

    background:
        rgba(
            255,
            255,
            255,
            0.9
        );

    padding: 0.85rem;

    box-shadow:
        0 20px 50px -30px
        rgba(
            15,
            23,
            42,
            0.5
        );

    backdrop-filter:
        blur(20px);
}

.delete-profile-button {
    flex: 1;

    border:
        1px solid
        #fecdd3;

    border-radius: 0.8rem;

    background: white;

    padding:
        0.75rem
        1rem;

    font-size: 0.68rem;

    font-weight: 900;

    color: #e11d48;

    transition:
        transform 0.2s ease,
        background 0.2s ease;
}

.delete-profile-button:hover:not(:disabled) {
    transform:
        translateY(-2px);

    background: #fff1f2;
}

.save-profile-button {
    display: inline-flex;

    flex: 1;

    align-items: center;
    justify-content: center;

    gap: 0.5rem;

    border-radius: 0.8rem;

    background:
        linear-gradient(
            135deg,
            #0f172a,
            #312e81,
            #4f46e5
        );

    padding:
        0.75rem
        1.2rem;

    font-size: 0.68rem;

    font-weight: 900;

    color: white;

    box-shadow:
        0 14px 30px -22px
        rgba(
            79,
            70,
            229,
            0.8
        );

    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease;
}

.save-profile-button:hover:not(:disabled) {
    transform:
        translateY(-2px);

    box-shadow:
        0 18px 34px -22px
        rgba(
            79,
            70,
            229,
            0.9
        );
}

.save-profile-button:disabled,
.delete-profile-button:disabled {
    cursor: wait;

    opacity: 0.55;
}

.spinner {
    height: 0.8rem;

    width: 0.8rem;

    border:
        2px solid
        rgba(
            255,
            255,
            255,
            0.3
        );

    border-top-color: white;

    border-radius: 9999px;

    animation:
        spin
        0.65s
        linear
        infinite;
}

/* ================================================================
   MODAL
================================================================ */

.premium-modal {
    overflow: hidden;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.8
        );

    border-radius: 1.5rem;

    background:
        rgba(
            255,
            255,
            255,
            0.97
        );

    box-shadow:
        0 35px 80px -30px
        rgba(
            15,
            23,
            42,
            0.55
        );

    backdrop-filter:
        blur(24px);
}

.modal-header {
    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 1rem;

    border-bottom:
        1px solid
        #f1f5f9;

    padding:
        1.4rem
        1.5rem;
}

.close-button {
    display: flex;

    height: 2.5rem;

    width: 2.5rem;

    flex-shrink: 0;

    align-items: center;
    justify-content: center;

    border:
        1px solid
        #e2e8f0;

    border-radius: 0.8rem;

    background: white;

    font-size: 1.1rem;

    color: #64748b;

    transition:
        transform 0.3s ease,
        background 0.3s ease,
        color 0.3s ease;
}

.close-button:hover {
    transform:
        rotate(90deg);

    background: #fff1f2;

    color: #e11d48;
}

.create-button {
    border-radius: 0.8rem;

    background:
        linear-gradient(
            135deg,
            #0f172a,
            #312e81,
            #6d28d9
        );

    padding: 0.75rem;

    font-size: 0.72rem;

    font-weight: 900;

    color: white;

    box-shadow:
        0 14px 30px -22px
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
        opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.modal-enter-active,
.modal-leave-active {
    transition:
        opacity 0.25s ease,
        transform 0.3s
        cubic-bezier(
            0.22,
            1,
            0.36,
            1
        );
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;

    transform:
        translateY(15px)
        scale(0.98);
}

.toast-enter-active,
.toast-leave-active {
    transition:
        opacity 0.25s ease,
        transform 0.25s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;

    transform:
        translateY(-8px);
}

/* ================================================================
   ANIMATIONS
================================================================ */

@keyframes heroReveal {
    from {
        opacity: 0;

        transform:
            translateY(12px)
            scale(0.99);
    }

    to {
        opacity: 1;

        transform:
            translateY(0)
            scale(1);
    }
}

@keyframes spin {
    to {
        transform:
            rotate(360deg);
    }
}

@keyframes statusPulse {
    0% {
        transform:
            scale(1);

        opacity: 0.35;
    }

    70% {
        transform:
            scale(2.1);

        opacity: 0;
    }

    100% {
        transform:
            scale(2.1);

        opacity: 0;
    }
}

/* ================================================================
   MOBILE
================================================================ */

@media (
    max-width: 639px
) {
    .save-bar {
        align-items: stretch;
    }

    .premium-status-toggle {
        width: 100%;

        justify-content:
            flex-start;
    }
}

/* ================================================================
   ACCESSIBILITY
================================================================ */

@media (
    prefers-reduced-motion:
    reduce
) {
    .profile-hero,
    .spinner,
    .premium-status-pulse {
        animation: none;
    }

    .profile-selector,
    .workspace-card,
    .rule-card,
    .alert-card,
    .premium-status-toggle,
    .mini-switch,
    .mini-switch span {
        transition: none;
    }
}
</style>