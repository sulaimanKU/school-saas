<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';
import { platformLinks, schoolGroups, schoolHref, schoolPages } from '../navigation';
import type { School, SchoolDashboard } from '../types';
const props = defineProps<{ schools: School[]; selectedSchool: School | null; page: string; dark: boolean; academicEnabled?: boolean; dashboard: SchoolDashboard | null }>();
defineEmits<{ menu: []; theme: [] }>();
const query = ref(''), searching = ref(false), notifications = ref(false);
const results = computed(() => {
    const entries = [...props.schools.map(s=>({label:s.name,detail:`Demo school · ${s.city}`,href:schoolHref(s.slug,'dashboard'),icon:'school'})), ...platformLinks.map(p=>({...p,detail:'Platform preview'})), ...schoolGroups.filter(g=>props.academicEnabled || g.label!=='ACADEMIC OPERATIONS').flatMap(g=>g.items.map(i=>({...i,detail:`School ${i.page==='dashboard'?'dashboard':'module placeholder'}`,href:schoolHref(props.selectedSchool?.slug,i.page)})))];
    return entries.filter(item=>`${item.label} ${item.detail}`.toLowerCase().includes(query.value.toLowerCase().trim())).filter((item,index,matches)=>matches.findIndex(entry=>entry.href===item.href)===index).slice(0,8);
});
function selectSchool(event: Event) {
    const slug = (event.target as HTMLSelectElement).value;
    router.visit(schoolHref(slug, [...schoolPages,'sessions'].includes(props.page) ? props.page : 'dashboard'));
}
</script>
<template>
    <header class="app-topbar" @keydown.esc="searching=false;notifications=false">
        <button class="icon-button mobile-toggle" aria-label="Open navigation" @click="$emit('menu')"><AppIcon name="menu"/></button>
        <div class="topbar-school"><span class="campus-icon"><AppIcon name="school" :size="21"/></span><div><label class="sr-only" for="school-switcher">Select demo school</label><select id="school-switcher" :value="selectedSchool?.slug ?? ''" :disabled="!schools.length" @change="selectSchool"><option v-if="!schools.length" value="">No demo schools</option><option v-for="school in schools" :key="school.slug" :value="school.slug">{{ school.name }}</option></select><Link :href="schoolHref(selectedSchool?.slug,'campuses')" class="campus-label">Campus preview <span>· {{ selectedSchool?.city ?? 'Local workspace' }}</span></Link></div></div>
        <div class="global-search" @focusout="event => { if (!(event.currentTarget as HTMLElement).contains(event.relatedTarget as Node)) searching=false; }"><AppIcon name="search" :size="17"/><input v-model="query" type="search" aria-label="Search demo schools and preview modules" placeholder="Search schools, modules, and more…" aria-controls="global-results" :aria-expanded="searching && !!query.trim()" @focus="searching=true" @input="searching=true" @keydown.enter.prevent="results[0] && query.trim() && (router.visit(results[0].href), searching=false)"/><kbd>Local</kbd>
            <div v-if="searching && query.trim()" id="global-results" class="search-results"><div class="popover-label">LOCAL PREVIEW RESULTS</div><Link v-for="result in results" :key="result.href" :href="result.href" @click="searching=false;query=''"><AppIcon :name="result.icon" :size="17"/><span>{{ result.label }}<small>{{ result.detail }}</small></span><AppIcon name="right" :size="14"/></Link><p v-if="!results.length" class="search-empty">No matching demo schools or modules.</p></div>
        </div>
        <div class="topbar-actions"><button class="icon-button theme-toggle" :aria-label="dark?'Use light theme':'Use dark theme'" @click="$emit('theme')"><AppIcon :name="dark?'sun':'moon'" :size="18"/></button><div class="notification-wrap"><button class="icon-button notification-button" aria-label="Show sample notifications" :aria-expanded="notifications" @click="notifications=!notifications"><AppIcon name="bell" :size="19"/><span v-if="dashboard?.events.length"/></button><section v-if="notifications" class="notifications-popover"><div class="popover-label">SAMPLE NOTIFICATIONS <button class="icon-button" aria-label="Close notifications" @click="notifications=false"><AppIcon name="close" :size="14"/></button></div><p>Upcoming events · {{ selectedSchool?.name }}</p><Link v-for="event in dashboard?.events ?? []" :key="event.title" :href="schoolHref(selectedSchool?.slug,'calendar')" @click="notifications=false"><AppIcon name="calendar" :size="16"/><span>{{ event.title }}<small>{{ event.day }} {{ event.month }} 2026 · {{ event.time }}</small></span></Link><p v-if="!dashboard">No demo events available.</p></section></div><span class="topbar-divider"/><div class="demo-profile"><span class="demo-avatar">D</span><div>Demo preview<small>Local workspace</small></div></div></div>
    </header>
</template>
