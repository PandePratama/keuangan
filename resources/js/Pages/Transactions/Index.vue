<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import QuickTransactionModal from '@/Components/QuickTransactionModal.vue';
import { formatCurrency, formatDate } from '@/Utils/formatters';

const props = defineProps({
    transactions: Object,
    filters: Object,
    activePeriod: Object,
    accounts: Array,
    categories: Array,
    stats: Object,
    currencySymbol: String,
});

const showQuickModal = ref(false);

const filterState = reactive({
    type: props.filters.type || '',
    account_id: props.filters.account_id || '',
    category_id: props.filters.category_id || '',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
    search: props.filters.search || '',
});

const expenseCategories = computed(() => {
    return (props.categories || []).filter(c => c.type === 'expense');
});

const incomeCategories = computed(() => {
    return (props.categories || []).filter(c => c.type === 'income');
});

const activeCategory = computed(() => {
    if (!filterState.category_id) return null;
    return (props.categories || []).find(c => c.id == filterState.category_id);
});

const hasActiveFilters = computed(() => {
    return Boolean(
        filterState.type ||
        filterState.account_id ||
        filterState.category_id ||
        filterState.start_date ||
        filterState.end_date ||
        filterState.search
    );
});

const applyFilters = () => {
    router.get(route('transactions.index'), filterState, {
        preserveState: true,
        preserveScroll: true,
    });
};

const selectCategory = (categoryId) => {
    if (filterState.category_id == categoryId) {
        // Toggle off if already selected
        filterState.category_id = '';
    } else {
        filterState.category_id = categoryId;
        if (categoryId) {
            const isExpense = expenseCategories.value.some(c => c.id == categoryId);
            if (isExpense && filterState.type !== 'expense') {
                filterState.type = 'expense';
            }
        }
    }
    applyFilters();
};

const onTypeChange = () => {
    if (filterState.type === 'transfer') {
        filterState.category_id = '';
    } else if (filterState.type === 'income' && activeCategory.value?.type === 'expense') {
        filterState.category_id = '';
    } else if (filterState.type === 'expense' && activeCategory.value?.type === 'income') {
        filterState.category_id = '';
    }
    applyFilters();
};

const resetFilters = () => {
    filterState.type = '';
    filterState.account_id = '';
    filterState.category_id = '';
    filterState.start_date = '';
    filterState.end_date = '';
    filterState.search = '';
    applyFilters();
};

