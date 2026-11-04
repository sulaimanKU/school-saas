<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
const props=defineProps<{ data:Record<string,any>; baseUrl:string }>();
const money=(n:number)=>new Intl.NumberFormat('en-PK',{style:'currency',minimumFractionDigits:2,maximumFractionDigits:2,currency:props.data.currency}).format(n/100);
const maximum=computed(()=>Math.max(...props.data.collections,...props.data.expenses,1));
</script>
<template>
<section class="finance-live">
    <div class="panel-heading"><div><h2>School finance <span class="live-data-badge">DATABASE</span></h2><p>Manually recorded school transactions · {{ data.currency }}</p></div><Link :href="`${baseUrl}/dashboard`" class="text-link">Finance workspace</Link></div>
    <div class="finance-metrics"><article v-for="item in [{label:'Total invoiced',key:'billed_minor'},{label:'Gross collected',key:'collected_minor'},{label:'Outstanding fees',key:'outstanding_minor'},{label:'Overdue fees',key:'overdue_minor'},{label:'Refunds posted',key:'refund_minor'},{label:'School expenses',key:'expense_minor'}]" :key="item.key" class="panel"><small>{{ item.label }}</small><strong>{{ money(data[item.key]) }}</strong></article></div>
    <p class="finance-note">Collection rate against issued invoices: {{ data.collection_rate===null?'No issued invoices':data.collection_rate+'%' }}. Gross collections include student credit; refunds and expenses are shown separately.</p>
    <div class="finance-two"><section class="panel"><div class="panel-heading"><h2>Monthly collection & expenses</h2></div><div class="finance-bars" role="img" aria-label="Database-backed monthly collection and expense trends"><div v-for="(month,i) in data.months" :key="month" class="finance-bar-group"><div class="finance-bar-pair"><span :style="{height:(data.collections[i]/maximum*130)+'px'}" :title="`Collected ${money(data.collections[i])}`"/><span class="expense-bar" :style="{height:(data.expenses[i]/maximum*130)+'px'}" :title="`Expenses ${money(data.expenses[i])}`"/></div><small>{{ month }}</small><span>{{ money(data.collections[i]) }}</span><span>{{ money(data.expenses[i]) }}</span></div></div><p class="finance-note">Green: gross collected · Amber: expenses. No transactions displays zero.</p></section>
    <section class="panel"><div class="panel-heading"><h2>Top overdue student balances</h2><Link :href="`${baseUrl}/defaulters`" class="text-link">View all</Link></div><div v-for="row in data.top" :key="row.student" class="finance-summary-row"><span>{{ row.student }}</span><strong>{{ money(row.amount_minor) }}</strong></div><p v-if="!data.top.length" class="academic-empty">No overdue student balances.</p><div class="panel-heading"><h2>Recent manual payments</h2></div><div v-for="p in data.recent" :key="p.id" class="finance-summary-row"><Link :href="`${baseUrl}/documents/receipt/${p.id}`">PAY-{{ p.id }} · {{ p.paid_on }} · {{ p.status }}</Link><strong>{{ money(p.amount_minor) }}</strong></div><p v-if="!data.recent.length" class="academic-empty">No payments recorded.</p></section></div>
</section>
</template>
