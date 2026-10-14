# ChiTito Smart Dispenser

Proyecto universitario IoT para consultar y almacenar las mediciones de un
dispensador para mascotas basado en ESP32.

**Aplicación publicada: https://15.235.63.174**

El acceso público utiliza HTTPS de Let's Encrypt para la IP del VPS. No requiere
dominio, No-IP, VPN ni túnel SSH. El dashboard y su API están protegidos mediante
inicio de sesión; las credenciales reales no forman parte de este repositorio.

## Arquitectura

```text
ESP32 (HX711 + VL53L1X)
        │ MQTT con TLS · puerto 8883
        ▼
Mosquitto existente
        │ suscripción persistente
        ▼
Laravel: consumidor MQTT → MySQL → API autenticada → Vue 3
                                                      │
                                              Nginx HTTPS · 443
```

El consumidor MQTT funciona independientemente de las peticiones HTTP. MySQL
conserva el historial en un volumen Docker y no publica su puerto en Internet.
Mosquitto se administra por separado del Compose de la aplicación web.

## Funcionalidades

- Lectura cruda HX711 después de tara; **no se convierte a gramos**.
- Distancia VL53L1X en milímetros y porcentaje enviado por el firmware.
- Estado del ESP32: conectado, desconectado o sin comunicación reciente.
- Tres gráficas, historial paginado y filtros de fechas.
- Actualización periódica cada tres segundos; conservación de últimas lecturas
  y avisos ante desconexiones o errores de la API.
- Autenticación por sesión, CSRF, cookies seguras y límite de intentos de acceso.
- Consumidor MQTT con TLS validado, validación de mensajes y reconexión.
- HTTPS público con certificado IP de corta duración y renovación automática.

## Tecnologías

| Área | Tecnologías |
|---|---|
| Firmware | ESP32/Arduino, HX711, VL53L1X, PubSubClient |
| Backend | Laravel 13, PHP 8.4, Eloquent, php-mqtt/client |
| Datos | MySQL 8.4; fechas UTC |
| Frontend | Vue 3, Vite, Chart.js; visualización America/Tijuana |
| Infraestructura | Docker Compose, Nginx, Mosquitto TLS |
| Certificados | Certbot 5.8.0, Let's Encrypt shortlived, systemd |

## Organización

```text
backend/          Aplicación Laravel, API, consumidor y migraciones
frontend/         Aplicación Vue, componentes y estilos
main/main.ino     Firmware original del ESP32
infrastructure/   Docker, Nginx y gestión de certificados HTTPS
docs/             Contrato MQTT, API y operación
compose.yaml      Servicios web, aplicación, consumidor y MySQL
.env.example      Variables de Docker Compose sin credenciales
```

## Configuración y ejecución

Requisitos: Docker con Compose, acceso a un broker MQTT TLS y Python 3/systemd
en el servidor para gestionar la renovación del certificado público.

1. Copiar `.env.example` a `.env` en la raíz y configurar claves/contraseñas propias.
   La clave Laravel puede generarse como 32 bytes aleatorios en formato `base64:`.
2. Preparar certificados y webroot según [operación](docs/OPERACION.md). La web
   pública exige un certificado válido: no se incluyen certificados ni claves.
3. Configurar una cuenta MQTT de lectura de los dos tópicos, su CA y las rutas
   del despliegue. La configuración actual está adaptada al VPS de ChiTito.
4. Construir las imágenes, iniciar MySQL y ejecutar las migraciones **solo para
   una instalación nueva**:

```bash
docker compose build
docker compose up -d db
docker compose run --rm app php artisan migrate --force
docker compose up -d
docker compose exec app php artisan chitito:admin admin@chitito.local
```

El último comando solicita la contraseña de forma oculta. Conserva la validación
de contraseña no vacía; no exige un mínimo de 16 caracteres. No ejecutarlo para
probar el sistema si se pretende conservar la contraseña de un usuario existente.

Para compilar el frontend por separado, usar Node.js compatible con Vite 7:

```bash
cd frontend
npm ci
npm run build
```

Laravel requiere PHP 8.3 o superior y sus extensiones; el Dockerfile incorpora
el entorno PHP necesario. `composer.lock` y `package-lock.json` fijan versiones.
El firmware requiere librerías Arduino y un `main/secrets.h` privado con Wi-Fi,
MQTT y CA; ese archivo está excluido de Git.

## Documentación

- [API y autenticación](docs/API.md).
- [Firmware, tópicos MQTT, sensores y limitaciones](docs/FIRMWARE-MQTT.md).
- [Despliegue HTTPS, renovación y operación](docs/OPERACION.md).

Este repositorio contiene únicamente fuente y configuración funcional. Pruebas,
simulaciones, auditorías, respaldos, dependencias instaladas y archivos temporales
quedan fuera de Git. No contiene credenciales ni datos históricos de producción.

El alcance actual no incluye actuadores, RFID, dispensación automática, agua,
horarios ni perfiles de mascotas. La validación física depende de disponer del
ESP32 conectado; publicar el código no demuestra por sí solo recepción real.
