<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import MainLayout from '../../Layouts/MainLayout.vue';
import { normalizeEmailInput } from '../../lib/normalizeEmailInput';

const page = usePage();
const emailPattern = /^[^\s@]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;

const copy = {
    eyebrow: 'Forgot Password',
    title: 'Reset your password',
    text: 'Enter your email address and we will send you a password reset link.',
    email: 'Email',
    emailPlaceholder: 'name@company.com',
    button: 'Send Reset Link',
    buttonSent: 'Reset Link Sent',
    buttonResend: 'Resend Reset Link',
    successTitle: 'Check your email',
    successText: 'If an account exists for this email address, we sent a password reset link.',
    spamHint: 'If you do not see it soon, please check your spam or junk folder.',
    useAnotherEmail: 'Use another email',
    back: 'Back to Sign In',
};

const form = useForm({
    email: '',
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const hasSentState = ref(false);
const cooldown = ref(0);
const lockedEmail = ref('');
let cooldownTimer = null;

const stopCooldown = () => {
    if (cooldownTimer) {
        window.clearInterval(cooldownTimer);
        cooldownTimer = null;
    }
};

const startCooldown = (seconds = 45) => {
    stopCooldown();
    cooldown.value = seconds;

    cooldownTimer = window.setInterval(() => {
        if (cooldown.value <= 1) {
            cooldown.value = 0;
            stopCooldown();
            return;
        }

        cooldown.value -= 1;
    }, 1000);
};

const activateSentState = () => {
    hasSentState.value = true;
    lockedEmail.value = form.email;
    startCooldown();
};

watch(flashSuccess, (value) => {
    if (!value || !form.email) {
        return;
    }

    activateSentState();
}, { immediate: true });

const inlineSuccessMessage = computed(() => {
    if (!hasSentState.value) {
        return null;
    }

    return copy.successText;
});

const submitButtonLabel = computed(() => {
    if (form.processing) {
        return hasSentState.value ? copy.buttonResend : copy.button;
    }

    if (hasSentState.value && cooldown.value > 0) {
        return `${copy.buttonSent} (${cooldown.value}s)`;
    }

    if (hasSentState.value) {
        return copy.buttonResend;
    }

    return copy.button;
});

const inputReadonly = computed(() => hasSentState.value && cooldown.value > 0);
const submitDisabled = computed(() => form.processing || (hasSentState.value && cooldown.value > 0));

const handleEmailInput = (value) => {
    form.email = normalizeEmailInput(value);

    if (emailPattern.test(form.email)) {
        form.clearErrors('email');
    }
};

const submit = () => {
    form.post('/forgot-password', {
        preserveScroll: true,
    });
};

const useAnotherEmail = () => {
    hasSentState.value = false;
    lockedEmail.value = '';
    cooldown.value = 0;
    stopCooldown();
    form.email = '';
    form.clearErrors();
};

onBeforeUnmount(() => {
    stopCooldown();
});
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

                <div v-if="inlineSuccessMessage" class="auth-success" aria-live="polite">
                    <strong>{{ copy.successTitle }}</strong>
                    <p>{{ inlineSuccessMessage }}</p>
                    <p v-if="lockedEmail" class="auth-success-email">{{ lockedEmail }}</p>
                    <small>{{ copy.spamHint }}</small>
                </div>

                <form class="form-grid" @submit.prevent="submit">
                    <label>
                        {{ copy.email }}
                        <input
                            :value="form.email"
                            type="email"
                            inputmode="email"
                            autocomplete="email"
                            :placeholder="copy.emailPlaceholder"
                            :readonly="inputReadonly"
                            :class="{ 'is-readonly': inputReadonly }"
                            @input="handleEmailInput($event.target.value)"
                        />
                        <small v-if="form.errors.email">{{ form.errors.email }}</small>
                    </label>

                    <div class="auth-actions">
                        <button type="submit" :disabled="submitDisabled">{{ submitButtonLabel }}</button>
                        <button
                            v-if="hasSentState"
                            type="button"
                            class="button-link"
                            @click="useAnotherEmail"
                        >
                            {{ copy.useAnotherEmail }}
                        </button>
                    </div>
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
.auth-success {
    display: grid;
    gap: 6px;
    margin-top: 20px;
    padding: 16px 18px;
    border: 1px solid rgba(11, 122, 82, 0.18);
    border-radius: 12px;
    background: rgba(239, 250, 243, 0.95);
    color: #0b4f37;
}
.auth-success strong { font-size: 0.98rem; }
.auth-success p,
.auth-success small { margin: 0; }
.auth-success-email {
    font-weight: 700;
    color: var(--color-ink);
    word-break: break-word;
}
.form-grid { display: grid; gap: 16px; margin-top: 24px; }
.form-grid label { display: grid; gap: 8px; font-weight: 600; }
.form-grid input { border: 1px solid rgba(4, 21, 31, 0.12); border-radius: 10px; padding: 14px 16px; background: rgba(255, 255, 255, 0.92); }
.form-grid input:focus { outline: none; border-color: rgba(15, 118, 110, 0.55); box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12); }
.form-grid input.is-readonly {
    background: rgba(241, 245, 249, 0.95);
    color: rgba(4, 21, 31, 0.72);
}
.auth-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-top: 6px;
}
.form-grid button { border: 0; border-radius: 10px; padding: 14px 18px; background: var(--color-ink); color: white; font-weight: 700; }
.form-grid button:disabled { cursor: not-allowed; opacity: 0.72; }
.button-link {
    padding: 0;
    border: 0;
    background: transparent !important;
    color: var(--color-ocean) !important;
    font-weight: 700;
    text-decoration: underline;
    text-underline-offset: 3px;
    box-shadow: none !important;
}
.form-grid small { color: #b42318; }
.auth-footer { margin: 18px 0 0; padding-top: 16px; border-top: 1px solid rgba(4, 21, 31, 0.08); }
.auth-footer a { font-weight: 700; }

@media (max-width: 640px) {
    .auth-actions {
        align-items: stretch;
    }

    .auth-actions > * {
        width: 100%;
    }

    .button-link {
        text-align: left;
    }
}
</style>
