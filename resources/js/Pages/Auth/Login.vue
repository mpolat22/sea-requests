<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '../../Layouts/MainLayout.vue';

const emailPattern = /^[^\s@]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;

const props = defineProps({
    next: {
        type: String,
        default: '',
    },
});

const copy = {
    eyebrow: 'Access your account',
    title: 'Log in to Sea Requests',
    text: 'Review your RFQs, supplier activity, and business profile from one place.',
    email: 'Email address',
    emailPlaceholder: 'name@company.com',
    password: 'Password',
    passwordPlaceholder: 'Enter your password',
    remember: 'Keep me signed in',
    forgot: 'Forgot password?',
    button: 'Log in',
    registerPrompt: "Don't have an account yet?",
    registerLink: 'Create an account',
};

const form = useForm({
    email: '',
    password: '',
    remember: false,
    next: props.next ?? '',
});

const handleEmailInput = (value) => {
    form.email = value.trim().toLowerCase();

    if (emailPattern.test(form.email)) {
        form.clearErrors('email');
    }
};

const submit = () => {
    form.post('/login');
};
</script>

<template>
    <Head title="Login | Sea Requests">
        <meta head-key="robots" name="robots" content="noindex, nofollow" />
    </Head>

    <MainLayout>
        <section class="login-shell">
            <div class="login-card">
                <p class="eyebrow">{{ copy.eyebrow }}</p>
                <h1>{{ copy.title }}</h1>
                <p class="login-subtitle">{{ copy.text }}</p>

                <form class="form-grid" @submit.prevent="submit">
                    <label>
                        {{ copy.email }}
                        <input :value="form.email" type="email" inputmode="email" autocomplete="email" :placeholder="copy.emailPlaceholder" @input="handleEmailInput($event.target.value)" />
                        <small v-if="form.errors.email">{{ form.errors.email }}</small>
                    </label>

                    <label>
                        {{ copy.password }}
                        <input v-model="form.password" type="password" :placeholder="copy.passwordPlaceholder" />
                        <small v-if="form.errors.password">{{ form.errors.password }}</small>
                    </label>

                    <div class="login-actions">
                        <label class="remember-row">
                            <input v-model="form.remember" type="checkbox" />
                            <span>{{ copy.remember }}</span>
                        </label>

                        <Link class="forgot-link" href="/forgot-password">{{ copy.forgot }}</Link>
                    </div>

                    <button type="submit" :disabled="form.processing">{{ copy.button }}</button>
                </form>

                <p class="auth-footer">
                    {{ copy.registerPrompt }}
                    <Link :href="form.next ? `/register?next=${encodeURIComponent(form.next)}` : '/register'">
                        {{ copy.registerLink }}
                    </Link>
                </p>
            </div>
        </section>
    </MainLayout>
</template>

<style scoped>
.login-shell {
    display: grid;
    place-items: center;
    padding: 16px 0 56px;
}

.login-card {
    width: min(620px, 100%);
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

.login-card h1 {
    margin: 0;
    font-family: var(--font-display);
    font-size: clamp(2rem, 2.8vw, 2.55rem);
    line-height: 1.05;
}

.login-subtitle {
    margin: 10px 0 0;
    color: rgba(4, 21, 31, 0.7);
    font-size: 1rem;
    line-height: 1.5;
}

.form-grid {
    display: grid;
    gap: 16px;
    margin-top: 24px;
}

.form-grid label {
    display: grid;
    gap: 8px;
    font-weight: 600;
}

.form-grid input {
    border: 1px solid rgba(4, 21, 31, 0.12);
    border-radius: 10px;
    padding: 14px 16px;
    background: rgba(255, 255, 255, 0.92);
}

.form-grid input:focus {
    outline: none;
    border-color: rgba(15, 118, 110, 0.55);
    box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12);
}

.form-grid button {
    margin-top: 6px;
    border: 0;
    border-radius: 999px;
    padding: 14px 18px;
    background: var(--color-ink);
    color: white;
    font-weight: 700;
}

.form-grid small {
    color: #b42318;
}

.login-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.form-grid .remember-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    width: fit-content;
    line-height: 1;
    grid-auto-flow: column;
}

.form-grid .remember-row input {
    width: 18px;
    height: 18px;
    margin: 0;
    flex: 0 0 auto;
}

.form-grid .remember-row span {
    display: inline-flex;
    align-items: center;
}

.forgot-link {
    font-weight: 700;
    color: var(--color-ocean);
}

.auth-footer {
    margin: 18px 0 0;
    padding-top: 16px;
    border-top: 1px solid rgba(4, 21, 31, 0.08);
    color: rgba(4, 21, 31, 0.72);
}

.auth-footer a {
    font-weight: 700;
}

@media (max-width: 720px) {
    .login-actions {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
