<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FinanceDashboard from './FinanceDashboard.vue';
import TrendChart from './TrendChart.vue';
import OperationsDashboard from './OperationsDashboard.vue';
import AppIcon from './AppIcon.vue';
import StatCard from './StatCard.vue';
import StudentGenderChart from './StudentGenderChart.vue';
import ClassDistributionChart from './ClassDistributionChart.vue';
import FeeCollectionCard from './FeeCollectionCard.vue';
import FinanceOverviewChart from './FinanceOverviewChart.vue';
import UpcomingEventsCard from './UpcomingEventsCard.vue';
import EnrollmentCard from './EnrollmentCard.vue';
import FeeDefaultersCard from './FeeDefaultersCard.vue';
import RecentActivityCard from './RecentActivityCard.vue';
import { schoolHref } from '../navigation';
import type { School, SchoolDashboard, LiveAcademic } from '../types';
defineProps<{ school: School; data: SchoolDashboard; months: string[]; live?:LiveAcademic|null }>();
const finance=computed(()=>usePage().props.liveFinance as Record<string,any>|null);
</script>
<template>
    <section class="welcome-banner" aria-label="Welcome to your school workspace">
        <div class="welcome-copy"><span class="welcome-eyebrow"><AppIcon name="brand" :size="16"/> YOUR SCHOOL, CONNECTED</span><h2>Welcome back. Make today a great school day.</h2><p>One clear view of {{ school.name }}. Your people, progress, and priorities, together.</p><Link :href="schoolHref(school.slug,'students')" class="welcome-link">Explore students <AppIcon name="arrow" :size="16"/></Link></div>
        <div class="welcome-art" aria-hidden="true"><svg viewBox="0 0 260 180" fill="none"><circle cx="150" cy="90" r="76" fill="white" fill-opacity=".08"/><circle cx="150" cy="90" r="55" stroke="white" stroke-opacity=".18" stroke-dasharray="5 7"/><rect x="69" y="52" width="130" height="94" rx="14" fill="#e0e7ff" transform="rotate(-8 69 52)"/><rect x="85" y="38" width="130" height="94" rx="14" fill="white"/><rect x="99" y="54" width="39" height="7" rx="3.5" fill="#c7d2fe"/><rect x="99" y="72" width="70" height="5" rx="2.5" fill="#e2e8f0"/><rect x="101" y="103" width="16" height="16" rx="4" fill="#a5b4fc"/><rect x="126" y="92" width="16" height="27" rx="4" fill="#818cf8"/><rect x="151" y="80" width="16" height="39" rx="4" fill="#6366f1"/><rect x="176" y="64" width="16" height="55" rx="4" fill="#14b8a6"/><circle cx="207" cy="132" r="24" fill="#2dd4bf"/><path d="m197 132 7 7 13-14" stroke="#134e4a" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="m51 37 3 8 8 3-8 3-3 8-3-8-8-3 8-3Z" fill="#fcd34d"/><circle cx="228" cy="37" r="5" fill="#a5b4fc"/></svg></div>
    </section>
    <div v-if="live" class="live-metrics-note"><strong>LIVE ACADEMIC DATA</strong><span>{{ live.session?.name ?? 'No current session; create and select one to see enrollment metrics' }}</span><select aria-label="Dashboard academic session" :value="live.session?.id ?? ''" @change="router.get(`/preview/schools/${school.slug}/dashboard`,{session_id:($event.target as HTMLSelectElement).value})"><option value="" disabled>Select session</option><option v-for="session in live.sessions" :key="session.id" :value="session.id">{{ session.name }}</option></select></div>
    <div class="school-stats"><StatCard label="Total Students" :value="live?.students ?? school.students" :live="!!live" icon="students" tone="teal" :detail="live?'Active enrollment in selected session':'Across all class groups'"/><StatCard label="Total Classes" :value="live?.classes ?? data.classes.length" :live="!!live" icon="classes" tone="blue" detail="Active school classes"/><StatCard label="Total Parents" :value="data.parents" icon="parents" tone="amber" detail="Illustrative family total"/><StatCard :label="live?'Total Teachers':'Total Staff'" :value="live?.teachers ?? data.staff.teachers+data.staff.support" :live="!!live" icon="staff" tone="violet" :detail="live ? 'Active teacher profiles in this school' : `${data.staff.teachers} teaching / ${data.staff.support} support`"/></div>
    <OperationsDashboard v-if="live?.operations" :data="live.operations" :school-slug="school.slug"/>
    <FinanceDashboard v-if="finance" :data="finance" :base-url="`/preview/schools/${school.slug}/finance`"/>
    <div class="school-dashboard-grid" :class="{'has-live-finance':!!finance}">
        <StudentGenderChart class="gender-card" :boys="live?.gender.boys ?? data.gender.boys" :girls="live?.gender.girls ?? data.gender.girls" :other="live?.gender.other ?? 0" :live="!!live"/>
        <ClassDistributionChart class="classes-card" :classes="live?.distribution ?? data.distribution" :live="!!live"/>
        <UpcomingEventsCard class="events-card" :events="data.events" :href="schoolHref(school.slug,'calendar')"/>
        <FeeCollectionCard v-if="!finance" class="fee-card" :collected="data.finance.collected_minor" :pending="data.finance.pending_minor"/>
        <FinanceOverviewChart v-if="!finance" class="finance-card" :series="data.finance.series" :months="months"/>
        <FeeDefaultersCard v-if="!finance" class="defaulters-card" :groups="data.finance.overdue_groups" :href="schoolHref(school.slug,'fees')"/>
        <EnrollmentCard class="enrollments-card" :enrollment="live?{new:live.new_admissions,previous_total:live.students,cohorts:[{label:'Admissions this month',count:live.new_admissions}]}:data.enrollment" :live="!!live" :href="schoolHref(school.slug,'students')"/>
        <RecentActivityCard class="recent-card" :activities="data.activities"/>
        <section class="dashboard-card quick-card"><header class="card-heading"><div><h2>Quick access</h2><p>Your everyday school workspace</p></div></header><div class="quick-links"><Link v-for="item in [{page:'students',label:'Students',icon:'students'},{page:'attendance',label:'Attendance',icon:'calendar'},{page:'fees',label:'Fee collection',icon:'fees'},{page:'examinations',label:'Examinations',icon:'academics'}]" :key="item.page" :href="schoolHref(school.slug,item.page)"><span><AppIcon :name="item.icon" :size="18"/></span><strong>{{ item.label }}</strong><small>Preview</small><AppIcon name="right" :size="13"/></Link></div></section>
    </div>
    <section v-if="live" class="panel enrollment-trend"><div class="panel-heading"><div><h2>Enrollment trend <span class="live-data-badge">DATABASE</span></h2><p>Month-end enrollment snapshots for the selected session. Archived students are excluded.</p></div></div><TrendChart :values="live.trend" :labels="live.months"/></section>
    <p class="dashboard-disclaimer"><AppIcon name="info" :size="13"/>{{ finance ? 'School finance values are database-backed manual records. Parent totals and panels labeled Sample remain demonstrations.' : live ? 'Academics, attendance, examinations, published results and the audit timeline are database-backed. Finance, parent totals and panels labeled Sample remain demonstrations; no financial transactions are created.' : 'All figures, events, and activity are deterministic samples. No operational records are created in read-only mode.' }}</p>
</template>
