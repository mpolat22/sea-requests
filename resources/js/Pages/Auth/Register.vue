<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from '../../lib/i18n';
import AuthPasswordInput from '../../Components/AuthPasswordInput.vue';
import MainLayout from '../../Layouts/MainLayout.vue';
import { normalizeEmailInput } from '../../lib/normalizeEmailInput';

const props = defineProps({
    next: {
        type: String,
        default: '',
    },
    role: {
        type: String,
        default: '',
    },
    countryOptions: {
        type: Array,
        default: () => [],
    },
});

const { section } = useI18n();
const copy = section('register');
const ui = computed(() => ({
    required: copy.value.required,
    email: copy.value.emailError,
    password: copy.value.passwordError,
    passwordConfirmation: copy.value.passwordConfirmationError,
    terms: copy.value.termsError,
    passwordRules: copy.value.passwordRules,
    minChars: copy.value.minChars,
    hasLetter: copy.value.hasLetter,
    hasNumber: copy.value.hasNumber,
}));

const resolveAccountType = (role) => (role === 'buyer' ? 'buyer' : 'seller');

const form = useForm({
    account_type: resolveAccountType(props.role),
    name: '',
    company_name: '',
    country: '',
    email: '',
    password: '',
    password_confirmation: '',
    agree_to_terms: false,
    next: props.next ?? '',
});

const fieldRefs = ref({});
const emailPattern = /^[^\s@]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;
const passwordPattern = /^(?=.*[A-Za-z])(?=.*\d).{8,}$/;
const countrySelectOptions = computed(() => Array.isArray(props.countryOptions) ? props.countryOptions : []);
const passwordChecks = computed(() => ({
    length: form.password.length >= 8,
    letter: /[A-Za-z]/.test(form.password),
    number: /\d/.test(form.password),
}));

const setFieldRef = (field) => (element) => {
    if (element) fieldRefs.value[field] = element;
};

const clearFieldError = (field) => form.clearErrors(field);

const handleEmailInput = (value) => {
    form.email = normalizeEmailInput(value);

    if (emailPattern.test(form.email)) {
        clearFieldError('email');
    }
};

const inputClass = (field) => ({
    'has-error': Boolean(form.errors[field]),
});

const formatRequiredLabel = (label) => String(label ?? '').replace(/\*/g, '<span class="required-star">*</span>');

const focusFirstError = async (errors) => {
    const firstField = Object.keys(errors)[0];

    if (!firstField) {
        return;
    }

    await nextTick();

    const element = fieldRefs.value[firstField];

    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        element.focus();
    }
};

const switchType = (type) => {
    form.account_type = type;

    form.country = '';

    form.clearErrors();
};

watch(
    () => props.role,
    (role) => {
        const nextType = resolveAccountType(role);

        if (form.account_type !== nextType) {
            switchType(nextType);
        }
    }
);

const validateForm = () => {
    const errors = {};

    if (!form.name || form.name.trim().length < 2) {
        errors.name = ui.value.required;
    }

    if (!emailPattern.test(form.email)) {
        errors.email = ui.value.email;
    }

    if (!form.company_name || form.company_name.trim().length < 2) {
        errors.company_name = ui.value.required;
    }

    if (!form.country) {
        errors.country = ui.value.required;
    }

    if (!passwordPattern.test(form.password)) {
        errors.password = ui.value.password;
    }

    if (!form.password_confirmation || form.password_confirmation !== form.password) {
        errors.password_confirmation = ui.value.passwordConfirmation;
    }

    if (!form.agree_to_terms) {
        errors.agree_to_terms = ui.value.terms;
    }

    return errors;
};

const submit = () => {
    const clientErrors = validateForm();

    if (Object.keys(clientErrors).length > 0) {
        form.setError(clientErrors);
        focusFirstError(clientErrors);
        return;
    }

    form.post('/register', {
        preserveState: true,
        preserveScroll: true,
        onError: focusFirstError,
    });
};
</script>

