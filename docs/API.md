# API de ChiTito

Mismo origen que Vue; autenticación de sesión Laravel, sin tokens en localStorage.
Todas las fechas JSON están en UTC. Vue muestra fechas en `America/Tijuana`.

| Método | Ruta | Función |
|---|---|---|
| GET | `/api/csrf` | Inicia sesión y entrega `csrf_token` |
| POST | `/api/login` | `email`, `password`; máximo 5 intentos/minuto por IP y correo/IP |
| GET | `/api/session` | Usuario autenticado |
| POST | `/api/logout` | Invalida sesión y renueva CSRF |
| GET | `/api/device` | Identidad, estado, última comunicación, última lectura y salud del consumidor |
| GET | `/api/telemetry/latest` | Última medición o `data: null` |
| GET | `/api/telemetry/history` | Historial original paginado |
| GET | `/api/telemetry/chart` | Promedios por intervalo, aproximadamente hasta 241 puntos |
| GET | `/up` | Salud HTTP de Laravel, sin datos privados |

Excepto CSRF, login y `/up`, las rutas requieren sesión. Cada POST necesita
`X-CSRF-TOKEN`. Cookies: `Secure`, `HttpOnly`, `SameSite=Strict`; sesión cifrada y
almacenada en MySQL, duración 120 minutos. Rutas autenticadas: 100 solicitudes/minuto
por usuario.

Historial y gráficas aceptan `from` y `to` en formato
`2026-10-12T10:00:00+00:00` (codificar `+` como `%2B` en URLs). Rango máximo 366 días;
por omisión, últimas 24 horas. El historial admite `page` (1–10000) y `per_page`
(1–100, predeterminado 25). Orden: fecha descendente e ID descendente.

Las respuestas habituales usan `{"data": ...}`. La paginación incluye `data`,
`current_page`, `last_page`, `total` y enlaces. Las gráficas incluyen
`meta.bucket_seconds`, `meta.aggregation = mean` y `meta.timezone = UTC`.
Errores Laravel JSON: `message`, y `errors` en validación; estados 401, 419, 422 y
429 según corresponda.

Los promedios de peso siguen siendo unidades crudas; no se convierten a gramos.
Los valores nulos no se sustituyen por cero. La caché de gráficas dura 15 segundos
y mantiene una sola entrada por dispositivo/usuario. El navegador consulta el
dispositivo cada 3 segundos sin peticiones solapadas y el historial/gráficas cada
cinco ciclos, aproximadamente 15 segundos.
