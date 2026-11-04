<script setup lang="ts">
defineProps<{ title: string; subtitle?: string; sample?: boolean }>();
</script>
<template><section class="dashboard-card"><header class="card-heading"><div><h2>{{ title }}<span v-if="sample" class="sample-badge">SAMPLE</span></h2><p v-if="subtitle">{{ subtitle }}</p></div><slot name="action"/></header><slot/></section></template>