const deleteTransaction = (tx) => {
    if (confirm(`Hapus transaksi ${tx.type.toUpperCase()} sebesar ${formatCurrency(tx.amount, props.currencySymbol)}? Saldo rekening terkait akan dikembalikan secara otomatis.`)) {
        router.delete(route('transactions.destroy', tx.id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Riwayat & Mutasi Transaksi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Transaksi & Arus Kas</h1>
                    <p class="text-xs sm:text-sm text-slate-500 font-medium">
                        Catatan lengkap buku periode {{ activePeriod?.name || 'Bulan Ini' }}
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <button
                        v-if="activePeriod?.status === 'open'"
                        type="button"
                        @click="showQuickModal = true"
                        class="inline-flex items-center justify-center space-x-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm transition active:scale-95"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>+ Transaksi Baru</span>
                    </button>
                    <span v-else class="px-3 py-1.5 rounded-xl bg-amber-100 text-amber-800 font-bold text-xs">
                        🔒 Buku Terkunci (Closed)
                    </span>
                </div>
            </div>
        </template>

        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Closed Period Notice Banner -->
            <div
                v-if="activePeriod && activePeriod.status === 'closed'"
                class="bg-amber-50 border border-amber-200/80 rounded-2xl p-4 flex items-center justify-between text-xs text-amber-900"
            >
                <div class="flex items-center space-x-2.5">
                    <span class="text-base">🔒</span>
                    <div>
                        <p class="font-bold">Buku Periode {{ activePeriod.name }} Telah Ditutup (Closed)</p>
                        <p class="text-[11px] text-amber-700 mt-0.5">
                            Data transaksi pada bulan ini terkunci untuk menjaga keaslian laporan keuangan. Anda dapat membuka kunci kembali di menu Buku Bulanan bila perlu koreksi.
                        </p>
                    </div>
                </div>
                <Link
                    :href="route('periods.index')"
                    class="px-3 py-1.5 bg-amber-200 hover:bg-amber-300 text-amber-950 font-bold rounded-lg transition shrink-0"
                >
                    Kelola Buku
                </Link>
            </div>

            <!-- Filtered Period Statistics Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-emerald-600 uppercase tracking-wider">
                            Total Pemasukan {{ activeCategory?.type === 'income' ? `(${activeCategory.name})` : '(Filter)' }}
                        </p>
                        <p class="text-xl sm:text-2xl font-extrabold text-emerald-600 mt-0.5">
                            + {{ formatCurrency(stats.income, currencySymbol) }}
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                        ↓
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-rose-500 uppercase tracking-wider">
                            Total Pengeluaran {{ activeCategory?.type === 'expense' ? `(${activeCategory.name})` : '(Filter)' }}
                        </p>
                        <p class="text-xl sm:text-2xl font-extrabold text-rose-600 mt-0.5">
                            - {{ formatCurrency(stats.expense, currencySymbol) }}
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold">
                        ↑
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Selisih Arus Kas Bersih</p>
                        <p
                            class="text-xl sm:text-2xl font-extrabold mt-0.5"
                            :class="stats.net >= 0 ? 'text-slate-900' : 'text-rose-600'"
                        >
                            {{ formatCurrency(stats.net, currencySymbol) }}
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center font-bold">
                        =
                    </div>
                </div>
            </div>

            <!-- Filter Controls Bar -->
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                    <!-- Search text -->
                    <div class="sm:col-span-2 lg:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Pencarian Catatan</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input
                                v-model="filterState.search"
                                @keyup.enter="applyFilters"
                                type="text"
                                placeholder="Cari transaksi..."
                                class="w-full text-xs rounded-xl border border-slate-200 pl-8 pr-3 py-2 outline-none focus:ring-2 focus:ring-emerald-500"
                            />
                        </div>
                    </div>

                    <!-- Type Filter -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Jenis Transaksi</label>
                        <select
                            v-model="filterState.type"
                            @change="onTypeChange"
                            class="w-full text-xs rounded-xl border border-slate-200 px-3 py-2 outline-none focus:ring-2 focus:ring-emerald-500 bg-white"
                        >
                            <option value="">Semua Jenis</option>
                            <option value="expense">Pengeluaran</option>
                            <option value="income">Pemasukan</option>
                            <option value="transfer">Transfer Antar-Akun</option>
                        </select>
                    </div>

                    <!-- Category Filter -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">
                            Kategori {{ filterState.type === 'expense' ? 'Pengeluaran' : (filterState.type === 'income' ? 'Pemasukan' : '') }}
                        </label>
                        <select
                            v-model="filterState.category_id"
                            @change="applyFilters"
                            class="w-full text-xs rounded-xl border border-slate-200 px-3 py-2 outline-none focus:ring-2 focus:ring-emerald-500 bg-white font-medium"
                            :disabled="filterState.type === 'transfer'"
                        >
                            <option value="">Semua Kategori</option>
                            <optgroup v-if="expenseCategories.length > 0 && filterState.type !== 'income'" label="Kategori Pengeluaran">
                                <option v-for="cat in expenseCategories" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </optgroup>
                            <optgroup v-if="incomeCategories.length > 0 && filterState.type !== 'expense'" label="Kategori Pemasukan">
                                <option v-for="cat in incomeCategories" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- Account Filter -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Rekening / Kantong</label>
                        <select
                            v-model="filterState.account_id"
                            @change="applyFilters"
                            class="w-full text-xs rounded-xl border border-slate-200 px-3 py-2 outline-none focus:ring-2 focus:ring-emerald-500 bg-white"
                        >
                            <option value="">Semua Rekening</option>
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                {{ acc.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Date Start -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Dari Tanggal</label>
                        <input
                            v-model="filterState.start_date"
                            @change="applyFilters"
                            type="date"
                            class="w-full text-xs rounded-xl border border-slate-200 px-3 py-2 outline-none focus:ring-2 focus:ring-emerald-500 bg-white"
                        />
                    </div>
                </div>

                <!-- Quick Filter Pills: Kategori Pengeluaran -->
                <div v-if="expenseCategories.length > 0" class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center gap-2">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider shrink-0 flex items-center space-x-1.5">
                        <span class="w-2 h-2 rounded-full bg-rose-500 inline-block"></span>
                        <span>Filter Pengeluaran:</span>
                    </span>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <button
                            type="button"
                            @click="selectCategory('')"
                            :class="[
                                'px-2.5 py-1 rounded-lg text-xs font-semibold transition',
                                !filterState.category_id && filterState.type === 'expense'
                                    ? 'bg-rose-600 text-white shadow-xs'
                                    : 'bg-slate-100 hover:bg-slate-200 text-slate-600'
                            ]"
                        >
                            Semua Pengeluaran
                        </button>
                        <button
                            v-for="cat in expenseCategories"
                            :key="cat.id"
                            type="button"
                            @click="selectCategory(cat.id)"
                            :class="[
                                'px-2.5 py-1 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5',
                                filterState.category_id == cat.id
                                    ? 'bg-rose-600 text-white shadow-xs ring-2 ring-rose-300'
                                    : 'bg-slate-100 hover:bg-slate-200 text-slate-700'
                            ]"
                        >
                            <span
                                class="w-2 h-2 rounded-full inline-block shrink-0"
                                :style="{ backgroundColor: filterState.category_id == cat.id ? '#ffffff' : (cat.color_hex || '#EF4444') }"
                            ></span>
                            <span>{{ cat.name }}</span>
                        </button>
                    </div>
                </div>

                <!-- Filter Status & Active Pills Summary -->
                <div class="pt-2 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs">
                    <div class="flex items-center gap-2 flex-wrap text-slate-500">
                        <span>Menampilkan total <strong>{{ transactions.total }}</strong> transaksi</span>
                        <span
                            v-if="activeCategory"
                            class="inline-flex items-center space-x-1 px-2.5 py-0.5 rounded-full text-xs font-bold"
                            :class="activeCategory.type === 'expense' ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'"
                        >
                            <span>Kategori: {{ activeCategory.name }}</span>
                            <button
                                type="button"
                                @click="selectCategory('')"
                                class="hover:opacity-75 font-black text-sm ml-0.5"
                                title="Hapus filter kategori"
                            >
                                ×
                            </button>
                        </span>
                        <span
                            v-if="filterState.type && !activeCategory"
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700"
                        >
                            {{ filterState.type === 'expense' ? 'Semua Pengeluaran' : (filterState.type === 'income' ? 'Semua Pemasukan' : 'Transfer') }}
                        </span>
                    </div>

                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        @click="resetFilters"
                        class="text-rose-500 hover:text-rose-700 font-bold flex items-center space-x-1"
                    >
                        <span>Reset Semua Filter</span>
                    </button>
                </div>
            </div>

            <!-- Transactions Table (Desktop & Tablet) / Cards (Mobile) -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <!-- Desktop Table View -->
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[11px] font-extrabold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                            <tr>
                                <th class="py-3 px-5">Tanggal</th>
                                <th class="py-3 px-5">Tipe & Keterangan</th>
                                <th class="py-3 px-5">Kantong / Rekening</th>
                                <th class="py-3 px-5">Kategori</th>
                                <th class="py-3 px-5 text-right">Nominal</th>
                                <th class="py-3 px-5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="transactions.data.length === 0">
                                <td colspan="6" class="text-center py-10 text-slate-400">
                                    Tidak ada transaksi yang tercatat pada buku periode ini.
                                </td>
                            </tr>
                            <tr
                                v-for="tx in transactions.data"
                                :key="tx.id"
                                class="hover:bg-slate-50/70 transition"
                            >
                                <td class="py-3.5 px-5 text-xs text-slate-500 whitespace-nowrap">
                                    {{ formatDate(tx.transaction_date) }}
                                </td>

                                <td class="py-3.5 px-5">
                                    <div class="flex items-center space-x-2.5">
                                        <span
                                            class="w-6 h-6 rounded-md flex items-center justify-center font-bold text-xs"
                                            :class="{
                                                'bg-rose-100 text-rose-600': tx.type === 'expense',
                                                'bg-emerald-100 text-emerald-600': tx.type === 'income',
                                                'bg-blue-100 text-blue-600': tx.type === 'transfer',
                                            }"
                                        >
                                            <span v-if="tx.type === 'expense'">-</span>
                                            <span v-else-if="tx.type === 'income'">+</span>
                                            <span v-else>⇆</span>
                                        </span>
                                        <div>
                                            <p class="font-bold text-slate-900 text-sm">
                                                {{ tx.notes || (tx.category ? tx.category.name : (tx.type === 'transfer' ? 'Transfer Antar Akun' : 'Tanpa Keterangan')) }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-5 text-xs">
                                    <span class="font-semibold text-slate-700">{{ tx.account ? tx.account.name : '-' }}</span>
                                    <span v-if="tx.type === 'transfer' && tx.to_account" class="text-slate-400">
                                        → <span class="font-semibold text-blue-600">{{ tx.to_account.name }}</span>
                                    </span>
                                </td>

                                <td class="py-3.5 px-5 text-xs">
                                    <button
                                        v-if="tx.category"
                                        type="button"
                                        @click="selectCategory(tx.category.id)"
                                        class="px-2.5 py-1 rounded-full font-semibold text-[11px] transition hover:scale-105 active:scale-95 cursor-pointer"
                                        :style="{ backgroundColor: tx.category.color_hex + '20', color: tx.category.color_hex }"
                                        title="Klik untuk memfilter kategori ini"
                                    >
                                        {{ tx.category.name }}
                                    </button>
                                    <span v-else class="text-slate-400">-</span>
                                </td>

                                <td class="py-3.5 px-5 text-right font-extrabold text-sm whitespace-nowrap"
                                    :class="{
                                        'text-rose-600': tx.type === 'expense',
                                        'text-emerald-600': tx.type === 'income',
                                        'text-blue-600': tx.type === 'transfer',
                                    }"
                                >
                                    {{ tx.type === 'expense' ? '-' : (tx.type === 'income' ? '+' : '') }}
                                    {{ formatCurrency(tx.amount, currencySymbol) }}
                                </td>

                                <td class="py-3.5 px-5 text-center">
                                    <button
                                        v-if="activePeriod?.status === 'open'"
                                        type="button"
                                        @click="deleteTransaction(tx)"
                                        class="p-1 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
                                        title="Hapus Transaksi"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                    <span v-else class="text-slate-300 text-xs">🔒</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Compact List View -->
                <div class="sm:hidden divide-y divide-slate-100">
                    <div v-if="transactions.data.length === 0" class="text-center py-10 text-slate-400 text-sm">
                        Tidak ada transaksi pada buku ini.
                    </div>
                    <div
                        v-for="tx in transactions.data"
                        :key="tx.id"
                        class="p-4 flex items-center justify-between"
                    >
                        <div class="flex items-center space-x-3 overflow-hidden pr-2">
                            <div
                                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 font-bold text-sm"
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
                                <p class="text-sm font-bold text-slate-900 truncate">
                                    {{ tx.notes || (tx.category ? tx.category.name : (tx.type === 'transfer' ? 'Transfer Antar Akun' : 'Transaksi')) }}
                                </p>
                                <p class="text-xs text-slate-400 mt-0.5 flex items-center space-x-1.5 flex-wrap">
                                    <span>{{ formatDate(tx.transaction_date) }} • {{ tx.account ? tx.account.name : '-' }}</span>
                                    <span v-if="tx.type === 'transfer' && tx.to_account"> → {{ tx.to_account.name }}</span>
                                    <button
                                        v-if="tx.category"
                                        type="button"
                                        @click="selectCategory(tx.category.id)"
                                        class="px-1.5 py-0.2 rounded text-[10px] font-semibold transition"
                                        :style="{ backgroundColor: tx.category.color_hex + '20', color: tx.category.color_hex }"
                                    >
                                        {{ tx.category.name }}
                                    </button>
                                </p>
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <p
                                class="text-sm font-extrabold"
                                :class="{
                                    'text-rose-600': tx.type === 'expense',
                                    'text-emerald-600': tx.type === 'income',
                                    'text-blue-600': tx.type === 'transfer',
                                }"
                            >
                                {{ tx.type === 'expense' ? '-' : (tx.type === 'income' ? '+' : '') }}
                                {{ formatCurrency(tx.amount, currencySymbol) }}
                            </p>
                            <button
                                v-if="activePeriod?.status === 'open'"
                                type="button"
                                @click="deleteTransaction(tx)"
                                class="text-[11px] text-rose-500 hover:text-rose-700 mt-1"
                            >
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pagination Footer -->
                <div v-if="transactions.links && transactions.links.length > 3" class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-xs">
                    <p class="text-slate-500">
                        Halaman <strong>{{ transactions.current_page }}</strong> dari <strong>{{ transactions.last_page }}</strong>
                    </p>
                    <div class="flex space-x-1">
                        <Link
                            v-for="(link, idx) in transactions.links"
                            :key="idx"
                            :href="link.url || '#'"
                            v-html="link.label"
                            :class="[
                                'px-3 py-1.5 rounded-lg font-semibold transition',
                                link.active ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-200',
                                !link.url ? 'opacity-40 pointer-events-none' : ''
                            ]"
                        />
                    </div>
                </div>
            </div>
        </div>

        <QuickTransactionModal
            :show="showQuickModal"
            :accounts="accounts"
            :categories="categories"
            @close="showQuickModal = false"
        />
    </AuthenticatedLayout>
</template>
