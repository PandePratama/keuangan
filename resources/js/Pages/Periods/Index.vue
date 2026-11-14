<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import { formatCurrency, formatDate } from '@/Utils/formatters';

const props = defineProps({
    periods: Array,
    activePeriod: Object,
    accounts: Array,
    currencySymbol: String,
});

const showCloseModal = ref(false);
const selectedPeriodToClose = ref(null);

const closeForm = useForm({
    actual_balances: [],
    notes: 'Tutup buku bulanan dan kunci periode',
});

const switchPeriod = (period) => {
    router.post(route('periods.switch', period.id), {}, {
        preserveScroll: true,
    });
};

const openCloseModal = (period) => {
    selectedPeriodToClose.value = period;
    closeForm.actual_balances = props.accounts.map(acc => ({
        account_id: acc.id,
        name: acc.name,
        type: acc.type,
        color_hex: acc.color_hex,
        actual_balance: parseFloat(acc.current_balance) || 0,
    }));
    showCloseModal.value = true;
};

const submitClose = () => {
    if (!selectedPeriodToClose.value) return;
    closeForm.post(route('periods.close', selectedPeriodToClose.value.id), {
        onSuccess: () => {
            showCloseModal.value = false;
        },
    });
};

const reopenPeriod = (period) => {
    if (confirm(`Buka kembali kunci buku periode "${period.name}"? Anda akan dapat menginput atau mengubah transaksi pada bulan tersebut kembali.`)) {
        router.post(route('periods.reopen', period.id), {}, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Buku Periode Bulanan & Tutup Buku" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Buku Periode Bulanan & Tutup Buku</h1>
                    <p class="text-xs sm:text-sm text-slate-500 font-medium">Isolasi pencatatan keuangan per bulan dengan saldo awal dan saldo akhir terkunci</p>
                </div>

                <div v-if="activePeriod && activePeriod.status === 'open'">
                    <button
                        type="button"
                        @click="openCloseModal(activePeriod)"
                        class="inline-flex items-center space-x-2 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs sm:text-sm font-bold rounded-xl shadow-sm transition active:scale-95"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>Tutup Buku {{ activePeriod.name }} (Closing)</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Active Period Highlight Card -->
            <div v-if="activePeriod" class="bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-2xl p-6 shadow-xl relative overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative z-10">
                    <div>
                        <div class="flex items-center space-x-2.5">
                            <span class="text-xs font-bold uppercase tracking-wider text-emerald-400">Buku Sedang Ditampilkan</span>
                            <span
                                class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase"
                                :class="activePeriod.status === 'open' ? 'bg-emerald-500 text-slate-950' : 'bg-amber-400 text-slate-950'"
                            >
                                {{ activePeriod.status === 'open' ? 'Terbuka (Open)' : 'Ditutup (Closed)' }}
                            </span>
                        </div>
                        <h2 class="text-3xl font-black text-white mt-1">{{ activePeriod.name }}</h2>
                        <p class="text-xs text-slate-400 mt-1">
                            Rentang: {{ formatDate(activePeriod.start_date) }} - {{ formatDate(activePeriod.end_date) }}
                        </p>
                    </div>

                    <div class="flex flex-wrap sm:flex-nowrap gap-4">
                        <div class="bg-slate-800/80 p-3.5 rounded-xl border border-slate-700/60 min-w-[130px]">
                            <p class="text-[11px] text-slate-400 font-semibold uppercase">Saldo Awal</p>
                            <p class="text-base font-extrabold text-white mt-0.5">
                                {{ formatCurrency(activePeriod.total_opening_balance, currencySymbol) }}
                            </p>
                        </div>
                        <div class="bg-slate-800/80 p-3.5 rounded-xl border border-slate-700/60 min-w-[130px]">
                            <p class="text-[11px] text-emerald-400 font-semibold uppercase">Pemasukan</p>
                            <p class="text-base font-extrabold text-emerald-400 mt-0.5">
                                +{{ formatCurrency(activePeriod.total_income, currencySymbol) }}
                            </p>
                        </div>
                        <div class="bg-slate-800/80 p-3.5 rounded-xl border border-slate-700/60 min-w-[130px]">
                            <p class="text-[11px] text-rose-400 font-semibold uppercase">Pengeluaran</p>
                            <p class="text-base font-extrabold text-rose-400 mt-0.5">
                                -{{ formatCurrency(activePeriod.total_expense, currencySymbol) }}
                            </p>
                        </div>
                        <div class="bg-slate-800/80 p-3.5 rounded-xl border border-slate-700/60 min-w-[130px]">
                            <p class="text-[11px] text-slate-400 font-semibold uppercase">Surplus / Tabungan</p>
                            <p
                                class="text-base font-extrabold mt-0.5"
                                :class="activePeriod.net_savings >= 0 ? 'text-teal-400' : 'text-rose-400'"
                            >
                                {{ activePeriod.net_savings >= 0 ? '+' : '' }}{{ formatCurrency(activePeriod.net_savings, currencySymbol) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- List of All Historical Periods -->
            <div class="space-y-4">
                <h2 class="text-base font-bold text-slate-800">Daftar Arsip & Periode Finansial Bulanan</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div
                        v-for="period in periods"
                        :key="period.id"
                        class="bg-white rounded-2xl border p-5 shadow-sm transition flex flex-col justify-between"
                        :class="[
                            activePeriod && activePeriod.id === period.id ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-200/80 hover:border-slate-300',
                            period.status === 'closed' ? 'bg-slate-50/50' : 'bg-white'
                        ]"
                    >
                        <div>
                            <!-- Header status -->
                            <div class="flex items-center justify-between mb-3">
                                <span
                                    class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase flex items-center space-x-1"
                                    :class="period.status === 'open' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'"
                                >
                                    <span v-if="period.status === 'open'" class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1 animate-pulse"></span>
                                    <span>{{ period.status === 'open' ? 'Buku Terbuka (Open)' : 'Terkunci (Closed)' }}</span>
                                </span>

                                <span v-if="activePeriod && activePeriod.id === period.id" class="text-xs font-bold text-emerald-600">
                                    ★ Aktif Ditampilkan
                                </span>
                            </div>

                            <h3 class="text-lg font-bold text-slate-900">{{ period.name }}</h3>
                            <p class="text-xs text-slate-400 mt-0.5">
                                {{ formatDate(period.start_date) }} - {{ formatDate(period.end_date) }}
                            </p>

                            <!-- Breakdown Grid -->
                            <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-2 gap-3 text-xs">
                                <div>
                                    <p class="text-slate-400 font-semibold">Saldo Awal (1 {{ period.name.split(' ')[0] }})</p>
                                    <p class="font-bold text-slate-800 mt-0.5">
                                        {{ formatCurrency(period.total_opening_balance, currencySymbol) }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-slate-400 font-semibold">Saldo Akhir Closing</p>
                                    <p class="font-bold text-slate-900 mt-0.5">
                                        {{ period.total_closing_balance !== null ? formatCurrency(period.total_closing_balance, currencySymbol) : '(Belum Ditutup)' }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-emerald-600 font-semibold">Total Pemasukan</p>
                                    <p class="font-bold text-emerald-700 mt-0.5">
                                        +{{ formatCurrency(period.total_income, currencySymbol) }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-rose-500 font-semibold">Total Pengeluaran</p>
                                    <p class="font-bold text-rose-600 mt-0.5">
                                        -{{ formatCurrency(period.total_expense, currencySymbol) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Card Action Buttons -->
                        <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold">
                            <button
                                v-if="!activePeriod || activePeriod.id !== period.id"
                                type="button"
                                @click="switchPeriod(period)"
                                class="text-emerald-600 hover:text-emerald-700"
                            >
                                Tampilkan Buku Ini →
                            </button>
                            <span v-else class="text-slate-400 font-semibold">Buku Terpilih</span>

                            <div class="flex items-center space-x-2">
                                <button
                                    v-if="period.status === 'open'"
                                    type="button"
                                    @click="openCloseModal(period)"
                                    class="text-rose-600 hover:text-rose-700"
                                >
                                    Tutup Buku
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    @click="reopenPeriod(period)"
                                    class="text-amber-600 hover:text-amber-700"
                                    title="Buka kunci untuk memperbaiki transaksi"
                                >
                                    Buka Kunci
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Closing Book Modal (Tutup Buku) -->
        <Modal :show="showCloseModal" @close="showCloseModal = false" max-width="lg">
            <div class="p-6">
                <div class="flex items-center space-x-2 text-rose-600 mb-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <h2 class="text-lg font-bold text-slate-900">
                        Tutup Buku Periode {{ selectedPeriodToClose?.name }}
                    </h2>
                </div>
                <p class="text-xs text-slate-500 mb-4">
                    Saat buku ditutup, saldo akhir tiap rekening akan dikunci dan otomatis menjadi <strong>Saldo Awal bulan berikutnya</strong>. Transaksi bulan ini akan dilindungi dari perubahan.
                </p>

                <!-- Performance Summary Preview -->
                <div v-if="selectedPeriodToClose" class="bg-slate-50 p-3.5 rounded-xl border border-slate-200/80 mb-4 text-xs space-y-1.5">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Total Pemasukan Bulan Ini:</span>
                        <strong class="text-emerald-600">+{{ formatCurrency(selectedPeriodToClose.total_income, currencySymbol) }}</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Total Pengeluaran Bulan Ini:</span>
                        <strong class="text-rose-600">-{{ formatCurrency(selectedPeriodToClose.total_expense, currencySymbol) }}</strong>
                    </div>
                    <div class="flex justify-between pt-1 border-t border-slate-200 font-bold">
                        <span>Surplus Bersih Tabungan:</span>
                        <span :class="selectedPeriodToClose.net_savings >= 0 ? 'text-teal-600' : 'text-rose-600'">
                            {{ formatCurrency(selectedPeriodToClose.net_savings, currencySymbol) }}
                        </span>
                    </div>
                </div>

                <form @submit.prevent="submitClose" class="space-y-4">
                    <div>
                        <p class="text-xs font-bold text-slate-700 uppercase mb-2">Konfirmasi Saldo Akhir Rekening (Riil Fisik)</p>
                        <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                            <div
                                v-for="item in closeForm.actual_balances"
                                :key="item.account_id"
                                class="flex items-center justify-between p-2.5 rounded-xl border border-slate-100 bg-white text-xs"
                            >
                                <span class="font-bold text-slate-800">{{ item.name }}</span>
                                <div class="w-36">
                                    <MoneyInput
                                        v-model="item.actual_balance"
                                        prefix="Rp"
                                        placeholder="0"
                                        required
                                        input-class="text-xs font-bold py-1.5 focus:ring-rose-500 focus:border-rose-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Catatan Penutupan Buku</label>
                        <input
                            v-model="closeForm.notes"
                            type="text"
                            class="w-full text-xs rounded-xl border border-slate-200 px-3 py-2 outline-none focus:ring-2 focus:ring-rose-500"
                        />
                    </div>

                    <div class="pt-3 flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="showCloseModal = false"
                            class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="closeForm.processing"
                            class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-sm transition disabled:opacity-50"
                        >
                            Kunci & Tutup Buku
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
