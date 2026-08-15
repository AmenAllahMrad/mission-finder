<script setup>
import {
    computed,
    ref,
} from 'vue';

import DashboardView from './components/DashboardView.vue';
import MissionsView from './components/MissionsView.vue';
import SourcesView from './components/SourcesView.vue';
import ProfilsView from './components/ProfilsView.vue';

/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

const page = ref(
    'dashboard'
);

const navigation = [
    {
        id: 'dashboard',
        label: 'Dashboard',

        icon: 'M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z',
    },

    {
        id: 'missions',
        label: 'Missions',

        icon: 'M9 4V2h6v2h5a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5Zm2 0h2V3h-2v1Zm-7 7v7h16v-7h-5v1a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-1H4Zm7 0h2V9h-2v2Z',
    },

    {
        id: 'sources',
        label: 'Sources',

        icon: 'M12 2a4 4 0 0 1 4 4c0 .73-.2 1.42-.54 2.01A7.002 7.002 0 0 1 19 14h2l-3 4-3-4h2a5 5 0 0 0-3.06-4.61A4 4 0 1 1 12 2Zm0 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4ZM5 10l3 4H6a5 5 0 0 0 5 5h1v2h-1a7 7 0 0 1-7-7H2l3-4Z',
    },

    {
        id: 'profils',
        label: 'Profils',

        icon: 'M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0-2a3 3 0 1 1 0-6 3 3 0 0 1 0 6ZM4 22v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2h-2v-2a4 4 0 0 0-4-4h-4a4 4 0 0 0-4 4v2H4Z',
    },
];

const pageActuelle =
    computed(() => {
        return navigation.find(
            (item) =>
                item.id ===
                page.value
        );
    });

const naviguer = (
    destination
) => {
    if (
        page.value ===
        destination
    ) {
        return;
    }

    page.value =
        destination;

    window.scrollTo({
        top: 0,
        behavior: 'smooth',
    });
};
</script>

