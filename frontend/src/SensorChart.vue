<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { Chart, registerables } from 'chart.js'
Chart.register(...registerables)
const props = defineProps({ points: Array, field: String, label: String, unit: String, color: { default: '#516c42' } })
const canvas = ref(null)
let chart
const time = value => new Intl.DateTimeFormat('es-MX', { timeZone: 'America/Tijuana', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value))
function update() {
  if (!chart) return
  chart.data.labels = props.points.map(p => time(p.received_at))
  chart.data.datasets[0].data = props.points.map(p => p[props.field])
  chart.update('none')
}
onMounted(() => {
  chart = new Chart(canvas.value, { type: 'line', data: { labels: [], datasets: [{ label: props.label, data: [], borderColor: props.color, backgroundColor: props.color + '15', borderWidth: 2, fill: true, pointRadius: 0, pointHitRadius: 12, spanGaps: false, tension: 0.15 }] }, options: { responsive: true, maintainAspectRatio: false, animation: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => `${c.parsed.y?.toLocaleString('es-MX', { maximumFractionDigits: 2 }) ?? 'Sin dato'} ${props.unit}` } } }, scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 5, maxRotation: 0, color: '#737a70' } }, y: { min: props.field === 'nivel_pct' ? 0 : undefined, max: props.field === 'nivel_pct' ? 100 : undefined, ticks: { maxTicksLimit: 4, color: '#737a70' }, grid: { color: '#edf0e9' } } } } })
  update()
})
watch(() => props.points, update)
onBeforeUnmount(() => chart?.destroy())
</script>
<template><div class="chart-canvas"><canvas ref="canvas" :aria-label="label" role="img"></canvas></div></template>
