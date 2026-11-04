<script setup lang="ts">
import { computed } from 'vue';
import DashboardCard from './DashboardCard.vue';
import DonutChart from './DonutChart.vue';
const props = withDefaults(defineProps<{ boys: number; girls: number; other?:number; live?:boolean }>(),{other:0,live:false});
const segments = computed(()=>[{label:'Boys',value:props.boys,color:'#188d76'},{label:'Girls',value:props.girls,color:'#93cbbd'},...(props.other?[{label:'Other / unspecified',value:props.other,color:'#e5bd79'}]:[])]);
</script>
<template><DashboardCard title="Students overview" subtitle="Student gender distribution" :sample="!live"><div class="gender-body"><DonutChart :segments="segments" :label="live?'Student gender':'Sample student gender'" :center="(boys+girls+other).toLocaleString()" caption="Total students"/><div class="gender-legend"><div v-for="s in segments" :key="s.label"><span class="legend-dot" :style="{background:s.color}"/><span>{{ s.label }}<strong>{{ s.value.toLocaleString() }}</strong></span><small>{{ Math.round(s.value/Math.max(boys+girls+other,1)*100) }}%</small></div></div></div></DashboardCard></template>
