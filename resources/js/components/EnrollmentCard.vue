<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DashboardCard from './DashboardCard.vue';
import AppIcon from './AppIcon.vue';
import type { SchoolDashboard } from '../types';
defineProps<{ enrollment: SchoolDashboard['enrollment']; href: string; live?:boolean }>();
</script>
<template><DashboardCard title="New enrollments" subtitle="November 2026 · aggregate sample" sample><div class="enrollment-summary"><strong>{{ enrollment.new.toLocaleString() }}</strong><span><AppIcon name="up" :size="13"/>{{ Math.round(enrollment.new/Math.max(enrollment.previous_total,1)*100) }}%<small>{{ live?'of session enrollment':'net growth this month' }}</small></span></div><div class="intake-bars"><div v-for="(cohort,i) in enrollment.cohorts" :key="cohort.label"><div><span>{{ cohort.label }}</span><strong>{{ cohort.count }}</strong></div><div class="progress-track"><i :class="{secondary:i===1}" :style="{width:cohort.count/Math.max(enrollment.new,1)*100+'%'}"/></div></div></div><Link :href="href" class="card-footer-link">Explore students preview<AppIcon name="arrow" :size="14"/></Link></DashboardCard></template>
