<script setup lang="ts">
import { computed } from 'vue';
import DashboardCard from './DashboardCard.vue';
import DonutChart from './DonutChart.vue';
import { money } from '../format';
const props = defineProps<{ collected: number; pending: number }>();
const percent = computed(()=>Math.round(props.collected/Math.max(props.collected+props.pending,1)*100));
const segments = computed(()=>[{label:'Collected (minor units)',value:props.collected,color:'#188d76'},{label:'Pending (minor units)',value:props.pending,color:'#f3be70'}]);
</script>
<template><DashboardCard title="Fee collection" subtitle="November 2026 · illustrative amounts" sample><div class="fee-body"><DonutChart :segments="segments" label="Sample fees" :center="percent+'%'" caption="Collection rate"/><div class="fee-totals"><div><span><i/>Collected</span><strong>{{ money(collected) }}</strong></div><div><span><i/>Pending</span><strong>{{ money(pending) }}</strong></div></div></div><div class="card-bottom-note">Sample school fees · no payments processed</div></DashboardCard></template>
