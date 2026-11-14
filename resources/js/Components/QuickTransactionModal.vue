<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import MoneyInput from '@/Components/MoneyInput.vue';

const props = defineProps({
    show: Boolean,
    accounts: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'saved']);

const form = useForm({
    type: 'expense',
    account_id: '',
    to_account_id: '',
    category_id: '',
    amount: '',
    transaction_date: new Date().toISOString().split('T')[0],
    notes: '',
});

// Auto-select first account if not set
watch(() => props.accounts, (newAccounts) => {
    if (newAccounts.length > 0 && !form.account_id) {
        form.account_id = newAccounts[0].id;
    }
}, { immediate: true });

// Filtered categories based on selected type
const filteredCategories = computed(() => {
    if (form.type === 'transfer') return [];
    return props.categories.filter(cat => cat.type === form.type);
});

// Auto-select first category when type changes
watch(() => form.type, (newType) => {
    if (newType === 'transfer') {
        form.category_id = '';
        if (props.accounts.length > 1 && (!form.to_account_id || form.to_account_id === form.account_id)) {
            const other = props.accounts.find(a => a.id !== form.account_id);
            if (other) form.to_account_id = other.id;
        }
    } else {
        const available = props.categories.filter(c => c.type === newType);
        if (available.length > 0) {
            form.category_id = available[0].id;
        } else {
            form.category_id = '';
        }
    }
});

const submit = () => {
    form.post(route('transactions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('amount', 'notes');
            emit('saved');
            emit('close');
        },
    });
};

const closeModal = () => {
    emit('close');
};
</script>

<template>
    <Modal :show="show" @close="closeModal" max-width="lg">
        <div class="p-6">
            <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                <h2 class="text-xl font-bold text-gray-800">
                    Catat Transaksi Cepat
                </h2>
                <button
                    type="button"
                    @click="closeModal"
                    class="text-gray-400 hover:text-gray-600 rounded-lg p-1 hover:bg-gray-100 transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Type Selector Tabs -->
            <div class="grid grid-cols-3 gap-2 p-1 bg-gray-100 rounded-xl my-4 text-sm font-semibold">
                <button
                    type="button"
                    @click="form.type = 'expense'"
                    :class="[
                        'py-2 px-3 rounded-lg transition-all text-center',
                        form.type === 'expense'
                            ? 'bg-rose-500 text-white shadow-sm'
                            : 'text-gray-600 hover:text-gray-900'
                    ]"
                >
                    Pengeluaran
                </button>
                <button
                    type="button"
                    @click="form.type = 'income'"
                    :class="[
                        'py-2 px-3 rounded-lg transition-all text-center',
                        form.type === 'income'
                            ? 'bg-emerald-500 text-white shadow-sm'
                            : 'text-gray-600 hover:text-gray-900'
                    ]"
                >
                    Pemasukan
                </button>
                <button
                    type="button"
                    @click="form.type = 'transfer'"
                    :class="[
                        'py-2 px-3 rounded-lg transition-all text-center',
                        form.type === 'transfer'
                            ? 'bg-blue-500 text-white shadow-sm'
                            : 'text-gray-600 hover:text-gray-900'
                    ]"
                >
                    Transfer
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <!-- Amount Input with Large Display -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">
                        Nominal (Rp)
                    </label>
                    <MoneyInput
                        v-model="form.amount"
                        prefix="Rp"
                        required
                        placeholder="0"
                        :input-class="[
                            'py-3 text-2xl font-bold',
                            form.type === 'expense' ? 'text-rose-600' : (form.type === 'income' ? 'text-emerald-600' : 'text-blue-600')
                        ]"
                    />
                    <p v-if="form.errors.amount" class="text-xs text-rose-500 mt-1">{{ form.errors.amount }}</p>
                </div>

                <!-- Account Selection -->
                <div class="grid grid-cols-1" :class="{ 'sm:grid-cols-2 gap-3': form.type === 'transfer' }">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">
                            {{ form.type === 'transfer' ? 'Dari Rekening' : (form.type === 'income' ? 'Rekening Tujuan' : 'Sumber Rekening') }}
                        </label>
                        <select
                            v-model="form.account_id"
                            required
                            class="w-full py-2.5 px-3 rounded-xl border border-gray-200 bg-white text-gray-800 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                        >
                            <option value="" disabled>Pilih Rekening / Kantong</option>
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                {{ acc.name }} ({{ acc.type.toUpperCase() }})
                            </option>
                        </select>
                        <p v-if="form.errors.account_id" class="text-xs text-rose-500 mt-1">{{ form.errors.account_id }}</p>
                    </div>

                    <!-- Destination Account for Transfer -->
                    <div v-if="form.type === 'transfer'">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">
                            Ke Rekening
                        </label>
                        <select
                            v-model="form.to_account_id"
                            required
                            class="w-full py-2.5 px-3 rounded-xl border border-gray-200 bg-white text-gray-800 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                        >
                            <option value="" disabled>Pilih Rekening Tujuan</option>
                            <option
                                v-for="acc in accounts.filter(a => a.id !== form.account_id)"
                                :key="acc.id"
                                :value="acc.id"
                            >
                                {{ acc.name }} ({{ acc.type.toUpperCase() }})
                            </option>
                        </select>
                        <p v-if="form.errors.to_account_id" class="text-xs text-rose-500 mt-1">{{ form.errors.to_account_id }}</p>
                    </div>
                </div>

                <!-- Category for Income/Expense -->
                <div v-if="form.type !== 'transfer'">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">
                        Kategori
                    </label>
                    <select
                        v-model="form.category_id"
                        class="w-full py-2.5 px-3 rounded-xl border border-gray-200 bg-white text-gray-800 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                    >
                        <option value="">Tanpa Kategori</option>
                        <option v-for="cat in filteredCategories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>
                </div>

                <!-- Transaction Date -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">
                        Tanggal Transaksi
                    </label>
                    <input
                        v-model="form.transaction_date"
                        type="date"
                        required
                        class="w-full py-2.5 px-3 rounded-xl border border-gray-200 text-gray-800 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                    />
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">
                        Catatan / Keterangan
                    </label>
                    <input
                        v-model="form.notes"
                        type="text"
                        placeholder="Contoh: Makan siang, bayar internet, tarik tunai ATM"
                        class="w-full py-2.5 px-3 rounded-xl border border-gray-200 text-gray-800 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                    />
                </div>

                <!-- Submit Button -->
                <div class="pt-2 flex items-center justify-end space-x-3">
                    <button
                        type="button"
                        @click="closeModal"
                        class="px-4 py-2.5 text-sm font-semibold text-gray-600 hover:text-gray-800 rounded-xl hover:bg-gray-100 transition"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl shadow-sm transition disabled:opacity-50"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Transaksi' }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
