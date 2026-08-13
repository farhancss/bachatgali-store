import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';

const appName = import.meta.env.VITE_APP_NAME ?? 'Bachat Gali';

void createInertiaApp({
    title: (title: string) => (title ? `${title} — ${appName}` : appName),

    resolve: (name: string) => {
        const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue');
        const page = pages[`./Pages/${name}.vue`];

        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return page();
    },

    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .mount(el);
    },

    progress: {
        color: 'var(--accent)',
        showSpinner: false,
    },
});