<template>
    <div
        class="app-shell min-h-screen"
    >
        <!-- ========================================================= -->
        <!-- GLOBAL HEADER -->
        <!-- ========================================================= -->

        <header
            class="global-header sticky top-0 z-40"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6"
            >
                <!-- ================================================= -->
                <!-- BRAND -->
                <!-- ================================================= -->

                <button
                    type="button"
                    class="brand group"
                    @click="
                        naviguer(
                            'dashboard'
                        )
                    "
                >
                    <div
                        class="brand-logo"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            class="h-5 w-5"
                            fill="none"
                        >
                            <path
                                d="M5 17V7l7-4 7 4v10l-7 4-7-4Z"
                                stroke="currentColor"
                                stroke-width="1.7"
                            />

                            <path
                                d="M9 15V9l3-2 3 2v6l-3 2-3-2Z"
                                fill="currentColor"
                                opacity=".9"
                            />
                        </svg>

                        <div
                            class="brand-glow"
                        ></div>
                    </div>

                    <div
                        class="hidden text-left sm:block"
                    >
                        <div
                            class="flex items-center gap-2"
                        >
                            <h1
                                class="text-[15px] font-black tracking-tight text-slate-900"
                            >
                                MissionFinder
                            </h1>

                            <span
                                class="beta-badge"
                            >
                                INTELLIGENCE
                            </span>
                        </div>

                        <p
                            class="mt-0.5 text-[9px] font-bold uppercase tracking-[0.16em] text-slate-400"
                        >
                            Opportunity Discovery Platform
                        </p>
                    </div>
                </button>

                <!-- ================================================= -->
                <!-- DESKTOP NAVIGATION -->
                <!-- ================================================= -->

                <nav
                    class="navigation-shell hidden items-center md:flex"
                >
                    <button
                        v-for="
                            item in navigation
                        "
                        :key="
                            item.id
                        "
                        type="button"
                        class="nav-item"
                        :class="
                            page === item.id
                                ? 'nav-item-active'
                                : 'nav-item-idle'
                        "
                        @click="
                            naviguer(
                                item.id
                            )
                        "
                    >
                        <span
                            class="nav-icon"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                class="h-[15px] w-[15px]"
                                fill="currentColor"
                            >
                                <path
                                    :d="
                                        item.icon
                                    "
                                />
                            </svg>
                        </span>

                        <span>
                            {{
                                item.label
                            }}
                        </span>

                        <span
                            v-if="
                                page === item.id
                            "
                            class="active-dot"
                        ></span>
                    </button>
                </nav>

                <!-- ================================================= -->
                <!-- RIGHT STATUS -->
                <!-- ================================================= -->

                <div
                    class="flex items-center gap-2"
                >
                    <!-- Current page -->

                    <div
                        class="current-page hidden xl:flex"
                    >
                        <span
                            class="text-[9px] font-black uppercase tracking-[0.12em] text-slate-400"
                        >
                            Vue
                        </span>

                        <span
                            class="ml-2 text-[11px] font-black text-slate-700"
                        >
                            {{
                                pageActuelle
                                    ?.label
                            }}
                        </span>
                    </div>

                    <!-- System health -->

                    <div
                        class="system-status"
                    >
                        <span
                            class="status-signal"
                        >
                            <span
                                class="status-pulse"
                            ></span>

                            <span
                                class="status-core"
                            ></span>
                        </span>

                        <span
                            class="hidden text-[9px] font-black uppercase tracking-[0.1em] text-emerald-700 lg:inline"
                        >
                            Operational
                        </span>
                    </div>
                </div>
            </div>

            <!-- Header subtle gradient line -->

            <div
                class="header-line"
            ></div>
        </header>

        <!-- ========================================================= -->
        <!-- MOBILE NAVIGATION -->
        <!-- ========================================================= -->

        <div
            class="mobile-navigation md:hidden"
        >
            <div
                class="grid grid-cols-4"
            >
                <button
                    v-for="
                        item in navigation
                    "
                    :key="
                        `mobile-${item.id}`
                    "
                    type="button"
                    class="mobile-nav-item"
                    :class="
                        page === item.id
                            ? 'mobile-nav-active'
                            : 'mobile-nav-idle'
                    "
                    @click="
                        naviguer(
                            item.id
                        )
                    "
                >
                    <span
                        class="mobile-icon"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            class="h-[17px] w-[17px]"
                            fill="currentColor"
                        >
                            <path
                                :d="
                                    item.icon
                                "
                            />
                        </svg>
                    </span>

                    <span
                        class="text-[9px] font-black"
                    >
                        {{
                            item.label
                        }}
                    </span>
                </button>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- PAGE CONTENT -->
        <!-- ========================================================= -->

        <div
            class="relative"
        >
            <Transition
                name="page-transition"
                mode="out-in"
            >
                <DashboardView
                    v-if="
                        page ===
                        'dashboard'
                    "
                    key="dashboard"
                    @navigate="
                        naviguer
                    "
                />

                <MissionsView
                    v-else-if="
                        page ===
                        'missions'
                    "
                    key="missions"
                />

                <SourcesView
                    v-else-if="
                        page ===
                        'sources'
                    "
                    key="sources"
                />

                <ProfilsView
                    v-else-if="
                        page ===
                        'profils'
                    "
                    key="profils"
                />
            </Transition>
        </div>
    </div>
</template>

<style scoped>
/* ================================================================
   APP BACKGROUND
================================================================ */

.app-shell {
    background:
        linear-gradient(
            135deg,
            #f8fafc 0%,
            #ffffff 48%,
            #f5f3ff 100%
        );
}

/* ================================================================
   HEADER
================================================================ */

.global-header {
    border-bottom:
        1px solid
        rgba(
            226,
            232,
            240,
            0.72
        );

    background:
        rgba(
            255,
            255,
            255,
            0.86
        );

    box-shadow:
        0 8px 35px -28px
        rgba(
            15,
            23,
            42,
            0.45
        );

    backdrop-filter:
        blur(22px);

    -webkit-backdrop-filter:
        blur(22px);
}

.header-line {
    height: 1px;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(
                99,
                102,
                241,
                0.18
            ),
            rgba(
                139,
                92,
                246,
                0.14
            ),
            transparent
        );
}

/* ================================================================
   BRAND
================================================================ */

