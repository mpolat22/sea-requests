<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthPasswordInput from '../../Components/AuthPasswordInput.vue';
import MainLayout from '../../Layouts/MainLayout.vue';
import { useI18n } from '../../lib/i18n';

const props = defineProps({
    profile: { type: Object, required: true },
    updateUrl: { type: String, required: true },
    backUrl: { type: String, required: true },
});

const { section } = useI18n();
const copy = section('admin.profile');
const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const passwordChecks = computed(() => ({
    length: form.password.length >= 8,
    letter: /[A-Za-z]/.test(form.password),
    number: /\d/.test(form.password),
}));

const submit = () => {
    form.patch(props.updateUrl, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head :title="copy.headTitle">
        <meta head-key="robots" name="robots" content="noindex, nofollow" />
    </Head>

    <MainLayout>
        <section class="profile-shell">
            <header class="profile-hero">
                <div>
                    <p class="eyebrow">{{ copy.eyebrow }}</p>
                    <h1>{{ copy.title }}</h1>
                    <p class="intro">{{ copy.intro }}</p>
                </div>
                <Link class="back-link" :href="backUrl">{{ copy.back }}</Link>
            </header>

            <section class="profile-card">
                <div class="section-heading">
                    <h2>{{ copy.accountTitle }}</h2>
                    <p>{{ copy.accountNote }}</p>
                </div>
                <div class="account-details">
                    <div><span>{{ copy.name }}</span><strong>{{ profile.name }}</strong></div>
                    <div><span>{{ copy.email }}</span><strong>{{ profile.email }}</strong></div>
                </div>
            </section>

            <section class="profile-card">
                <div class="section-heading">
                    <h2>{{ copy.passwordTitle }}</h2>
                    <p>{{ copy.passwordNote }}</p>
                </div>

                <form class="password-form" @submit.prevent="submit">
                    <label class="field">
                        <span>{{ copy.currentPassword }}</span>
                        <AuthPasswordInput
                            v-model="form.current_password"
                            :class="{ 'has-error': Boolean(form.errors.current_password) }"
                            autocomplete="current-password"
                            required
                            @input="form.clearErrors('current_password')"
                        />
                        <small v-if="form.errors.current_password" class="field-error">{{ form.errors.current_password }}</small>
                    </label>

                    <div class="new-password-fields">
                        <label class="field">
                            <span>{{ copy.newPassword }}</span>
                            <AuthPasswordInput
                                v-model="form.password"
                                :class="{ 'has-error': Boolean(form.errors.password) }"
                                autocomplete="new-password"
                                required
                                @input="form.clearErrors('password')"
                            />
                            <small v-if="form.errors.password" class="field-error">{{ form.errors.password }}</small>
                        </label>

                        <label class="field">
                            <span>{{ copy.confirmPassword }}</span>
                            <AuthPasswordInput
                                v-model="form.password_confirmation"
                                :class="{ 'has-error': Boolean(form.errors.password_confirmation) }"
                                autocomplete="new-password"
                                required
                                @input="form.clearErrors('password_confirmation')"
                            />
                            <small v-if="form.errors.password_confirmation" class="field-error">{{ form.errors.password_confirmation }}</small>
                        </label>
                    </div>

                    <div class="password-rules" aria-live="polite">
                        <span :class="{ met: passwordChecks.length }">{{ copy.minChars }}</span>
                        <span :class="{ met: passwordChecks.letter }">{{ copy.hasLetter }}</span>
                        <span :class="{ met: passwordChecks.number }">{{ copy.hasNumber }}</span>
                    </div>

                    <div class="form-actions">
                        <button type="submit" :disabled="form.processing">
                            {{ form.processing ? copy.saving : copy.save }}
                        </button>
                    </div>
                </form>
            </section>
        </section>
    </MainLayout>
</template>

<style scoped>
.profile-shell{max-width:960px;margin:0 auto;padding:16px 0 56px;display:grid;gap:20px}
.profile-hero,.profile-card{padding:32px 36px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.94);box-shadow:0 20px 42px rgba(15,23,42,.06)}
.profile-hero{display:flex;justify-content:space-between;align-items:flex-start;gap:24px}
.eyebrow{margin:0 0 10px;color:#0f766e;font-size:.75rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase}
h1{margin:0;color:#0f172a;font-size:clamp(1.8rem,3vw,2.5rem);line-height:1.2}
.intro,.section-heading p{margin:10px 0 0;color:#64748b;line-height:1.65}
.back-link{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 18px;border:1px solid #d9e2ef;border-radius:10px;color:#0f172a;text-decoration:none;white-space:nowrap}
.section-heading{margin-bottom:20px}
.section-heading h2{margin:0;color:#0f172a;font-size:1.05rem}
.account-details{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.account-details div{display:grid;gap:6px;min-width:0;padding:18px;border-radius:10px;background:#f7f9fa}
.account-details span{color:#64748b;font-size:.84rem}
.account-details strong{overflow-wrap:anywhere;color:#0f172a;font-size:.94rem}
.password-form,.field{display:grid;gap:10px}
.password-form{gap:20px}
.field{min-width:0;color:#0f172a;font-size:.9rem;font-weight:700}
.new-password-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
.field-error{color:#be123c;font-size:.82rem;font-weight:400;line-height:1.45}
.password-rules{display:flex;flex-wrap:wrap;gap:8px}
.password-rules span{padding:7px 10px;border-radius:999px;background:#f1f5f9;color:#64748b;font-size:.8rem}
.password-rules span.met{background:#e5f6ef;color:#047857}
.form-actions{display:flex;justify-content:flex-end}
.form-actions button{min-height:44px;padding:0 20px;border:0;border-radius:10px;background:#2563eb;color:#fff;font:inherit;font-weight:700;cursor:pointer}
.form-actions button:disabled{opacity:.6;cursor:not-allowed}
@media(max-width:720px){.profile-shell{padding:12px 0 40px}.profile-hero,.profile-card{padding:20px}.profile-hero{flex-direction:column}.back-link,.form-actions button{width:100%}.new-password-fields,.account-details{grid-template-columns:1fr}}
</style>
