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

const configurationsOuvertes =
    ref([]);

/*
|--------------------------------------------------------------------------
| Create modal
|--------------------------------------------------------------------------
*/

const creationOuverte =
    ref(false);

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

const resultatTest =
    ref(null);

const sourceTestee =
    ref(null);

const modalTestOuvert =
    ref(false);

/*
|--------------------------------------------------------------------------
| Global statistics
|--------------------------------------------------------------------------
*/

const sourcesActives =
    computed(
        () =>
            sources.value.filter(
                (source) =>
                    source.actif
            ).length
    );

const totalMissions =
    (source) => {
        return Number(
            source
                .mission_occurrences_count
            ??
            source
                .missions_count
            ??
            0
        );
    };

const missionsCollectees =
    computed(
        () =>
            sources.value.reduce(
                (
                    total,
                    source
                ) =>
                    total
                    +
                    totalMissions(
                        source
                    ),
                0
            )
    );

/*
|--------------------------------------------------------------------------
| Health
|--------------------------------------------------------------------------
*/

const statutSante =
    (source) => {
        if (
            !source.actif
        ) {
            return {
                key:
                    'inactive',

                label:
                    'Inactive',

                description:
                    'Collecte désactivée',
            };
        }

        if (
            !source
                .derniere_execution
        ) {
            return {
                key:
                    'pending',

                label:
                    'En attente',

                description:
                    'Jamais exécutée',
            };
        }

        const value =
            String(
                source
                    .dernier_statut
                ?? ''
            )
                .toLowerCase();

        if (
            value.includes(
                'ok'
            ) ||
            value.includes(
                'success'
            ) ||
            value.includes(
                'succès'
            ) ||
            value.includes(
                'reussi'
            ) ||
            value.includes(
                'réussi'
            )
        ) {
            return {
                key:
                    'healthy',

                label:
                    'Healthy',

                description:
                    'Source opérationnelle',
            };
        }

        if (
            value.includes(
                'error'
            ) ||
            value.includes(
                'erreur'
            ) ||
            value.includes(
                'failed'
            ) ||
            value.includes(
                'échec'
            ) ||
            value.includes(
                'echec'
            )
        ) {
            return {
                key:
                    'error',

                label:
                    'Erreur',

                description:
                    'Intervention recommandée',
            };
        }

        return {
            key:
                'warning',

            label:
                'À vérifier',

            description:
                source
                    .dernier_statut
                ||
                'Statut inconnu',
        };
    };

const sourcesHealthy =
    computed(
        () =>
            sources.value.filter(
                (source) =>
                    statutSante(
                        source
                    ).key ===
                    'healthy'
            ).length
    );

const sourcesAttention =
    computed(
        () =>
            sources.value.filter(
                (source) => {
                    const key =
                        statutSante(
                            source
                        ).key;

                    return (
                        source.actif &&
                        (
                            key ===
                                'error'
                            ||
                            key ===
                                'warning'
                            ||
                            key ===
                                'pending'
                        )
                    );
                }
            ).length
    );

const classeHealthBadge =
    (source) => {
        const key =
            statutSante(
                source
            ).key;

        const classes = {
            healthy:
                'health-healthy',

            error:
                'health-error',

            warning:
                'health-warning',

            pending:
                'health-pending',

            inactive:
                'health-inactive',
        };

        return (
            classes[key]
            ??
            classes.pending
        );
    };

const classeHealthDot =
    (source) => {
        const key =
            statutSante(
                source
            ).key;

        const classes = {
            healthy:
                'bg-emerald-500',

            error:
                'bg-rose-500',

            warning:
                'bg-amber-500',

            pending:
                'bg-blue-400',

            inactive:
                'bg-slate-300',
        };

        return (
            classes[key]
            ??
            'bg-slate-300'
        );
    };

/*
|--------------------------------------------------------------------------
| Type
|--------------------------------------------------------------------------
*/

const labelType =
    (type) => {
        const labels = {
            api:
                'API',

            rss:
                'RSS Feed',

            html:
                'Web Scraping',

            email:
                'Email / IMAP',
        };

        return (
            labels[type]
            ??
            type
            ??
            'Inconnu'
        );
    };

const iconeType =
    (type) => {
        const icons = {
            api:
                '⌘',

            rss:
                '◌',

            html:
                '◇',

            email:
                '✉',
        };

        return (
            icons[type]
            ??
            '◎'
        );
    };

const classeType =
    (type) => {
        const classes = {
            api:
                'type-api',

            rss:
                'type-rss',

            html:
                'type-html',

            email:
                'type-email',
        };

        return (
            classes[type]
            ??
            'type-api'
        );
    };

/*
|--------------------------------------------------------------------------
| Parser
|--------------------------------------------------------------------------
*/

const parserLabel =
    (parserClass) => {
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
            return (
                'Non renseigné'
            );
        }

        return parserClass
            .split('\\')
            .pop();
    };

/*
|--------------------------------------------------------------------------
| Dates
|--------------------------------------------------------------------------
*/

const formatDate =
    (date) => {
        if (!date) {
            return 'Jamais';
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
                new Date(
                    date
                )
            );
    };

const formatDateRelative =
    (date) => {
        if (!date) {
            return 'Jamais exécutée';
        }

        const timestamp =
            new Date(
                date
            ).getTime();

        const difference =
            Date.now()
            -
            timestamp;

        const minutes =
            Math.max(
                0,
                Math.floor(
                    difference
                    /
                    60000
                )
            );

        if (
            minutes < 1
        ) {
            return (
                'À l’instant'
            );
        }

        if (
            minutes < 60
        ) {
            return `Il y a ${minutes} min`;
        }

        const heures =
            Math.floor(
                minutes / 60
            );

        if (
            heures < 24
        ) {
            return `Il y a ${heures} h`;
        }

        const jours =
            Math.floor(
                heures / 24
            );

        if (
            jours < 7
        ) {
            return `Il y a ${jours} j`;
        }

        return formatDate(
            date
        );
    };

/*
|--------------------------------------------------------------------------
| Polling
|--------------------------------------------------------------------------
*/