.brand {
    display: flex;

    flex-shrink: 0;

    align-items: center;

    gap: 0.75rem;
}

.brand-logo {
    position: relative;

    display: flex;

    height: 2.65rem;
    width: 2.65rem;

    flex-shrink: 0;

    align-items: center;
    justify-content: center;

    overflow: hidden;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.13
        );

    border-radius: 0.9rem;

    background:
        linear-gradient(
            145deg,
            #0f172a,
            #312e81 55%,
            #6d28d9
        );

    color: white;

    box-shadow:
        0 10px 25px -14px
        rgba(
            79,
            70,
            229,
            0.75
        );

    transition:
        transform 0.35s
        cubic-bezier(
            0.22,
            1,
            0.36,
            1
        ),
        box-shadow 0.35s ease;
}

.brand:hover
.brand-logo {
    transform:
        translateY(-2px)
        rotate(-4deg)
        scale(1.04);

    box-shadow:
        0 15px 28px -13px
        rgba(
            79,
            70,
            229,
            0.85
        );
}

.brand-glow {
    position: absolute;

    right: -0.4rem;
    top: -0.4rem;

    height: 1.4rem;
    width: 1.4rem;

    border-radius:
        9999px;

    background:
        rgba(
            165,
            180,
            252,
            0.45
        );

    filter:
        blur(10px);
}

.beta-badge {
    border:
        1px solid
        #e0e7ff;

    border-radius:
        9999px;

    background:
        #eef2ff;

    padding:
        0.15rem
        0.35rem;

    font-size:
        0.45rem;

    font-weight:
        900;

    letter-spacing:
        0.08em;

    color:
        #6366f1;
}

/* ================================================================
   DESKTOP NAV
================================================================ */

.navigation-shell {
    gap: 0.2rem;

    border:
        1px solid
        rgba(
            226,
            232,
            240,
            0.75
        );

    border-radius:
        1rem;

    background:
        rgba(
            248,
            250,
            252,
            0.75
        );

    padding:
        0.28rem;

    box-shadow:
        inset
        0
        1px
        3px
        rgba(
            15,
            23,
            42,
            0.035
        );
}

.nav-item {
    position: relative;

    display: inline-flex;

    align-items: center;

    gap: 0.5rem;

    border-radius:
        0.75rem;

    padding:
        0.6rem
        0.78rem;

    font-size:
        0.68rem;

    font-weight:
        850;

    white-space:
        nowrap;

    transition:
        transform 0.25s ease,
        color 0.25s ease,
        background 0.25s ease,
        box-shadow 0.25s ease;
}

.nav-icon {
    display: flex;

    height: 1.7rem;
    width: 1.7rem;

    align-items: center;
    justify-content: center;

    border-radius:
        0.55rem;

    transition:
        background 0.25s ease,
        color 0.25s ease,
        transform 0.25s ease;
}

.nav-item-idle {
    color: #94a3b8;
}

.nav-item-idle:hover {
    background:
        rgba(
            255,
            255,
            255,
            0.75
        );

    color: #475569;

    transform:
        translateY(-1px);
}

.nav-item-idle:hover
.nav-icon {
    background: #f1f5f9;

    color: #6366f1;

    transform:
        scale(1.05);
}

.nav-item-active {
    background: white;

    color: #312e81;

    box-shadow:
        0 7px 18px -13px
        rgba(
            15,
            23,
            42,
            0.5
        );
}

.nav-item-active
.nav-icon {
    background:
        linear-gradient(
            135deg,
            #eef2ff,
            #ede9fe
        );

    color: #6366f1;
}

.active-dot {
    position: absolute;

    bottom: 0.18rem;
    left: 50%;

    height: 2px;
    width: 15px;

    border-radius:
        9999px;

    background:
        linear-gradient(
            90deg,
            #6366f1,
            #8b5cf6
        );

    transform:
        translateX(-50%);

    box-shadow:
        0 0 8px
        rgba(
            99,
            102,
            241,
            0.35
        );
}

/* ================================================================
   CURRENT PAGE
================================================================ */

