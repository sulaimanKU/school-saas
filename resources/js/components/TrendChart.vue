<script setup lang="ts">
import { computed } from 'vue';
const props = defineProps<{ values: number[]; labels: string[] }>();
const max = computed(() => Math.max(...props.values, 1) * 1.2);
const points = computed(() => props.values.map((v, i) => `${30 + i * 100},${185 - v / max.value * 155}`).join(' '));
</script>
<template>
    <div class="chart" role="img" :aria-label="`Sample enrollment: ${labels.map((l, i) => `${l} ${values[i]}`).join(', ')}`">
        <svg viewBox="0 0 560 220"><defs><linearGradient id="fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#188d76" stop-opacity=".24"/><stop offset="100%" stop-color="#188d76" stop-opacity="0"/></linearGradient></defs>
            <g class="chart-grid"><line v-for="y in [30,80,130,180]" :key="y" x1="20" x2="540" :y1="y" :y2="y"/></g>
            <polygon :points="`30,185 ${points} 530,185`" fill="url(#fill)"/><polyline :points="points" fill="none" stroke="#188d76" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
            <circle v-for="(v,i) in values" :key="i" :cx="30+i*100" :cy="185-v/max*155" r="4" fill="#188d76" stroke="var(--panel)" stroke-width="2"/>
            <text v-for="(label,i) in labels" :key="label" :x="30+i*100" y="214" text-anchor="middle">{{ label }}</text>
        </svg>
    </div>
</template>
