<script setup lang="ts">
import AppIcon from './AppIcon.vue';
defineProps<{ label: string; value: string | number; detail: string; icon: string; tone?: string }>();
</script>
<template>
    <article class="metric panel"><div class="metric-top"><span>{{ label }}</span><span class="metric-icon" :class="tone"><AppIcon :name="({'▦':'school','♧':'students','▤':'staff','◇':'plans'} as Record<string,string>)[icon] ?? icon" :size="18"/></span></div><strong>{{ value }}</strong><p>{{ detail }}</p></article>
</template>
