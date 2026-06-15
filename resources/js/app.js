import './bootstrap';

import { Fragment, createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import CookieConsentBanner from './Components/CookieConsentBanner.vue';

const pages = import.meta.glob('./Pages/**/*.vue');

createInertiaApp({
    resolve: (name) => {
        const page = pages[`./Pages/${name}.vue`];

        if (!page) {
            throw new Error(`Unknown Inertia page: ${name}`);
        }

        return page();
    },
    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(Fragment, [
                h(App, props),
                h(CookieConsentBanner),
            ]),
        })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#0f766e',
    },
});