<template>
    <Head :title="copy.headTitle">
        <meta head-key="robots" name="robots" content="noindex, nofollow" />
    </Head>

    <MainLayout>
        <section class="auth-shell">
            <div class="auth-card">
                <p class="eyebrow">{{ copy.eyebrow }}</p>
                <h1>{{ copy.title }}</h1>
                <p class="auth-subtitle">{{ copy.text }}</p>

                <div class="type-switcher">
                    <button type="button" :class="{ active: form.account_type === 'buyer' }" @click="switchType('buyer')">
                        {{ copy.buyer }}
                    </button>
                    <button type="button" :class="{ active: form.account_type === 'seller' }" @click="switchType('seller')">
                        {{ copy.seller }}
                    </button>
                </div>

                <form class="form-grid" @submit.prevent="submit">
                    <div class="field-grid">
                        <label>
                            <span v-html="formatRequiredLabel(copy.fullName)"></span>
                            <input
                                :ref="setFieldRef('name')"
                                v-model="form.name"
                                :class="inputClass('name')"
                                type="text"
                                :placeholder="copy.fullNamePlaceholder"
                                @input="clearFieldError('name')"
                            />
                            <small v-if="form.errors.name">{{ form.errors.name }}</small>
                        </label>

                        <label>
                            <span v-html="formatRequiredLabel(copy.email)"></span>
                            <input
                                :ref="setFieldRef('email')"
                                :value="form.email"
                                :class="inputClass('email')"
                                type="email"
                                inputmode="email"
                                autocomplete="email"
                                :placeholder="copy.emailPlaceholder"
                                @input="handleEmailInput($event.target.value)"
                            />
                            <small v-if="form.errors.email">{{ form.errors.email }}</small>
                        </label>
                    </div>

                    <label>
                        <span v-html="formatRequiredLabel(copy.companyName)"></span>
                        <input
                            :ref="setFieldRef('company_name')"
                            v-model="form.company_name"
                            :class="inputClass('company_name')"
                            type="text"
                            :placeholder="copy.companyNamePlaceholder"
                            @input="clearFieldError('company_name')"
                        />
                        <small v-if="form.errors.company_name">{{ form.errors.company_name }}</small>
                    </label>

                    <label>
                        <span v-html="formatRequiredLabel(copy.country)"></span>
                        <select
                            :ref="setFieldRef('country')"
                            v-model="form.country"
                            :class="inputClass('country')"
                            @change="clearFieldError('country')"
                        >
                            <option value="" disabled>{{ copy.selectCountry }}</option>
                            <option v-for="item in countrySelectOptions" :key="item.value" :value="item.value">{{ item.label }}</option>
                        </select>
                        <small v-if="form.errors.country">{{ form.errors.country }}</small>
                    </label>

                    <div class="password-block">
                        <div class="field-grid">
                            <label>
                                <span v-html="formatRequiredLabel(copy.password)"></span>
                                <AuthPasswordInput
                                    :ref="setFieldRef('password')"
                                    v-model="form.password"
                                    :class="inputClass('password')"
                                    autocomplete="new-password"
                                    :placeholder="copy.passwordPlaceholder"
                                    @input="clearFieldError('password')"
                                />
                            </label>

                            <label>
                                <span v-html="formatRequiredLabel(copy.passwordConfirmation)"></span>
                                <AuthPasswordInput
                                    :ref="setFieldRef('password_confirmation')"
                                    v-model="form.password_confirmation"
                                    :class="inputClass('password_confirmation')"
                                    autocomplete="new-password"
                                    :placeholder="copy.passwordConfirmationPlaceholder"
                                    @input="clearFieldError('password_confirmation')"
                                />
                            </label>
                        </div>

                        <div class="password-rules">
                            <p>{{ ui.passwordRules }}</p>
                            <div class="password-rule-item" :class="{ active: passwordChecks.length }">
                                <span class="rule-icon" aria-hidden="true"></span>
                                <span>{{ ui.minChars }}</span>
                            </div>
                            <div class="password-rule-item" :class="{ active: passwordChecks.letter }">
                                <span class="rule-icon" aria-hidden="true"></span>
                                <span>{{ ui.hasLetter }}</span>
                            </div>
                            <div class="password-rule-item" :class="{ active: passwordChecks.number }">
                                <span class="rule-icon" aria-hidden="true"></span>
                                <span>{{ ui.hasNumber }}</span>
                            </div>
                        </div>

                        <small v-if="form.errors.password || form.errors.password_confirmation" class="password-error">
                            {{ form.errors.password || form.errors.password_confirmation }}
                        </small>
                    </div>

                    <label class="checkbox-row">
                        <input :ref="setFieldRef('agree_to_terms')" v-model="form.agree_to_terms" type="checkbox" @change="clearFieldError('agree_to_terms')" />
                        <span>
                            {{ copy.agreementLead }}
                            <Link href="/terms-of-service">{{ copy.agreementTerms }}</Link>
                            {{ copy.agreementJoiner }}
                            <Link href="/privacy-policy">{{ copy.agreementPrivacy }}</Link>
                            {{ copy.agreementTail }}
                        </span>
                    </label>
                    <small v-if="form.errors.agree_to_terms" class="checkbox-error">{{ form.errors.agree_to_terms }}</small>

                    <p v-if="form.account_type === 'seller'" class="next-step-note">{{ copy.sellerNextStep }}</p>
                    <button type="submit" :disabled="form.processing">{{ form.account_type === 'seller' ? copy.sellerButton : copy.button }}</button>
                </form>

                <p class="auth-footer">
                    {{ copy.loginPrompt }}
                    <Link :href="form.next ? `/login?next=${encodeURIComponent(form.next)}` : '/login'">
                        {{ copy.loginLink }}
                    </Link>
                </p>
            </div>
        </section>
    </MainLayout>
</template>

<style scoped>
.auth-shell {
    display: grid;
    place-items: center;
    padding: 16px 0 56px;
}

.auth-card {
    width: min(760px, 100%);
    padding: 26px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background:
        radial-gradient(circle at top left, rgba(15, 118, 110, 0.14), transparent 35%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(247, 241, 232, 0.96));
    box-shadow: 0 24px 48px rgba(15, 23, 42, 0.08);
}