const pollingLisible =
    (minutes) => {
        const valeur =
            Number(
                minutes
            );

        if (!valeur) {
            return (
                'Non configuré'
            );
        }

        if (
            valeur < 60
        ) {
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

/*
|--------------------------------------------------------------------------
| Deletion
|--------------------------------------------------------------------------
*/

const sourceSupprimable =
    (source) => {
        return (
            Number(
                source
                    .missions_count
                ?? 0
            ) === 0
            &&
            Number(
                source
                    .mission_occurrences_count
                ?? 0
            ) === 0
        );
    };

/*
|--------------------------------------------------------------------------
| Configuration accordion
|--------------------------------------------------------------------------
*/

const configurationOuverte =
    (sourceId) => {
        return configurationsOuvertes
            .value
            .includes(
                sourceId
            );
    };

const toggleConfiguration =
    (sourceId) => {
        if (
            configurationOuverte(
                sourceId
            )
        ) {
            configurationsOuvertes.value =
                configurationsOuvertes
                    .value
                    .filter(
                        (id) =>
                            id !==
                            sourceId
                    );

            return;
        }

        configurationsOuvertes.value = [
            ...configurationsOuvertes
                .value,

            sourceId,
        ];
    };

/*
|--------------------------------------------------------------------------
| URLs
|--------------------------------------------------------------------------
*/

const urlParParser =
    (parserClass) => {
        const defaults = {
            'App\\Scrapers\\RemoteOkParser':
                'https://remoteok.com/api',

            'App\\Scrapers\\WeWorkRemotelyParser':
                'https://weworkremotely.com/remote-jobs.rss',

            'App\\Scrapers\\FreeWorkParser':
                'https://www.free-work.com/fr/tech-it/jobs?contracts=contractor',
        };

        return (
            defaults[
                parserClass
            ]
            ??
            ''
        );
    };

const appliquerParserNouvelleSource =
    () => {
        const parser =
            parsers.value.find(
                (item) =>
                    item.class ===
                    nouvelleSource
                        .value
                        .parser_class
            );

        if (!parser) {
            return;
        }

        nouvelleSource.value.type =
            parser.type;

        nouvelleSource.value.url_base =
            urlParParser(
                parser.class
            );

        nouvelleSource.value.nom =
            parser.label;
    };

const appliquerParserSource =
    (source) => {
        const parser =
            parsers.value.find(
                (item) =>
                    item.class ===
                    source
                        .parser_class
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
| Loading
|--------------------------------------------------------------------------
*/

const chargerParsers =
    async () => {
        const response =
            await axios.get(
                '/api/source-parsers'
            );

        parsers.value =
            response.data;
    };

const chargerSources =
    async () => {
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

                    credentialsText:
                        '',
                })
            );
    };

const chargerDonnees =
    async () => {
        loading.value =
            true;

        error.value =
            null;

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

const parseCredentials =
    (texte) => {
        const value =
            texte?.trim();

        if (!value) {
            return undefined;
        }

        const parsed =
            JSON.parse(
                value
            );

        if (
            typeof parsed !==
                'object'
            ||
            parsed ===
                null
            ||
            Array.isArray(
                parsed
            )
        ) {
            throw new Error(
                'Les credentials doivent être un objet JSON.'
            );
        }

        return parsed;
    };

/*
|--------------------------------------------------------------------------
| Success
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
            3500
        );
    };

/*
|--------------------------------------------------------------------------
| Save
|--------------------------------------------------------------------------
*/

const sauvegarderSource =
    async (source) => {
        savingId.value =
            source.id;

        error.value =
            null;

        successMessage.value =
            null;

        try {
            const payload = {
                nom:
                    source.nom,

                type:
                    source.type,

                url_base:
                    source.url_base
                    ||
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

            Object.assign(
                source,
                response.data
                    .source,
                {
                    credentialsText:
                        '',
                }
            );

            afficherSucces(
                `${source.nom} a été mise à jour.`
            );
        } catch (err) {
            console.error(
                err
            );

            gererErreurFormulaire(
                err,
                'Impossible de sauvegarder la source.'
            );
        } finally {
            savingId.value =
                null;
        }
    };

/*
|--------------------------------------------------------------------------
| Error helper
|--------------------------------------------------------------------------
*/

const gererErreurFormulaire =
    (
        err,
        fallback
    ) => {
        if (
            err instanceof
                SyntaxError
        ) {
            error.value =
                'Le JSON des credentials est invalide.';

            return;
        }

        if (
            err.response
                ?.status ===
                422
        ) {
            const errors =
                err.response
                    .data
                    ?.errors;

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
                    errors[key]?.[0]
                    ??
                    'Configuration invalide.';

                return;
            }

            error.value =
                err.response
                    .data
                    ?.message
                ??
                'Configuration invalide.';

            return;
        }

        error.value =
            fallback;
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
                    ?.class
                ?? '',
            frequence_polling_minutes:
                60,
            actif: true,
            credentialsText: '',
        };

        appliquerParserNouvelleSource();

        creationOuverte.value =
            true;

        error.value =
            null;
    };

const fermerCreation =
    () => {
        creationOuverte.value =
            false;
    };

const creerSource =
    async () => {
        creating.value =
            true;

        error.value =
            null;

        try {
            const payload = {
                nom:
                    nouvelleSource
                        .value
                        .nom,

                type:
                    nouvelleSource
                        .value
                        .type,

                url_base:
                    nouvelleSource
                        .value
                        .url_base
                    ||
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

                credentialsText:
                    '',
            });

            sources.value.sort(
                (a, b) =>
                    a.nom.localeCompare(
                        b.nom
                    )
            );

            creationOuverte.value =
                false;

            afficherSucces(
                'Source créée avec succès.'
            );
        } catch (err) {
            console.error(
                err
            );

            gererErreurFormulaire(
                err,
                'Impossible de créer la source.'
            );
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

        if (
            !confirmation
        ) {
            return;
        }

        deletingId.value =
            source.id;

        error.value =
            null;

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

            afficherSucces(
                'Source supprimée avec succès.'
            );
        } catch (err) {
            console.error(
                err
            );

            error.value =
                err.response
                    ?.data
                    ?.message
                ??
                'Impossible de supprimer cette source.';
        } finally {
            deletingId.value =
                null;
        }
    };

/*
|--------------------------------------------------------------------------
| Test
|--------------------------------------------------------------------------
*/

const testerSource =
    async (source) => {
        testingId.value =
            source.id;

        error.value =
            null;

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
                success:
                    false,

                message:
                    err.response
                        ?.data
                        ?.message
                    ??
                    'Le test de la source a échoué.',

                items_count:
                    err.response
                        ?.data
                        ?.items_count
                    ??
                    0,

                duration_ms:
                    err.response
                        ?.data
                        ?.duration_ms
                    ??
                    null,

                sample:
                    null,
            };

            modalTestOuvert.value =
                true;
        } finally {
            testingId.value =
                null;
        }
    };

const fermerTest =
    () => {
        modalTestOuvert.value =
            false;

        resultatTest.value =
            null;

        sourceTestee.value =
            null;
    };

/*
|--------------------------------------------------------------------------
| Test description
|--------------------------------------------------------------------------
*/

const descriptionCourte =
    computed(
        () => {
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
                div.textContent
                ||
                div.innerText
                ||
                '';

            if (
                texte.length >
                    350
            ) {
                return (
                    texte.slice(
                        0,
                        350
                    )
                    +
                    '…'
                );
            }

            return texte;
        }
    );

/*
|--------------------------------------------------------------------------
| Mounted
|--------------------------------------------------------------------------
*/

onMounted(
    () => {
        chargerDonnees();
    }
);
</script>

