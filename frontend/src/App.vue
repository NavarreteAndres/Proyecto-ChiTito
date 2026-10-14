<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { api, csrf } from './api'
import SensorChart from './SensorChart.vue'
const user = ref(null), loading = ref(true), busy = ref(false), error = ref(''), apiError = ref('')
const email = ref('admin@chitito.local'), password = ref(''), device = ref(null), points = ref([]), history = ref({ data: [], current_page: 1, last_page: 1, total: 0 })
const range = ref('1'), customFrom = ref(''), customTo = ref(''), tab = ref('overview'), rangeError = ref(''), queryBusy = ref(false), chartMeta = ref(null)
let timer, tick = 0, generation = 0, disposed = false
const metrics = [{ field: 'peso_raw', label: 'Báscula HX711', unit: 'raw', subtitle: 'Lectura después de tara · sin calibrar', color: '#526b42' }, { field: 'distancia_mm', label: 'Distancia VL53L1X', unit: 'mm', subtitle: 'Distancia hasta el alimento', color: '#8d7b3d' }, { field: 'nivel_pct', label: 'Alimento disponible', unit: '%', subtitle: 'Porcentaje informado por el ESP32', color: '#3e7168' }]
const latest = computed(() => device.value?.latest)
const stateLabels = { online: 'Conectado', offline: 'Desconectado', stale: 'Sin datos recientes', unknown: 'Sin información' }
const state = computed(() => apiError.value ? (latest.value ? 'stale' : 'unknown') : (device.value?.connection_state || 'unknown'))
const consumerHealthy = computed(() => !apiError.value && device.value?.consumer?.connected && Date.now() - Date.parse(device.value.consumer.heartbeat_at) < 30000)
const receiving = computed(() => consumerHealthy.value && state.value === 'online')
const format = v => v === null || v === undefined ? '—' : Number(v).toLocaleString('es-MX', { maximumFractionDigits: 2 })
const date = v => v ? new Intl.DateTimeFormat('es-MX', { timeZone: 'America/Tijuana', dateStyle: 'medium', timeStyle: 'medium' }).format(new Date(v)) : 'Sin comunicación registrada'
function bounds() {
  const to = range.value === 'custom' ? new Date(customTo.value) : new Date()
  const from = range.value === 'custom' ? new Date(customFrom.value) : new Date(to.getTime() - Number(range.value) * 3600000)
  if (!Number.isFinite(from.getTime()) || !Number.isFinite(to.getTime()) || from > to || to - from > 366 * 86400000) throw new Error('Selecciona un rango válido de hasta 366 días.')
  return new URLSearchParams({ from: from.toISOString().replace('.000Z', '+00:00').replace(/\.\d{3}Z$/, '+00:00'), to: to.toISOString().replace(/\.\d{3}Z$/, '+00:00') })
}
async function loadRange(page = 1) {
  const current = ++generation
  queryBusy.value = true
  rangeError.value = ''
  try {
    const q = bounds()
    const [chart, rows] = await Promise.all([api('telemetry/chart?' + q), api('telemetry/history?' + q + '&page=' + page)])
    if (current !== generation || !user.value) return
    points.value = chart.data; chartMeta.value = chart.meta; history.value = rows
  } catch (e) { rangeError.value = e.status === 401 ? 'La sesión expiró.' : e.message; if (e.status === 401) user.value = null }
  finally { if (current === generation) queryBusy.value = false }
}
async function refresh() {
  if (!user.value || disposed) return
  try {
    const r = await api('device')
    if (!user.value) return
    device.value = r.data; apiError.value = ''
    if (++tick % 5 === 0 && !queryBusy.value) await loadRange(history.value.current_page)
  } catch (e) { apiError.value = e.status === 401 ? 'Tu sesión expiró. Inicia sesión nuevamente.' : 'Sin conexión con la API. Se conservan las últimas lecturas.'; if (e.status === 401) user.value = null }
}
async function loop() { await refresh(); if (!disposed) timer = setTimeout(loop, 3000) }
async function login() {
  busy.value = true; error.value = ''
  try { user.value = (await api('login', 'POST', { email: email.value, password: password.value })).data; password.value = ''; await csrf(); await refresh(); await loadRange() }
  catch (e) { error.value = e.message }
  finally { busy.value = false }
}
async function logout() {
  busy.value = true
  try { await api('logout', 'POST'); user.value = null; device.value = null; points.value = []; history.value = { data: [], current_page: 1, last_page: 1, total: 0 }; await csrf() }
  catch (e) { apiError.value = e.message }
  finally { busy.value = false }
}
onMounted(async () => {
  try { await csrf(); user.value = (await api('session')).data; await refresh(); await loadRange() }
  catch (e) { if (e.status !== 401) error.value = 'No se pudo conectar con el servidor.' }
  finally { loading.value = false; loop() }
})
onBeforeUnmount(() => { disposed = true; clearTimeout(timer); generation++ })
</script>
<template>
  <div v-if="loading" class="loading">Conectando con ChiTito…</div>
  <main v-else-if="!user" class="login-layout">
    <section class="login-story"><div class="brand"><span class="brand-mark">ct</span><span>ChiTito<small>SMART DISPENSER</small></span></div><div><span class="eyebrow light">TELEMETRÍA PARA CUIDAR MEJOR</span><h1>Un vistazo.<br>Todo en orden.</h1><p>Consulta el alimento disponible y las lecturas de tu dispensador, en un solo lugar.</p><div class="device-illustration"><div class="hopper"><span></span><span></span><span></span></div><div class="dispenser-base">ChiTito <i>●</i></div><div class="bowl"></div></div></div><small>Proyecto universitario · HX711 + VL53L1X</small></section>
    <section class="login-form"><form @submit.prevent="login"><span class="eyebrow">ACCESO PRIVADO</span><h2>Bienvenido a ChiTito</h2><p>Inicia sesión para consultar tu dispensador.</p><label for="email">Correo electrónico</label><input id="email" v-model="email" type="email" autocomplete="username" required maxlength="254"><label for="password">Contraseña</label><input id="password" v-model="password" type="password" autocomplete="current-password" required maxlength="200"><p v-if="error" class="form-error" role="alert">{{ error }}</p><button class="primary" :disabled="busy">{{ busy ? 'Verificando…' : 'Entrar al dashboard →' }}</button><small>Conexión privada · Tus datos requieren autenticación</small></form></section>
  </main>
  <div v-else class="app-layout">
    <aside class="sidebar"><div class="brand"><span class="brand-mark">ct</span><span>ChiTito<small>SMART DISPENSER</small></span></div><div class="nav-caption">MI DISPENSADOR</div><nav aria-label="Navegación"><button :class="{ active: tab === 'overview' }" @click="tab = 'overview'">◫ <span>Vista general</span></button><button :class="{ active: tab === 'history' }" @click="tab = 'history'">≡ <span>Historial de lecturas</span></button></nav><div class="sidebar-bottom"><span class="tiny-status"></span> device01<small>HX711 · VL53L1X<br>Proyecto universitario IoT</small></div></aside>
    <div class="main-area"><header class="topbar"><span>Monitoreo del dispensador</span><div><span class="avatar">A</span><span>{{ user.name }}</span><button class="quiet" @click="logout" :disabled="busy">Salir</button></div></header>
      <main class="content"><div class="page-heading"><div><span class="eyebrow">CHITITO / DEVICE01</span><h1>{{ tab === 'overview' ? 'Tu dispensador, al día.' : 'Historial de lecturas' }}</h1><p>{{ tab === 'overview' ? 'Las lecturas del alimento y la báscula, en tiempo real.' : 'Explora las mediciones almacenadas de tu ESP32.' }}</p></div><span class="poll-indicator"><i :class="{ live: receiving }"></i>{{ receiving ? 'Recepción activa' : consumerHealthy ? 'Esperando telemetría' : 'Recepción sin confirmar' }}</span></div>
      <p v-if="apiError" class="notice warning" role="alert">{{ apiError }}</p>
      <section class="connection-strip"><div><span class="status-dot" :class="state"></span><strong>{{ stateLabels[state] }}</strong><span class="device-name">ESP32 · device01</span></div><div><small>ÚLTIMA COMUNICACIÓN</small><span>{{ date(device?.last_seen_at) }}</span></div></section>
      <p v-if="state !== 'online' && latest" class="notice">Estas son las últimas mediciones recibidas; ya no se consideran actuales.</p><p v-if="!latest" class="notice">Todavía no hay lecturas. Los valores aparecerán cuando el ESP32 publique telemetría válida.</p>
      <template v-if="tab === 'overview'"><section class="metric-grid"><article v-for="(metric,index) in metrics" :key="metric.field" class="metric-card"><div class="metric-label"><span>{{ metric.label }}</span><span class="sensor-icon">{{ ['⚖','↧','◒'][index] }}</span></div><div class="metric-value">{{ format(latest?.[metric.field]) }}<small>{{ metric.unit }}</small></div><p>{{ metric.subtitle }}</p><div v-if="metric.field === 'nivel_pct'" class="level-track" role="meter" aria-label="Alimento disponible" :aria-valuenow="latest?.nivel_pct" aria-valuemin="0" aria-valuemax="100"><span :style="{ width: (latest?.nivel_pct ?? 0) + '%' }"></span></div><footer>{{ latest ? date(latest.received_at) : 'Esperando la primera lectura' }}</footer></article></section></template>
      <section class="data-section"><div class="section-heading"><div><h2>{{ tab === 'overview' ? 'La evolución de tus lecturas' : 'Mediciones almacenadas' }}</h2><p>{{ tab === 'overview' ? 'Promedios por intervalo · hora de Tijuana' : 'Lecturas originales · hora de Tijuana' }}</p></div><div class="range-picker"><label class="sr-only" for="range">Rango de fechas</label><select id="range" v-model="range" @change="range !== 'custom' && loadRange()"><option value="1">Última hora</option><option value="24">Últimas 24 horas</option><option value="168">Últimos 7 días</option><option value="custom">Rango personalizado</option></select><button class="secondary" @click="loadRange()" :disabled="queryBusy">{{ queryBusy ? 'Consultando…' : 'Actualizar' }}</button></div></div>
      <form v-if="range === 'custom'" class="custom-range" @submit.prevent="loadRange()"><label>Desde<input v-model="customFrom" type="datetime-local" required></label><label>Hasta<input v-model="customTo" type="datetime-local" required></label><button class="primary" :disabled="queryBusy">Aplicar rango</button><small>Se utiliza la zona horaria de este navegador.</small></form>
      <p v-if="rangeError" class="form-error" role="alert">{{ rangeError }}</p>
      <template v-if="tab === 'overview'"><div v-if="points.length" class="charts-grid"><article v-for="metric in metrics" :key="metric.field" class="chart-card"><h3>{{ metric.label }} <small>{{ metric.unit }}</small></h3><SensorChart :points="points" :field="metric.field" :label="metric.label" :unit="metric.unit" :color="metric.color" /></article></div><div v-else class="empty-state"><span>⌁</span><h3>Sin lecturas en este intervalo</h3><p>Prueba otro rango o espera la próxima comunicación del dispensador.</p></div><p v-if="chartMeta" class="aggregation-note">Cada punto resume {{ chartMeta.bucket_seconds }} segundos. Los valores originales se conservan en el historial.</p></template>
      <template v-else><div class="table-wrap"><table><thead><tr><th>Fecha y hora · Tijuana</th><th>Peso crudo</th><th>Distancia (mm)</th><th>Alimento (%)</th></tr></thead><tbody><tr v-for="row in history.data" :key="row.id"><td>{{ date(row.received_at) }}</td><td>{{ format(row.peso_raw) }}</td><td>{{ format(row.distancia_mm) }}</td><td>{{ format(row.nivel_pct) }}</td></tr><tr v-if="!history.data.length"><td colspan="4" class="empty-row">No hay mediciones en este rango.</td></tr></tbody></table></div><div class="pagination"><span>{{ format(history.total) }} lecturas · Página {{ history.current_page }} de {{ history.last_page }}</span><div><button class="secondary" :disabled="queryBusy || history.current_page <= 1" @click="loadRange(history.current_page - 1)">Anterior</button><button class="secondary" :disabled="queryBusy || history.current_page >= history.last_page" @click="loadRange(history.current_page + 1)">Siguiente</button></div></div></template>
      </section><footer class="page-footer"><span>ChiTito Smart Dispenser</span><span>HX711 sin calibrar · No se muestran gramos</span></footer>
      </main>
    </div>
  </div>
</template>
