<script setup>
import { onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCurrency, formatDate } from '@/Utils/formatters';
import Chart from 'chart.js/auto';

const props = defineProps({
    summary: Object,
    activePeriod: Object,
    monthlyCashFlow: Object,
    expenseByCategory: Object,
    recentTransactions: Array,
    upcomingDebts: Array,
    accounts: Array,
    categories: Array,
});

const cashFlowCanvas = ref(null);
const categoryCanvas = ref(null);
let cashFlowChartInstance = null;
let categoryChartInstance = null;

const renderCharts = () => {
    // 1. Monthly Cashflow Bar Chart
    if (cashFlowCanvas.value) {
        if (cashFlowChartInstance) cashFlowChartInstance.destroy();
        cashFlowChartInstance = new Chart(cashFlowCanvas.value, {
            type: 'bar',
            data: {
                labels: props.monthlyCashFlow?.labels || [],
                datasets: [
                    {
                        label: 'Pemasukan',
                        data: props.monthlyCashFlow?.income || [],
                        backgroundColor: '#10B981',
                        borderRadius: 6,
                    },
                    {
                        label: 'Pengeluaran',
                        data: props.monthlyCashFlow?.expense || [],
                        backgroundColor: '#EF4444',
                        borderRadius: 6,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { weight: 'bold' } },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => 'Rp ' + (value / 1000).toLocaleString('id-ID') + 'k',
                        },
                    },
                },
            },
        });
    }

    // 2. Category Donut Chart
    if (categoryCanvas.value) {
        if (categoryChartInstance) categoryChartInstance.destroy();

        const dataLength = props.expenseByCategory?.data?.length || 0;
        categoryChartInstance = new Chart(categoryCanvas.value, {
            type: 'doughnut',
            data: {
                labels: dataLength ? props.expenseByCategory.labels : ['Belum Ada Pengeluaran'],
                datasets: [
                    {
                        data: dataLength ? props.expenseByCategory.data : [1],
                        backgroundColor: dataLength ? props.expenseByCategory.colors : ['#E2E8F0'],
                        borderWidth: 2,
                        hoverOffset: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, font: { size: 11 } },
                    },
                },
                cutout: '70%',
            },
        });
    }
};

onMounted(() => {
    renderCharts();
});

watch(() => [props.monthlyCashFlow, props.expenseByCategory], () => {
    renderCharts();
}, { deep: true });

const reopenPeriod = (period) => {
    if (confirm(`Buka kunci buku periode "${period.name}"?`)) {
        router.post(route('periods.reopen', period.id));
    }
};
</script>

