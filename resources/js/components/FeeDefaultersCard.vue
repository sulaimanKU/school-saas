<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DashboardCard from './DashboardCard.vue';
import AppIcon from './AppIcon.vue';
import { money } from '../format';
import type { SchoolDashboard } from '../types';
defineProps<{ groups: SchoolDashboard['finance']['overdue_groups']; href: string }>();
</script>
<template><DashboardCard title="Top fee defaulters" subtitle="Anonymous aggregate groups · sample only" sample><div class="overdue-table"><div class="overdue-header"><span>Overdue group</span><span>Balance</span></div><div v-for="(group,i) in groups" :key="group.label" class="overdue-row"><span class="group-avatar">{{ String(i+1).padStart(2,'0') }}</span><div><strong>{{ group.label }}</strong><small>{{ group.period }}</small></div><strong>{{ money(group.amount_minor) }}</strong></div><p v-if="!groups.length" class="empty-copy">No sample overdue balances.</p></div><Link :href="href" class="card-footer-link">Open fee collection preview<AppIcon name="arrow" :size="14"/></Link></DashboardCard></template>