.current-page {
    align-items: center;

    border:
        1px solid
        #e2e8f0;

    border-radius:
        0.75rem;

    background:
        rgba(
            248,
            250,
            252,
            0.8
        );

    padding:
        0.5rem
        0.7rem;
}

/* ================================================================
   SYSTEM STATUS
================================================================ */

.system-status {
    display: flex;

    align-items: center;

    gap: 0.5rem;

    border:
        1px solid
        #d1fae5;

    border-radius:
        0.75rem;

    background:
        rgba(
            236,
            253,
            245,
            0.78
        );

    padding:
        0.55rem
        0.65rem;
}

.status-signal {
    position: relative;

    display: flex;

    height: 0.55rem;
    width: 0.55rem;

    align-items: center;
    justify-content: center;
}

.status-pulse {
    position: absolute;

    inset: 0;

    border-radius:
        9999px;

    background:
        #10b981;

    animation:
        systemPulse
        1.8s
        ease-out
        infinite;
}

.status-core {
    position: relative;

    z-index: 2;

    height: 0.4rem;
    width: 0.4rem;

    border-radius:
        9999px;

    background:
        #10b981;
}

/* ================================================================
   MOBILE NAVIGATION
================================================================ */

.mobile-navigation {
    position: fixed;

    bottom: 0.8rem;
    left: 50%;

    z-index: 50;

    width:
        calc(
            100% - 1.5rem
        );

    max-width:
        430px;

    overflow: hidden;

    border:
        1px solid
        rgba(
            226,
            232,
            240,
            0.78
        );

    border-radius:
        1.25rem;

    background:
        rgba(
            255,
            255,
            255,
            0.92
        );

    padding:
        0.3rem;

    box-shadow:
        0 20px 50px -22px
        rgba(
            15,
            23,
            42,
            0.42
        );

    backdrop-filter:
        blur(22px);

    transform:
        translateX(-50%);
}

.mobile-nav-item {
    display: flex;

    flex-direction: column;

    align-items: center;
    justify-content: center;

    gap: 0.2rem;

    border-radius:
        0.9rem;

    padding:
        0.5rem
        0.25rem;

    transition:
        background 0.25s ease,
        color 0.25s ease,
        transform 0.25s ease;
}

.mobile-nav-idle {
    color: #94a3b8;
}

.mobile-nav-active {
    background:
        linear-gradient(
            135deg,
            #eef2ff,
            #f5f3ff
        );

    color: #4f46e5;

    transform:
        translateY(-1px);
}

.mobile-icon {
    display: flex;

    height: 1.65rem;
    width: 1.65rem;

    align-items: center;
    justify-content: center;

    border-radius:
        0.55rem;
}

/* ================================================================
   PAGE TRANSITIONS
================================================================ */

.page-transition-enter-active {
    transition:
        opacity 0.32s ease,
        transform 0.38s
        cubic-bezier(
            0.22,
            1,
            0.36,
            1
        );
}

.page-transition-leave-active {
    transition:
        opacity 0.16s ease,
        transform 0.16s ease;
}

.page-transition-enter-from {
    opacity: 0;

    transform:
        translateY(10px)
        scale(0.995);
}

.page-transition-leave-to {
    opacity: 0;

    transform:
        translateY(-4px);
}

/* ================================================================
   ANIMATION
================================================================ */

@keyframes systemPulse {
    0% {
        transform:
            scale(1);

        opacity: 0.4;
    }

    70% {
        transform:
            scale(2.2);

        opacity: 0;
    }

    100% {
        transform:
            scale(2.2);

        opacity: 0;
    }
}

/* ================================================================
   MOBILE SPACE FOR BOTTOM NAV
================================================================ */

@media (
    max-width: 767px
) {
    .app-shell {
        padding-bottom:
            5.2rem;
    }
}

/* ================================================================
   REDUCED MOTION
================================================================ */

@media (
    prefers-reduced-motion:
    reduce
) {
    .status-pulse {
        animation: none;
    }

    .page-transition-enter-active,
    .page-transition-leave-active,
    .brand-logo,
    .nav-item,
    .nav-icon,
    .mobile-nav-item {
        transition: none;
    }
}
</style>