<script setup lang="ts">
import { reactive } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';
import { platformLinks, schoolGroups, schoolHref } from '../navigation';
defineProps<{ page: string; schoolSlug?: string; open: boolean; academicEnabled?: boolean }>();
defineEmits<{ close: [] }>();
const collapsed = reactive<Record<string, boolean>>({});
</script>
<template>
    <button v-if="open" class="sidebar-overlay" aria-label="Close navigation" @click="$emit('close')"/>
    <aside class="app-sidebar" :class="{ 'is-open': open }" aria-label="Main navigation">
        <Link :href="schoolHref(schoolSlug, 'dashboard')" class="app-brand"><span class="brand-symbol"><AppIcon name="brand" :size="24"/></span><span>School<span>Labs</span></span></Link>
        <div class="sidebar-scroll"><nav>
            <section v-for="group in schoolGroups.filter(g=>academicEnabled || g.label!=='ACADEMIC OPERATIONS')" :key="group.label" class="nav-group">
                <button class="nav-group-title" :aria-expanded="!collapsed[group.label]" @click="collapsed[group.label] = !collapsed[group.label]">{{ group.label }}<AppIcon :name="collapsed[group.label] ? 'right' : 'down'" :size="11"/></button>
                <div v-show="!collapsed[group.label]"><Link v-for="item in group.items" :key="item.page" :href="schoolHref(schoolSlug, item.page)" class="app-nav-item" :class="{ active: page === item.page }" :aria-current="page===item.page ? 'page' : undefined" @click="$emit('close')"><AppIcon :name="item.icon" :size="16"/><span>{{ item.label }}</span><span v-if="page===item.page" class="nav-active-dot"/></Link></div>
            </section>
            <section class="nav-group platform-group"><div class="nav-group-title">SAAS PLATFORM</div><Link v-for="item in platformLinks" :key="item.page" :href="item.href" class="app-nav-item" :class="{active:page===item.page || (page==='profile' && item.page==='schools')}" @click="$emit('close')"><AppIcon :name="item.icon" :size="16"/><span>{{ item.label }}</span></Link></section>
        </nav></div>
        <div class="sidebar-foot"><AppIcon name="shield" :size="17"/><div>Local demo workspace<small>{{ academicEnabled?'Academic changes enabled':'Read-only preview' }}</small></div><span class="live-dot"/></div>
    </aside>
</template>
