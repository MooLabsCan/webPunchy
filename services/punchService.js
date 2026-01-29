import { ensureAuthenticated } from './authService'

const base = '/back/api/time_records'

async function authedToken() {
  const resp = await ensureAuthenticated()
  const token = resp?.received_token || (typeof localStorage !== 'undefined' && localStorage.getItem('auth_token')) || (typeof sessionStorage !== 'undefined' && sessionStorage.getItem('auth_token'))
  if (!token) throw new Error('No auth token')
  return token
}

export async function listRecords() {
  const token = await authedToken()
  const r = await fetch(`${base}/list.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ token })
  })
  const data = await r.json()
  return data
}

export async function punchIn(whenIso) {
  const token = await authedToken()
  const r = await fetch(`${base}/punch_in.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ token, when: whenIso })
  })
  const data = await r.json()
  return data
}

export async function punchOut(whenIso) {
  const token = await authedToken()
  const r = await fetch(`${base}/punch_out.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ token, when: whenIso })
  })
  const data = await r.json()
  return data
}

export const punchService = { listRecords, punchIn, punchOut }
