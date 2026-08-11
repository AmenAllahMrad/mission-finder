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

const sources = ref([]);
const parsers = ref([]);

const loading = ref(true);
const savingId = ref(null);
const testingId = ref(null);
const deletingId = ref(null);
const creating = ref(false);

const error = ref(null);
const successMessage = ref(null);

/*
|--------------------------------------------------------------------------
| Create modal
|--------------------------------------------------------------------------
*/

const creationOuverte = ref(false);

const nouvelleSource = ref({
    nom: '',
    type: 'api',
    url_base: '',
    parser_class: '',
    frequence_polling_minutes: 60,
    actif: true,
    credentialsText: '',
});

/*
|--------------------------------------------------------------------------
| Test modal
|--------------------------------------------------------------------------
*/

const resultatTest = ref(null);
const sourceTestee = ref(null);
const modalTestOuvert = ref(false);

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

const parserLabel = (
    parserClass
) => {
    const parser =
        parsers.value.find(
            (item) =>
                item.class ===
                parserClass
        );

    if (parser) {
        return parser.label;
    }

    if (!parserClass) {
        return 'Non renseigné';
    }

    return parserClass
        .split('\\')
        .pop();
};

const labelType = (type) => {
    const labels = {
        api: 'API',
        rss: 'RSS',
        html: 'HTML Scraping',
        email: 'Email / IMAP',
    };

    return (
        labels[type] ??
        type ??
        'Inconnu'
    );
};

