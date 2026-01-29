<script setup>
import { ref, computed, onMounted } from 'vue'
import TimeLog from './components/TimeLog.vue'
import { ensureAuthenticated } from '../services/authService.js'
import { listRecords, punchIn, punchOut } from '../services/punchService.js'

// State
const isClockedIn = ref(false)
const currentStart = ref(null) // ISO string
const logs = ref([]) // { start: ISO, end: ISO, durationMs: number }
const username = ref('')

async function refreshFromServer() {
  try {
    // ensure auth and set username once
    const resp = await ensureAuthenticated()
    let u = resp?.user?.username ?? resp?.username ?? resp?.user ?? resp?.email ?? ''
    if (u && typeof u === 'object') { u = u.username || u.name || '' }
    username.value = u || ''

    const data = await listRecords()
    const open = data?.open_record || null
    isClockedIn.value = !!open
    currentStart.value = open ? new Date(open.clock_in).toISOString() : null
    const recs = Array.isArray(data?.records) ? data.records : []
    logs.value = recs.map(r => ({
      start: new Date(r.clock_in).toISOString(),
      end: new Date(r.clock_out).toISOString(),
      durationMs: Number(r.duration_ms || 0)
    }))
  } catch (e) {
    // ensureAuthenticated will redirect on failure
  }
}

onMounted(async () => {
  await refreshFromServer()
})

// Confirm panel
const showConfirm = ref(false)
const actionTime = ref('') // datetime-local string
const errorMsg = ref('')

function openConfirm() {
  // Prefill with now
  const now = new Date()
  actionTime.value = toDatetimeLocal(now)
  errorMsg.value = ''
  showConfirm.value = true
}

function toDatetimeLocal(d) {
  // convert Date -> yyyy-MM-ddTHH:mm
  const pad = (n) => String(n).padStart(2, '0')
  const yyyy = d.getFullYear()
  const mm = pad(d.getMonth() + 1)
  const dd = pad(d.getDate())
  const hh = pad(d.getHours())
  const mi = pad(d.getMinutes())
  return `${yyyy}-${mm}-${dd}T${hh}:${mi}`
}

function fromDatetimeLocal(s) {
  // treat as local time
  return new Date(s)
}

const buttonLabel = computed(() => (isClockedIn.value ? 'Tap Out' : 'Tap In'))

async function confirmAction() {
  errorMsg.value = ''
  if (!actionTime.value) {
    errorMsg.value = 'Please choose a date & time.'
    return
  }
  const when = fromDatetimeLocal(actionTime.value)
  if (isNaN(when.getTime())) {
    errorMsg.value = 'Invalid date & time.'
    return
  }

  try {
    if (!isClockedIn.value) {
      // Tapping in -> server punch in
      const res = await punchIn(when.toISOString())
      if (res?.status !== 'ok') {
        errorMsg.value = res?.message || 'Failed to punch in.'
        return
      }
    } else {
      // Tapping out -> server punch out
      const res = await punchOut(when.toISOString())
      if (res?.status !== 'ok') {
        errorMsg.value = res?.message || 'Failed to punch out.'
        return
      }
    }
    showConfirm.value = false
    await refreshFromServer()
  } catch (e) {
    errorMsg.value = 'Network error.'
  }
}

function cancelAction() {
  showConfirm.value = false
}

const elapsedNow = computed(() => {
  if (!isClockedIn.value || !currentStart.value) return null
  const ms = Date.now() - new Date(currentStart.value).getTime()
  return formatDuration(ms)
})

function formatDuration(ms) {
  const sec = Math.floor(ms / 1000)
  const h = Math.floor(sec / 3600)
  const m = Math.floor((sec % 3600) / 60)
  const s = sec % 60
  const pad = (n) => String(n).padStart(2, '0')
  return `${pad(h)}:${pad(m)}:${pad(s)}`
}

</script>

<template>
  <div class="container">
    <h1 class="brand">webpunchy</h1>
    <div class="user" v-if="username">Signed in as {{ username }}</div>

    <div class="status" v-if="isClockedIn">
      Clocked in since {{ new Date(currentStart).toLocaleString() }} · Elapsed {{ elapsedNow }}
    </div>
    <div class="status" v-else>
      Currently clocked out
    </div>

    <button class="giant" :class="{ in: isClockedIn, out: !isClockedIn }" @click="openConfirm">{{ buttonLabel }}</button>

    <div v-if="showConfirm" class="modal">
      <div class="dialog">
        <h2>{{ buttonLabel }}</h2>
        <label class="field">
          <span>Adjust time</span>
          <input type="datetime-local" v-model="actionTime" />
        </label>
        <p v-if="errorMsg" class="error">{{ errorMsg }}</p>
        <div class="actions">
          <button class="secondary" @click="cancelAction">Cancel</button>
          <button class="primary" @click="confirmAction">Confirm</button>
        </div>
      </div>
    </div>


    <TimeLog :logs="logs" />
  </div>
</template>

<style scoped>
.container {
  max-width: 900px;
  margin: 2rem auto;
  padding: 0 1rem 4rem;
}
.brand {
  text-align: center;
  font-size: 2.5rem;
  margin-bottom: 1rem;
}
.status {
  text-align: center;
  margin-bottom: 1rem;
  color: #555;
}
.giant {
  display: grid;
  place-items: center;
  margin: 1.5rem auto;
  width: min(80vw, 360px);
  aspect-ratio: 1 / 1;
  font-size: 2.2rem;
  border-radius: 50%;
  border: none;
  cursor: pointer;
  color: white;
  box-shadow: 0 10px 20px rgba(0,0,0,0.15);
  transition: filter 0.2s ease, transform 0.05s ease;
}
.giant:hover { filter: brightness(1.05); }
.giant:active { transform: scale(0.98); }
.giant.in { background: linear-gradient(135deg, #22c55e, #16a34a); }
.giant.out { background: linear-gradient(135deg, #ef4444, #b91c1c); }

.modal {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.35);
  display: grid;
  place-items: center;
}
.dialog {
  background: white;
  padding: 1.2rem;
  border-radius: 0.75rem;
  width: min(520px, 92vw);
  box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}
.field { display: grid; gap: 0.4rem; margin: 1rem 0; }
.field input { padding: 0.5rem; font-size: 1rem; }
.actions { display: flex; justify-content: flex-end; gap: 0.5rem; }
button.primary { background: #42b883; color: white; border: none; padding: 0.6rem 1rem; border-radius: 0.5rem; }
button.secondary { background: #eee; border: none; padding: 0.6rem 1rem; border-radius: 0.5rem; }
button.small { font-size: 0.9rem; padding: 0.4rem 0.6rem; }
.error { color: #c00; }

.toolbar { margin: 1rem 0; text-align: right; }
.user { text-align: center; margin-bottom: 0.25rem; color: #333; font-size: 0.95rem; }
</style>
