<script setup lang="ts">
import AppIcon from './AppIcon.vue';
defineProps<{ label: string; value: number; icon: string; tone: string; detail: string; live?: boolean }>();
</script>
<template><article class="stat-card" :data-tone="tone"><span class="stat-icon" :class="tone"><AppIcon :name="icon" :size="23"/></span><div><p>{{ label }}</p><strong>{{ value.toLocaleString() }}</strong><small>{{ detail }}</small></div><span class="stat-sample">{{ live?'Database':'Sample' }}</span></article></template>
