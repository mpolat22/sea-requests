<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import MainLayout from '../../Layouts/MainLayout.vue';
import PublicMetaHead from '../../Components/PublicMetaHead.vue';
import StaticPageLayout from './StaticPageLayout.vue';

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
            eyebrow="Company"
            title="Contact"
            intro="Send a message to our team and we will get back to you as soon as possible."
        >
            <div class="contact-layout">
                <section class="contact-form-panel">
                    <div class="contact-section-head">
                        <h2>Start a Conversation</h2>
                        <p>Fill out the form below and we&apos;ll get back to you as soon as possible.</p>
                    </div>

                    <form class="contact-form-grid" @submit.prevent="submitContactForm">
                        <label>
                            <span>Name <span class="required-star">*</span></span>
                            <input v-model="contactForm.name" type="text" placeholder="Your name" />
                            <small v-if="contactForm.errors.name">{{ contactForm.errors.name }}</small>
                        </label>

                        <label>
                            <span>Email <span class="required-star">*</span></span>
                            <input v-model="contactForm.email" type="email" placeholder="you@company.com" />
                            <small v-if="contactForm.errors.email">{{ contactForm.errors.email }}</small>
                        </label>

                        <label>
                            <span>Phone <span class="required-star">*</span></span>
                            <input v-model="contactForm.phone" type="text" placeholder="+44 7520 658048" />
                            <small v-if="contactForm.errors.phone">{{ contactForm.errors.phone }}</small>
                        </label>

                        <label>
                            <span>Subject</span>
                            <input v-model="contactForm.subject" type="text" placeholder="How can we help?" />
                            <small v-if="contactForm.errors.subject">{{ contactForm.errors.subject }}</small>
                        </label>

                        <label class="contact-form-full">
                            <span>Message <span class="required-star">*</span></span>
                            <textarea
                                v-model="contactForm.message"
                                rows="6"
                                placeholder="Tell us more about your enquiry..."
                            />
                            <small v-if="contactForm.errors.message">{{ contactForm.errors.message }}</small>
                        </label>

                        <label class="contact-consent contact-form-full">
                            <input v-model="contactForm.agree_to_contact" type="checkbox" />
                            <span>
                                I authorize Sea Requests to send notifications via SMS, RCS, phone, email, and WhatsApp.
                                I have read, understood, and agree to the
                                <Link href="/terms-of-service">Terms &amp; Conditions</Link>
                                and
                                <Link href="/privacy-policy">Privacy Policy</Link>.
                            </span>
                        </label>
                        <small v-if="contactForm.errors.agree_to_contact" class="contact-form-full">
                            {{ contactForm.errors.agree_to_contact }}
                        </small>

                        <div class="contact-actions contact-form-full">
                            <button type="submit" :disabled="contactForm.processing">
                                {{ contactForm.processing ? 'Sending...' : 'Send Message' }}
                            </button>
                        </div>
                    </form>
                </section>

                <aside class="contact-info-panel">
                    <div class="contact-section-head">
                        <h2>Our Contact Information</h2>
                    </div>

                    <div class="contact-info-list">
                        <div class="contact-info-item">
                            <strong>Email</strong>
                            <a :href="`mailto:${contactEmailDisplay}`">{{ contactEmailDisplay }}</a>
                        </div>

                        <div class="contact-info-item">
                            <strong>Working Hours</strong>
                            <p>Monday - Friday: 9:00 AM to 5:00 PM</p>
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
