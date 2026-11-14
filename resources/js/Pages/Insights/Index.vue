<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCurrency } from '@/Utils/formatters';

const props = defineProps({
    period: Object,
    insight: Object,
    currencySymbol: String,
});

const m = computed(() => props.insight.metrics);
const ev = computed(() => props.insight.evaluation);

const scoreColor = computed(() => {
    const s = ev.value.score;
    if (s === null) return 'text-slate-400';
    if (s >= 80) return 'text-emerald-600';
    if (s >= 60) return 'text-teal-600';
    if (s >= 40) return 'text-amber-500';
    return 'text-rose-600';
});
</script>

<template>
    <Head title="Analisa Keuangan AI" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">Analisa Keuangan</h1>
                <p class="text-xs sm:text-sm text-slate-500 font-medium">
                    Kesehatan keuangan periode {{ period?.name }}
                </p>
            </div>
        </template>

        <div class="space-y-6 max-w-5xl mx-auto">
            <!-- Score -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 flex items-center gap-6">
                <div class="text-center shrink-0">
                    <p class="text-5xl font-black" :class="scoreColor">{{ ev.score ?? '-' }}</p>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">/ 100</p>
                </div>
                <div>
                    <p class="text-lg font-extrabold" :class="scoreColor">{{ ev.label }}</p>
                    <p class="text-xs text-slate-500 mt-1">
                        Skor dihitung dari tingkat tabungan, dana darurat, beban utang, dan tren pengeluaran.
                    </p>
                </div>
            </div>

            <!-- Key metrics -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80">
                    <p class="text-[11px] font-bold text-slate-500 uppercase">Pemasukan</p>
                    <p class="font-extrabold text-emerald-600">{{ formatCurrency(m.income, currencySymbol) }}</p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80">
                    <p class="text-[11px] font-bold text-slate-500 uppercase">Pengeluaran</p>
                    <p class="font-extrabold text-rose-600">{{ formatCurrency(m.expense, currencySymbol) }}</p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80">
                    <p class="text-[11px] font-bold text-slate-500 uppercase">Rasio Tabungan</p>
                    <p class="font-extrabold text-slate-900">{{ m.savings_rate }}%</p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80">
                    <p class="text-[11px] font-bold text-slate-500 uppercase">Dana Darurat</p>
                    <p class="font-extrabold text-slate-900">
                        {{ m.emergency_fund_months !== null ? m.emergency_fund_months + ' bulan' : '-' }}
                    </p>
                </div>
            </div>

            <!-- AI narrative -->
            <div class="bg-gradient-to-br from-violet-50 to-indigo-50 rounded-2xl border border-indigo-200/60 p-5">
                <p class="text-xs font-extrabold text-indigo-700 uppercase tracking-wider mb-2">✨ Analisa AI</p>
                <p v-if="insight.ai_narrative" class="text-sm text-slate-800 whitespace-pre-line leading-relaxed">{{ insight.ai_narrative }}</p>
                <p v-else-if="!insight.ai_enabled" class="text-xs text-slate-500">
                    AI belum aktif. Isi <code class="bg-white px-1 rounded">GEMINI_API_KEY</code> di file <code class="bg-white px-1 rounded">.env</code> untuk mengaktifkan narasi AI. Sementara itu, gunakan temuan & saran otomatis di bawah.
                </p>
                <p v-else class="text-xs text-slate-500">Narasi AI sedang tidak tersedia. Gunakan temuan & saran otomatis di bawah.</p>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div class="bg-white rounded-2xl border border-slate-200/80 p-5">
                    <p class="text-xs font-extrabold text-slate-600 uppercase mb-3">Temuan</p>
                    <ul class="space-y-2 text-sm text-slate-700 list-disc pl-5">
                        <li v-for="(f, i) in ev.findings" :key="i">{{ f }}</li>
                    </ul>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200/80 p-5">
                    <p class="text-xs font-extrabold text-slate-600 uppercase mb-3">Saran</p>
                    <ul v-if="ev.tips.length" class="space-y-2 text-sm text-slate-700 list-disc pl-5">
                        <li v-for="(t, i) in ev.tips" :key="i">{{ t }}</li>
                    </ul>
                    <p v-else class="text-sm text-slate-500">Pertahankan kebiasaan keuangan Anda saat ini 👍</p>
                </div>
            </div>

            <!-- Top categories -->
            <div v-if="m.top_expense_categories.length" class="bg-white rounded-2xl border border-slate-200/80 p-5">
                <p class="text-xs font-extrabold text-slate-600 uppercase mb-3">Pengeluaran Terbesar</p>
                <div class="space-y-3">
                    <div v-for="c in m.top_expense_categories" :key="c.name">
                        <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                            <span>{{ c.name }}</span>
                            <span>{{ formatCurrency(c.total, currencySymbol) }} · {{ c.percent }}%</span>
                        </div>
                        <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-rose-500 rounded-full" :style="{ width: c.percent + '%' }"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
