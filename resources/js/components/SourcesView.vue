<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';

/*
|--------------------------------------------------------------------------
| Data
|--------------------------------------------------------------------------
*/

const sources = ref([]);

const loading = ref(true);
const error = ref(null);

const savingSourceId = ref(null);

const successMessage = ref(null);

/*
|--------------------------------------------------------------------------
| Load sources
|--------------------------------------------------------------------------
*/

const chargerSources = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(
            '/api/sources'
        );

        sources.value = response.data.map(
            (source) => ({
                ...source,

                actif: Boolean(source.actif),

                frequence_polling_minutes:
                    Number(
                        source.frequence_polling_minutes
                    ),
            })
        );
    } catch (err) {
        console.error(
            'Erreur chargement sources :',
            err
        );

        error.value =
            'Impossible de charger les sources.';
    } finally {
        loading.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| Save source
|--------------------------------------------------------------------------
*/

const sauvegarderSource = async (source) => {
    savingSourceId.value = source.id;

    error.value = null;
    successMessage.value = null;

    try {
        const response = await axios.patch(
            `/api/sources/${source.id}`,
            {
                actif: source.actif,

                frequence_polling_minutes:
                    Number(
                        source.frequence_polling_minutes
                    ),
            }
        );

        /*
         * Synchronisation avec la réponse Laravel.
         */
        const sourceMiseAJour =
            response.data.source;

        source.actif =
            Boolean(
                sourceMiseAJour.actif
            );

        source.frequence_polling_minutes =
            Number(
                sourceMiseAJour
                    .frequence_polling_minutes
            );

        source.derniere_execution =
            sourceMiseAJour
                .derniere_execution;

        source.dernier_statut =
            sourceMiseAJour
                .dernier_statut;

        successMessage.value =
            `${source.nom} a été mise à jour.`;

        setTimeout(() => {
            successMessage.value = null;
        }, 3000);
    } catch (err) {
        console.error(
            'Erreur sauvegarde source :',
            err
        );

        if (
            err.response?.status === 422
        ) {
            error.value =
                'La fréquence doit être comprise entre 1 et 10080 minutes.';
        } else {
            error.value =
                'Impossible de sauvegarder cette source.';
        }

        /*
         * Recharge les vraies valeurs
         * depuis la base en cas d'erreur.
         */
        await chargerSources();
    } finally {
        savingSourceId.value = null;
    }
};

/*
|--------------------------------------------------------------------------
| Format date
|--------------------------------------------------------------------------
*/

const formaterDate = (date) => {
    if (!date) {
        return 'Jamais exécutée';
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
    ).format(
        new Date(date)
    );
};

/*
|--------------------------------------------------------------------------
| Source type label
|--------------------------------------------------------------------------
*/

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
        'Non renseigné'
    );
};

/*
|--------------------------------------------------------------------------
| Source status style
|--------------------------------------------------------------------------
*/

const classeDernierStatut = (statut) => {
    if (!statut) {
        return (
            'bg-slate-100 text-slate-600'
        );
    }

    const valeur =
        String(statut).toLowerCase();

    if (
        valeur.includes('success') ||
        valeur.includes('succès') ||
        valeur.includes('ok')
    ) {
        return (
            'bg-green-100 text-green-700'
        );
    }

    if (
        valeur.includes('error') ||
        valeur.includes('erreur') ||
        valeur.includes('failed') ||
        valeur.includes('échec')
    ) {
        return (
            'bg-red-100 text-red-700'
        );
    }

    return (
        'bg-amber-100 text-amber-700'
    );
};

/*
|--------------------------------------------------------------------------
| Parser short name
|--------------------------------------------------------------------------
*/

const nomParser = (parserClass) => {
    if (!parserClass) {
        return 'Non renseigné';
    }

    const parties =
        parserClass.split('\\');

    return (
        parties[
            parties.length - 1
        ] ?? parserClass
    );
};

