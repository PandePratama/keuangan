<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    accounts: Array,
    metrics: Object,
    currencySymbol: String,
});

// Modals state
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showAdjustModal = ref(false);
const showBulkModal = ref(false);
const selectedAccount = ref(null);

// Forms
const createForm = useForm({
    name: '',
    type: 'bank',
    account_number: '',
    initial_balance: '',
    color_hex: '#10B981',
    icon: 'wallet',
});

const editForm = useForm({
    name: '',
    type: 'bank',
    account_number: '',
    color_hex: '#10B981',
    icon: 'wallet',
    is_active: true,
});

const adjustForm = useForm({
    actual_balance: '',
    notes: '',
});

const bulkForm = useForm({
    adjustments: [],
    notes: 'Koreksi saldo akhir bulan (transaksi kecil terlupa)',
});

const openCreateModal = () => {
    createForm.reset();
    showCreateModal.value = true;
};

const submitCreate = () => {
    createForm.post(route('accounts.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

const openEditModal = (acc) => {
    selectedAccount.value = acc;
    editForm.name = acc.name;
    editForm.type = acc.type;
    editForm.account_number = acc.account_number || '';
    editForm.color_hex = acc.color_hex;
    editForm.icon = acc.icon;
    editForm.is_active = acc.is_active;
    showEditModal.value = true;
};

const submitEdit = () => {
    if (!selectedAccount.value) return;
    editForm.put(route('accounts.update', selectedAccount.value.id), {
        onSuccess: () => {
            showEditModal.value = false;
        },
    });
};

const openAdjustModal = (acc) => {
    selectedAccount.value = acc;
    adjustForm.actual_balance = acc.current_balance;
    adjustForm.notes = '';
    showAdjustModal.value = true;
};

const submitAdjust = () => {
    if (!selectedAccount.value) return;
    adjustForm.post(route('accounts.adjust', selectedAccount.value.id), {
        onSuccess: () => {
            showAdjustModal.value = false;
        },
    });
};

const openBulkModal = () => {
    bulkForm.adjustments = props.accounts
        .filter(a => a.is_active)
        .map(a => ({
            account_id: a.id,
            name: a.name,
            type: a.type,
            color_hex: a.color_hex,
            recorded_balance: parseFloat(a.current_balance) || 0,
            actual_balance: parseFloat(a.current_balance) || 0,
        }));
    showBulkModal.value = true;
};

const submitBulkReconcile = () => {
    const changed = bulkForm.adjustments.filter(
        a => Math.round((a.actual_balance - a.recorded_balance) * 100) / 100 !== 0
    );

    if (changed.length === 0) {
        alert('Semua saldo sudah sesuai dengan catatan aplikasi, tidak ada koreksi yang perlu dicatat.');
        showBulkModal.value = false;
        return;
    }

    useForm({
        adjustments: changed.map(a => ({
            account_id: a.account_id,
            actual_balance: a.actual_balance,
        })),
        notes: bulkForm.notes,
    }).post(route('accounts.reconcile-bulk'), {
        onSuccess: () => {
            showBulkModal.value = false;
        },
    });
};

const toggleStatus = (acc) => {
    if (confirm(`Ubah status rekening "${acc.name}"?`)) {
        useForm({}).post(route('accounts.toggle', acc.id));
    }
};

const deleteAccount = (acc) => {
    if (confirm(`Apakah Anda yakin ingin menghapus rekening "${acc.name}"? Riwayat transaksi terkait akan terpengaruh.`)) {
        useForm({}).delete(route('accounts.destroy', acc.id));
    }
};

const presetColors = ['#00529C', '#F37021', '#00AED6', '#4C2A86', '#10B981', '#EF4444', '#8B5CF6', '#14B8A6'];
</script>

<template>
    <Head title="Rekening & Kantong Finansial" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Rekening & Kantong Finansial</h1>
                    <p class="text-xs sm:text-sm text-slate-500 font-medium">Kelola rekening bank konvensional, e-wallet, dan dompet tunai fisik</p>
                </div>
                <div class="flex items-center space-x-2.5">
                    <button
                        type="button"
                        @click="openBulkModal"
                        class="inline-flex items-center justify-center space-x-2 px-3.5 py-2.5 bg-slate-800 hover:bg-slate-900 text-white text-xs sm:text-sm font-bold rounded-xl shadow-sm transition active:scale-95"
                    >
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <span>Rekonsiliasi Saldo Akhir Bulan</span>
                    </button>
                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center justify-center space-x-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-bold rounded-xl shadow-sm transition active:scale-95"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>+ Tambah Rekening</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Summary Metric Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Semua Saldo</p>
                    <p class="text-xl sm:text-2xl font-extrabold text-slate-900 mt-1">
                        {{ formatCurrency(metrics.total, currencySymbol) }}
                    </p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                    <p class="text-xs font-bold text-blue-500 uppercase tracking-wider">Rekening Bank</p>
                    <p class="text-xl sm:text-2xl font-extrabold text-slate-800 mt-1">
                        {{ formatCurrency(metrics.bank, currencySymbol) }}
                    </p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                    <p class="text-xs font-bold text-teal-500 uppercase tracking-wider">E-Wallet Digital</p>
                    <p class="text-xl sm:text-2xl font-extrabold text-slate-800 mt-1">
                        {{ formatCurrency(metrics.ewallet, currencySymbol) }}
                    </p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                    <p class="text-xs font-bold text-emerald-500 uppercase tracking-wider">Dompet Cash Tunai</p>
                    <p class="text-xl sm:text-2xl font-extrabold text-slate-800 mt-1">
                        {{ formatCurrency(metrics.cash, currencySymbol) }}
                    </p>
                </div>
            </div>

            <!-- Accounts Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div
                    v-for="acc in accounts"
                    :key="acc.id"
                    class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col justify-between hover:border-slate-300 transition"
                    :class="{ 'opacity-60 bg-slate-50': !acc.is_active }"
                >
                    <!-- Header with Accent Color -->
                    <div class="p-5 flex-1">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center space-x-2">
                                <span class="w-3.5 h-3.5 rounded-full" :style="{ backgroundColor: acc.color_hex }"></span>
                                <span class="text-xs font-extrabold uppercase px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700">
                                    {{ acc.type }}
                                </span>
                            </div>
                            <span v-if="!acc.is_active" class="text-[10px] font-bold px-2 py-0.5 rounded bg-gray-200 text-gray-700">
                                Nonaktif (Arsip)
                            </span>
                        </div>

                        <h3 class="text-lg font-bold text-slate-900 tracking-tight">{{ acc.name }}</h3>
                        <p v-if="acc.account_number" class="text-xs text-slate-400 font-mono mt-0.5">
                            No: {{ acc.account_number }}
                        </p>

                        <div class="mt-4 pt-4 border-t border-slate-100">
                            <p class="text-xs text-slate-400 font-semibold">Saldo Saat Ini</p>
                            <p class="text-2xl font-extrabold text-slate-900 mt-0.5">
                                {{ formatCurrency(acc.current_balance, currencySymbol) }}
                            </p>
                        </div>
                    </div>

                    <!-- Actions Footer -->
                    <div class="bg-slate-50 px-5 py-3 border-t border-slate-100 flex items-center justify-between text-xs font-semibold">
                        <button
                            type="button"
                            @click="openAdjustModal(acc)"
                            class="text-emerald-700 hover:text-emerald-800 flex items-center space-x-1"
                        >
                            <span>Rekonsiliasi Saldo</span>
                        </button>

                        <div class="flex items-center space-x-3">
                            <button
                                type="button"
                                @click="openEditModal(acc)"
                                class="text-slate-600 hover:text-slate-900"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                @click="toggleStatus(acc)"
                                class="text-slate-400 hover:text-slate-700"
                            >
                                {{ acc.is_active ? 'Arsipkan' : 'Aktifkan' }}
                            </button>
                            <button
                                type="button"
                                @click="deleteAccount(acc)"
                                class="text-rose-500 hover:text-rose-700"
                            >
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Reconciliation Modal (Akhir Bulan) -->
        <Modal :show="showBulkModal" @close="showBulkModal = false" max-width="2xl">
            <div class="p-6">
                <div class="flex items-center space-x-2 text-slate-900 mb-2">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500 text-white flex items-center justify-center font-bold">
                        ✓
                    </div>
                    <div>
                        <h2 class="text-lg font-bold">Rekonsiliasi Saldo Akhir Bulan</h2>
                        <p class="text-xs text-slate-500">Cocokkan saldo catatan aplikasi dengan uang fisik atau saldo riil di mutasi rekening Anda</p>
                    </div>
                </div>

                <div class="mt-4 space-y-3 max-h-96 overflow-y-auto divide-y divide-slate-100 pr-1">
                    <div
                        v-for="item in bulkForm.adjustments"
                        :key="item.account_id"
                        class="pt-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-sm"
                    >
                        <div class="flex items-center space-x-2.5 min-w-[180px]">
                            <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: item.color_hex }"></span>
                            <div>
                                <p class="font-bold text-slate-900">{{ item.name }}</p>
                                <p class="text-[11px] text-slate-400">
                                    Tercatat: {{ formatCurrency(item.recorded_balance, currencySymbol) }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3">
                            <div class="w-40">
                                <MoneyInput
                                    v-model="item.actual_balance"
                                    prefix="Rp"
                                    placeholder="0"
                                    input-class="text-xs font-bold py-1.5"
                                />
                            </div>

                            <div class="w-28 text-right text-xs">
                                <span
                                    v-if="item.actual_balance - item.recorded_balance === 0"
                                    class="px-2 py-1 rounded bg-slate-100 text-slate-500 font-bold"
                                >
                                    Cocok (0)
                                </span>
                                <span
                                    v-else-if="item.actual_balance - item.recorded_balance > 0"
                                    class="px-2 py-1 rounded bg-emerald-100 text-emerald-800 font-bold"
                                >
                                    +{{ formatCurrency(item.actual_balance - item.recorded_balance, '') }}
                                </span>
                                <span
                                    v-else
                                    class="px-2 py-1 rounded bg-rose-100 text-rose-800 font-bold"
                                >
                                    -{{ formatCurrency(Math.abs(item.actual_balance - item.recorded_balance), '') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-100 space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Catatan Koreksi Global</label>
                        <input
                            v-model="bulkForm.notes"
                            type="text"
                            placeholder="Contoh: Rekonsiliasi akhir bulan (penyesuaian belanja/parkir terlupa)"
                            class="w-full text-xs rounded-xl border border-slate-200 px-3 py-2 outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-2">
                        <button
                            type="button"
                            @click="showBulkModal = false"
                            class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            @click="submitBulkReconcile"
                            class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition"
                        >
                            Simpan Seluruh Koreksi Saldo
                        </button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Create Account Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false" max-width="md">
            <div class="p-6">
                <h2 class="text-lg font-bold text-slate-900 mb-4">Tambah Rekening / Kantong Baru</h2>
                <form @submit.prevent="submitCreate" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nama Rekening / Kantong</label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            required
                            placeholder="Contoh: BCA Prioritas, Jenius, GoPay, Dompet Tunai"
                            class="w-full text-sm rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Jenis Akun</label>
                        <select
                            v-model="createForm.type"
                            class="w-full text-sm rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none"
                        >
                            <option value="bank">Rekening Bank</option>
                            <option value="ewallet">E-Wallet Digital</option>
                            <option value="cash">Uang Tunai (Cash)</option>
                            <option value="investment">Investasi / Saham / Reksadana</option>
                            <option value="other">Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nomor Rekening / No HP (Opsional)</label>
                        <input
                            v-model="createForm.account_number"
                            type="text"
                            placeholder="Contoh: 123-456-7890"
                            class="w-full text-sm rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Saldo Awal (Rp)</label>
                        <MoneyInput
                            v-model="createForm.initial_balance"
                            prefix="Rp"
                            required
                            placeholder="0"
                            input-class="py-2.5 text-sm font-bold"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Warna Label</label>
                        <div class="flex items-center space-x-2">
                            <button
                                v-for="color in presetColors"
                                :key="color"
                                type="button"
                                @click="createForm.color_hex = color"
                                class="w-7 h-7 rounded-full border-2 transition"
                                :class="createForm.color_hex === color ? 'border-slate-800 scale-110 shadow-sm' : 'border-transparent'"
                                :style="{ backgroundColor: color }"
                            ></button>
                        </div>
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
                            Simpan Rekening
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Edit Account Modal -->
        <Modal :show="showEditModal" @close="showEditModal = false" max-width="md">
            <div class="p-6">
                <h2 class="text-lg font-bold text-slate-900 mb-4">Edit Rekening</h2>
                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nama Rekening</label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            required
                            class="w-full text-sm rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Jenis Akun</label>
                        <select
                            v-model="editForm.type"
                            class="w-full text-sm rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none"
                        >
                            <option value="bank">Rekening Bank</option>
                            <option value="ewallet">E-Wallet Digital</option>
                            <option value="cash">Uang Tunai (Cash)</option>
                            <option value="investment">Investasi / Saham / Reksadana</option>
                            <option value="other">Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nomor Rekening</label>
                        <input
                            v-model="editForm.account_number"
                            type="text"
                            class="w-full text-sm rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Warna Label</label>
                        <div class="flex items-center space-x-2">
                            <button
                                v-for="color in presetColors"
                                :key="color"
                                type="button"
                                @click="editForm.color_hex = color"
                                class="w-7 h-7 rounded-full border-2 transition"
                                :class="editForm.color_hex === color ? 'border-slate-800 scale-110 shadow-sm' : 'border-transparent'"
                                :style="{ backgroundColor: color }"
                            ></button>
                        </div>
                    </div>

                    <div class="pt-3 flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="showEditModal = false"
                            class="px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 rounded-xl"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm transition disabled:opacity-50"
                        >
                            Perbarui
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Manual Balance Adjustment Modal (FR-ACC-04) -->
        <Modal :show="showAdjustModal" @close="showAdjustModal = false" max-width="md">
            <div class="p-6">
                <div class="flex items-center space-x-2 text-emerald-600 mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                    <h2 class="text-lg font-bold text-slate-900">Penyesuaian Saldo Manual</h2>
                </div>
                <p class="text-xs text-slate-500 mb-4">
                    Rekonsiliasi saldo tercatat di aplikasi dengan saldo fisik riil di bank / dompet Anda. Sistem akan mencatat selisihnya secara otomatis sebagai koreksi saldo.
                </p>

                <div v-if="selectedAccount" class="bg-slate-50 p-3 rounded-xl mb-4 border border-slate-100">
                    <p class="text-xs text-slate-400 font-semibold">Rekening Terpilih</p>
                    <p class="text-sm font-bold text-slate-800">{{ selectedAccount.name }}</p>
                    <p class="text-xs text-slate-500 mt-1">
                        Saldo Tercatat: <strong class="text-slate-800">{{ formatCurrency(selectedAccount.current_balance, currencySymbol) }}</strong>
                    </p>
                </div>

                <form @submit.prevent="submitAdjust" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">
                            Saldo Riil Sebenarnya Saat Ini (Rp)
                        </label>
                        <MoneyInput
                            v-model="adjustForm.actual_balance"
                            prefix="Rp"
                            required
                            placeholder="0"
                            input-class="py-2.5 text-xl font-bold"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Alasan Penyesuaian (Opsional)</label>
                        <input
                            v-model="adjustForm.notes"
                            type="text"
                            placeholder="Contoh: Bunga bank, biaya admin terlupa, selisih uang kecil"
                            class="w-full text-sm rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none"
                        />
                    </div>

                    <div class="pt-3 flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="showAdjustModal = false"
                            class="px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 rounded-xl"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="adjustForm.processing"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm transition disabled:opacity-50"
                        >
                            Simpan Koreksi Saldo
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
