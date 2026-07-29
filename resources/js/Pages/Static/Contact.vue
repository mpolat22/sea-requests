<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import MainLayout from '../../Layouts/MainLayout.vue';
import PublicMetaHead from '../../Components/PublicMetaHead.vue';
import StaticPageLayout from './StaticPageLayout.vue';
import { useI18n } from '../../lib/i18n';

const props = defineProps({
    contactEmail: {
        type: String,
        default: '',
    },
    meta: {
        type: Object,
        default: () => ({
            title: 'Contact | Sea Requests',
            description: '',
            canonical: '',
            robots: 'index, follow',
            ogImage: '',
            twitterCard: 'summary_large_image',
        }),
    },
});

const { section } = useI18n();
const page = computed(() => section('staticPages').value.contact);
const contactEmailDisplay = computed(() => props.contactEmail || 'support@searequests.ai');

const contactForm = useForm({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
    agree_to_contact: false,
});

const submitContactForm = () => {
    contactForm.post('/contact', {
        preserveScroll: true,
        onSuccess: () => contactForm.reset(),
    });
};
</script>

<template>
    <PublicMetaHead :meta="props.meta" />

    <MainLayout>
        <StaticPageLayout
            :eyebrow="page.eyebrow"
            :title="page.title"
            :intro="page.intro"
        >
            <div class="contact-layout">
                <section class="contact-form-panel">
                    <div class="contact-section-head">
                        <h2>{{ page.startTitle }}</h2>
                        <p>{{ page.startText }}</p>
                    </div>

                    <form class="contact-form-grid" @submit.prevent="submitContactForm">
                        <label>
                            <span>{{ page.name }} <span class="required-star">*</span></span>
                            <input v-model="contactForm.name" type="text" :placeholder="page.namePlaceholder" />
                            <small v-if="contactForm.errors.name">{{ contactForm.errors.name }}</small>
                        </label>

                        <label>
                            <span>{{ page.email }} <span class="required-star">*</span></span>
                            <input v-model="contactForm.email" type="email" :placeholder="page.emailPlaceholder" />
                            <small v-if="contactForm.errors.email">{{ contactForm.errors.email }}</small>
                        </label>

                        <label>
                            <span>{{ page.phone }} <span class="required-star">*</span></span>
                            <input v-model="contactForm.phone" type="text" :placeholder="page.phonePlaceholder" />
                            <small v-if="contactForm.errors.phone">{{ contactForm.errors.phone }}</small>
                        </label>

                        <label>
                            <span>{{ page.subject }}</span>
                            <input v-model="contactForm.subject" type="text" :placeholder="page.subjectPlaceholder" />
                            <small v-if="contactForm.errors.subject">{{ contactForm.errors.subject }}</small>
                        </label>

                        <label class="contact-form-full">
                            <span>{{ page.message }} <span class="required-star">*</span></span>
                            <textarea
                                v-model="contactForm.message"
                                rows="6"
                                :placeholder="page.messagePlaceholder"
                            />
                            <small v-if="contactForm.errors.message">{{ contactForm.errors.message }}</small>
                        </label>

                        <label class="contact-consent contact-form-full">
                            <input v-model="contactForm.agree_to_contact" type="checkbox" />
                            <span>
                                {{ page.consentLead }}
                                <Link href="/terms-of-service">{{ page.terms }}</Link>
                                {{ page.and }}
                                <Link href="/privacy-policy">{{ page.privacy }}</Link>.
                            </span>
                        </label>
                        <small v-if="contactForm.errors.agree_to_contact" class="contact-form-full">
                            {{ contactForm.errors.agree_to_contact }}
                        </small>

                        <div class="contact-actions contact-form-full">
                            <button type="submit" :disabled="contactForm.processing">
                                {{ contactForm.processing ? page.sending : page.sendMessage }}
                            </button>
                        </div>
                    </form>
                </section>

                <aside class="contact-info-panel">
                    <div class="contact-section-head">
                        <h2>{{ page.infoTitle }}</h2>
                    </div>

                    <div class="contact-info-list">
                        <div class="contact-info-item">
                            <strong>{{ page.email }}</strong>
                            <a :href="`mailto:${contactEmailDisplay}`">{{ contactEmailDisplay }}</a>
                        </div>

                        <div class="contact-info-item">
                            <strong>{{ page.workingHours }}</strong>
                            <p>{{ page.workingHoursText }}</p>
                        </div>
                    </div>
                </aside>
            </div>
        </StaticPageLayout>
    </MainLayout>
</template>
<style scoped>
.contact-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.25fr) minmax(300px, 0.8fr);
    gap: 20px;
    align-items: start;
}

.contact-form-panel,
.contact-info-panel {
    display: grid;
    gap: 18px;
}

.contact-section-head {
    display: grid;
    gap: 8px;
}

.contact-section-head h2 {
    margin: 0;
    color: #04151f;
    font-size: 1.16rem;
    font-weight: 700;
    line-height: 1.28;
}

.contact-section-head p {
    margin: 0;
    color: rgba(4, 21, 31, 0.74);
    line-height: 1.75;
}

.contact-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.contact-form-grid label {
    display: grid;
    gap: 8px;
    font-weight: 600;
}

.contact-form-grid input,
.contact-form-grid textarea {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    border: 1px solid rgba(4, 21, 31, 0.12);
    border-radius: 10px;
    padding: 14px 16px;
    background: rgba(255, 255, 255, 0.96);
    transition: border-color 160ms ease, background-color 160ms ease;
}

.contact-form-grid input:focus,
.contact-form-grid textarea:focus {
    outline: none;
    border-color: rgba(15, 118, 110, 0.55);
}

.contact-form-grid small {
    color: #b42318;
    font-size: 0.82rem;
    line-height: 1.45;
}

.contact-form-full {
    grid-column: 1 / -1;
}

.required-star {
    color: #be123c;
}

.contact-consent {
    grid-template-columns: auto 1fr;
    align-items: start;
    gap: 12px;
    font-weight: 500;
}

.contact-consent input {
    width: 18px;
    height: 18px;
    margin-top: 4px;
}

.contact-consent span {
    color: rgba(4, 21, 31, 0.8);
    line-height: 1.7;
}

.contact-consent a {
    color: #0e7490;
    font-weight: 700;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.contact-actions {
    display: flex;
    justify-content: flex-start;
}

.contact-actions button {
    border: 0;
    border-radius: 10px;
    padding: 14px 20px;
    background: #04151f;
    color: #ffffff;
    font-size: 0.94rem;
    font-weight: 700;
}

.contact-actions button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.contact-info-list {
    display: grid;
    gap: 16px;
}

.contact-info-item {
    display: grid;
    gap: 6px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(4, 21, 31, 0.08);
}

.contact-info-item:last-child {
    padding-bottom: 0;
    border-bottom: 0;
}

.contact-info-item strong {
    color: #04151f;
    font-size: 0.95rem;
    font-weight: 700;
}

.contact-info-item a,
.contact-info-item p {
    margin: 0;
    color: rgba(4, 21, 31, 0.78);
    line-height: 1.75;
    text-decoration: none;
}

@media (max-width: 900px) {
    .contact-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 720px) {
    .contact-form-grid {
        grid-template-columns: 1fr;
    }

    .contact-actions {
        justify-content: stretch;
    }

    .contact-actions button {
        width: 100%;
    }
}
</style>
