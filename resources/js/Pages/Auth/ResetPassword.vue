<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthPasswordInput from '../../Components/AuthPasswordInput.vue';
import MainLayout from '../../Layouts/MainLayout.vue';

const props = defineProps({
    email: { type: String, default: '' },
    token: { type: String, required: true },
});

const copy = {
    eyebrow: 'Reset Password',
    title: 'Create a new password',
    text: 'Choose a new password for your account and confirm it below.',
    email: 'Email',
    emailPlaceholder: 'name@company.com',
    password: 'Password',
    passwordPlaceholder: 'Enter your new password',
    passwordConfirmation: 'Password Confirmation',
    passwordConfirmationPlaceholder: 'Repeat your new password',
    passwordRules: 'Password Rules',
    minChars: 'At least 8 characters',
    hasLetter: 'At least 1 letter',
    hasNumber: 'At least 1 number',
    button: 'Reset Password',
};

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const passwordChecks = computed(() => ({
    length: form.password.length >= 8,
    letter: /[A-Za-z]/.test(form.password),
    number: /\d/.test(form.password),
}));

const submit = () => {
    form.post('/reset-password');
};
</script>

<template>
    <Head title="Reset Password | Sea Requests">
        <meta head-key="robots" name="robots" content="noindex, nofollow" />
    </Head>

    <MainLayout>
        <section class="auth-shell">
            <div class="auth-card">
                <p class="eyebrow">{{ copy.eyebrow }}</p>
                <h1>{{ copy.title }}</h1>
                <p class="auth-subtitle">{{ copy.text }}</p>

                <form class="form-grid" @submit.prevent="submit">
                    <label>
                        {{ copy.email }}
                        <input v-model="form.email" type="email" inputmode="email" autocomplete="email" :placeholder="copy.emailPlaceholder" />
                        <small v-if="form.errors.email">{{ form.errors.email }}</small>
                    </label>

                    <div class="field-grid">
                        <label>
                            {{ copy.password }}
                            <AuthPasswordInput
                                v-model="form.password"
                                autocomplete="new-password"
                                :placeholder="copy.passwordPlaceholder"
                            />
                        </label>

                        <label>
                            {{ copy.passwordConfirmation }}
                            <AuthPasswordInput
                                v-model="form.password_confirmation"
                                autocomplete="new-password"
                                :placeholder="copy.passwordConfirmationPlaceholder"
                            />
                        </label>
                    </div>

                    <div class="password-rules">
                        <p>{{ copy.passwordRules }}</p>
                        <div class="password-rule-item" :class="{ active: passwordChecks.length }">
                            <span class="rule-icon">+</span>
                            <span>{{ copy.minChars }}</span>
                        </div>
                        <div class="password-rule-item" :class="{ active: passwordChecks.letter }">
                            <span class="rule-icon">+</span>
                            <span>{{ copy.hasLetter }}</span>
                        </div>
                        <div class="password-rule-item" :class="{ active: passwordChecks.number }">
                            <span class="rule-icon">+</span>
                            <span>{{ copy.hasNumber }}</span>
                        </div>
                    </div>

                    <small v-if="form.errors.password || form.errors.password_confirmation" class="password-error">
                        {{ form.errors.password || form.errors.password_confirmation }}
                    </small>

                    <button type="submit" :disabled="form.processing">{{ copy.button }}</button>
                </form>
            </div>
        </section>
    </MainLayout>
</template>

<style scoped>
.auth-shell { display: grid; place-items: center; padding: 16px 0 56px; }
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
.eyebrow { margin: 0 0 10px; font-size: 0.8rem; letter-spacing: 0.18em; text-transform: uppercase; color: var(--color-ocean); font-weight: 700; }
.auth-card h1 { margin: 0; font-family: var(--font-display); font-size: clamp(2rem, 2.8vw, 2.55rem); line-height: 1.05; }
.auth-subtitle { margin: 10px 0 0; color: rgba(4, 21, 31, 0.7); font-size: 1rem; line-height: 1.5; }
.form-grid { display: grid; gap: 16px; margin-top: 24px; }
.field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; align-items: start; }
.form-grid label { display: grid; gap: 8px; font-weight: 600; }
.form-grid input { border: 1px solid rgba(4, 21, 31, 0.12); border-radius: 10px; padding: 14px 16px; background: rgba(255, 255, 255, 0.92); }
.form-grid input:focus { outline: none; border-color: rgba(15, 118, 110, 0.55); box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12); }
.form-grid button { margin-top: 6px; border: 0; border-radius: 10px; padding: 14px 18px; background: var(--color-ink); color: white; font-weight: 700; }
.form-grid small, .password-error { color: #b42318; }
.password-rules { display: grid; gap: 8px; padding: 14px 16px; border: 1px solid rgba(4, 21, 31, 0.08); border-radius: 10px; background: rgba(255, 255, 255, 0.62); }
.password-rules p { margin: 0; color: rgba(4, 21, 31, 0.72); font-size: 0.92rem; font-weight: 600; }
.password-rule-item { display: flex; align-items: center; gap: 10px; color: rgba(4, 21, 31, 0.56); font-size: 0.92rem; font-weight: 600; }
.password-rule-item.active { color: #0f766e; }
.rule-icon { width: 18px; text-align: center; font-weight: 700; }
@media (max-width: 720px) { .field-grid { grid-template-columns: 1fr; } }
</style>
