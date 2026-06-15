<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '../../../Layouts/MainLayout.vue';
import { countryOptions, dialCodes, parseDialPhone, sanitizePhoneDigits } from '../../../lib/accountContactOptions';

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
    updateUrl: {
        type: String,
        required: true,
    },
    backUrl: {
        type: String,
        required: true,
    },
});

const copy = {
    eyebrow: 'Buyer Account',
    title: 'Edit Profile',
    text: 'Update the same buyer account details used during registration and future communication.',
    emailNote: 'If you change your email address, a new verification link will be sent before you continue.',
    back: 'Back to My RFQs',
    save: 'Save Changes',
    saving: 'Saving...',
    fields: {
        name: 'Full Name',
        email: 'Email Address',
        country: 'Country',
        phone: 'Phone Number',
        whatsapp: 'WhatsApp Number',
    },
    hints: {
        name: 'Enter the full name that should appear on your buyer account.',
        email: 'This email is used for login and important account notifications.',
        country: 'Choose the main country for your buyer account.',
        phone: 'Select a country code and enter a phone number between 6 and 15 digits.',
        whatsapp: 'Optional. Add a WhatsApp number if you want suppliers or support to reach you there.',
    },
    selectCountry: 'Select Country',
    selectCode: 'Select Code',
    placeholders: {
        name: 'Your full name',
        email: 'name@company.com',
        phone: 'Enter phone number',
        whatsapp: 'Enter WhatsApp number',
    },
    verified: 'Email verified',
    verificationRequired: 'Email verification required',
};

const parsedPhone = parseDialPhone(props.profile.phone);
const parsedWhatsapp = parseDialPhone(props.profile.whatsapp_number, parsedPhone.code || '');

const mergeOption = (options, value, formatter = (item) => item) => {
    const normalizedValue = String(value ?? '').trim();

    if (!normalizedValue) {
        return options;
    }

    if (options.some((item) => item.value === normalizedValue)) {
        return options;
    }

    return [
        formatter(normalizedValue),
        ...options,
    ];
};

const profileCountryOptions = computed(() => mergeOption(
    countryOptions,
    props.profile.country,
    (value) => ({ label: value, value })
));

const phoneDialOptions = computed(() => mergeOption(
    dialCodes,
    parsedPhone.code,
    (value) => ({ label: `${value} (saved)`, value })
));

const whatsappDialOptions = computed(() => mergeOption(
    dialCodes,
    parsedWhatsapp.code,
    (value) => ({ label: `${value} (saved)`, value })
));

const form = useForm({
    name: props.profile.name ?? '',
    email: props.profile.email ?? '',
    country: props.profile.country ?? '',
    phone_country_code: parsedPhone.code,
    phone: parsedPhone.number,
    whatsapp_country_code: parsedWhatsapp.code,
    whatsapp_number: parsedWhatsapp.number,
});

const emailStatusLabel = computed(() => (
    props.profile.email_verified_at ? copy.verified : copy.verificationRequired
));

const emailStatusClass = computed(() => (
    props.profile.email_verified_at ? 'is-verified' : 'is-pending'
));

const inputClass = (field) => ({
    'has-error': Boolean(form.errors[field]),
});

const handleDigitsInput = (field, value) => {
    form[field] = sanitizePhoneDigits(value);
    form.clearErrors(field);
};

