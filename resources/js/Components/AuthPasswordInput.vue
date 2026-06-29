<script setup>
import { computed, ref, useAttrs } from 'vue';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue', 'input', 'focus', 'blur']);

const attrs = useAttrs();
const inputEl = ref(null);
const isVisible = ref(false);

const inputAttrs = computed(() => {
    const { class: _class, ...rest } = attrs;

    return rest;
});

const inputClasses = computed(() => ['password-toggle-input', attrs.class]);
const toggleLabel = computed(() => (isVisible.value ? 'Hide password' : 'Show password'));

const handleInput = (event) => {
    emit('update:modelValue', event.target.value);
    emit('input', event);
};

const handleFocus = (event) => emit('focus', event);
const handleBlur = (event) => emit('blur', event);
const toggleVisibility = () => {
    isVisible.value = !isVisible.value;
    inputEl.value?.focus();
};

const focus = () => inputEl.value?.focus();
const scrollIntoView = (options) => inputEl.value?.scrollIntoView(options);

defineExpose({
    focus,
    scrollIntoView,
    input: inputEl,
});
</script>

<template>
    <div class="password-toggle-shell">
        <input
            ref="inputEl"
            v-bind="inputAttrs"
            :value="modelValue"
            :type="isVisible ? 'text' : 'password'"
            :class="inputClasses"
            @input="handleInput"
            @focus="handleFocus"
            @blur="handleBlur"
        />

        <button
            type="button"
            class="password-toggle-button"
            :aria-label="toggleLabel"
            :aria-pressed="isVisible"
            :disabled="Boolean(attrs.disabled)"
            @click="toggleVisibility"
        >
            <svg v-if="!isVisible" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" />
                <path d="M12 15.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" />
            </svg>

            <svg v-else viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M3 3l18 18" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" />
                <path d="M10.584 10.587A3.25 3.25 0 0 0 15 15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" />
                <path d="M9.88 5.643A10.85 10.85 0 0 1 12 5.25c6 0 9.75 6.75 9.75 6.75a16.96 16.96 0 0 1-3.136 3.977" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" />
                <path d="M6.228 7.23C3.93 9.014 2.25 12 2.25 12S6 18.75 12 18.75c1.338 0 2.575-.336 3.695-.88" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" />
            </svg>
        </button>
    </div>
</template>

<style scoped>
.password-toggle-shell {
    position: relative;
    display: block;
    width: 100%;
}

.password-toggle-input {
    width: 100%;
    border: 1px solid rgba(4, 21, 31, 0.12);
    border-radius: 10px;
    padding: 14px 48px 14px 16px;
    background: rgba(255, 255, 255, 0.92);
    color: inherit;
    font: inherit;
}

.password-toggle-input:focus {
    outline: none;
    border-color: rgba(15, 118, 110, 0.55);
    box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12);
}

.password-toggle-input.has-error {
    border-color: #d92d20;
}

.password-toggle-button {
    position: absolute;
    top: 50%;
    right: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    padding: 0;
    border: 0;
    background: transparent;
    color: rgba(4, 21, 31, 0.56);
    transform: translateY(-50%);
    cursor: pointer;
}

.password-toggle-button:hover {
    color: rgba(4, 21, 31, 0.78);
}

.password-toggle-button:focus-visible {
    outline: 2px solid rgba(15, 118, 110, 0.35);
    outline-offset: 2px;
    border-radius: 999px;
}

.password-toggle-button:disabled {
    cursor: default;
    opacity: 0.55;
}

.password-toggle-button svg {
    width: 20px;
    height: 20px;
}
</style>
