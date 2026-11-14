<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import QuickTransactionModal from '@/Components/QuickTransactionModal.vue';

const page = usePage();
const showQuickModal = ref(false);
const liveToast = ref(null);
let toastTimeout = null;

const triggerToast = (message, type = 'info') => {
    liveToast.value = { message, type };
    if (toastTimeout) clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        liveToast.value = null;
    }, 4000);
};

const switchPeriod = (periodId) => {
    router.post(route('periods.switch', periodId), {}, {
        preserveScroll: true,
    });
};

// Setup Laravel Echo Realtime WebSockets listener
onMounted(() => {
    const userId = page.props.auth?.user?.id;
    if (window.Echo && userId) {
        window.Echo.private(`user.${userId}`)
            .listen('.BalanceUpdated', (e) => {
                triggerToast(`Saldo ${e.account_name} diperbarui: Rp ${new Intl.NumberFormat('id-ID').format(e.current_balance)}`, 'success');
                router.reload({ preserveScroll: true });
            })
            .listen('.TransactionRecorded', (e) => {
                triggerToast(`Transaksi baru: ${e.transaction.type.toUpperCase()} Rp ${new Intl.NumberFormat('id-ID').format(e.transaction.amount)}`, 'info');
                router.reload({ preserveScroll: true });
            })
            .listen('.DebtStatusChanged', (e) => {
                triggerToast(`Status utang/piutang diperbarui (${e.status})`, 'info');
                router.reload({ preserveScroll: true });
            });
    }
});

