<script setup lang="ts">
import { computed } from 'vue';
const props = defineProps<{ segments: { label: string; value: number; color: string }[]; label: string; center: string; caption: string }>();
const total = computed(()=>props.segments.reduce((sum,s)=>sum+s.value,0));
const stops = computed(()=>props.segments.map((s,i)=>`${s.color} ${props.segments.slice(0,i).reduce((sum,s)=>sum+s.value,0)/Math.max(total.value,1)*100}% ${props.segments.slice(0,i+1).reduce((sum,s)=>sum+s.value,0)/Math.max(total.value,1)*100}%`).join(','));
</script>
<template><div class="ring-chart" role="img" :aria-label="`${label}: ${segments.map(s=>`${s.label} ${s.value.toLocaleString()}`).join(', ')}`" :style="{background:total ? `conic-gradient(from -90deg, ${stops})` : 'var(--line)'}"><div><strong>{{ center }}</strong><span>{{ caption }}</span></div></div></template>
