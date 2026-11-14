<script setup>
import { ref, watch, nextTick } from 'vue';
import { formatRupiahInput, parseRupiahInput } from '@/Utils/formatters';

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: '',
    },
    placeholder: {
        type: String,
        default: '0',
    },
    prefix: {
        type: String,
        default: 'Rp',
    },
    inputClass: {
        type: [String, Array, Object],
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
    id: {
        type: String,
        default: null,
    },
    name: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue', 'blur', 'focus']);

const inputRef = ref(null);
const displayValue = ref('');

watch(
    () => props.modelValue,
    (newVal) => {
        const parsed = parseRupiahInput(displayValue.value);
        const target = parseRupiahInput(newVal);
        if (parsed !== target || (newVal === '' && displayValue.value !== '')) {
            displayValue.value = formatRupiahInput(newVal);
        }
    },
    { immediate: true }
);

function handleInput(e) {
    const el = e.target;
    const oldVal = el.value;
    const oldCursor = el.selectionEnd || oldVal.length;

    const digitsBeforeCursor = oldVal.slice(0, oldCursor).replace(/\D/g, '').length;
    const rawNum = parseRupiahInput(oldVal);
    const newFormatted = formatRupiahInput(oldVal);

    displayValue.value = newFormatted;
    el.value = newFormatted;

    nextTick(() => {
        if (!el) return;
        let newCursor = 0;
        let digitCount = 0;
        for (let i = 0; i < newFormatted.length; i++) {
            if (/\d/.test(newFormatted[i])) {
                digitCount++;
            }
            if (digitCount >= digitsBeforeCursor) {
                newCursor = i + 1;
                break;
            }
        }
        if (digitCount < digitsBeforeCursor) {
            newCursor = newFormatted.length;
        }
        el.setSelectionRange(newCursor, newCursor);
    });

    emit('update:modelValue', rawNum);
}

defineExpose({
    focus: () => inputRef.value?.focus(),
});
</script>

<template>
    <div class="relative rounded-xl shadow-sm">
        <span
            v-if="prefix"
            class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center font-bold text-slate-400 select-none text-sm"
        >
            {{ prefix }}
        </span>
        <input
            ref="inputRef"
            :id="id"
            :name="name"
            type="text"
            inputmode="numeric"
            :value="displayValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            @input="handleInput"
            @blur="$emit('blur', $event)"
            @focus="$emit('focus', $event)"
            :class="[
                'w-full outline-none transition font-semibold text-slate-900 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500',
                prefix ? 'pl-11 pr-4' : 'px-3',
                inputClass
            ]"
        />
    </div>
</template>
