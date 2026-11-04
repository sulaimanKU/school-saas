<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DashboardCard from './DashboardCard.vue';
import AppIcon from './AppIcon.vue';
import type { SchoolDashboard } from '../types';
defineProps<{ events: SchoolDashboard['events']; href: string }>();
</script>
<template><DashboardCard title="Upcoming events" subtitle="Your school calendar at a glance" sample><template #action><Link :href="href" class="card-icon-link" aria-label="Open calendar preview"><AppIcon name="up" :size="17"/></Link></template><div class="events-list"><div v-for="(event,i) in events" :key="event.title" class="event-row"><div class="event-date" :class="`event-tone-${i}`"><strong>{{ event.day }}</strong><span>{{ event.month }}</span></div><div><h3>{{ event.title }}</h3><p><AppIcon name="clock" :size="11"/>{{ event.time }}</p><small>{{ event.location }}</small></div></div><p v-if="!events.length" class="empty-copy">No sample events scheduled.</p></div><Link :href="href" class="card-footer-link">View calendar preview<AppIcon name="arrow" :size="14"/></Link></DashboardCard></template>
