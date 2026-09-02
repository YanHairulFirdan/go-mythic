import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Keep the brand palette in sync after SPA navigation, where the <head> <style>
// block from app.blade.php is not re-rendered (e.g. right after saving a new
// colour). On a full page load the blade block already covers first paint.
const applyBranding = (page) => {
    const scale = page?.props?.branding?.primary?.scale;
    if (!scale) {
        return;
    }
    const root = document.documentElement;
    Object.entries(scale).forEach(([weight, rgb]) => {
        root.style.setProperty(`--c-primary-${weight}`, rgb, 'important');
    });
};

router.on('navigate', (event) => applyBranding(event.detail.page));

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        applyBranding(props.initialPage);

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
