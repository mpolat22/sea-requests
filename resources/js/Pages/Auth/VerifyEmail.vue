<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '../../Layouts/MainLayout.vue';

const copy = {
    eyebrow: 'Verify Email',
    title: 'Confirm your email address',
    text: 'Please verify your email address before continuing.',
    sending: 'Sending...',
    resend: 'Resend Verification Email',
    logout: 'Log Out',
};

const form = useForm({});

const resend = () => {
    form.post('/email/verification-notification');
};
</script>

<template>
    <Head title="Verify Email | Sea Requests" />

    <MainLayout>
        <section class="notice-shell">
            <div class="notice-card">
                <p class="eyebrow">{{ copy.eyebrow }}</p>
                <h1>{{ copy.title }}</h1>
                <p>{{ copy.text }}</p>

                <div class="notice-actions">
                    <button type="button" :disabled="form.processing" @click="resend">
                        {{ form.processing ? copy.sending : copy.resend }}
                    </button>
                    <Link href="/logout" method="post" as="button" class="ghost">{{ copy.logout }}</Link>
                </div>
            </div>
        </section>
    </MainLayout>
</template>

<style scoped>
.notice-shell {
    display: grid;
    place-items: center;
    padding: 16px 0 56px;
}

.notice-card {
    width: min(760px, 100%);
    padding: 34px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.82);
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.08);
}

.notice-card h1 {
    margin: 0;
    font-family: var(--font-display);
    font-size: clamp(2.05rem, 3vw, 2.75rem);
    line-height: 1.05;
}

.notice-card p:not(.eyebrow) {
    color: rgba(4, 21, 31, 0.72);
    line-height: 1.7;
}

.eyebrow {
    margin: 0 0 12px;
    font-size: 0.82rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--color-ocean);
    font-weight: 700;
}

.notice-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 18px;
}

.notice-actions button {
    border: 0;
    border-radius: 999px;
    padding: 14px 18px;
    background: var(--color-ink);
    color: white;
    font-weight: 700;
    transition: opacity 160ms ease, transform 160ms ease;
}

.notice-actions button:disabled {
    opacity: 0.68;
    cursor: not-allowed;
}

.notice-actions .ghost {
    background: rgba(4, 21, 31, 0.08);
    color: var(--color-ink);
}
</style>
