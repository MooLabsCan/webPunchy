<script setup>
import { ref, computed, watch, onMounted } from 'vue'

const props = defineProps({
  logs: { type: Array, default: () => [] },
  modelValue: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue'])

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})

// Helper: format date/time and durations
function formatDate(iso) {
  if (!iso) return '-'
  try { return new Date(iso).toLocaleString() } catch { return iso }
}
function formatDuration(ms) {
  const sec = Math.floor((ms || 0) / 1000)
  const h = Math.floor(sec / 3600)
  const m = Math.floor((sec % 3600) / 60)
  const s = sec % 60
  const pad = (n) => String(n).padStart(2, '0')
  return `${pad(h)}:${pad(m)}:${pad(s)}`
}

// Week selector state. Use input type="week" value format: YYYY-Www
const weekValue = ref('')

function setCurrentWeek() {
  const d = new Date()
  // Determine ISO week
  const tmp = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()))
  // Thursday in current week decides the year.
  tmp.setUTCDate(tmp.getUTCDate() + 4 - (tmp.getUTCDay() || 7))
  const yearStart = new Date(Date.UTC(tmp.getUTCFullYear(), 0, 1))
  const weekNo = Math.ceil((((tmp - yearStart) / 86400000) + 1) / 7)
  const year = tmp.getUTCFullYear()
  const ww = String(weekNo).padStart(2, '0')
  weekValue.value = `${year}-W${ww}`
}

onMounted(() => {
  if (!weekValue.value) setCurrentWeek()
})

function getWeekRange(value) {
  // value like "2026-W05"
  if (!value || !/^\d{4}-W\d{2}$/.test(value)) return null
  const [y, w] = value.split('-W')
  const year = parseInt(y, 10)
  const week = parseInt(w, 10)
  // ISO week date: week starts Monday. Calculate date of Monday of the given week.
  const simple = new Date(Date.UTC(year, 0, 1 + (week - 1) * 7))
  const dow = simple.getUTCDay() || 7 // 1..7
  const monday = new Date(simple)
  if (dow <= 4) {
    // shift back to Monday
    monday.setUTCDate(simple.getUTCDate() - (dow - 1))
  } else {
    monday.setUTCDate(simple.getUTCDate() + (8 - dow))
  }
  const sundayEnd = new Date(monday)
  sundayEnd.setUTCDate(monday.getUTCDate() + 7)
  return { start: monday, end: sundayEnd } // [start, end) end exclusive
}

const range = computed(() => getWeekRange(weekValue.value))

const weekLogs = computed(() => {
  if (!range.value) return []
  const start = range.value.start
  const end = range.value.end
  return (props.logs || []).filter(r => {
    const s = r.start ? new Date(r.start) : null
    const e = r.end ? new Date(r.end) : null
    // Compare using UTC boundaries to match ISO math; convert to ms numbers
    const inStart = s && s.getTime() >= start.getTime() && s.getTime() < end.getTime()
    const inEnd = e && e.getTime() >= start.getTime() && e.getTime() < end.getTime()
    return inStart || inEnd
  })
})

const totalMs = computed(() => weekLogs.value.reduce((sum, r) => sum + (r.durationMs || r.duration_ms || 0), 0))
</script>

<template>
  <div v-if="visible" class="modal-overlay" @click.self="visible = false">
    <div class="modal">
      <header class="modal-header">
        <h3>History</h3>
        <button class="icon" @click="visible = false" aria-label="Close">×</button>
      </header>

      <div class="controls">
        <label>
          Week:
          <input type="week" v-model="weekValue" />
        </label>
        <span v-if="range" class="muted">
          {{ range.start.toLocaleDateString() }} → {{ new Date(range.end.getTime() - 1).toLocaleDateString() }}
        </span>
      </div>

      <div class="content">
        <p v-if="!weekLogs || weekLogs.length === 0" class="muted">No entries for this week.</p>
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
            <tr v-for="(row, idx) in weekLogs" :key="(row.start || '') + (row.end || '') + idx">
              <td>{{ weekLogs.length - idx }}</td>
              <td>{{ formatDate(row.start) }}</td>
              <td>{{ formatDate(row.end) }}</td>
              <td>{{ formatDuration(row.durationMs || row.duration_ms) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" class="right">Total</td>
              <td><strong>{{ formatDuration(totalMs) }}</strong></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; width: min(800px, 92vw); max-height: 90vh; overflow: auto; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 0.8rem 1rem; border-bottom: 1px solid #eee; }
.icon { font-size: 20px; background: transparent; border: none; cursor: pointer; }
.controls { display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-bottom: 1px solid #f0f0f0; }
.content { padding: 0.8rem 1rem 1rem; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { border-bottom: 1px solid #eee; padding: 0.5rem 0.4rem; text-align: left; }
.table thead th { border-bottom: 2px solid #ddd; }
.muted { color: #777; }
.right { text-align: right; }
</style>