<template>
    <Head title="Dashboard Keuangan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Ringkasan Finansial</h1>
                    <p class="text-xs sm:text-sm text-slate-500 font-medium">
                        Pantau saldo riil, arus kas, dan utang piutang Anda per buku bulanan
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <Link
                        :href="route('periods.index')"
                        class="inline-flex items-center space-x-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition"
                    >
                        <span>Kelola Tutup Buku</span>
                        <span>→</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Period Context Banner -->
            <div
                v-if="activePeriod"
                class="rounded-2xl p-4 sm:p-5 border flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition"
                :class="activePeriod.status === 'open' ? 'bg-emerald-50/70 border-emerald-200/80 text-emerald-950' : 'bg-amber-50/70 border-amber-200/80 text-amber-950'"
            >
                <div class="flex items-center space-x-3">
                    <div
                        class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-white shrink-0 shadow-sm"
                        :class="activePeriod.status === 'open' ? 'bg-emerald-600' : 'bg-amber-500'"
                    >
                        <svg v-if="activePeriod.status === 'open'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Buku Periode Aktif</span>
                            <span
                                class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase"
                                :class="activePeriod.status === 'open' ? 'bg-emerald-200 text-emerald-900' : 'bg-amber-200 text-amber-900'"
                            >
                                {{ activePeriod.status === 'open' ? 'Terbuka (Bisa Input)' : 'Terkunci (Closing)' }}
                            </span>
                        </div>
                        <h2 class="text-lg font-black text-slate-900 mt-0.5">{{ activePeriod.name }}</h2>
                    </div>
                </div>

                <div class="flex items-center space-x-4 text-xs">
                    <div>
                        <span class="text-slate-500 block">Saldo Awal Bulan:</span>
                        <strong class="font-extrabold text-slate-900 text-sm">
                            {{ formatCurrency(activePeriod.total_opening_balance, summary.currency_symbol) }}
                        </strong>
                    </div>

                    <div v-if="activePeriod.status === 'closed'">
                        <span class="text-slate-500 block">Saldo Akhir Closing:</span>
                        <strong class="font-extrabold text-slate-900 text-sm">
                            {{ formatCurrency(activePeriod.total_closing_balance, summary.currency_symbol) }}
                        </strong>
                    </div>

                    <div class="pl-2">
                        <Link
                            v-if="activePeriod.status === 'open'"
                            :href="route('periods.index')"
                            class="px-3.5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl transition shadow-sm"
                        >
                            Tutup Buku Bulan Ini
                        </Link>
                        <button
                            v-else
                            type="button"
                            @click="reopenPeriod(activePeriod)"
                            class="px-3 py-1.5 bg-amber-200 hover:bg-amber-300 text-amber-900 font-bold rounded-xl transition"
                        >
                            Buka Kunci
                        </button>
                    </div>
                </div>
            </div>

            <!-- 1. Executive Summary Cards (Net Worth & Liquidity) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Net Worth Card (Gradient highlight) -->
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 to-slate-800 p-5 text-white shadow-xl">
                    <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-emerald-500/10 rounded-full blur-xl pointer-events-none"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Net Worth (Kekayaan Bersih)</span>
                        <div class="p-1.5 rounded-lg bg-emerald-500/20 text-emerald-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3">
                        <p class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">
                            {{ formatCurrency(summary.net_worth, summary.currency_symbol) }}
                        </p>
                        <p class="text-[11px] text-slate-400 mt-1">Saldo + Piutang - Utang</p>
                    </div>
                </div>

                <!-- Total Liquid Balance -->
                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Saldo Likuid Riil</span>
                        <div class="p-1.5 rounded-lg bg-blue-50 text-blue-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3">
                        <p class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-800">
                            {{ formatCurrency(summary.total_liquid_balance, summary.currency_symbol) }}
                        </p>
                        <p class="text-[11px] text-emerald-600 font-semibold mt-1">Total di {{ accounts.length }} rekening/dompet</p>
                    </div>
                </div>

                <!-- Total Receivables (Piutang) -->
                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Piutang Aktif</span>
                        <div class="p-1.5 rounded-lg bg-teal-50 text-teal-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3">
                        <p class="text-2xl sm:text-3xl font-extrabold tracking-tight text-teal-600">
                            {{ formatCurrency(summary.total_receivables, summary.currency_symbol) }}
                        </p>
                        <p class="text-[11px] text-slate-400 mt-1">Uang Anda di pihak lain</p>
                    </div>
                </div>

                <!-- Total Debts (Utang) -->
                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Utang Aktif</span>
                        <div class="p-1.5 rounded-lg bg-rose-50 text-rose-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3">
                        <p class="text-2xl sm:text-3xl font-extrabold tracking-tight text-rose-600">
                            {{ formatCurrency(summary.total_debts, summary.currency_symbol) }}
                        </p>
                        <p class="text-[11px] text-slate-400 mt-1">Kewajiban pelunasan Anda</p>
                    </div>
                </div>
            </div>

            <!-- 2. Account Quick Cards (Horizontal Scroll on Mobile, Grid on Desktop) -->
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-bold text-slate-800">Daftar Kantong Dana & Saldo</h2>
                    <Link :href="route('accounts.index')" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700">
                        Kelola Rekening →
                    </Link>
                </div>
                <div class="flex overflow-x-auto space-x-3.5 pb-2 scrollbar-none sm:grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 sm:space-x-0 sm:gap-3.5">
                    <div
                        v-for="acc in accounts"
                        :key="acc.id"
                        class="min-w-[210px] sm:min-w-0 bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm hover:border-emerald-500 transition relative overflow-hidden"
                    >
                        <div class="w-1.5 absolute top-0 bottom-0 left-0" :style="{ backgroundColor: acc.color_hex }"></div>
                        <div class="flex items-center justify-between pl-1">
                            <span class="text-xs font-semibold text-slate-500 uppercase">{{ acc.type }}</span>
                            <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: acc.color_hex }"></span>
                        </div>
                        <p class="font-bold text-slate-800 text-sm mt-1 truncate pl-1">{{ acc.name }}</p>
                        <p class="text-base font-extrabold text-slate-900 mt-2 pl-1">
                            {{ formatCurrency(acc.current_balance, summary.currency_symbol) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- 3. Analytical Charts Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Arus Kas 6 Bulan Terakhir -->
                <div class="lg:col-span-2 bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex flex-col">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-base font-bold text-slate-800">Arus Kas Masuk vs Keluar</h2>
                            <p class="text-xs text-slate-500">Performa transaksi 6 bulan terakhir</p>
                        </div>
                    </div>
                    <div class="flex-1 min-h-[260px] relative">
                        <canvas ref="cashFlowCanvas"></canvas>
                    </div>
                </div>

                <!-- Donut Chart Pengeluaran Bulan Ini -->
                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex flex-col">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-base font-bold text-slate-800">Kategori Pengeluaran</h2>
                            <p class="text-xs text-slate-500">Alokasi biaya bulan {{ summary.period_name }}</p>
                        </div>
                    </div>
                    <div class="flex-1 min-h-[260px] relative flex items-center justify-center">
                        <canvas ref="categoryCanvas"></canvas>
                    </div>
                </div>
            </div>

            <!-- 4. Lower Two Columns: Recent Transactions & Upcoming Due Dates -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Transactions -->
                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-slate-800">Transaksi {{ summary.period_name }}</h2>
                        <Link :href="route('transactions.index')" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700">
                            Lihat Semua →
                        </Link>
                    </div>

                    <div v-if="recentTransactions.length === 0" class="py-8 text-center text-slate-400 text-sm">
                        Belum ada transaksi tercatat pada buku periode ini.
                    </div>

                    <div v-else class="divide-y divide-slate-100">
                        <div
                            v-for="tx in recentTransactions"
                            :key="tx.id"
                            class="py-3 flex items-center justify-between hover:bg-slate-50/50 rounded-xl px-2 transition"
                        >
                            <div class="flex items-center space-x-3 overflow-hidden">
                                <div
                                    class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 font-bold text-sm"
                                    :class="{
                                        'bg-rose-100 text-rose-600': tx.type === 'expense',
                                        'bg-emerald-100 text-emerald-600': tx.type === 'income',
                                        'bg-blue-100 text-blue-600': tx.type === 'transfer',
                                    }"
                                >
                                    <span v-if="tx.type === 'expense'">-</span>
                                    <span v-else-if="tx.type === 'income'">+</span>
                                    <span v-else>⇆</span>
                                </div>
                                <div class="overflow-hidden">
                                    <p class="text-sm font-bold text-slate-800 truncate">
                                        {{ tx.notes || (tx.category ? tx.category.name : (tx.type === 'transfer' ? 'Transfer Antar Akun' : 'Transaksi')) }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ formatDate(tx.transaction_date) }} • {{ tx.account ? tx.account.name : '-' }}
                                        <span v-if="tx.type === 'transfer' && tx.to_account"> → {{ tx.to_account.name }}</span>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right shrink-0 pl-3">
                                <p
                                    class="text-sm font-extrabold"
                                    :class="{
                                        'text-rose-600': tx.type === 'expense',
                                        'text-emerald-600': tx.type === 'income',
                                        'text-blue-600': tx.type === 'transfer',
                                    }"
                                >
                                    {{ tx.type === 'expense' ? '-' : (tx.type === 'income' ? '+' : '') }}
                                    {{ formatCurrency(tx.amount, summary.currency_symbol) }}
                                </p>
                                <span class="text-[10px] uppercase font-semibold text-slate-400">
                                    {{ tx.type }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Debts / Receivables -->
                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-slate-800">Jatuh Tempo Utang & Piutang</h2>
                        <Link :href="route('debts.index')" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700">
                            Kelola Buku Utang →
                        </Link>
                    </div>

                    <div v-if="upcomingDebts.length === 0" class="py-8 text-center text-slate-400 text-sm">
                        Tidak ada tagihan atau piutang aktif saat ini.
                    </div>

                    <div v-else class="divide-y divide-slate-100">
                        <div
                            v-for="debt in upcomingDebts"
                            :key="debt.id"
                            class="py-3 flex items-center justify-between hover:bg-slate-50/50 rounded-xl px-2 transition"
                        >
                            <div class="overflow-hidden pr-3">
                                <div class="flex items-center space-x-2">
                                    <span
                                        class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                                        :class="debt.type === 'debt' ? 'bg-rose-100 text-rose-700' : 'bg-teal-100 text-teal-700'"
                                    >
                                        {{ debt.type === 'debt' ? 'Utang Saya' : 'Piutang Saya' }}
                                    </span>
                                    <span v-if="debt.is_overdue" class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-600 text-white animate-pulse">
                                        Terlambat
                                    </span>
                                    <span v-else-if="debt.is_due_soon" class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500 text-white">
                                        Segera Jatuh Tempo
                                    </span>
                                </div>
                                <p class="text-sm font-bold text-slate-800 truncate mt-1">{{ debt.party_name }}</p>
                                <p class="text-xs text-slate-500">
                                    Jatuh Tempo: {{ formatDate(debt.due_date) }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-extrabold text-slate-900">
                                    {{ formatCurrency(debt.remaining_amount, summary.currency_symbol) }}
                                </p>
                                <p class="text-[10px] text-slate-400">
                                    dari {{ formatCurrency(debt.total_amount, summary.currency_symbol) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
