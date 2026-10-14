let token = null
export async function csrf() {
  const r = await fetch('/api/csrf', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
  if (!r.ok) throw new Error('No se pudo establecer una sesión segura.')
  token = (await r.json()).csrf_token
}
export async function api(path, method = 'GET', body) {
  if (method !== 'GET' && !token) await csrf()
  const r = await fetch('/api/' + path, { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(method !== 'GET' ? { 'X-CSRF-TOKEN': token } : {}) }, ...(body ? { body: JSON.stringify(body) } : {}), signal: AbortSignal.timeout(12000) })
  const data = await r.json().catch(() => ({}))
  if (!r.ok) {
    if (r.status === 419) token = null
    const error = new Error(r.status === 429 ? 'Demasiados intentos. Espera un minuto.' : (Object.values(data.errors || {}).flat()[0] || data.message || 'No se pudo consultar el servidor.'))
    error.status = r.status
    throw error
  }
  return data
}
