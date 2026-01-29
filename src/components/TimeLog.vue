<script setup>
import { ref, computed } from 'vue'
import WeekLogsModal from './WeekLogsModal.vue'

const props = defineProps({
  logs: {
    type: Array,
    default: () => []
  }
})

const showHistory = ref(false)

function formatDate(iso) {
  if (!iso) return '-'
  try { return new Date(iso).toLocaleString() } catch { return iso }
}

function formatDuration(ms) {
  const sec = Math.floor(ms / 1000)
  const h = Math.floor(sec / 3600)
  const m = Math.floor((sec % 3600) / 60)
  const s = sec % 60
  const pad = (n) => String(n).padStart(2, '0')
  return `${pad(h)}:${pad(m)}:${pad(s)}`
}

function formatDurationMinutes(ms) {
  const minutes = Math.round((ms || 0) / 60000)
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  const pad = (n) => String(n).padStart(2, '0')
  return `${pad(h)}:${pad(m)}`
}

function isSameLocalDay(d1, d2) {
  return d1.getFullYear() === d2.getFullYear() && d1.getMonth() === d2.getMonth() && d1.getDate() === d2.getDate()
}

const today = new Date()

const todayLogs = computed(() => {
  const t = today
  return (props.logs || []).filter(r => {
    const s = r.start ? new Date(r.start) : null
    const e = r.end ? new Date(r.end) : null
    return (s && isSameLocalDay(s, t)) || (e && isSameLocalDay(e, t))
  })
})

const totalMsToday = computed(() => todayLogs.value.reduce((sum, r) => sum + (r.durationMs || r.duration_ms || 0), 0))
</script>

<template>
  <section class="log">
    <header class="header">
      <div>
        <h2>Today's Time Log</h2>
        <div class="muted">{{ new Date().toLocaleDateString() }}</div>
      </div>
      <div class="actions">
        <button class="btn" type="button" @click="showHistory = true">View history</button>
      </div>
    </header>

    <p v-if="!todayLogs || todayLogs.length === 0" class="muted">No entries today.</p>

    <table v-else class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>Start</th>
          <th>End</th>
          <th>Duration</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, idx) in todayLogs" :key="(row.start || '') + (row.end || '') + idx">
          <td>{{ todayLogs.length - idx }}</td>
          <td>{{ formatDate(row.start) }}</td>
          <td>{{ formatDate(row.end) }}</td>
          <td>{{ formatDuration(row.durationMs || row.duration_ms) }}</td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" class="right">Today's Total: </td>
          <td><strong>{{ formatDurationMinutes(totalMsToday) }}</strong></td>
        </tr>
      </tfoot>
    </table>

    <WeekLogsModal v-model="showHistory" :logs="logs" />
  </section>
</template>

<style scoped>
.log { margin-top: 1.5rem; }
.header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.table { width: 100%; border-collapse: collapse; margin-top: 0.5rem; }
.table th, .table td { border-bottom: 1px solid #eee; padding: 0.5rem 0.4rem; text-align: left; }
.table thead th { border-bottom: 2px solid #ddd; }
.muted { color: #777; }
.right { text-align: right; }
.btn { padding: 0.4rem 0.7rem; border: 1px solid #ccc; background: #f9f9f9; border-radius: 4px; cursor: pointer; }
.btn:hover { background: #f1f1f1; }
</style>
