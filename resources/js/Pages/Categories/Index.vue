<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    categories: Array,
});

const activeTab = ref('expense');
const showCreateModal = ref(false);

const filteredCategories = computed(() => {
    return props.categories.filter(cat => cat.type === activeTab.value);
});

const form = useForm({
    name: '',
    type: 'expense',
    color_hex: '#10B981',
    icon: 'tag',
});

const openCreateModal = () => {
    form.type = activeTab.value;
    form.reset('name');
    showCreateModal.value = true;
};

const submitCreate = () => {
    form.post(route('categories.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset('name');
        },
    });
};

const deleteCategory = (category) => {
    if (confirm(`Hapus kategori "${category.name}"?`)) {
        router.delete(route('categories.destroy', category.id), {
            preserveScroll: true,
        });
    }
};

const presetColors = ['#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#8B5CF6', '#EC4899', '#06B6D4', '#6B7280'];
</script>

<template>
    <Head title="Kategori Transaksi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Kategori Finansial</h1>
                    <p class="text-xs sm:text-sm text-slate-500 font-medium">Kelompokkan arus kas pengeluaran dan pemasukan Anda</p>
                </div>
                <button
                    type="button"
                    @click="openCreateModal"
                    class="inline-flex items-center justify-center space-x-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm transition active:scale-95"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>+ Tambah Kategori Baru</span>
                </button>
            </div>
        </template>

        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Tabs: Pengeluaran vs Pemasukan -->
            <div class="flex items-center space-x-2 bg-slate-200/70 p-1.5 rounded-2xl max-w-sm">
                <button
                    type="button"
                    @click="activeTab = 'expense'"
                    :class="[
                        'flex-1 py-2 px-4 rounded-xl text-sm font-bold transition text-center',
                        activeTab === 'expense'
                            ? 'bg-white text-rose-600 shadow-sm'
                            : 'text-slate-600 hover:text-slate-900'
                    ]"
                >
                    Kategori Pengeluaran
                </button>
                <button
                    type="button"
                    @click="activeTab = 'income'"
                    :class="[
                        'flex-1 py-2 px-4 rounded-xl text-sm font-bold transition text-center',
                        activeTab === 'income'
                            ? 'bg-white text-emerald-600 shadow-sm'
                            : 'text-slate-600 hover:text-slate-900'
                    ]"
                >
                    Kategori Pemasukan
                </button>
            </div>

            <!-- Categories Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div
                    v-for="cat in filteredCategories"
                    :key="cat.id"
                    class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between hover:border-slate-300 transition"
                >
                    <div class="flex items-center space-x-3 overflow-hidden">
                        <div
                            class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-white text-sm shrink-0"
                            :style="{ backgroundColor: cat.color_hex }"
                        >
                            {{ cat.name.charAt(0) }}
                        </div>
                        <div class="overflow-hidden">
                            <p class="font-bold text-slate-900 text-sm truncate">{{ cat.name }}</p>
                            <span v-if="!cat.user_id" class="text-[10px] text-slate-400 font-semibold">
                                Sistem Bawaan
                            </span>
                            <span v-else class="text-[10px] text-emerald-600 font-semibold">
                                Kategori Kustom Anda
                            </span>
                        </div>
                    </div>

                    <div class="shrink-0 flex items-center space-x-2">
                        <span v-if="cat.transactions_count !== undefined" class="text-xs text-slate-400">
                            {{ cat.transactions_count }} tx
                        </span>
                        <button
                            v-if="cat.user_id"
                            type="button"
                            @click="deleteCategory(cat)"
                            class="text-rose-400 hover:text-rose-600 p-1"
                            title="Hapus Kategori"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Category Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false" max-width="md">
            <div class="p-6">
                <h2 class="text-lg font-bold text-slate-900 mb-4">
                    Tambah Kategori {{ form.type === 'expense' ? 'Pengeluaran' : 'Pemasukan' }} Baru
                </h2>

                <form @submit.prevent="submitCreate" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nama Kategori</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Contoh: Asuransi, Belanja Online, Kopi Harian"
                            class="w-full text-sm rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Pilihan Warna</label>
                        <div class="flex items-center space-x-2">
                            <button
                                v-for="color in presetColors"
                                :key="color"
                                type="button"
                                @click="form.color_hex = color"
                                class="w-7 h-7 rounded-full border-2 transition"
                                :class="form.color_hex === color ? 'border-slate-800 scale-110 shadow-sm' : 'border-transparent'"
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
                            :disabled="form.processing"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm transition disabled:opacity-50"
                        >
                            Simpan Kategori
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
