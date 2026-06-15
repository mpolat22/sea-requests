<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '../../Layouts/MainLayout.vue';

const copy = {
    eyebrow: 'Forgot Password',
    title: 'Reset your password',
    text: 'Enter your email address and we will send you a password reset link.',
    email: 'Email',
    emailPlaceholder: 'name@company.com',
    button: 'Send Reset Link',
    back: 'Back to Sign In',
};

const form = useForm({
    email: '',
});

const submit = () => {
    form.post('/forgot-password');
};
</script>

<template>
    <Head title="Forgot Password | Sea Requests">
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

                    <button type="submit" :disabled="form.processing">{{ copy.button }}</button>
                </form>

                <p class="auth-footer">
                    <Link href="/login">{{ copy.back }}</Link>
                </p>
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
.form-grid label { display: grid; gap: 8px; font-weight: 600; }
.form-grid input { border: 1px solid rgba(4, 21, 31, 0.12); border-radius: 10px; padding: 14px 16px; background: rgba(255, 255, 255, 0.92); }
.form-grid input:focus { outline: none; border-color: rgba(15, 118, 110, 0.55); box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12); }
.form-grid button { margin-top: 6px; border: 0; border-radius: 10px; padding: 14px 18px; background: var(--color-ink); color: white; font-weight: 700; }
.form-grid small { color: #b42318; }
.auth-footer { margin: 18px 0 0; padding-top: 16px; border-top: 1px solid rgba(4, 21, 31, 0.08); }
.auth-footer a { font-weight: 700; }
</style>
