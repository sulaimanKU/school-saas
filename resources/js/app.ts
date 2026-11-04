import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import Preview from './pages/Preview.vue';
import Academic from './pages/Academic.vue';
import Operations from './pages/Operations.vue';
import ReportCard from './pages/ReportCard.vue';
import Finance from './pages/Finance.vue';
import FinanceDocument from './pages/FinanceDocument.vue';
import { configureFinanceNavigation } from './navigation';
import { configureAcademicNavigation } from './navigation';

createInertiaApp({
    title: title => `${title} · SchoolLabs`,
    // Inertia supplies page props at runtime; the page itself retains its strict prop types.
    resolve: name => { const pages = {Preview, Academic, Operations, ReportCard, Finance, FinanceDocument}; if (!(name in pages)) throw new Error('Unknown page'); return pages[name as keyof typeof pages] as unknown as DefineComponent; },
    setup({ el, App, props, plugin }) { configureFinanceNavigation(props.initialPage.props.financeEnabled === true); configureAcademicNavigation(props.initialPage.props.academicEnabled === true); createApp({ render: () => h(App, props) }).use(plugin).mount(el); },
    progress: { color: '#188d76', showSpinner: true },
});
