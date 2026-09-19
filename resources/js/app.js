import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { TextPlugin } from 'gsap/TextPlugin';

// Register GSAP plugins
gsap.registerPlugin(ScrollTrigger, TextPlugin);

// Global GSAP defaults — premium feel
gsap.defaults({
    ease: 'power2.out',
    duration: 0.35,
});

// Always dark mode
document.documentElement.classList.add('dark');

// Import global components
import Modal from './Components/Common/Modal.vue';
import Button from './Components/Common/Button.vue';
import Avatar from './Components/Common/Avatar.vue';
import Toast from './Components/Common/Toast.vue';
import ToastContainer from './Components/Common/ToastContainer.vue';
import SkeletonLoader from './Components/Common/SkeletonLoader.vue';
import Atmosphere from './Components/Common/Atmosphere.vue';

const pinia = createPinia();

const appName = import.meta.env.VITE_APP_NAME || 'TeamCollab';

createInertiaApp({
    title: title => `${title} — ${appName}`,
    resolve: name =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        // Install plugins
        app.use(plugin);
        app.use(pinia);
        app.use(ZiggyVue);

        // Register global components
        app.component('Modal', Modal);
        app.component('AppButton', Button);
        app.component('Avatar', Avatar);
        app.component('Toast', Toast);
        app.component('ToastContainer', ToastContainer);
        app.component('SkeletonLoader', SkeletonLoader);
        app.component('Atmosphere', Atmosphere);

        // Global GSAP helper available via app.config.globalProperties
        app.config.globalProperties.$gsap = gsap;

        app.mount(el);

        return app;
    },
    progress: {
        color: '#5c7cfa',
        showSpinner: false,
    },
});

// Inertia router events for page transitions
router.on('start', () => {
    document.body.style.cursor = 'wait';
});

router.on('finish', () => {
    document.body.style.cursor = '';
});