/*
|--------------------------------------------------------------------------
| Polling human readable
|--------------------------------------------------------------------------
*/

const pollingLisible = (minutes) => {
    const valeur =
        Number(minutes);

    if (!valeur) {
        return 'Non configuré';
    }

    if (valeur < 60) {
        return (
            `Toutes les ${valeur} minute(s)`
        );
    }

    if (
        valeur % 1440 === 0
    ) {
        const jours =
            valeur / 1440;

        return (
            `Tous les ${jours} jour(s)`
        );
    }

    if (
        valeur % 60 === 0
    ) {
        const heures =
            valeur / 60;

        return (
            `Toutes les ${heures} heure(s)`
        );
    }

    return (
        `Toutes les ${valeur} minutes`
    );
};

/*
|--------------------------------------------------------------------------
| Mounted
|--------------------------------------------------------------------------
*/

onMounted(() => {
    chargerSources();
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
                    Sources
                </h2>

                <p
                    class="mt-2 text-slate-500"
                >
                    Gérez les plateformes utilisées
                    pour collecter automatiquement
                    les missions.
                </p>
            </div>

            <div
                class="self-start rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white sm:self-auto"
            >
                {{ sources.length }}
                source(s)
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
            class="rounded-xl border border-slate-200 bg-white p-10 text-center text-slate-500 shadow-sm"
        >
            Chargement des sources...
        </div>

        <!-- ========================================================= -->
        <!-- EMPTY -->
        <!-- ========================================================= -->

        <div
            v-else-if="sources.length === 0"
            class="rounded-xl border border-slate-200 bg-white p-10 text-center shadow-sm"
        >
            <div class="text-4xl">
                📡
            </div>

            <h3
                class="mt-4 font-semibold text-slate-900"
            >
                Aucune source
            </h3>

            <p
                class="mt-2 text-sm text-slate-500"
            >
                Aucune source n'est actuellement
                configurée dans MissionFinder.
            </p>
        </div>

        <!-- ========================================================= -->
        <!-- SOURCES -->
        <!-- ========================================================= -->

        <div
            v-else
            class="grid gap-6 lg:grid-cols-2"
        >
            <article
                v-for="source in sources"
                :key="source.id"
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            >

                <!-- ================================================= -->
                <!-- CARD HEADER -->
                <!-- ================================================= -->

                <div
                    class="border-b border-slate-100 px-6 py-5"
                >
                    <div
                        class="flex items-start justify-between gap-4"
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
                                    class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600"
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
                                    pollingLisible(
                                        source.frequence_polling_minutes
                                    )
                                }}
                            </p>
                        </div>

                        <!-- Active badge -->
                        <span
                            v-if="source.actif"
                            class="whitespace-nowrap rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700"
                        >
                            ● Active
                        </span>

                        <span
                            v-else
                            class="whitespace-nowrap rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700"
                        >
                            ● Inactive
                        </span>
                    </div>
                </div>

                <!-- ================================================= -->
                <!-- INFORMATION -->
                <!-- ================================================= -->

                <div
                    class="grid gap-5 border-b border-slate-100 px-6 py-6 sm:grid-cols-2"
                >

                    <!-- Last execution -->
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-slate-400"
                        >
                            Dernière exécution
                        </p>

                        <p
                            class="mt-2 text-sm font-medium text-slate-800"
                        >
                            {{
                                formaterDate(
                                    source.derniere_execution
                                )
                            }}
                        </p>
                    </div>

                    <!-- Last status -->
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-slate-400"
                        >
                            Dernier statut
                        </p>

                        <div class="mt-2">
                            <span
                                :class="
                                    classeDernierStatut(
                                        source.dernier_statut
                                    )
                                "
                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                            >
                                {{
                                    source.dernier_statut
                                        || 'Aucun statut'
                                }}
                            </span>
                        </div>
                    </div>

                    <!-- Parser -->
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-slate-400"
                        >
                            Parser
                        </p>

                        <p
                            class="mt-2 text-sm font-medium text-slate-800"
                        >
                            {{
                                nomParser(
                                    source.parser_class
                                )
                            }}
                        </p>
                    </div>

                    <!-- URL -->
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-slate-400"
                        >
                            URL
                        </p>

                        <a
                            v-if="source.url_base"
                            :href="source.url_base"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-2 block max-w-full truncate text-sm font-medium text-blue-600 hover:underline"
                        >
                            {{ source.url_base }}
                        </a>

                        <p
                            v-else
                            class="mt-2 text-sm text-slate-400"
                        >
                            Non renseignée
                        </p>
                    </div>

                </div>

                <!-- ================================================= -->
                <!-- CONFIGURATION -->
                <!-- ================================================= -->

                <div
                    class="px-6 py-6"
                >
                    <h4
                        class="font-semibold text-slate-900"
                    >
                        Configuration
                    </h4>

                    <p
                        class="mt-1 text-sm text-slate-500"
                    >
                        Modifiez l'état et la fréquence
                        de collecte de cette source.
                    </p>

                    <!-- Active switch -->
                    <div
                        class="mt-6 flex items-center justify-between rounded-xl bg-slate-50 px-4 py-4"
                    >
                        <div>
                            <p
                                class="font-medium text-slate-800"
                            >
                                Collecte active
                            </p>

                            <p
                                class="mt-1 text-xs text-slate-500"
                            >
                                Le scheduler peut collecter
                                cette source.
                            </p>
                        </div>

                        <button
                            type="button"
                            role="switch"
                            :aria-checked="source.actif"
                            class="relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full transition"
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
                                class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transition"
                                :class="
                                    source.actif
                                        ? 'translate-x-6 translate-y-1'
                                        : 'translate-x-1 translate-y-1'
                                "
                            ></span>
                        </button>
                    </div>

                    <!-- Frequency -->
                    <div class="mt-5">
                        <label
                            class="block text-sm font-medium text-slate-800"
                        >
                            Fréquence de polling
                        </label>

                        <p
                            class="mt-1 text-xs text-slate-500"
                        >
                            Nombre de minutes entre
                            deux collectes.
                        </p>

                        <div
                            class="mt-3 flex items-center gap-3"
                        >
                            <input
                                v-model.number="
                                    source.frequence_polling_minutes
                                "
                                type="number"
                                min="1"
                                max="10080"
                                class="w-32 rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-500"
                            >

                            <span
                                class="text-sm text-slate-500"
                            >
                                minutes
                            </span>
                        </div>

                        <p
                            class="mt-2 text-xs font-medium text-slate-500"
                        >
                            {{
                                pollingLisible(
                                    source.frequence_polling_minutes
                                )
                            }}
                        </p>
                    </div>

                    <!-- Save -->
                    <button
                        type="button"
                        :disabled="
                            savingSourceId
                                === source.id
                        "
                        class="mt-6 flex w-full items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-wait disabled:opacity-50"
                        @click="
                            sauvegarderSource(
                                source
                            )
                        "
                    >
                        <template
                            v-if="
                                savingSourceId
                                    === source.id
                            "
                        >
                            Enregistrement...
                        </template>

                        <template v-else>
                            Save configuration
                        </template>
                    </button>
                </div>

            </article>
        </div>

        <!-- ========================================================= -->
        <!-- INFO -->
        <!-- ========================================================= -->

        <div
            class="mt-8 rounded-xl border border-blue-200 bg-blue-50 p-5"
        >
            <h3
                class="font-semibold text-blue-900"
            >
                ℹ️ Collecte automatique
            </h3>

            <p
                class="mt-2 text-sm leading-6 text-blue-700"
            >
                MissionFinder vérifie régulièrement
                les sources actives. Une source inactive
                reste configurée dans la base mais n'est
                plus collectée automatiquement.
            </p>
        </div>

    </main>
</template>