.eyebrow {
    margin: 0 0 10px;
    font-size: 0.8rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--color-ocean);
    font-weight: 700;
}

.auth-card h1 {
    margin: 0;
    font-family: var(--font-display);
    font-size: clamp(2rem, 2.8vw, 2.55rem);
    line-height: 1.05;
}

.auth-subtitle {
    margin: 10px 0 0;
    color: rgba(4, 21, 31, 0.7);
    font-size: 1rem;
    line-height: 1.5;
}

.type-switcher {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    width: min(320px, 100%);
    margin-top: 18px;
    margin-inline: auto;
    padding: 6px;
    border-radius: 10px;
    background: rgba(4, 21, 31, 0.05);
}

.type-switcher button {
    border: 0;
    border-radius: 10px;
    padding: 12px 18px;
    background: transparent;
    color: var(--color-ink);
    font-weight: 700;
    transition: background 160ms ease, color 160ms ease;
}

.type-switcher button.active {
    background: var(--color-ink);
    color: white;
}

.form-grid {
    display: grid;
    gap: 14px;
    margin-top: 22px;
}

.field-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    align-items: start;
}

.form-grid label,
.password-block {
    display: grid;
    gap: 8px;
}

.form-grid label {
    font-weight: 600;
}

:deep(.required-star) {
    color: #be123c;
}

.label-row {
    display: flex;
    align-items: center;
}

.form-grid input,
.form-grid textarea,
.form-grid select {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    border: 1px solid rgba(4, 21, 31, 0.12);
    border-radius: 10px;
    padding: 14px 16px;
    background: rgba(255, 255, 255, 0.92);
    transition: border-color 160ms ease, background-color 160ms ease;
}

.form-grid input:focus,
.form-grid textarea:focus,
.form-grid select:focus {
    outline: none;
    border-color: rgba(15, 118, 110, 0.55);
    box-shadow: none;
}

.form-grid input.has-error,
.form-grid textarea.has-error,
.form-grid select.has-error {
    border-color: #d92d20;
    box-shadow: none;
    background: rgba(255, 245, 245, 0.95);
}

.country-stack {
    display: grid;
    gap: 10px;
}

.country-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
}

.country-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.add-country-inline,
.remove-country {
    width: 42px;
    height: 42px;
    border: 0;
    border-radius: 10px;
    font-size: 1.2rem;
    font-weight: 700;
}

.add-country-inline {
    background: rgba(15, 118, 110, 0.12);
    color: var(--color-ocean);
}

.remove-country {
    background: rgba(217, 45, 32, 0.1);
    color: #b42318;
}

.password-rules {
    display: grid;
    gap: 8px;
    padding: 14px 16px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.62);
}

.password-rules p {
    margin: 0;
    color: rgba(4, 21, 31, 0.72);
    font-size: 0.92rem;
    font-weight: 600;
}

.password-rule-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: rgba(4, 21, 31, 0.56);
    font-size: 0.92rem;
    font-weight: 600;
}

.password-rule-item.active {
    color: #0f766e;
}

.rule-icon {
    position: relative;
    flex: 0 0 18px;
    width: 18px;
    height: 18px;
}

.rule-icon::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 5px;
    height: 5px;
    border-radius: 999px;
    background: rgba(4, 21, 31, 0.54);
    transform: translate(-50%, -50%);
}

.password-rule-item.active .rule-icon::before {
    content: '✓';
    width: auto;
    height: auto;
    background: transparent;
    color: #0f766e;
    font-size: 0.95rem;
    font-weight: 700;
}

.password-error {
    color: #b42318;
}

.checkbox-row {
    grid-template-columns: auto 1fr;
    align-items: start;
}

.checkbox-row input {
    width: 18px;
    height: 18px;
    margin-top: 4px;
}

.checkbox-row span {
    color: rgba(4, 21, 31, 0.82);
    line-height: 1.6;
}

.checkbox-row a {
    color: var(--color-ocean);
    font-weight: 700;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.form-grid > button[type='submit'] {
    margin-top: 8px;
    border: 0;
    border-radius: 10px;
    padding: 14px 18px;
    background: var(--color-ink);
    color: white;
    font-weight: 700;
}

.next-step-note {
    margin: 4px 0 0;
    color: rgba(4, 21, 31, 0.72);
    font-size: 0.92rem;
    line-height: 1.5;
}

.checkbox-error,
.form-grid small,
.auth-footer {
    color: rgba(4, 21, 31, 0.72);
}

.checkbox-error,
.form-grid small {
    color: #b42318;
}

.auth-footer {
    margin: 18px 0 0;
    padding-top: 16px;
    border-top: 1px solid rgba(4, 21, 31, 0.08);
}

.auth-footer a {
    font-weight: 700;
}

@media (max-width: 720px) {
    .field-grid {
        grid-template-columns: 1fr;
    }

    .type-switcher {
        width: 100%;
    }

    .auth-card h1 {
        max-width: none;
    }

    .country-row {
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
    }

    .country-actions {
        padding-top: 0;
        justify-content: flex-end;
    }
}
</style>