<template>
    <main
        class="sources-page relative min-h-screen overflow-hidden"
    >
        <!-- Atmosphere -->

        <div
            class="pointer-events-none absolute -left-40 top-20 h-[430px] w-[430px] rounded-full bg-indigo-400/10 blur-3xl"
        ></div>

        <div
            class="pointer-events-none absolute -right-40 top-[450px] h-[480px] w-[480px] rounded-full bg-violet-400/10 blur-3xl"
        ></div>

        <div
            class="relative mx-auto max-w-7xl px-6 py-10 lg:py-12"
        >
            <!-- ===================================================== -->
            <!-- HERO -->
            <!-- ===================================================== -->

            <section
                class="sources-hero relative mb-7 overflow-hidden rounded-[30px] border border-white/70 bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 p-7 text-white shadow-2xl shadow-indigo-200/40 lg:p-9"
            >
                <div
                    class="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-indigo-400/20 blur-3xl"
                ></div>

                <div
                    class="absolute bottom-0 left-1/3 h-40 w-40 rounded-full bg-violet-500/10 blur-3xl"
                ></div>

                <div
                    class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div
                        class="max-w-2xl"
                    >
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.16em] text-indigo-100 backdrop-blur"
                        >
                            <span
                                class="relative flex h-2 w-2"
                            >
                                <span
                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-40"
                                ></span>

                                <span
                                    class="relative h-2 w-2 rounded-full bg-emerald-400"
                                ></span>
                            </span>

                            Connector Intelligence
                        </div>

                        <h1
                            class="mt-5 text-3xl font-black tracking-tight sm:text-4xl"
                        >
                            Sources
                            <span
                                class="hero-accent"
                            >
                                Intelligence
                            </span>
                        </h1>

                        <p
                            class="mt-4 max-w-xl text-sm leading-7 text-slate-300 sm:text-base"
                        >
                            Supervisez vos connecteurs, contrôlez leur santé et gérez la collecte automatique des opportunités.
                        </p>

                        <button
                            type="button"
                            class="hero-button mt-6"
                            @click="
                                ouvrirCreation
                            "
                        >
                            <span>
                                ＋
                            </span>

                            Ajouter une source
                        </button>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-2"
                    >
                        <div
                            class="hero-stat"
                        >
                            <span
                                class="hero-stat-label"
                            >
                                Sources
                            </span>

                            <strong
                                class="hero-stat-value"
                            >
                                {{
                                    sources.length
                                }}
                            </strong>
                        </div>

                        <div
                            class="hero-stat"
                        >
                            <span
                                class="hero-stat-label"
                            >
                                Actives
                            </span>

                            <strong
                                class="hero-stat-value text-emerald-300"
                            >
                                {{
                                    sourcesActives
                                }}
                            </strong>
                        </div>

                        <div
                            class="hero-stat"
                        >
                            <span
                                class="hero-stat-label"
                            >
                                Healthy
                            </span>

                            <strong
                                class="hero-stat-value text-indigo-300"
                            >
                                {{
                                    sourcesHealthy
                                }}
                            </strong>
                        </div>

                        <div
                            class="hero-stat"
                        >
                            <span
                                class="hero-stat-label"
                            >
                                Missions
                            </span>

                            <strong
                                class="hero-stat-value"
                            >
                                {{
                                    missionsCollectees
                                }}
                            </strong>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===================================================== -->
            <!-- OVERVIEW -->
            <!-- ===================================================== -->

            <section
                class="mb-7 grid gap-4 md:grid-cols-3"
            >
                <article
                    class="overview-card"
                >
                    <div
                        class="overview-icon bg-emerald-50 text-emerald-600"
                    >
                        ✓
                    </div>

                    <div>
                        <p
                            class="overview-label"
                        >
                            Connecteurs opérationnels
                        </p>

                        <p
                            class="overview-value"
                        >
                            {{
                                sourcesHealthy
                            }}
                        </p>
                    </div>
                </article>

                <article
                    class="overview-card"
                >
                    <div
                        class="overview-icon bg-amber-50 text-amber-600"
                    >
                        !
                    </div>

                    <div>
                        <p
                            class="overview-label"
                        >
                            À surveiller
                        </p>

                        <p
                            class="overview-value"
                        >
                            {{
                                sourcesAttention
                            }}
                        </p>
                    </div>
                </article>

                <article
                    class="overview-card"
                >
                    <div
                        class="overview-icon bg-indigo-50 text-indigo-600"
                    >
                        ◈
                    </div>

                    <div>
                        <p
                            class="overview-label"
                        >
                            Occurrences collectées
                        </p>

                        <p
                            class="overview-value"
                        >
                            {{
                                missionsCollectees
                            }}
                        </p>
                    </div>
                </article>
            </section>

            <!-- Success -->

            <transition
                name="toast"
            >
                <div
                    v-if="
                        successMessage
                    "
                    class="mb-6 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/90 px-5 py-4 text-sm font-semibold text-emerald-700 shadow-lg shadow-emerald-100/50 backdrop-blur"
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
                class="mb-6 flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50/90 px-5 py-4 text-sm font-semibold text-rose-700 shadow-sm"
            >
                <span
                    class="text-xl"
                >
                    ⚠
                </span>

                {{ error }}
            </div>

            <!-- ===================================================== -->
            <!-- LOADING -->
            <!-- ===================================================== -->

            <div
                v-if="loading"
                class="grid gap-6 xl:grid-cols-2"
            >
                <div
                    v-for="
                        index in 4
                    "
                    :key="
                        index
                    "
                    class="h-[330px] animate-pulse rounded-[26px] border border-slate-200 bg-white"
                ></div>
            </div>

            <!-- ===================================================== -->
            <!-- EMPTY -->
            <!-- ===================================================== -->

            <div
                v-else-if="
                    sources.length === 0
                "
                class="rounded-[28px] border border-dashed border-slate-300 bg-white/80 px-6 py-16 text-center shadow-sm"
            >
                <div
                    class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 text-2xl text-indigo-600"
                >
                    ◎
                </div>

                <h3
                    class="mt-5 text-lg font-black text-slate-900"
                >
                    Aucune source configurée
                </h3>

                <p
                    class="mx-auto mt-2 max-w-sm text-sm leading-6 text-slate-500"
                >
                    Ajoutez votre premier connecteur pour commencer à collecter automatiquement des missions.
                </p>

                <button
                    type="button"
                    class="mt-6 rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white"
                    @click="
                        ouvrirCreation
                    "
                >
                    + Ajouter une source
                </button>
            </div>

            <!-- ===================================================== -->
            <!-- SOURCE CARDS -->
            <!-- ===================================================== -->

            <section
                v-else
                class="grid gap-6 xl:grid-cols-2"
            >
                <article
                    v-for="
                        source in sources
                    "
                    :key="
                        source.id
                    "
                    class="source-card group"
                >
                    <!-- Accent -->

                    <div
                        class="source-accent"
                        :class="
                            classeType(
                                source.type
                            )
                        "
                    ></div>

                    <!-- Card main -->

                    <div
                        class="p-6"
                    >
                        <!-- Header -->

                        <div
                            class="flex items-start justify-between gap-4"
                        >
                            <div
                                class="flex min-w-0 items-start gap-4"
                            >
                                <div
                                    class="source-icon"
                                    :class="
                                        classeType(
                                            source.type
                                        )
                                    "
                                >
                                    {{
                                        iconeType(
                                            source.type
                                        )
                                    }}
                                </div>

                                <div
                                    class="min-w-0"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h2
                                            class="truncate text-xl font-black tracking-tight text-slate-900"
                                        >
                                            {{
                                                source.nom
                                            }}
                                        </h2>

                                        <span
                                            class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500"
                                        >
                                            {{
                                                labelType(
                                                    source.type
                                                )
                                            }}
                                        </span>
                                    </div>

                                    <p
                                        class="mt-1 truncate text-xs font-medium text-slate-400"
                                    >
                                        {{
                                            parserLabel(
                                                source.parser_class
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="health-badge"
                                :class="
                                    classeHealthBadge(
                                        source
                                    )
                                "
                            >
                                <span
                                    class="h-2 w-2 rounded-full"
                                    :class="
                                        classeHealthDot(
                                            source
                                        )
                                    "
                                ></span>

                                {{
                                    statutSante(
                                        source
                                    ).label
                                }}
                            </div>
                        </div>

                        <!-- Health message -->

                        <div
                            class="mt-5 flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3"
                        >
                            <div
                                class="flex items-center gap-3"
                            >
                                <span
                                    class="relative flex h-2.5 w-2.5"
                                >
                                    <span
                                        v-if="
                                            statutSante(
                                                source
                                            ).key ===
                                                'healthy'
                                        "
                                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-30"
                                    ></span>

                                    <span
                                        class="relative h-2.5 w-2.5 rounded-full"
                                        :class="
                                            classeHealthDot(
                                                source
                                            )
                                        "
                                    ></span>
                                </span>

                                <div>
                                    <p
                                        class="text-xs font-bold text-slate-700"
                                    >
                                        {{
                                            statutSante(
                                                source
                                            ).description
                                        }}
                                    </p>

                                    <p
                                        class="mt-0.5 text-[10px] text-slate-400"
                                    >
                                        {{
                                            source.dernier_statut
                                            ||
                                            'Aucun statut enregistré'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <span
                                class="text-[10px] font-semibold text-slate-400"
                            >
                                {{
                                    formatDateRelative(
                                        source.derniere_execution
                                    )
                                }}
                            </span>
                        </div>

                        <!-- Metrics -->

                        <div
                            class="mt-5 grid grid-cols-3 gap-3"
                        >
                            <div
                                class="metric-card"
                            >
                                <p
                                    class="metric-label"
                                >
                                    Missions
                                </p>

                                <strong
                                    class="metric-value"
                                >
                                    {{
                                        totalMissions(
                                            source
                                        )
                                    }}
                                </strong>
                            </div>

                            <div
                                class="metric-card"
                            >
                                <p
                                    class="metric-label"
                                >
                                    Polling
                                </p>

                                <strong
                                    class="metric-value-small"
                                >
                                    {{
                                        pollingLisible(
                                            source.frequence_polling_minutes
                                        )
                                    }}
                                </strong>
                            </div>

                            <div
                                class="metric-card"
                            >
                                <p
                                    class="metric-label"
                                >
                                    Credentials
                                </p>

                                <strong
                                    class="metric-value-small"
                                    :class="
                                        source.credentials_configured
                                            ? 'text-emerald-600'
                                            : 'text-slate-400'
                                    "
                                >
                                    {{
                                        source.credentials_configured
                                            ? 'Configurés'
                                            : 'Aucun'
                                    }}
                                </strong>
                            </div>
                        </div>

                        <!-- URL -->

                        <div
                            v-if="
                                source.url_base
                            "
                            class="mt-4 rounded-xl border border-slate-100 bg-white px-3 py-2"
                        >
                            <p
                                class="truncate text-[10px] font-medium text-slate-400"
                                :title="
                                    source.url_base
                                "
                            >
                                {{
                                    source.url_base
                                }}
                            </p>
                        </div>

                        <!-- Actions -->

                        <div
                            class="mt-5 grid grid-cols-[1fr_1fr_auto] gap-2"
                        >
                            <button
                                type="button"
                                :disabled="
                                    testingId ===
                                    source.id
                                "
                                class="test-button"
                                @click="
                                    testerSource(
                                        source
                                    )
                                "
                            >
                                <span
                                    v-if="
                                        testingId ===
                                        source.id
                                    "
                                    class="spinner"
                                ></span>

                                <span v-else>
                                    ◉
                                </span>

                                {{
                                    testingId ===
                                    source.id
                                        ? 'Test...'
                                        : 'Tester'
                                }}
                            </button>

                            <button
                                type="button"
                                class="config-button"
                                @click="
                                    toggleConfiguration(
                                        source.id
                                    )
                                "
                            >
                                ⚙

                                {{
                                    configurationOuverte(
                                        source.id
                                    )
                                        ? 'Fermer'
                                        : 'Configurer'
                                }}
                            </button>

                            <a
                                v-if="
                                    source.url_base
                                "
                                :href="
                                    source.url_base
                                "
                                target="_blank"
                                rel="noopener noreferrer"
                                class="external-source-button"
                                title="Ouvrir la source"
                            >
                                ↗
                            </a>
                        </div>
                    </div>

                    <!-- ============================================= -->
                    <!-- CONFIGURATION -->
                    <!-- ============================================= -->

                    <transition
                        name="configuration"
                    >
                        <div
                            v-if="
                                configurationOuverte(
                                    source.id
                                )
                            "
                            class="configuration-panel"
                        >
                            <div
                                class="mb-5"
                            >
                                <p
                                    class="text-[10px] font-black uppercase tracking-[0.15em] text-indigo-500"
                                >
                                    Configuration
                                </p>

                                <h3
                                    class="mt-1 text-base font-black text-slate-900"
                                >
                                    Paramètres du connecteur
                                </h3>
                            </div>

                            <div
                                class="grid gap-4 sm:grid-cols-2"
                            >
                                <div>
                                    <label
                                        class="field-label"
                                    >
                                        Nom
                                    </label>

                                    <input
                                        v-model="
                                            source.nom
                                        "
                                        type="text"
                                        class="field-control"
                                    >
                                </div>

                                <div>
                                    <label
                                        class="field-label"
                                    >
                                        Parser
                                    </label>

                                    <select
                                        v-model="
                                            source.parser_class
                                        "
                                        class="field-control"
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

                                <div>
                                    <label
                                        class="field-label"
                                    >
                                        Type
                                    </label>

                                    <select
                                        v-model="
                                            source.type
                                        "
                                        class="field-control"
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

                                <div>
                                    <label
                                        class="field-label"
                                    >
                                        Polling (minutes)
                                    </label>

                                    <input
                                        v-model.number="
                                            source.frequence_polling_minutes
                                        "
                                        type="number"
                                        min="1"
                                        max="10080"
                                        class="field-control"
                                    >
                                </div>
                            </div>

                            <div
                                class="mt-4"
                            >
                                <label
                                    class="field-label"
                                >
                                    URL
                                </label>

                                <input
                                    v-model="
                                        source.url_base
                                    "
                                    type="url"
                                    class="field-control"
                                >
                            </div>

                            <!-- Credentials -->

                            <div
                                class="credentials-box mt-4"
                            >
                                <div
                                    class="flex items-start justify-between gap-4"
                                >
                                    <div>
                                        <div
                                            class="flex items-center gap-2"
                                        >
                                            <span>
                                                🔐
                                            </span>

                                            <p
                                                class="text-sm font-black text-slate-800"
                                            >
                                                Credentials
                                            </p>
                                        </div>

                                        <p
                                            class="mt-1 text-xs leading-5 text-slate-400"
                                        >
                                            Les secrets existants ne sont jamais renvoyés au navigateur.
                                        </p>
                                    </div>

                                    <span
                                        class="credential-badge"
                                        :class="
                                            source.credentials_configured
                                                ? 'credential-ready'
                                                : 'credential-empty'
                                        "
                                    >
                                        {{
                                            source.credentials_configured
                                                ? 'Configurés'
                                                : 'Non configurés'
                                        }}
                                    </span>
                                </div>

                                <textarea
                                    v-model="
                                        source.credentialsText
                                    "
                                    rows="3"
                                    placeholder='{"username":"...","token":"..."}'
                                    class="field-control mt-4 font-mono text-xs"
                                ></textarea>

                                <p
                                    class="mt-2 text-[10px] text-slate-400"
                                >
                                    Laissez vide pour conserver les secrets actuels.
                                </p>
                            </div>

                            <!-- Active -->

                            <div
                                class="mt-4 flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4"
                            >
                                <div>
                                    <p
                                        class="text-sm font-black text-slate-800"
                                    >
                                        Collecte automatique
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-slate-400"
                                    >
                                        Autoriser le scheduler à exécuter cette source.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="switch"
                                    :class="
                                        source.actif
                                            ? 'switch-on'
                                            : 'switch-off'
                                    "
                                    @click="
                                        source.actif =
                                            !source.actif
                                    "
                                >
                                    <span
                                        class="switch-thumb"
                                        :class="
                                            source.actif
                                                ? 'switch-thumb-on'
                                                : 'switch-thumb-off'
                                        "
                                    ></span>
                                </button>
                            </div>

                            <!-- Last run -->

                            <div
                                class="mt-4 grid gap-3 sm:grid-cols-2"
                            >
                                <div
                                    class="detail-mini"
                                >
                                    <p
                                        class="detail-mini-label"
                                    >
                                        Dernière exécution
                                    </p>

                                    <p
                                        class="detail-mini-value"
                                    >
                                        {{
                                            formatDate(
                                                source.derniere_execution
                                            )
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="detail-mini"
                                >
                                    <p
                                        class="detail-mini-label"
                                    >
                                        Dernier statut
                                    </p>

                                    <p
                                        class="detail-mini-value"
                                    >
                                        {{
                                            source.dernier_statut
                                            ||
                                            'Aucun'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <!-- Save/delete -->

                            <div
                                class="mt-5 flex flex-col gap-3 sm:flex-row"
                            >
                                <button
                                    type="button"
                                    :disabled="
                                        savingId ===
                                        source.id
                                    "
                                    class="save-button"
                                    @click="
                                        sauvegarderSource(
                                            source
                                        )
                                    "
                                >
                                    {{
                                        savingId ===
                                        source.id
                                            ? 'Enregistrement...'
                                            : '✓ Enregistrer'
                                    }}
                                </button>

                                <button
                                    type="button"
                                    :disabled="
                                        deletingId ===
                                            source.id
                                        ||
                                        !sourceSupprimable(
                                            source
                                        )
                                    "
                                    class="delete-button"
                                    @click="
                                        supprimerSource(
                                            source
                                        )
                                    "
                                >
                                    {{
                                        deletingId ===
                                        source.id
                                            ? 'Suppression...'
                                            : 'Supprimer'
                                    }}
                                </button>
                            </div>

                            <p
                                v-if="
                                    !sourceSupprimable(
                                        source
                                    )
                                "
                                class="mt-3 text-center text-[10px] leading-5 text-slate-400"
                            >
                                Cette source possède déjà des données et ne peut plus être supprimée. Désactivez-la si nécessaire.
                            </p>
                        </div>
                    </transition>
                </article>
            </section>
        </div>

        <!-- ========================================================= -->
        <!-- CREATE OVERLAY -->
        <!-- ========================================================= -->

        <transition
            name="fade"
        >
            <div
                v-if="
                    creationOuverte
                "
                class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm"
                @click="
                    fermerCreation
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
                    creationOuverte
                "
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-8"
            >
                <div
                    class="premium-modal w-full max-w-xl"
                    @click.stop
                >
                    <div
                        class="modal-header"
                    >
                        <div>
                            <div
                                class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.16em] text-indigo-500"
                            >
                                <span
                                    class="h-2 w-2 rounded-full bg-indigo-500"
                                ></span>

                                Nouveau connecteur
                            </div>

                            <h2
                                class="mt-2 text-2xl font-black tracking-tight text-slate-900"
                            >
                                Ajouter une source
                            </h2>

                            <p
                                class="mt-1 text-xs text-slate-400"
                            >
                                Connectez une nouvelle plateforme à MissionFinder.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="close-button"
                            @click="
                                fermerCreation
                            "
                        >
                            ×
                        </button>
                    </div>

                    <div
                        class="p-6"
                    >
                        <div
                            class="space-y-4"
                        >
                            <div>
                                <label
                                    class="field-label"
                                >
                                    Parser
                                </label>

                                <select
                                    v-model="
                                        nouvelleSource.parser_class
                                    "
                                    class="field-control"
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
                                    class="field-label"
                                >
                                    Nom
                                </label>

                                <input
                                    v-model="
                                        nouvelleSource.nom
                                    "
                                    type="text"
                                    class="field-control"
                                >
                            </div>

                            <div
                                class="grid gap-4 sm:grid-cols-2"
                            >
                                <div>
                                    <label
                                        class="field-label"
                                    >
                                        Type
                                    </label>

                                    <select
                                        v-model="
                                            nouvelleSource.type
                                        "
                                        class="field-control"
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

                                <div>
                                    <label
                                        class="field-label"
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
                                        class="field-control"
                                    >
                                </div>
                            </div>

                            <div>
                                <label
                                    class="field-label"
                                >
                                    URL
                                </label>

                                <input
                                    v-model="
                                        nouvelleSource.url_base
                                    "
                                    type="url"
                                    class="field-control"
                                >
                            </div>

                            <div>
                                <label
                                    class="field-label"
                                >
                                    Credentials JSON
                                    <span
                                        class="normal-case tracking-normal text-slate-300"
                                    >
                                        — optionnel
                                    </span>
                                </label>

                                <textarea
                                    v-model="
                                        nouvelleSource.credentialsText
                                    "
                                    rows="3"
                                    placeholder='{"token":"..."}'
                                    class="field-control font-mono text-xs"
                                ></textarea>
                            </div>

                            <div
                                class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4"
                            >
                                <div>
                                    <p
                                        class="text-sm font-black text-slate-800"
                                    >
                                        Source active
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-slate-400"
                                    >
                                        Activer immédiatement la collecte.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="switch"
                                    :class="
                                        nouvelleSource.actif
                                            ? 'switch-on'
                                            : 'switch-off'
                                    "
                                    @click="
                                        nouvelleSource.actif =
                                            !nouvelleSource.actif
                                    "
                                >
                                    <span
                                        class="switch-thumb"
                                        :class="
                                            nouvelleSource.actif
                                                ? 'switch-thumb-on'
                                                : 'switch-thumb-off'
                                        "
                                    ></span>
                                </button>
                            </div>
                        </div>

                        <div
                            class="mt-6 flex gap-3"
                        >
                            <button
                                type="button"
                                class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50"
                                @click="
                                    fermerCreation
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
                                    creerSource
                                "
                            >
                                {{
                                    creating
                                        ? 'Création...'
                                        : 'Créer la source'
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- ========================================================= -->
        <!-- TEST OVERLAY -->
        <!-- ========================================================= -->

        <transition
            name="fade"
        >
            <div
                v-if="
                    modalTestOuvert
                    &&
                    resultatTest
                "
                class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm"
                @click="
                    fermerTest
                "
            ></div>
        </transition>

        <!-- ========================================================= -->
        <!-- TEST RESULT -->
        <!-- ========================================================= -->

        <transition
            name="modal"
        >
            <div
                v-if="
                    modalTestOuvert
                    &&
                    resultatTest
                "
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-8"
            >
                <div
                    class="premium-modal w-full max-w-2xl"
                    @click.stop
                >
                    <div
                        class="modal-header"
                    >
                        <div>
                            <p
                                class="text-[10px] font-black uppercase tracking-[0.16em] text-indigo-500"
                            >
                                Diagnostic connecteur
                            </p>

                            <h2
                                class="mt-2 text-2xl font-black text-slate-900"
                            >
                                {{
                                    sourceTestee?.nom
                                }}
                            </h2>
                        </div>

                        <button
                            type="button"
                            class="close-button"
                            @click="
                                fermerTest
                            "
                        >
                            ×
                        </button>
                    </div>

                    <div
                        class="p-6"
                    >
                        <!-- Result -->

                        <div
                            class="test-result"
                            :class="
                                resultatTest.success
                                    ? 'test-success'
                                    : 'test-error'
                            "
                        >
                            <div
                                class="test-result-icon"
                            >
                                {{
                                    resultatTest.success
                                        ? '✓'
                                        : '!'
                                }}
                            </div>

                            <div>
                                <p
                                    class="text-sm font-black"
                                >
                                    {{
                                        resultatTest.success
                                            ? 'Connexion opérationnelle'
                                            : 'Échec du test'
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs leading-5 opacity-80"
                                >
                                    {{
                                        resultatTest.message
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Stats -->

                        <div
                            class="mt-5 grid grid-cols-2 gap-4"
                        >
                            <div
                                class="test-stat"
                            >
                                <p
                                    class="metric-label"
                                >
                                    Éléments
                                </p>

                                <p
                                    class="mt-2 text-3xl font-black text-slate-900"
                                >
                                    {{
                                        resultatTest.items_count
                                        ??
                                        0
                                    }}
                                </p>
                            </div>

                            <div
                                class="test-stat"
                            >
                                <p
                                    class="metric-label"
                                >
                                    Durée
                                </p>

                                <p
                                    class="mt-2 text-3xl font-black text-slate-900"
                                >
                                    {{
                                        resultatTest.duration_ms
                                        ??
                                        0
                                    }}

                                    <span
                                        class="text-xs text-slate-400"
                                    >
                                        ms
                                    </span>
                                </p>
                            </div>
                        </div>

                        <!-- Sample -->

                        <div
                            v-if="
                                resultatTest.sample
                            "
                            class="sample-card mt-5"
                        >
                            <p
                                class="text-[10px] font-black uppercase tracking-[0.15em] text-indigo-500"
                            >
                                Aperçu normalisé
                            </p>

                            <h3
                                class="mt-3 text-xl font-black leading-tight text-slate-900"
                            >
                                {{
                                    resultatTest
                                        .sample
                                        .titre
                                }}
                            </h3>

                            <p
                                class="mt-1 text-sm font-medium text-slate-400"
                            >
                                {{
                                    resultatTest
                                        .sample
                                        .entreprise
                                    ||
                                    'Entreprise non renseignée'
                                }}
                            </p>

                            <div
                                class="mt-5 grid gap-3 sm:grid-cols-2"
                            >
                                <div
                                    class="sample-info"
                                >
                                    <span>
                                        🌍
                                    </span>

                                    <div>
                                        <p
                                            class="sample-label"
                                        >
                                            Remote
                                        </p>

                                        <p
                                            class="sample-value"
                                        >
                                            {{
                                                resultatTest
                                                    .sample
                                                    .remote_type
                                                ||
                                                '—'
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="sample-info"
                                >
                                    <span>
                                        📍
                                    </span>

                                    <div>
                                        <p
                                            class="sample-label"
                                        >
                                            Localisation
                                        </p>

                                        <p
                                            class="sample-value"
                                        >
                                            {{
                                                resultatTest
                                                    .sample
                                                    .localisation
                                                ||
                                                '—'
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="sample-info"
                                >
                                    <span>
                                        💰
                                    </span>

                                    <div>
                                        <p
                                            class="sample-label"
                                        >
                                            TJM
                                        </p>

                                        <p
                                            class="sample-value"
                                        >
                                            <template
                                                v-if="
                                                    resultatTest.sample.tjm_min !== null
                                                    &&
                                                    resultatTest.sample.tjm_min !== undefined
                                                "
                                            >
                                                {{
                                                    resultatTest
                                                        .sample
                                                        .tjm_min
                                                }}

                                                <template
                                                    v-if="
                                                        resultatTest.sample.tjm_max !== null
                                                        &&
                                                        resultatTest.sample.tjm_max !== undefined
                                                        &&
                                                        Number(
                                                            resultatTest.sample.tjm_max
                                                        )
                                                        !==
                                                        Number(
                                                            resultatTest.sample.tjm_min
                                                        )
                                                    "
                                                >
                                                    -
                                                    {{
                                                        resultatTest
                                                            .sample
                                                            .tjm_max
                                                    }}
                                                </template>

                                                €/j
                                            </template>

                                            <template
                                                v-else
                                            >
                                                —
                                            </template>
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="sample-info"
                                >
                                    <span>
                                        🗓
                                    </span>

                                    <div>
                                        <p
                                            class="sample-label"
                                        >
                                            Durée
                                        </p>

                                        <p
                                            class="sample-value"
                                        >
                                            <template
                                                v-if="
                                                    resultatTest.sample.duree_mois !== null
                                                    &&
                                                    resultatTest.sample.duree_mois !== undefined
                                                "
                                            >
                                                {{
                                                    resultatTest
                                                        .sample
                                                        .duree_mois
                                                }}
                                                mois
                                            </template>

                                            <template
                                                v-else
                                            >
                                                —
                                            </template>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="
                                    resultatTest
                                        .sample
                                        .stacks
                                        ?.length
                                "
                                class="mt-4 flex flex-wrap gap-2"
                            >
                                <span
                                    v-for="
                                        stack in
                                        resultatTest
                                            .sample
                                            .stacks
                                    "
                                    :key="
                                        stack
                                    "
                                    class="rounded-lg border border-indigo-100 bg-indigo-50 px-2.5 py-1 text-[10px] font-bold text-indigo-600"
                                >
                                    {{
                                        stack
                                    }}
                                </span>
                            </div>

                            <p
                                v-if="
                                    descriptionCourte
                                "
                                class="mt-5 text-xs leading-6 text-slate-500"
                            >
                                {{
                                    descriptionCourte
                                }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="mt-6 w-full rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white transition-all duration-300 hover:-translate-y-0.5 hover:bg-indigo-950"
                            @click="
                                fermerTest
                            "
                        >
                            Fermer
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </main>
</template>

<style scoped>
.sources-page {
    background:
        linear-gradient(
            135deg,
            #f8fafc 0%,
            #ffffff 45%,
            #f5f3ff 100%
        );
}

.sources-hero {
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

.hero-accent {
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
    min-width: 105px;

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

.hero-stat-label {
    display: block;

    font-size: 0.55rem;
    font-weight: 900;

    letter-spacing: 0.12em;

    text-transform:
        uppercase;

    color: #94a3b8;
}

.hero-stat-value {
    display: block;

    margin-top: 0.3rem;

    font-size: 1.5rem;
    font-weight: 900;
}

.overview-card {
    display: flex;

    align-items: center;

    gap: 1rem;

    border:
        1px solid
        rgba(
            226,
            232,
            240,
            0.8
        );

    border-radius: 1.25rem;

    background:
        rgba(
            255,
            255,
            255,
            0.85
        );

    padding: 1rem;

    box-shadow:
        0 14px 35px -30px
        rgba(
            15,
            23,
            42,
            0.4
        );

    backdrop-filter:
        blur(14px);
}

.overview-icon {
    display: flex;

    height: 2.7rem;
    width: 2.7rem;

    flex-shrink: 0;

    align-items: center;
    justify-content: center;

    border-radius: 0.9rem;

    font-weight: 900;
}

.overview-label {
    font-size: 0.62rem;
    font-weight: 800;

    text-transform:
        uppercase;

    letter-spacing:
        0.08em;

    color: #94a3b8;
}

.overview-value {
    margin-top: 0.15rem;

    font-size: 1.5rem;
    font-weight: 900;

    color: #0f172a;
}

.source-card {
    position: relative;

    overflow: hidden;

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

    transition:
        transform 0.3s ease,
        box-shadow 0.3s ease,
        border-color 0.3s ease;
}

.source-card:hover {
    transform:
        translateY(-4px);

    border-color:
        rgba(
            129,
            140,
            248,
            0.25
        );

    box-shadow:
        0 28px 55px -38px
        rgba(
            79,
            70,
            229,
            0.35
        );
}

.source-accent {
    height: 3px;
    width: 100%;
}

.source-icon {
    display: flex;

    height: 3rem;
    width: 3rem;

    flex-shrink: 0;

    align-items: center;
    justify-content: center;

    border-radius: 1rem;

    font-size: 1.1rem;
    font-weight: 900;
}

.type-api {
    background:
        linear-gradient(
            135deg,
            #eef2ff,
            #e0e7ff
        );

    color: #4f46e5;
}

.source-accent.type-api {
    background:
        linear-gradient(
            90deg,
            #4f46e5,
            #818cf8
        );
}

.type-rss {
    background:
        linear-gradient(
            135deg,
            #eff6ff,
            #dbeafe
        );

    color: #2563eb;
}

.source-accent.type-rss {
    background:
        linear-gradient(
            90deg,
            #2563eb,
            #60a5fa
        );
}

.type-html {
    background:
        linear-gradient(
            135deg,
            #f5f3ff,
            #ede9fe
        );

    color: #7c3aed;
}

.source-accent.type-html {
    background:
        linear-gradient(
            90deg,
            #7c3aed,
            #a78bfa
        );
}

.type-email {
    background:
        linear-gradient(
            135deg,
            #fffbeb,
            #fef3c7
        );

    color: #d97706;
}

.source-accent.type-email {
    background:
        linear-gradient(
            90deg,
            #d97706,
            #fbbf24
        );
}

.health-badge {
    display: inline-flex;

    flex-shrink: 0;

    align-items: center;

    gap: 0.45rem;

    border-radius: 9999px;

    border: 1px solid;

    padding:
        0.38rem
        0.6rem;

    font-size: 0.62rem;
    font-weight: 900;
}

.health-healthy {
    border-color: #a7f3d0;
    background: #ecfdf5;
    color: #047857;
}

.health-error {
    border-color: #fecdd3;
    background: #fff1f2;
    color: #be123c;
}

.health-warning {
    border-color: #fde68a;
    background: #fffbeb;
    color: #b45309;
}

.health-pending {
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #1d4ed8;
}

.health-inactive {
    border-color: #e2e8f0;
    background: #f8fafc;
    color: #64748b;
}

.metric-card {
    border:
        1px solid #f1f5f9;

    border-radius: 0.9rem;

    background:
        #f8fafc;

    padding:
        0.8rem;
}

.metric-label {
    font-size: 0.56rem;
    font-weight: 900;

    text-transform:
        uppercase;

    letter-spacing:
        0.08em;

    color: #94a3b8;
}

.metric-value {
    display: block;

    margin-top: 0.3rem;

    font-size: 1.4rem;
    font-weight: 900;

    color: #0f172a;
}

.metric-value-small {
    display: block;

    margin-top: 0.45rem;

    font-size: 0.72rem;
    font-weight: 900;

    color: #334155;
}

.test-button,
.config-button {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 0.45rem;

    border-radius: 0.8rem;

    padding:
        0.7rem
        0.75rem;

    font-size: 0.7rem;
    font-weight: 900;

    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        border-color 0.2s ease;
}

.test-button {
    border:
        1px solid
        #c7d2fe;

    background:
        #eef2ff;

    color:
        #4f46e5;
}

.test-button:hover:not(:disabled) {
    transform:
        translateY(-2px);

    box-shadow:
        0 10px 25px -18px
        rgba(
            79,
            70,
            229,
            0.55
        );
}

.config-button {
    border:
        1px solid
        #e2e8f0;

    background:
        white;

    color:
        #475569;
}

.config-button:hover {
    transform:
        translateY(-2px);

    border-color:
        #cbd5e1;
}

.external-source-button {
    display: flex;

    height: 2.4rem;
    width: 2.4rem;

    align-items: center;
    justify-content: center;

    border:
        1px solid
        #e2e8f0;

    border-radius: 0.8rem;

    background: white;

    font-size: 0.8rem;
    font-weight: 900;

    color: #64748b;

    transition:
        transform 0.2s ease,
        color 0.2s ease;
}

.external-source-button:hover {
    transform:
        translateY(-2px);

    color: #4f46e5;
}

.configuration-panel {
    border-top:
        1px solid #f1f5f9;

    background:
        linear-gradient(
            180deg,
            rgba(
                248,
                250,
                252,
                0.8
            ),
            white
        );

    padding: 1.5rem;
}

.field-label {
    margin-bottom: 0.45rem;

    display: block;

    font-size: 0.62rem;
    font-weight: 900;

    text-transform:
        uppercase;

    letter-spacing:
        0.08em;

    color: #64748b;
}

.field-control {
    width: 100%;

    border:
        1px solid #e2e8f0;

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

    font-size: 0.78rem;

    color: #334155;

    outline: none;

    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        background 0.2s ease;
}

.field-control:focus {
    border-color:
        #818cf8;

    background:
        white;

    box-shadow:
        0 0 0 4px
        rgba(
            99,
            102,
            241,
            0.08
        );
}

.credentials-box {
    border:
        1px solid #e2e8f0;

    border-radius: 1rem;

    background:
        linear-gradient(
            135deg,
            #f8fafc,
            white
        );

    padding: 1rem;
}

.credential-badge {
    flex-shrink: 0;

    border-radius:
        9999px;

    padding:
        0.35rem
        0.55rem;

    font-size:
        0.58rem;

    font-weight:
        900;
}

.credential-ready {
    background: #ecfdf5;
    color: #047857;
}

.credential-empty {
    background: #f1f5f9;
    color: #64748b;
}

.switch {
    position: relative;

    height: 1.75rem;
    width: 3rem;

    flex-shrink: 0;

    border-radius:
        9999px;

    transition:
        background 0.25s ease;
}

.switch-on {
    background: #10b981;
}

.switch-off {
    background: #cbd5e1;
}

.switch-thumb {
    position: absolute;

    top: 0.25rem;

    height: 1.25rem;
    width: 1.25rem;

    border-radius: 9999px;

    background: white;

    box-shadow:
        0 3px 8px
        rgba(
            15,
            23,
            42,
            0.18
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

.switch-thumb-off {
    transform:
        translateX(
            0.25rem
        );
}

.switch-thumb-on {
    transform:
        translateX(
            1.5rem
        );
}

.detail-mini {
    border:
        1px solid #f1f5f9;

    border-radius: 0.9rem;

    background: #f8fafc;

    padding: 0.8rem;
}

.detail-mini-label {
    font-size: 0.55rem;
    font-weight: 900;

    text-transform:
        uppercase;

    letter-spacing:
        0.08em;

    color: #94a3b8;
}

.detail-mini-value {
    margin-top: 0.3rem;

    font-size: 0.7rem;
    font-weight: 700;

    color: #475569;
}

.save-button {
    flex: 1;

    border-radius: 0.85rem;

    background:
        linear-gradient(
            135deg,
            #0f172a,
            #312e81
        );

    padding: 0.75rem;

    font-size: 0.72rem;
    font-weight: 900;

    color: white;

    box-shadow:
        0 12px 25px -20px
        rgba(
            49,
            46,
            129,
            0.8
        );

    transition:
        transform 0.2s ease;
}

.save-button:hover:not(:disabled) {
    transform:
        translateY(-2px);
}

.delete-button {
    border:
        1px solid #fecdd3;

    border-radius: 0.85rem;

    background: #fff;

    padding:
        0.75rem
        1rem;

    font-size: 0.72rem;
    font-weight: 800;

    color: #e11d48;
}

.delete-button:disabled {
    cursor: not-allowed;
    opacity: 0.35;
}

.spinner {
    height: 0.85rem;
    width: 0.85rem;

    border:
        2px solid
        rgba(
            79,
            70,
            229,
            0.2
        );

    border-top-color:
        #4f46e5;

    border-radius:
        9999px;

    animation:
        spin 0.65s
        linear infinite;
}

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

    align-items:
        flex-start;

    justify-content:
        space-between;

    gap: 1rem;

    border-bottom:
        1px solid #f1f5f9;

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
        1px solid #e2e8f0;

    border-radius: 0.8rem;

    background: white;

    font-size: 1.1rem;

    color: #64748b;

    transition:
        transform 0.3s ease,
        color 0.3s ease,
        background 0.3s ease;
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
            #312e81
        );

    padding:
        0.75rem;

    font-size: 0.75rem;
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

.test-result {
    display: flex;

    align-items:
        flex-start;

    gap: 1rem;

    border: 1px solid;

    border-radius: 1rem;

    padding: 1rem;
}

.test-success {
    border-color: #a7f3d0;
    background: #ecfdf5;
    color: #047857;
}

.test-error {
    border-color: #fecdd3;
    background: #fff1f2;
    color: #be123c;
}

.test-result-icon {
    display: flex;

    height: 2.5rem;
    width: 2.5rem;

    flex-shrink: 0;

    align-items: center;
    justify-content: center;

    border-radius: 0.8rem;

    background:
        rgba(
            255,
            255,
            255,
            0.7
        );

    font-weight: 900;
}

.test-stat {
    border:
        1px solid #f1f5f9;

    border-radius: 1rem;

    background:
        #f8fafc;

    padding: 1rem;
}

.sample-card {
    border:
        1px solid #e2e8f0;

    border-radius: 1.2rem;

    background: white;

    padding: 1.2rem;

    box-shadow:
        0 12px 30px -28px
        rgba(
            15,
            23,
            42,
            0.4
        );
}

.sample-info {
    display: flex;

    align-items: center;

    gap: 0.7rem;

    border-radius: 0.8rem;

    background: #f8fafc;

    padding: 0.75rem;
}

.sample-label {
    font-size: 0.55rem;
    font-weight: 900;

    text-transform:
        uppercase;

    letter-spacing:
        0.07em;

    color: #94a3b8;
}

.sample-value {
    margin-top: 0.15rem;

    font-size: 0.68rem;
    font-weight: 800;

    color: #475569;
}

.configuration-enter-active,
.configuration-leave-active {
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

.configuration-enter-from,
.configuration-leave-to {
    opacity: 0;

    transform:
        translateY(-8px);
}

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

@media (
    prefers-reduced-motion:
    reduce
) {
    .sources-hero,
    .spinner {
        animation: none;
    }

    .source-card,
    .configuration-enter-active,
    .configuration-leave-active,
    .modal-enter-active,
    .modal-leave-active {
        transition: none;
    }
}
</style>