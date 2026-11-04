<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { School } from '../types';
defineProps<{ schools: School[] }>();
</script>
<template>
    <div class="table-scroll"><table><thead><tr><th>School name</th><th>Location</th><th>Students*</th><th>Plan</th><th>Status</th><th><span class="sr-only">Open school</span></th></tr></thead>
    <tbody><tr v-for="school in schools" :key="school.slug"><td><Link class="school-name" :href="`/preview/schools/${school.slug}`"><span class="avatar" :style="{ background: school.color + '18', color: school.color }">{{ school.name.split(' ').map(s => s[0]).slice(0,2).join('') }}</span><span>{{ school.name }}<small>{{ school.domain }}</small></span></Link></td><td>{{ school.city }}</td><td>{{ school.students.toLocaleString() }}</td><td><span class="plan-chip">{{ school.plan }}</span></td><td><span class="status"><i/>Demo</span></td><td><Link class="arrow-link" :href="`/preview/schools/${school.slug}`" :aria-label="`View ${school.name}`">↗</Link></td></tr></tbody></table>
    <div v-if="!schools.length" class="empty"><strong>No demo schools found</strong><p>Try another search or run the local demo seeder.</p></div></div>
</template>