const submit = () => {
    form.patch(props.updateUrl, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Edit Profile | Sea Requests" />

    <MainLayout>
        <section class="profile-shell">
            <section class="surface-panel hero-panel">
                <div class="hero-copy">
                    <p class="directory-eyebrow">{{ copy.eyebrow }}</p>
                    <h1 class="directory-page-title">{{ copy.title }}</h1>
                    <p class="directory-intro-copy">{{ copy.text }}</p>

                    <div class="hero-pills">
                        <span class="status-pill" :class="emailStatusClass">
                            {{ emailStatusLabel }}
                        </span>
                    </div>
                </div>

                <div class="hero-actions">
                    <Link :href="backUrl" class="back-button">
                        {{ copy.back }}
                    </Link>
                </div>
            </section>

            <section class="surface-card form-card">
                <div class="section-heading">
                    <h2 class="directory-section-title">{{ copy.title }}</h2>
                    <p class="section-copy">{{ copy.emailNote }}</p>
                </div>

                <form class="profile-form" @submit.prevent="submit">
                    <label class="field">
                        <span class="field-label">{{ copy.fields.name }}</span>
                        <span class="field-hint">{{ copy.hints.name }}</span>
                        <input
                            v-model="form.name"
                            :class="inputClass('name')"
                            type="text"
                            autocomplete="name"
                            :placeholder="copy.placeholders.name"
                            @input="form.clearErrors('name')"
                        >
                        <small v-if="form.errors.name" class="field-error">{{ form.errors.name }}</small>
                    </label>

                    <label class="field">
                        <span class="field-label">{{ copy.fields.email }}</span>
                        <span class="field-hint">{{ copy.hints.email }}</span>
                        <input
                            v-model="form.email"
                            :class="inputClass('email')"
                            type="email"
                            inputmode="email"
                            autocomplete="email"
                            :placeholder="copy.placeholders.email"
                            @input="form.clearErrors('email')"
                        >
                        <small v-if="form.errors.email" class="field-error">{{ form.errors.email }}</small>
                    </label>

                    <label class="field">
                        <span class="field-label">{{ copy.fields.country }}</span>
                        <span class="field-hint">{{ copy.hints.country }}</span>
                        <select
                            v-model="form.country"
                            :class="inputClass('country')"
                            autocomplete="country-name"
                            @change="form.clearErrors('country')"
                        >
                            <option value="" disabled>{{ copy.selectCountry }}</option>
                            <option v-for="item in profileCountryOptions" :key="item.value" :value="item.value">{{ item.label }}</option>
                        </select>
                        <small v-if="form.errors.country" class="field-error">{{ form.errors.country }}</small>
                    </label>

                    <label class="field">
                        <span class="field-label">{{ copy.fields.phone }}</span>
                        <span class="field-hint">{{ copy.hints.phone }}</span>
                        <div class="phone-group">
                            <select
                                v-model="form.phone_country_code"
                                :class="inputClass('phone_country_code')"
                                @change="form.clearErrors('phone_country_code')"
                            >
                                <option value="" disabled>{{ copy.selectCode }}</option>
                                <option v-for="item in phoneDialOptions" :key="`phone-${item.value}`" :value="item.value">{{ item.label }}</option>
                            </select>
                            <input
                                :value="form.phone"
                                :class="inputClass('phone')"
                                type="tel"
                                inputmode="numeric"
                                autocomplete="tel-national"
                                :placeholder="copy.placeholders.phone"
                                @input="handleDigitsInput('phone', $event.target.value)"
                            >
                        </div>
                        <small v-if="form.errors.phone_country_code" class="field-error">{{ form.errors.phone_country_code }}</small>
                        <small v-if="form.errors.phone" class="field-error">{{ form.errors.phone }}</small>
                    </label>

                    <label class="field field-wide">
                        <span class="field-label">{{ copy.fields.whatsapp }}</span>
                        <span class="field-hint">{{ copy.hints.whatsapp }}</span>
                        <div class="phone-group">
                            <select
                                v-model="form.whatsapp_country_code"
                                :class="inputClass('whatsapp_country_code')"
                                @change="form.clearErrors('whatsapp_country_code')"
                            >
                                <option value="" disabled>{{ copy.selectCode }}</option>
                                <option v-for="item in whatsappDialOptions" :key="`whatsapp-${item.value}`" :value="item.value">{{ item.label }}</option>
                            </select>
                            <input
                                :value="form.whatsapp_number"
                                :class="inputClass('whatsapp_number')"
                                type="tel"
                                inputmode="numeric"
                                autocomplete="tel-national"
                                :placeholder="copy.placeholders.whatsapp"
                                @input="handleDigitsInput('whatsapp_number', $event.target.value)"
                            >
                        </div>
                        <small v-if="form.errors.whatsapp_country_code" class="field-error">{{ form.errors.whatsapp_country_code }}</small>
                        <small v-if="form.errors.whatsapp_number" class="field-error">{{ form.errors.whatsapp_number }}</small>
                    </label>

                    <div class="form-actions">
                        <button type="submit" class="save-button" :disabled="form.processing">
                            {{ form.processing ? copy.saving : copy.save }}
                        </button>
                    </div>
                </form>
            </section>
        </section>
    </MainLayout>
</template>

<style scoped>
.profile-shell{padding:16px 0 56px;display:grid;gap:20px}
.surface-panel,.surface-card{padding:32px 36px;border:1px solid rgba(4,21,31,.08);border-radius:10px;background:rgba(255,255,255,.94);box-shadow:0 20px 42px rgba(15,23,42,.06)}
.hero-panel{display:flex;align-items:flex-start;justify-content:space-between;gap:24px}
.hero-copy{display:grid;gap:14px}
.hero-copy :deep(.directory-page-title){margin:0}
.hero-copy :deep(.directory-intro-copy){max-width:72ch}
.hero-pills{display:flex;flex-wrap:wrap;gap:10px}
.hero-actions{display:flex;flex-wrap:wrap;gap:10px}
.back-button,.save-button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 18px;border-radius:10px;border:1px solid transparent;font-size:.92rem;font-weight:600;text-decoration:none}
.back-button{background:#fff;border-color:#d9e2ef;color:#0f172a}
.save-button{background:#2563eb;border-color:#2563eb;color:#fff;box-shadow:0 12px 24px rgba(37,99,235,.18)}
.save-button:disabled{opacity:.6;cursor:not-allowed}
.status-pill{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 12px;border-radius:10px;font-size:.82rem;font-weight:600}
.status-pill.is-verified{background:rgba(34,197,94,.12);color:#15803d}
.status-pill.is-pending{background:rgba(245,158,11,.14);color:#b45309}
.section-heading{display:grid;gap:8px;margin-bottom:20px}
.section-heading :deep(.directory-section-title){margin:0;font-size:1.04rem;font-weight:700;line-height:1.25;color:#0f172a}
.section-copy{margin:0;color:#64748b;line-height:1.7}
.profile-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 20px}
.field{display:grid;gap:7px;min-width:0}
.field-wide{grid-column:1 / -1}
.field-label{color:#0f172a;font-size:.9rem;font-weight:700;line-height:1.35}
.field-hint{color:#64748b;font-size:.84rem;line-height:1.55}
.field input,.field select{width:100%;min-height:48px;padding:0 16px;border:1px solid rgba(148,163,184,.32);border-radius:10px;background:#fff;color:#0f172a;font-size:.92rem}
.field input:focus,.field select:focus{outline:none;border-color:#60a5fa;box-shadow:0 0 0 4px rgba(96,165,250,.14)}
.field input.has-error,.field select.has-error{border-color:#e11d48;box-shadow:0 0 0 4px rgba(225,29,72,.12)}
.phone-group{display:grid;grid-template-columns:minmax(172px,220px) minmax(0,1fr);gap:12px}
.field-error{color:#be123c;font-size:.82rem;line-height:1.45}
.form-actions{grid-column:1 / -1;display:flex;justify-content:flex-end;padding-top:4px}
@media (max-width: 860px){
    .profile-form{grid-template-columns:1fr}
}
@media (max-width: 720px){
    .profile-shell{padding:12px 0 40px}
    .surface-panel,.surface-card{padding:20px}
    .hero-panel{flex-direction:column}
    .hero-actions{width:100%}
    .back-button,.save-button{width:100%}
    .form-actions{justify-content:stretch}
    .phone-group{grid-template-columns:1fr}
}
</style>
