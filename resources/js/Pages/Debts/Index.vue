<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import { formatCurrency, formatDate } from '@/Utils/formatters';

const props = defineProps({
    debts: Array,
    currentType: String, // 'debt' or 'receivable'
    accounts: Array,
    metrics: Object,
    currencySymbol: String,
});

// Modals
const showCreateModal = ref(false);
const showPaymentModal = ref(false);
const showHistoryModal = ref(false);
const selectedDebt = ref(null);

// Forms
const createForm = useForm({
    party_name: '',
    type: props.currentType || 'debt',
    total_amount: '',
    due_date: '',
    account_id: '',
    notes: '',
});

const paymentForm = useForm({
    amount: '',
    account_id: '',
    payment_date: new Date().toISOString().split('T')[0],
    notes: '',
    mark_as_settled: false,
});

const switchTab = (type) => {
    router.get(route('debts.index'), { type }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const openCreateModal = () => {
    createForm.type = props.currentType;
    createForm.reset('party_name', 'total_amount', 'due_date', 'account_id', 'notes');
    showCreateModal.value = true;
};

const submitCreate = () => {
    createForm.post(route('debts.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

const openPaymentModal = (debt) => {
    selectedDebt.value = debt;
    paymentForm.amount = debt.remaining_amount; // defaults to full payment
    paymentForm.account_id = props.accounts.length > 0 ? props.accounts[0].id : '';
    paymentForm.notes = '';
    paymentForm.payment_date = new Date().toISOString().split('T')[0];
    paymentForm.mark_as_settled = false;
    showPaymentModal.value = true;
};

const paymentDifference = computed(() => {
    if (!selectedDebt.value) return 0;
    const remaining = parseFloat(selectedDebt.value.remaining_amount) || 0;
    const amount = parseFloat(paymentForm.amount) || 0;
    return amount - remaining;
});

const submitPayment = () => {
    if (!selectedDebt.value) return;
    paymentForm.post(route('debts.payments.store', selectedDebt.value.id), {
        onSuccess: () => {
            showPaymentModal.value = false;
        },
    });
};

const triggerWriteOff = (debt) => {
    const reason = prompt(`Ikhlaskan sisa tagihan sebesar ${formatCurrency(debt.remaining_amount, props.currencySymbol)} dari "${debt.party_name}" dan tandai lunas?\n\nAlasan penutupan (opsional):`, 'Disepakati selesai / dibulatkan');
    if (reason !== null) {
        router.post(route('debts.write-off', debt.id), { reason }, {
            preserveScroll: true,
        });
    }
};

const openHistoryModal = (debt) => {
    selectedDebt.value = debt;
    showHistoryModal.value = true;
};

const deleteDebt = (debt) => {
    if (confirm(`Apakah Anda yakin ingin menghapus catatan untuk "${debt.party_name}"?`)) {
        router.delete(route('debts.destroy', debt.id), {
            preserveScroll: true,
        });
    }
};

const calculateProgress = (debt) => {
    const total = parseFloat(debt.total_amount) || 1;
    const remaining = parseFloat(debt.remaining_amount) || 0;
    const paid = total - remaining;
    return Math.min(100, Math.max(0, Math.round((paid / total) * 100)));
};
</script>

<template>
    <Head :title="currentType === 'debt' ? 'Buku Utang Saya' : 'Buku Piutang Saya'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Buku Utang & Piutang</h1>
                    <p class="text-xs sm:text-sm text-slate-500 font-medium">Pantau kewajiban dan hak tagih Anda lengkap dengan riwayat pelunasan</p>
                </div>
                <button
                    type="button"
                    @click="openCreateModal"
                    class="inline-flex items-center justify-center space-x-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm transition active:scale-95"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>{{ currentType === 'debt' ? '+ Catat Utang Baru' : '+ Catat Piutang Baru' }}</span>
                </button>
            </div>
        </template>

        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Tabs Switcher: Utang vs Piutang -->
            <div class="flex items-center space-x-2 bg-slate-200/70 p-1.5 rounded-2xl max-w-md">
                <button
                    type="button"
                    @click="switchTab('debt')"
                    :class="[
                        'flex-1 py-2.5 px-4 rounded-xl text-sm font-bold transition flex items-center justify-center space-x-2',
                        currentType === 'debt'
                            ? 'bg-white text-rose-600 shadow-sm'
                            : 'text-slate-600 hover:text-slate-900'
                    ]"
                >
                    <span>Utang Saya (Hutang)</span>
                    <span v-if="metrics.overdue_debts > 0" class="px-1.5 py-0.5 rounded-full text-[10px] bg-rose-500 text-white font-extrabold">
                        {{ metrics.overdue_debts }}!
                    </span>
                </button>

                <button
                    type="button"
                    @click="switchTab('receivable')"
                    :class="[
                        'flex-1 py-2.5 px-4 rounded-xl text-sm font-bold transition flex items-center justify-center space-x-2',
                        currentType === 'receivable'
                            ? 'bg-white text-teal-600 shadow-sm'
                            : 'text-slate-600 hover:text-slate-900'
                    ]"
                >
                    <span>Piutang Saya</span>
                    <span v-if="metrics.overdue_receivables > 0" class="px-1.5 py-0.5 rounded-full text-[10px] bg-red-500 text-white font-extrabold">
                        {{ metrics.overdue_receivables }}!
                    </span>
                </button>
            </div>

            <!-- Overview Metrics for Active Tab -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        {{ currentType === 'debt' ? 'Total Utang Belum Lunas' : 'Total Piutang Belum Diterima' }}
                    </p>
                    <p
                        class="text-2xl sm:text-3xl font-extrabold mt-1"
                        :class="currentType === 'debt' ? 'text-rose-600' : 'text-teal-600'"
                    >
                        {{ formatCurrency(currentType === 'debt' ? metrics.total_debts : metrics.total_receivables, currencySymbol) }}
                    </p>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Status Terlambat (Overdue)</p>
                    <p class="text-2xl sm:text-3xl font-extrabold text-red-600 mt-1">
                        {{ currentType === 'debt' ? metrics.overdue_debts : metrics.overdue_receivables }} Catatan
                    </p>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Jumlah Catatan Aktif</p>
                    <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1">
                        {{ debts.filter(d => d.status !== 'paid').length }} / {{ debts.length }} Total
                    </p>
                </div>
            </div>

            <!-- Debts Cards Grid -->
            <div v-if="debts.length === 0" class="bg-white rounded-2xl border border-slate-200/80 p-12 text-center text-slate-400">
                <p class="text-base font-semibold">Belum ada catatan {{ currentType === 'debt' ? 'utang' : 'piutang' }} saat ini.</p>
                <p class="text-xs mt-1">Gunakan tombol di atas untuk menambahkan catatan baru.</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div
                    v-for="debt in debts"
                    :key="debt.id"
                    class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col justify-between hover:border-slate-300 transition"
                    :class="{ 'opacity-70 bg-slate-50': debt.status === 'paid' }"
                >
                    <!-- Card Body -->
                    <div class="p-5 flex-1">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <span
                                    class="inline-block px-2 py-0.5 rounded text-[10px] font-extrabold uppercase mb-1"
                                    :class="{
                                        'bg-gray-100 text-gray-700': debt.status === 'paid',
                                        'bg-amber-100 text-amber-800': debt.status === 'partially_paid',
                                        'bg-rose-100 text-rose-800': debt.status === 'unpaid',
                                    }"
                                >
                                    {{ debt.status === 'paid' ? 'Lunas' : (debt.status === 'partially_paid' ? 'Dicicil Sebagian' : 'Belum Dibayar') }}
                                </span>
                                <h3 class="text-base font-bold text-slate-900 leading-snug">{{ debt.party_name }}</h3>
                            </div>

                            <!-- Overdue / Due Soon Badge -->
                            <div class="shrink-0 text-right">
                                <span v-if="debt.is_overdue" class="px-2 py-1 rounded text-[10px] font-extrabold bg-red-600 text-white animate-pulse">
                                    Overdue!
                                </span>
                                <span v-else-if="debt.is_due_soon" class="px-2 py-1 rounded text-[10px] font-bold bg-amber-500 text-white">
                                    ≤ 3 Hari
                                </span>
                            </div>
                        </div>

                        <p v-if="debt.notes" class="text-xs text-slate-500 line-clamp-2 mt-1">
                            {{ debt.notes }}
                        </p>

                        <!-- Due Date -->
                        <div class="mt-3 flex items-center text-xs text-slate-500 space-x-1.5">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>Jatuh Tempo: <strong>{{ debt.due_date ? formatDate(debt.due_date) : 'Tidak Ditentukan' }}</strong></span>
                        </div>

                        <!-- Amounts & Progress Bar -->
                        <div class="mt-4 pt-4 border-t border-slate-100 space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-400 font-semibold">Sisa yang Belum Lunas</span>
                                <span class="font-extrabold text-sm" :class="currentType === 'debt' ? 'text-rose-600' : 'text-teal-600'">
                                    {{ formatCurrency(debt.remaining_amount, currencySymbol) }}
                                </span>
                            </div>

                            <!-- Progress Bar -->
                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="debt.status === 'paid' ? 'bg-emerald-500' : (currentType === 'debt' ? 'bg-rose-500' : 'bg-teal-500')"
                                    :style="{ width: calculateProgress(debt) + '%' }"
                                ></div>
                            </div>

                            <div class="flex items-center justify-between text-[11px] text-slate-400">
                                <span>Terbayar {{ calculateProgress(debt) }}%</span>
                                <span>Total: {{ formatCurrency(debt.total_amount, currencySymbol) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Card Actions -->
                    <div class="bg-slate-50 px-5 py-3 border-t border-slate-100 flex items-center justify-between text-xs font-semibold">
                        <button
                            v-if="debt.status !== 'paid'"
                            type="button"
                            @click="openPaymentModal(debt)"
                            class="px-3 py-1.5 rounded-lg text-white font-bold transition active:scale-95 shadow-sm"
                            :class="currentType === 'debt' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-teal-600 hover:bg-teal-700'"
                        >
                            {{ currentType === 'debt' ? 'Bayar Cicilan' : 'Terima Pembayaran' }}
                        </button>
                        <span v-else class="text-emerald-600 font-bold flex items-center space-x-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Telah Lunas</span>
                        </span>

                        <div class="flex items-center space-x-3">
                            <button
                                v-if="debt.status === 'partially_paid'"
                                type="button"
                                @click="triggerWriteOff(debt)"
                                class="text-amber-600 hover:text-amber-800"
                                title="Ikhlaskan sisa tagihan tanpa mutasi rekening"
                            >
                                Ikhlaskan
                            </button>
                            <button
                                type="button"
                                @click="openHistoryModal(debt)"
                                class="text-slate-600 hover:text-slate-900"
                            >
                                Riwayat ({{ debt.payments ? debt.payments.length : 0 }})
                            </button>
                            <button
                                type="button"
                                @click="deleteDebt(debt)"
                                class="text-rose-500 hover:text-rose-700"
                            >
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Debt Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false" max-width="md">
            <div class="p-6">
                <h2 class="text-lg font-bold text-slate-900 mb-4">
                    {{ currentType === 'debt' ? 'Catat Utang Baru (Kewajiban)' : 'Catat Piutang Baru (Hak Tagih)' }}
                </h2>

                <form @submit.prevent="submitCreate" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">
                            {{ currentType === 'debt' ? 'Pihak Tempat Meminjam (Nama / Instansi)' : 'Peminjam / Pihak yang Berutang' }}
                        </label>
                        <input
                            v-model="createForm.party_name"
                            type="text"
                            required
                            placeholder="Contoh: Budi Santoso, Tokopedia, Bank Mandiri"
                            class="w-full text-sm rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Total Nominal Pokok (Rp)</label>
                        <MoneyInput
                            v-model="createForm.total_amount"
                            prefix="Rp"
                            required
                            placeholder="0"
                            input-class="py-2.5 text-xl font-bold"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tanggal Jatuh Tempo (Opsional)</label>
                        <input
                            v-model="createForm.due_date"
                            type="date"
                            class="w-full text-sm rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">
                            {{ currentType === 'debt' ? 'Dana Masuk ke Rekening Sekarang? (Opsional)' : 'Dana Keluar dari Rekening Sekarang? (Opsional)' }}
                        </label>
                        <select
                            v-model="createForm.account_id"
                            class="w-full text-sm rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none bg-white"
                        >
                            <option value="">Tidak, Hanya Catat Tagihan Saja</option>
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                Hubungkan ke {{ acc.name }} ({{ acc.type.toUpperCase() }})
                            </option>
                        </select>
                        <p class="text-[11px] text-slate-400 mt-1">
                            Jika dipilih, sistem akan langsung menambah saldo (jika utang) atau memotong saldo rekening (jika piutang) secara otomatis.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Catatan Tambahan (Opsional)</label>
                        <textarea
                            v-model="createForm.notes"
                            rows="2"
                            placeholder="Keterangan pinjaman..."
                            class="w-full text-sm rounded-xl border border-slate-200 px-3 py-2 focus:ring-2 focus:ring-emerald-500 outline-none"
                        ></textarea>
                    </div>

                    <div class="pt-3 flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 rounded-xl"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm transition disabled:opacity-50"
                        >
                            Simpan Catatan
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Record Payment / Installment Modal -->
        <Modal :show="showPaymentModal" @close="showPaymentModal = false" max-width="md">
            <div class="p-6">
                <h2 class="text-lg font-bold text-slate-900 mb-1">
                    {{ currentType === 'debt' ? 'Bayar Cicilan Utang' : 'Terima Pembayaran Piutang' }}
                </h2>
                <p v-if="selectedDebt" class="text-xs text-slate-500 mb-4">
                    Pihak: <strong>{{ selectedDebt.party_name }}</strong> • Sisa Tagihan: <strong class="text-rose-600">{{ formatCurrency(selectedDebt.remaining_amount, currencySymbol) }}</strong>
                </p>

                <form @submit.prevent="submitPayment" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nominal Pembayaran Riil (Rp)</label>
                        <MoneyInput
                            v-model="paymentForm.amount"
                            prefix="Rp"
                            required
                            placeholder="0"
                            input-class="py-2.5 text-2xl font-extrabold"
                        />
                    </div>

                    <!-- Underpayment / Overpayment Intelligent Helper -->
                    <div v-if="paymentDifference < 0" class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs">
                        <div class="flex items-start space-x-2.5">
                            <input
                                type="checkbox"
                                id="mark_as_settled"
                                v-model="paymentForm.mark_as_settled"
                                class="rounded border-amber-300 text-emerald-600 focus:ring-emerald-500 mt-0.5"
                            />
                            <label for="mark_as_settled" class="text-amber-900 font-medium cursor-pointer">
                                <span class="font-bold">Tandai Lunas Sepenuhnya?</span>
                                <p class="text-[11px] text-amber-700 mt-0.5">
                                    Sisa {{ formatCurrency(Math.abs(paymentDifference), currencySymbol) }} diikhlaskan / dianggap lunas sehingga tagihan ditutup (Rp 0) tanpa sisa menggantung.
                                </p>
                            </label>
                        </div>
                    </div>

                    <div v-else-if="paymentDifference > 0" class="p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-xs text-emerald-900">
                        <p class="font-bold">ℹ️ Pembayaran Melebihi Sisa Tagihan (+{{ formatCurrency(paymentDifference, currencySymbol) }})</p>
                        <p class="text-[11px] text-emerald-700 mt-0.5">
                            Tagihan pokok ditutup lunas (Rp 0), dan kelebihan dana tetap masuk/keluar ke rekening sebagai pencatatan pembulatan / tip.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">
                            {{ currentType === 'debt' ? 'Potong dari Rekening' : 'Setor ke Rekening' }}
                        </label>
                        <select
                            v-model="paymentForm.account_id"
                            required
                            class="w-full text-sm rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none bg-white"
                        >
                            <option value="" disabled>Pilih Rekening Pembayaran</option>
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                {{ acc.name }} (Saldo: {{ formatCurrency(acc.current_balance, currencySymbol) }})
                            </option>
                        </select>
                        <p class="text-[11px] text-slate-400 mt-1">
                            Saldo rekening akan otomatis disesuaikan sesuai uang riil yang dibayar.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tanggal Pembayaran</label>
                        <input
                            v-model="paymentForm.payment_date"
                            type="date"
                            required
                            class="w-full text-sm rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Catatan (Opsional)</label>
                        <input
                            v-model="paymentForm.notes"
                            type="text"
                            placeholder="Contoh: Dibayarkan tunai, dibulatkan"
                            class="w-full text-sm rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none"
                        />
                    </div>

                    <div class="pt-3 flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="showPaymentModal = false"
                            class="px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 rounded-xl"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="paymentForm.processing"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm transition disabled:opacity-50"
                        >
                            Konfirmasi Pembayaran
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Payment History Modal -->
        <Modal :show="showHistoryModal" @close="showHistoryModal = false" max-width="md">
            <div class="p-6">
                <h2 class="text-lg font-bold text-slate-900 mb-1">Riwayat Pembayaran Cicilan</h2>
                <p v-if="selectedDebt" class="text-xs text-slate-500 mb-4">
                    Catatan untuk: <strong>{{ selectedDebt.party_name }}</strong>
                </p>

                <div v-if="!selectedDebt?.payments || selectedDebt.payments.length === 0" class="py-8 text-center text-slate-400 text-sm">
                    Belum ada riwayat pembayaran yang tercatat.
                </div>

                <div v-else class="space-y-3 max-h-80 overflow-y-auto divide-y divide-slate-100">
                    <div
                        v-for="p in selectedDebt.payments"
                        :key="p.id"
                        class="pt-3 flex items-center justify-between text-sm"
                    >
                        <div>
                            <p class="font-bold text-slate-900">{{ formatCurrency(p.amount, currencySymbol) }}</p>
                            <p class="text-xs text-slate-500">{{ formatDate(p.payment_date) }}</p>
                            <p v-if="p.notes" class="text-xs text-slate-400 mt-0.5">{{ p.notes }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-semibold px-2 py-0.5 bg-emerald-50 text-emerald-700 rounded-md">
                                Sukses
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-3 border-t border-slate-100 flex justify-end">
                    <button
                        type="button"
                        @click="showHistoryModal = false"
                        class="px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 rounded-xl"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
