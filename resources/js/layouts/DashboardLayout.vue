<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppSidebar from '../components/AppSidebar.vue';
import AppTopbar from '../components/AppTopbar.vue';
import AppIcon from '../components/AppIcon.vue';
import type { School, SchoolDashboard } from '../types';
defineProps<{ page: string; title: string; schools: School[]; selectedSchool: School | null; dashboard: SchoolDashboard | null }>();
const open = ref(false), dark = ref(false), busy = ref(false);
const pageState = usePage();
const academicCrud = computed(()=>pageState.props.academicEnabled===true);
const removeListeners: (()=>void)[] = [];
function toggleTheme() { dark.value=!dark.value; document.documentElement.classList.toggle('dark',dark.value); try { localStorage.setItem('schoolspace-theme',dark.value?'dark':'light'); } catch {} }
onMounted(()=>{
    try { dark.value=localStorage.getItem('schoolspace-theme')==='dark'; } catch {}
    document.documentElement.classList.toggle('dark',dark.value);
    removeListeners.push(router.on('start',()=>{busy.value=true;open.value=false;}));
    removeListeners.push(router.on('finish',()=>{busy.value=false;}));
});
onUnmounted(()=>removeListeners.forEach(remove=>remove()));
</script>
<template>
    <div class="dashboard-app" @keydown.esc="open=false"><a href="#main" class="skip-link">Skip to content</a><AppSidebar :open="open" :academic-enabled="academicCrud" :page="page" :school-slug="selectedSchool?.slug" @close="open=false"/>
        <div class="dashboard-main"><AppTopbar :academic-enabled="academicCrud" :page="page" :schools="schools" :selected-school="selectedSchool" :dashboard="dashboard" :dark="dark" @menu="open=true" @theme="toggleTheme"/>
            <div class="local-preview-bar"><span class="live-dot"/><strong>DEMO / LOCAL PREVIEW</strong><span>{{ pageState.props.financeEnabled ? 'Local school finance enabled · manually recorded transactions' : academicCrud ? 'Local academic operations enabled; finance remains sample' : 'Sample data - read only - no live operations' }}</span><span class="preview-right"><AppIcon name="shield" :size="12"/>Local environment</span></div>
            <main id="main" class="dashboard-content" :aria-busy="busy"><div v-if="busy" class="loading-status" role="status">Loading preview…</div><nav class="page-breadcrumb" aria-label="Breadcrumb"><Link href="/preview">Platform</Link><AppIcon name="right" :size="12"/><Link v-if="selectedSchool && !['overview','schools','plans','settings'].includes(page)" :href="`/preview/schools/${selectedSchool.slug}/dashboard`">{{ selectedSchool.name }}</Link><AppIcon v-if="selectedSchool && !['overview','schools','plans','settings'].includes(page)" name="right" :size="12"/><span aria-current="page">{{ title }}</span></nav><slot v-bind="{ dark, toggleTheme }"/>
                <footer class="page-footer"><span>© 2026 SchoolLabs. A better school day starts here.</span><span>Demo workspace <i/> Local only</span></footer>
            </main>
        </div>
    </div>
</template>
