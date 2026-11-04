<script setup lang="ts">
import DashboardCard from './DashboardCard.vue';
import AppIcon from './AppIcon.vue';
import type { SchoolDashboard } from '../types';
defineProps<{ activities: SchoolDashboard['activities'] }>();
</script>
<template><DashboardCard title="Recent activity" subtitle="Illustrative workspace updates" sample><div class="activity-list"><div v-for="activity in activities" :key="activity.title" class="activity-row"><span class="activity-icon" :class="activity.type"><AppIcon :name="activity.type==='enrollment'?'students':activity.type==='finance'?'wallet':'academics'" :size="16"/></span><div><h3>{{ activity.title }}</h3><p>{{ activity.detail }}</p><small>{{ activity.time }} · sample</small></div></div><p v-if="!activities.length" class="empty-copy">No sample activity yet.</p></div></DashboardCard></template>