onUnmounted(() => {
    const userId = page.props.auth?.user?.id;
    if (window.Echo && userId) {
        window.Echo.leave(`user.${userId}`);
    }
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex flex-col lg:flex-row pb-20 lg:pb-0">
        <!-- Toast Notification Notification Bar -->
        <transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="liveToast"
                class="fixed bottom-24 lg:bottom-6 right-6 z-50 flex items-center p-4 max-w-sm w-full bg-slate-900 text-white rounded-2xl shadow-2xl border border-slate-700/50"
            >
                <div class="p-2 rounded-xl bg-emerald-500/20 text-emerald-400 mr-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="text-sm font-medium flex-1">
                    <p class="text-xs uppercase font-bold text-emerald-400 tracking-wider">Pembaruan Real-Time</p>
                    <p class="mt-0.5 text-slate-200">{{ liveToast.message }}</p>
                </div>
                <button @click="liveToast = null" class="text-slate-400 hover:text-white ml-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </transition>

        <!-- Desktop Sidebar (Hidden on Mobile) -->
        <aside class="hidden lg:flex flex-col w-64 bg-slate-900 text-white shrink-0 border-r border-slate-800 select-none">
            <!-- App Brand / Logo -->
            <div class="h-20 flex items-center px-6 border-b border-slate-800/80">
                <Link :href="route('dashboard')" class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500 flex items-center justify-center shadow-lg shadow-emerald-500/30">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-lg font-black tracking-tight text-white">KasKita</span>
                        <span class="block text-xs font-semibold text-emerald-400">FinTrack System</span>
                    </div>
                </Link>
            </div>

            <!-- Period Selector in Sidebar -->
            <div v-if="$page.props.periods && $page.props.periods.length > 0" class="px-4 pt-4">
                <div class="bg-slate-800/80 rounded-xl p-2.5 border border-slate-700/60">
                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-400 mb-1">
                        <span>BUKU PERIODE</span>
                        <span
                            class="px-1.5 py-0.2 rounded text-[9px] uppercase font-extrabold"
                            :class="$page.props.activePeriod?.status === 'open' ? 'bg-emerald-500 text-slate-950' : 'bg-amber-400 text-slate-950'"
                        >
                            {{ $page.props.activePeriod?.status === 'open' ? 'Open' : 'Closed' }}
                        </span>
                    </div>
                    <select
                        :value="$page.props.activePeriod?.id"
                        @change="switchPeriod($event.target.value)"
                        class="w-full text-xs font-bold bg-slate-900 text-white rounded-lg border border-slate-700 py-1.5 px-2 focus:ring-1 focus:ring-emerald-500 outline-none cursor-pointer"
                    >
                        <option
                            v-for="p in $page.props.periods"
                            :key="p.id"
                            :value="p.id"
                        >
                            {{ p.name }} ({{ p.status === 'open' ? 'Terbuka' : 'Terkunci' }})
                        </option>
                    </select>
                </div>
            </div>

            <!-- Quick Action Button in Sidebar -->
            <div class="p-4">
                <button
                    type="button"
                    @click="showQuickModal = true"
                    :disabled="$page.props.activePeriod?.status === 'closed'"
                    class="w-full flex items-center justify-center space-x-2 py-3 px-4 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm rounded-xl transition shadow-lg shadow-emerald-500/20 active:scale-[0.98] disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>+ Catat Transaksi</span>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 px-4 space-y-1.5 overflow-y-auto">
                <Link
                    :href="route('dashboard')"
                    :class="[
                        'flex items-center space-x-3 px-3.5 py-3 rounded-xl text-sm font-semibold transition-all',
                        route().current('dashboard')
                            ? 'bg-slate-800 text-emerald-400 shadow-sm'
                            : 'text-slate-400 hover:text-white hover:bg-slate-800/50'
                    ]"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Dashboard</span>
                </Link>

                <Link
                    :href="route('periods.index')"
                    :class="[
                        'flex items-center space-x-3 px-3.5 py-3 rounded-xl text-sm font-semibold transition-all',
                        route().current('periods.*')
                            ? 'bg-slate-800 text-emerald-400 shadow-sm'
                            : 'text-slate-400 hover:text-white hover:bg-slate-800/50'
                    ]"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>Buku Bulanan (Closing)</span>
                </Link>

                <Link
                    :href="route('insights.index')"
                    :class="[
                        'flex items-center space-x-3 px-3.5 py-3 rounded-xl text-sm font-semibold transition-all',
                        route().current('insights.*')
                            ? 'bg-slate-800 text-emerald-400 shadow-sm'
                            : 'text-slate-400 hover:text-white hover:bg-slate-800/50'
                    ]"
                >
                    <span class="w-5 text-center">✨</span>
                    <span>Analisa AI</span>
                </Link>

                <Link
                    :href="route('transactions.index')"
                    :class="[
                        'flex items-center space-x-3 px-3.5 py-3 rounded-xl text-sm font-semibold transition-all',
                        route().current('transactions.*')
                            ? 'bg-slate-800 text-emerald-400 shadow-sm'
                            : 'text-slate-400 hover:text-white hover:bg-slate-800/50'
                    ]"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                    <span>Transaksi & Mutasi</span>
                </Link>

                <Link
                    :href="route('accounts.index')"
                    :class="[
                        'flex items-center space-x-3 px-3.5 py-3 rounded-xl text-sm font-semibold transition-all',
                        route().current('accounts.*')
                            ? 'bg-slate-800 text-emerald-400 shadow-sm'
                            : 'text-slate-400 hover:text-white hover:bg-slate-800/50'
                    ]"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    <span>Rekening & Kantong</span>
                </Link>

                <Link
                    :href="route('debts.index')"
                    :class="[
                        'flex items-center space-x-3 px-3.5 py-3 rounded-xl text-sm font-semibold transition-all',
                        route().current('debts.*')
                            ? 'bg-slate-800 text-emerald-400 shadow-sm'
                            : 'text-slate-400 hover:text-white hover:bg-slate-800/50'
                    ]"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span>Buku Utang & Piutang</span>
                </Link>

                <Link
                    :href="route('categories.index')"
                    :class="[
                        'flex items-center space-x-3 px-3.5 py-3 rounded-xl text-sm font-semibold transition-all',
                        route().current('categories.*')
                            ? 'bg-slate-800 text-emerald-400 shadow-sm'
                            : 'text-slate-400 hover:text-white hover:bg-slate-800/50'
                    ]"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    <span>Kategori Pengeluaran</span>
                </Link>
            </nav>

            <!-- User Menu at Bottom of Sidebar -->
            <div class="p-4 border-t border-slate-800">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3 overflow-hidden">
                        <div class="w-9 h-9 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-emerald-400 text-sm">
                            {{ $page.props.auth.user.name.charAt(0) }}
                        </div>
                        <div class="overflow-hidden">
                            <p class="text-sm font-semibold text-white truncate">{{ $page.props.auth.user.name }}</p>
                            <p class="text-xs text-slate-400 truncate">{{ $page.props.auth.user.email }}</p>
                        </div>
                    </div>
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                </svg>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('profile.edit')">Profil Pengguna</DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">Keluar</DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Mobile Top Header Bar -->
            <header class="lg:hidden bg-slate-900 text-white px-4 py-3 flex items-center justify-between sticky top-0 z-30 shadow-md">
                <Link :href="route('dashboard')" class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <span class="font-bold text-sm tracking-tight text-white">KasKita</span>
                </Link>

                <!-- Mobile Period Selector -->
                <div v-if="$page.props.periods && $page.props.periods.length > 0">
                    <select
                        :value="$page.props.activePeriod?.id"
                        @change="switchPeriod($event.target.value)"
                        class="text-xs font-bold bg-slate-800 text-white rounded-lg border border-slate-700 py-1 px-2 focus:ring-1 focus:ring-emerald-500 outline-none"
                    >
                        <option
                            v-for="p in $page.props.periods"
                            :key="p.id"
                            :value="p.id"
                        >
                            {{ p.name }}
                        </option>
                    </select>
                </div>

                <div class="flex items-center space-x-2">
                    <Link :href="route('profile.edit')" class="w-8 h-8 rounded-full bg-slate-800 text-emerald-400 font-bold flex items-center justify-center text-xs border border-slate-700">
                        {{ $page.props.auth.user.name.charAt(0) }}
                    </Link>
                </div>
            </header>

            <!-- Page Header slot (Desktop) -->
            <div v-if="$slots.header" class="hidden lg:block bg-white border-b border-gray-200/80 px-8 py-5">
                <slot name="header" />
            </div>

            <!-- Page Main Content Slot -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>

        <!-- Mobile Floating Action Button (+) -->
        <button
            type="button"
            @click="showQuickModal = true"
            :disabled="$page.props.activePeriod?.status === 'closed'"
            class="lg:hidden fixed bottom-20 right-5 z-40 w-14 h-14 rounded-full bg-emerald-500 text-white shadow-xl shadow-emerald-500/40 flex items-center justify-center active:scale-95 transition disabled:opacity-40"
            aria-label="Catat Transaksi"
        >
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
        </button>

        <!-- Mobile Bottom Navigation Bar -->
        <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200/80 px-2 py-2 flex items-center justify-around z-30 shadow-lg">
            <Link
                :href="route('dashboard')"
                :class="[
                    'flex flex-col items-center py-1 px-2.5 rounded-lg text-[11px] font-semibold transition',
                    route().current('dashboard') ? 'text-emerald-600 font-bold' : 'text-gray-400 hover:text-gray-600'
                ]"
            >
                <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>Home</span>
            </Link>

            <Link
                :href="route('transactions.index')"
                :class="[
                    'flex flex-col items-center py-1 px-2.5 rounded-lg text-[11px] font-semibold transition',
                    route().current('transactions.*') ? 'text-emerald-600 font-bold' : 'text-gray-400 hover:text-gray-600'
                ]"
            >
                <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                <span>Transaksi</span>
            </Link>

            <Link
                :href="route('periods.index')"
                :class="[
                    'flex flex-col items-center py-1 px-2.5 rounded-lg text-[11px] font-semibold transition',
                    route().current('periods.*') ? 'text-emerald-600 font-bold' : 'text-gray-400 hover:text-gray-600'
                ]"
            >
                <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>Buku</span>
            </Link>

            <Link
                :href="route('debts.index')"
                :class="[
                    'flex flex-col items-center py-1 px-2.5 rounded-lg text-[11px] font-semibold transition',
                    route().current('debts.*') ? 'text-emerald-600 font-bold' : 'text-gray-400 hover:text-gray-600'
                ]"
            >
                <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span>Utang</span>
            </Link>

            <Link
                :href="route('accounts.index')"
                :class="[
                    'flex flex-col items-center py-1 px-2.5 rounded-lg text-[11px] font-semibold transition',
                    route().current('accounts.*') ? 'text-emerald-600 font-bold' : 'text-gray-400 hover:text-gray-600'
                ]"
            >
                <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <span>Rekening</span>
            </Link>
        </nav>

        <!-- Global Quick Transaction Modal -->
        <QuickTransactionModal
            :show="showQuickModal"
            :accounts="$page.props.accounts || []"
            :categories="$page.props.categories || []"
            @close="showQuickModal = false"
        />
    </div>
</template>