const formatDate = (date) => {
    if (!date) {
        return 'Jamais';
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

const pollingLisible = (
    minutes
) => {
    const valeur =
        Number(minutes);

    if (!valeur) {
        return 'Non configuré';
    }

    if (valeur < 60) {
        return `${valeur} min`;
    }

    if (
        valeur % 1440 === 0
    ) {
        const jours =
            valeur / 1440;

        return `${jours} jour(s)`;
    }

    if (
        valeur % 60 === 0
    ) {
        const heures =
            valeur / 60;

        return `${heures} h`;
    }

    return `${valeur} min`;
};

const classeStatut = (
    statut
) => {
    if (!statut) {
        return (
            'bg-slate-100 text-slate-600'
        );
    }

    const value =
        String(statut)
            .toLowerCase();

    if (
        value.includes('success') ||
        value.includes('ok') ||
        value.includes('succès')
    ) {
        return (
            'bg-green-100 text-green-700'
        );
    }

    if (
        value.includes('error') ||
        value.includes('erreur') ||
        value.includes('failed') ||
        value.includes('échec')
    ) {
        return (
            'bg-red-100 text-red-700'
        );
    }

    return (
        'bg-amber-100 text-amber-700'
    );
};

const totalMissions = (
    source
) => {
    return Number(
        source.mission_occurrences_count ??
        source.missions_count ??
        0
    );
};

const sourceSupprimable = (
    source
) => {
    return (
        Number(
            source.missions_count ?? 0
        ) === 0 &&
        Number(
            source.mission_occurrences_count
                ?? 0
        ) === 0
    );
};

/*
|--------------------------------------------------------------------------
| Parser defaults
|--------------------------------------------------------------------------
*/

const urlParParser = (
    parserClass
) => {
    const defaults = {
        'App\\Scrapers\\RemoteOkParser':
            'https://remoteok.com/api',

        'App\\Scrapers\\WeWorkRemotelyParser':
            'https://weworkremotely.com/remote-jobs.rss',
    };

    return (
        defaults[
            parserClass
        ] ?? ''
    );
};

const appliquerParserNouvelleSource = () => {
    const parser =
        parsers.value.find(
            (item) =>
                item.class ===
                nouvelleSource.value.parser_class
        );

    if (!parser) {
        return;
    }

    nouvelleSource.value.type =
        parser.type;

    /*
     * Change toujours l'URL lorsque
     * le parser change.
     *
     * LinkedIn Email => URL vide
     */
    nouvelleSource.value.url_base =
        urlParParser(
            parser.class
        );

    nouvelleSource.value.nom =
        parser.label;
};


const appliquerParserSource = (source) => {
    const parser =
        parsers.value.find(
            (item) =>
                item.class ===
                source.parser_class
        );

    if (!parser) {
        return;
    }

    source.type =
        parser.type;

    source.url_base =
        urlParParser(
            parser.class
        );
};

/*
|--------------------------------------------------------------------------
| Load
|--------------------------------------------------------------------------
*/

const chargerParsers = async () => {
    const response =
        await axios.get(
            '/api/source-parsers'
        );

    parsers.value =
        response.data;
};

const chargerSources = async () => {
    const response =
        await axios.get(
            '/api/sources'
        );

    sources.value =
        response.data.map(
            (source) => ({
                ...source,

                actif:
                    Boolean(
                        source.actif
                    ),

                frequence_polling_minutes:
                    Number(
                        source
                            .frequence_polling_minutes
                    ),

                /*
                 * Jamais alimenté depuis
                 * l'API avec un secret.
                 */
                credentialsText: '',
            })
        );
};

const chargerDonnees =
    async () => {
        loading.value = true;
        error.value = null;

        try {
            await Promise.all([
                chargerParsers(),
                chargerSources(),
            ]);
        } catch (err) {
            console.error(
                err
            );

            error.value =
                'Impossible de charger les sources.';
        } finally {
            loading.value =
                false;
        }
    };

/*
|--------------------------------------------------------------------------
| Credentials
|--------------------------------------------------------------------------
*/

const parseCredentials = (
    texte
) => {
    const value =
        texte?.trim();

    if (!value) {
        return undefined;
    }

    const parsed =
        JSON.parse(value);

    if (
        typeof parsed !==
            'object' ||
        parsed === null ||
        Array.isArray(parsed)
    ) {
        throw new Error(
            'Les credentials doivent être un objet JSON.'
        );
    }

    return parsed;
};

/*
|--------------------------------------------------------------------------
| Save existing source
|--------------------------------------------------------------------------
*/

const sauvegarderSource =
    async (source) => {
        savingId.value =
            source.id;

        error.value = null;
        successMessage.value =
            null;

        try {
            const payload = {
                nom:
                    source.nom,

                type:
                    source.type,

                url_base:
                    source.url_base ||
                    null,

                parser_class:
                    source.parser_class,

                frequence_polling_minutes:
                    Number(
                        source
                            .frequence_polling_minutes
                    ),

                actif:
                    Boolean(
                        source.actif
                    ),
            };

            /*
             * Si la zone credentials est vide,
             * aucun secret existant n'est modifié.
             */
            const credentials =
                parseCredentials(
                    source
                        .credentialsText
                );

            if (
                credentials !==
                undefined
            ) {
                payload.credentials =
                    credentials;
            }

            const response =
                await axios.patch(
                    `/api/sources/${source.id}`,
                    payload
                );

            const updated =
                response.data.source;

            Object.assign(
                source,
                updated,
                {
                    credentialsText:
                        '',
                }
            );

            successMessage.value =
                `${source.nom} a été mise à jour.`;

            setTimeout(() => {
                successMessage.value =
                    null;
            }, 3500);
        } catch (err) {
            console.error(
                err
            );

            if (
                err instanceof
                SyntaxError
            ) {
                error.value =
                    'Le JSON des credentials est invalide.';
            } else if (
                err.response
                    ?.status ===
                422
            ) {
                const errors =
                    err.response
                        .data?.errors;

                if (
                    errors &&
                    Object.keys(
                        errors
                    ).length
                ) {
                    const key =
                        Object.keys(
                            errors
                        )[0];

                    error.value =
                        errors[key]
                            ?.[0] ??
                        'Configuration invalide.';
                } else {
                    error.value =
                        err.response
                            .data
                            ?.message ??
                        'Configuration invalide.';
                }
            } else {
                error.value =
                    'Impossible de sauvegarder la source.';
            }
        } finally {
            savingId.value =
                null;
        }
    };

/*
|--------------------------------------------------------------------------
| Create
|--------------------------------------------------------------------------
*/

const ouvrirCreation =
    () => {
        nouvelleSource.value = {
            nom: '',
            type: 'api',
            url_base: '',
            parser_class:
                parsers.value[0]
                    ?.class ?? '',
            frequence_polling_minutes:
                60,
            actif: true,
            credentialsText: '',
        };

        appliquerParserNouvelleSource();

        creationOuverte.value =
            true;

        error.value = null;
    };

const fermerCreation =
    () => {
        creationOuverte.value =
            false;
    };

const creerSource =
    async () => {
        creating.value = true;
        error.value = null;

        try {
            const payload = {
                nom:
                    nouvelleSource
                        .value.nom,

                type:
                    nouvelleSource
                        .value.type,

                url_base:
                    nouvelleSource
                        .value
                        .url_base ||
                    null,

                parser_class:
                    nouvelleSource
                        .value
                        .parser_class,

                frequence_polling_minutes:
                    Number(
                        nouvelleSource
                            .value
                            .frequence_polling_minutes
                    ),

                actif:
                    Boolean(
                        nouvelleSource
                            .value
                            .actif
                    ),
            };

            const credentials =
                parseCredentials(
                    nouvelleSource
                        .value
                        .credentialsText
                );

            if (
                credentials !==
                undefined
            ) {
                payload.credentials =
                    credentials;
            }

            const response =
                await axios.post(
                    '/api/sources',
                    payload
                );

            sources.value.push({
                ...response.data
                    .source,

                credentialsText: '',
            });

            sources.value.sort(
                (a, b) =>
                    a.nom.localeCompare(
                        b.nom
                    )
            );

            creationOuverte.value =
                false;

            successMessage.value =
                'Source créée avec succès.';
        } catch (err) {
            console.error(
                err
            );

            if (
                err instanceof
                SyntaxError
            ) {
                error.value =
                    'Le JSON des credentials est invalide.';
            } else if (
                err.response
                    ?.status ===
                422
            ) {
                const errors =
                    err.response
                        .data?.errors;

                if (
                    errors &&
                    Object.keys(
                        errors
                    ).length
                ) {
                    const key =
                        Object.keys(
                            errors
                        )[0];

                    error.value =
                        errors[key]
                            ?.[0] ??
                        'Configuration invalide.';
                } else {
                    error.value =
                        err.response
                            .data
                            ?.message ??
                        'Configuration invalide.';
                }
            } else {
                error.value =
                    'Impossible de créer la source.';
            }
        } finally {
            creating.value =
                false;
        }
    };

/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/

const supprimerSource =
    async (source) => {
        if (
            !sourceSupprimable(
                source
            )
        ) {
            window.alert(
                'Cette source possède déjà des missions. Désactivez-la au lieu de la supprimer.'
            );

            return;
        }

        const confirmation =
            window.confirm(
                `Supprimer définitivement la source "${source.nom}" ?`
            );

        if (!confirmation) {
            return;
        }

        deletingId.value =
            source.id;

        error.value = null;

        try {
            await axios.delete(
                `/api/sources/${source.id}`
            );

            sources.value =
                sources.value.filter(
                    (item) =>
                        item.id !==
                        source.id
                );

            successMessage.value =
                'Source supprimée avec succès.';
        } catch (err) {
            console.error(
                err
            );

            error.value =
                err.response?.data
                    ?.message ??
                'Impossible de supprimer cette source.';
        } finally {
            deletingId.value =
                null;
        }
    };

/*
|--------------------------------------------------------------------------
| Test source
|--------------------------------------------------------------------------
*/

const testerSource =
    async (source) => {
        testingId.value =
            source.id;

        error.value = null;

        try {
            const response =
                await axios.post(
                    `/api/sources/${source.id}/test`
                );

            sourceTestee.value =
                source;

            resultatTest.value =
                response.data;

            modalTestOuvert.value =
                true;
        } catch (err) {
            console.error(
                err
            );

            sourceTestee.value =
                source;

            resultatTest.value = {
                success: false,

                message:
                    err.response
                        ?.data
                        ?.message ??
                    'Le test de la source a échoué.',

                items_count:
                    err.response
                        ?.data
                        ?.items_count ??
                    0,

                duration_ms:
                    err.response
                        ?.data
                        ?.duration_ms ??
                    null,

                sample: null,
            };

            modalTestOuvert.value =
                true;
        } finally {
            testingId.value =
                null;
        }
    };

const fermerTest = () => {
    modalTestOuvert.value =
        false;

    resultatTest.value =
        null;

    sourceTestee.value =
        null;
};

/*
|--------------------------------------------------------------------------
| Sample helpers
|--------------------------------------------------------------------------
*/

const descriptionCourte =
    computed(() => {
        const description =
            resultatTest.value
                ?.sample
                ?.description;

        if (!description) {
            return null;
        }

        const div =
            document.createElement(
                'div'
            );

        div.innerHTML =
            description;

        const texte =
            div.textContent ||
            div.innerText ||
            '';

        if (
            texte.length > 350
        ) {
            return (
                texte.slice(
                    0,
                    350
                ) + '…'
            );
        }

        return texte;
    });

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
        <!-- Header -->

        <div
            class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h2
                    class="text-3xl font-bold text-slate-900"
                >
                    Sources
                </h2>

                <p
                    class="mt-2 text-slate-500"
                >
                    Configurez et testez les sources
                    utilisées pour collecter les missions.
                </p>
            </div>

            <div
                class="flex items-center gap-3"
            >
                <span
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
                >
                    {{ sources.length }}
                    source(s)
                </span>

                <button
                    type="button"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                    @click="
                        ouvrirCreation
                    "
                >
                    + Nouvelle source
                </button>
            </div>
        </div>

        <!-- Messages -->

        <div
            v-if="successMessage"
            class="mb-6 rounded-xl border border-green-200 bg-green-50 px-5 py-4 text-green-700"
        >
            ✓ {{ successMessage }}
        </div>

        <div
            v-if="error"
            class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-red-700"
        >
            {{ error }}
        </div>

        <!-- Loading -->

        <div
            v-if="loading"
            class="rounded-xl border border-slate-200 bg-white p-12 text-center text-slate-500 shadow-sm"
        >
            Chargement des sources...
        </div>

        <!-- Empty -->

        <div
            v-else-if="
                sources.length === 0
            "
            class="rounded-xl border border-slate-200 bg-white p-12 text-center shadow-sm"
        >
            <div class="text-5xl">
                📡
            </div>

            <h3
                class="mt-4 text-xl font-semibold"
            >
                Aucune source
            </h3>

            <button
                type="button"
                class="mt-6 rounded-lg bg-slate-900 px-5 py-3 font-semibold text-white"
                @click="
                    ouvrirCreation
                "
            >
                + Ajouter une source
            </button>
        </div>

        <!-- Sources -->

        <div
            v-else
            class="grid gap-6 xl:grid-cols-2"
        >
            <article
                v-for="
                    source in sources
                "
                :key="source.id"
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            >
                <!-- Card header -->

                <div
                    class="border-b border-slate-100 px-6 py-5"
                >
                    <div
                        class="flex items-start justify-between gap-5"
                    >
                        <div>
                            <div
                                class="flex flex-wrap items-center gap-2"
                            >
                                <h3
                                    class="text-xl font-bold text-slate-900"
                                >
                                    {{ source.nom }}
                                </h3>

                                <span
                                    class="rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600"
                                >
                                    {{
                                        labelType(
                                            source.type
                                        )
                                    }}
                                </span>
                            </div>

                            <p
                                class="mt-2 text-sm text-slate-500"
                            >
                                {{
                                    parserLabel(
                                        source.parser_class
                                    )
                                }}
                            </p>
                        </div>

                        <span
                            v-if="source.actif"
                            class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700"
                        >
                            ● Active
                        </span>

                        <span
                            v-else
                            class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500"
                        >
                            ● Inactive
                        </span>
                    </div>
                </div>

                <!-- Stats -->

                <div
                    class="grid grid-cols-2 gap-4 border-b border-slate-100 px-6 py-5 sm:grid-cols-4"
                >
                    <div>
                        <p
                            class="text-xs font-semibold uppercase text-slate-400"
                        >
                            Missions
                        </p>

                        <p
                            class="mt-1 text-lg font-bold"
                        >
                            {{
                                totalMissions(
                                    source
                                )
                            }}
                        </p>
                    </div>

                    <div>
                        <p
                            class="text-xs font-semibold uppercase text-slate-400"
                        >
                            Polling
                        </p>

                        <p
                            class="mt-1 text-lg font-bold"
                        >
                            {{
                                pollingLisible(
                                    source.frequence_polling_minutes
                                )
                            }}
                        </p>
                    </div>

                    <div>
                        <p
                            class="text-xs font-semibold uppercase text-slate-400"
                        >
                            Dernier run
                        </p>

                        <p
                            class="mt-1 text-xs font-medium"
                        >
                            {{
                                formatDate(
                                    source.derniere_execution
                                )
                            }}
                        </p>
                    </div>

                    <div>
                        <p
                            class="text-xs font-semibold uppercase text-slate-400"
                        >
                            Statut
                        </p>

                        <span
                            :class="
                                classeStatut(
                                    source.dernier_statut
                                )
                            "
                            class="mt-1 inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                        >
                            {{
                                source.dernier_statut ||
                                'Aucun'
                            }}
                        </span>
                    </div>
                </div>

                <!-- Form -->

                <div
                    class="space-y-5 px-6 py-6"
                >
                    <div
                        class="grid gap-4 sm:grid-cols-2"
                    >
                        <!-- Name -->

                        <div>
                            <label
                                class="mb-2 block text-xs font-semibold uppercase text-slate-400"
                            >
                                Nom
                            </label>

                            <input
                                v-model="
                                    source.nom
                                "
                                type="text"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2"
                            >
                        </div>

                        <!-- Parser -->

                        <div>
                            <label
                                class="mb-2 block text-xs font-semibold uppercase text-slate-400"
                            >
                                Parser
                            </label>

                            <select
                                v-model="
                                    source.parser_class
                                "
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                                @change="
                                    appliquerParserSource(
                                        source
                                    )
                                "
                            >
                                <option
                                    v-for="
                                        parser in parsers
                                    "
                                    :key="
                                        parser.class
                                    "
                                    :value="
                                        parser.class
                                    "
                                >
                                    {{
                                        parser.label
                                    }}
                                </option>
                            </select>
                        </div>

                        <!-- Type -->

                        <div>
                            <label
                                class="mb-2 block text-xs font-semibold uppercase text-slate-400"
                            >
                                Type
                            </label>

                            <select
                                v-model="
                                    source.type
                                "
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                            >
                                <option value="api">
                                    API
                                </option>

                                <option value="rss">
                                    RSS
                                </option>

                                <option value="html">
                                    HTML
                                </option>

                                <option value="email">
                                    Email / IMAP
                                </option>
                            </select>
                        </div>

                        <!-- Frequency -->

                        <div>
                            <label
                                class="mb-2 block text-xs font-semibold uppercase text-slate-400"
                            >
                                Fréquence (minutes)
                            </label>

                            <input
                                v-model.number="
                                    source.frequence_polling_minutes
                                "
                                type="number"
                                min="1"
                                max="10080"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2"
                            >
                        </div>
                    </div>

                    <!-- URL -->

                    <div>
                        <label
                            class="mb-2 block text-xs font-semibold uppercase text-slate-400"
                        >
                            URL
                        </label>

                        <input
                            v-model="
                                source.url_base
                            "
                            type="url"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        >
                    </div>

                    <!-- Credentials -->

                    <div
                        class="rounded-xl border border-slate-200 bg-slate-50 p-4"
                    >
                        <div
                            class="flex items-center justify-between gap-3"
                        >
                            <div>
                                <p
                                    class="text-sm font-semibold text-slate-800"
                                >
                                    🔐 Credentials
                                </p>

                                <p
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    Le contenu existant n'est jamais renvoyé par l'API.
                                </p>
                            </div>

                            <span
                                v-if="
                                    source.credentials_configured
                                "
                                class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700"
                            >
                                Configurés
                            </span>

                            <span
                                v-else
                                class="rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold text-slate-600"
                            >
                                Aucun
                            </span>
                        </div>

                        <textarea
                            v-model="
                                source.credentialsText
                            "
                            rows="3"
                            placeholder='Optionnel — ex: {"username":"...","token":"..."}'
                            class="mt-4 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm"
                        ></textarea>

                        <p
                            class="mt-2 text-xs text-slate-500"
                        >
                            Laissez vide pour conserver les credentials existants.
                        </p>
                    </div>

                    <!-- Active -->

                    <div
                        class="flex items-center justify-between rounded-xl bg-slate-50 p-4"
                    >
                        <div>
                            <p
                                class="font-semibold text-slate-800"
                            >
                                Collecte active
                            </p>

                            <p
                                class="mt-1 text-xs text-slate-500"
                            >
                                Le scheduler peut exécuter cette source.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="relative h-7 w-12 rounded-full transition"
                            :class="
                                source.actif
                                    ? 'bg-green-500'
                                    : 'bg-slate-300'
                            "
                            @click="
                                source.actif =
                                    !source.actif
                            "
                        >
                            <span
                                class="absolute left-0 top-0 h-5 w-5 rounded-full bg-white shadow transition"
                                :class="
                                    source.actif
                                        ? 'translate-x-6 translate-y-1'
                                        : 'translate-x-1 translate-y-1'
                                "
                            ></span>
                        </button>
                    </div>

                    <!-- Actions -->

                    <div
                        class="grid gap-3 sm:grid-cols-3"
                    >
                        <button
                            type="button"
                            :disabled="
                                testingId ===
                                source.id
                            "
                            class="rounded-xl border border-blue-200 px-4 py-3 text-sm font-semibold text-blue-700 hover:bg-blue-50 disabled:opacity-50"
                            @click="
                                testerSource(
                                    source
                                )
                            "
                        >
                            {{
                                testingId ===
                                source.id
                                    ? 'Test...'
                                    : '🧪 Tester'
                            }}
                        </button>

                        <button
                            type="button"
                            :disabled="
                                savingId ===
                                source.id
                            "
                            class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-50"
                            @click="
                                sauvegarderSource(
                                    source
                                )
                            "
                        >
                            {{
                                savingId ===
                                source.id
                                    ? 'Sauvegarde...'
                                    : 'Save'
                            }}
                        </button>

                        <button
                            type="button"
                            :disabled="
                                deletingId ===
                                    source.id ||
                                !sourceSupprimable(
                                    source
                                )
                            "
                            class="rounded-xl border border-red-200 px-4 py-3 text-sm font-semibold text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                            @click="
                                supprimerSource(
                                    source
                                )
                            "
                        >
                            🗑 Supprimer
                        </button>
                    </div>

                    <p
                        v-if="
                            !sourceSupprimable(
                                source
                            )
                        "
                        class="text-center text-xs text-slate-500"
                    >
                        Cette source possède déjà des données : désactivez-la plutôt que de la supprimer.
                    </p>
                </div>
            </article>
        </div>

        <!-- ========================================================= -->
        <!-- CREATE MODAL -->
        <!-- ========================================================= -->

        <div
            v-if="creationOuverte"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/50 px-4 py-8"
            @click.self="
                fermerCreation
            "
        >
            <div
                class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl"
            >
                <div
                    class="flex items-start justify-between"
                >
                    <div>
                        <h3
                            class="text-xl font-bold"
                        >
                            Nouvelle source
                        </h3>

                        <p
                            class="mt-1 text-sm text-slate-500"
                        >
                            Ajoutez un connecteur à MissionFinder.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100"
                        @click="
                            fermerCreation
                        "
                    >
                        ×
                    </button>
                </div>

                <div
                    class="mt-6 space-y-4"
                >
                    <div>
                        <label
                            class="mb-2 block text-sm font-semibold"
                        >
                            Parser
                        </label>

                        <select
                            v-model="
                                nouvelleSource.parser_class
                            "
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-3"
                            @change="
                                appliquerParserNouvelleSource
                            "
                        >
                            <option
                                v-for="
                                    parser in parsers
                                "
                                :key="
                                    parser.class
                                "
                                :value="
                                    parser.class
                                "
                            >
                                {{
                                    parser.label
                                }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="mb-2 block text-sm font-semibold"
                        >
                            Nom
                        </label>

                        <input
                            v-model="
                                nouvelleSource.nom
                            "
                            type="text"
                            class="w-full rounded-lg border border-slate-300 px-3 py-3"
                        >
                    </div>

                    <div
                        class="grid gap-4 sm:grid-cols-2"
                    >
                        <div>
                            <label
                                class="mb-2 block text-sm font-semibold"
                            >
                                Type
                            </label>

                            <select
                                v-model="
                                    nouvelleSource.type
                                "
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-3"
                            >
                                <option value="api">
                                    API
                                </option>

                                <option value="rss">
                                    RSS
                                </option>

                                <option value="html">
                                    HTML
                                </option>

                                <option value="email">
                                    Email
                                </option>
                            </select>
                        </div>

                        <div>
                            <label
                                class="mb-2 block text-sm font-semibold"
                            >
                                Polling
                            </label>

                            <input
                                v-model.number="
                                    nouvelleSource.frequence_polling_minutes
                                "
                                type="number"
                                min="1"
                                max="10080"
                                class="w-full rounded-lg border border-slate-300 px-3 py-3"
                            >
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-2 block text-sm font-semibold"
                        >
                            URL
                        </label>

                        <input
                            v-model="
                                nouvelleSource.url_base
                            "
                            type="url"
                            class="w-full rounded-lg border border-slate-300 px-3 py-3"
                        >
                    </div>

                    <div>
                        <label
                            class="mb-2 block text-sm font-semibold"
                        >
                            Credentials JSON
                            <span
                                class="font-normal text-slate-400"
                            >
                                (optionnel)
                            </span>
                        </label>

                        <textarea
                            v-model="
                                nouvelleSource.credentialsText
                            "
                            rows="3"
                            placeholder='{"token":"..."}'
                            class="w-full rounded-lg border border-slate-300 px-3 py-3 font-mono text-sm"
                        ></textarea>
                    </div>

                    <label
                        class="flex cursor-pointer items-center justify-between rounded-xl bg-slate-50 p-4"
                    >
                        <div>
                            <p class="font-semibold">
                                Active
                            </p>

                            <p
                                class="mt-1 text-xs text-slate-500"
                            >
                                Autoriser la collecte automatique.
                            </p>
                        </div>

                        <input
                            v-model="
                                nouvelleSource.actif
                            "
                            type="checkbox"
                            class="h-5 w-5"
                        >
                    </label>
                </div>

                <div
                    class="mt-6 flex gap-3"
                >
                    <button
                        type="button"
                        class="flex-1 rounded-xl border border-slate-300 px-4 py-3 font-semibold"
                        @click="
                            fermerCreation
                        "
                    >
                        Annuler
                    </button>

                    <button
                        type="button"
                        :disabled="creating"
                        class="flex-1 rounded-xl bg-slate-900 px-4 py-3 font-semibold text-white disabled:opacity-50"
                        @click="
                            creerSource
                        "
                    >
                        {{
                            creating
                                ? 'Création...'
                                : 'Créer'
                        }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- TEST RESULT MODAL -->
        <!-- ========================================================= -->

        <div
            v-if="
                modalTestOuvert &&
                resultatTest
            "
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/50 px-4 py-8"
            @click.self="
                fermerTest
            "
        >
            <div
                class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl"
            >
                <div
                    class="flex items-start justify-between gap-4"
                >
                    <div>
                        <p
                            class="text-sm text-slate-500"
                        >
                            Test source
                        </p>

                        <h3
                            class="mt-1 text-xl font-bold"
                        >
                            {{
                                sourceTestee?.nom
                            }}
                        </h3>
                    </div>

                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100"
                        @click="
                            fermerTest
                        "
                    >
                        ×
                    </button>
                </div>

                <div
                    class="mt-6 rounded-xl p-5"
                    :class="
                        resultatTest.success
                            ? 'border border-green-200 bg-green-50'
                            : 'border border-red-200 bg-red-50'
                    "
                >
                    <p
                        class="font-semibold"
                        :class="
                            resultatTest.success
                                ? 'text-green-700'
                                : 'text-red-700'
                        "
                    >
                        {{
                            resultatTest.success
                                ? '✓ Test réussi'
                                : '✕ Test échoué'
                        }}
                    </p>

                    <p
                        class="mt-2 text-sm"
                    >
                        {{
                            resultatTest.message
                        }}
                    </p>
                </div>

                <div
                    class="mt-5 grid gap-4 sm:grid-cols-2"
                >
                    <div
                        class="rounded-xl bg-slate-50 p-4"
                    >
                        <p
                            class="text-xs font-semibold uppercase text-slate-400"
                        >
                            Éléments trouvés
                        </p>

                        <p
                            class="mt-1 text-2xl font-bold"
                        >
                            {{
                                resultatTest.items_count
                                ?? 0
                            }}
                        </p>
                    </div>

                    <div
                        class="rounded-xl bg-slate-50 p-4"
                    >
                        <p
                            class="text-xs font-semibold uppercase text-slate-400"
                        >
                            Durée
                        </p>

                        <p
                            class="mt-1 text-2xl font-bold"
                        >
                            {{
                                resultatTest.duration_ms
                                ?? 0
                            }}
                            ms
                        </p>
                    </div>
                </div>

                <div
                    v-if="
                        resultatTest.sample
                    "
                    class="mt-6 rounded-xl border border-slate-200 p-5"
                >
                    <p
                        class="text-xs font-semibold uppercase text-slate-400"
                    >
                        Exemple normalisé
                    </p>

                    <h4
                        class="mt-3 text-lg font-bold"
                    >
                        {{
                            resultatTest.sample
                                .titre
                        }}
                    </h4>

                    <p
                        class="mt-1 text-sm text-slate-500"
                    >
                        {{
                            resultatTest.sample
                                .entreprise ||
                            'Entreprise non renseignée'
                        }}
                    </p>

                    <div
                        class="mt-4 grid gap-3 sm:grid-cols-2"
                    >
                        <p class="text-sm">
                            <strong>
                                Remote :
                            </strong>

                            {{
                                resultatTest.sample
                                    .remote_type ||
                                '—'
                            }}
                        </p>

                        <p class="text-sm">
                            <strong>
                                Localisation :
                            </strong>

                            {{
                                resultatTest.sample
                                    .localisation ||
                                '—'
                            }}
                        </p>

                        <p class="text-sm">
                            <strong>
                                Date :
                            </strong>

                            {{
                                resultatTest.sample
                                    .date_publication ||
                                '—'
                            }}
                        </p>

                        <p class="text-sm">
                            <strong>
                                Secteur :
                            </strong>

                            {{
                                resultatTest.sample
                                    .secteur ||
                                '—'
                            }}
                        </p>
                    </div>

                    <div
                        v-if="
                            resultatTest.sample
                                .stacks?.length
                        "
                        class="mt-4 flex flex-wrap gap-2"
                    >
                        <span
                            v-for="
                                stack in
                                resultatTest.sample
                                    .stacks
                            "
                            :key="stack"
                            class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700"
                        >
                            {{ stack }}
                        </span>
                    </div>

                    <p
                        v-if="
                            descriptionCourte
                        "
                        class="mt-5 text-sm leading-6 text-slate-600"
                    >
                        {{ descriptionCourte }}
                    </p>
                </div>

                <button
                    type="button"
                    class="mt-6 w-full rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white"
                    @click="
                        fermerTest
                    "
                >
                    Fermer
                </button>
            </div>
        </div>
    </main>
</template>