# Despliegue y operación

## Configuración del despliegue

URL pública: la indicada en `APP_URL`. Directorio del proyecto:
el elegido al instalar la aplicación. El dashboard exige iniciar sesión. No se necesita
importar certificados ni abrir un túnel para usar la URL pública.

La configuración se adapta a cada servidor mediante variables de entorno. Ajustar IP,
rutas, identidad TLS MQTT y configuración de Nginx/Certbot; no copiar secretos
de producción al repositorio.

Los valores `192.0.2.10`, `mqtt.example.invalid`, `app_user`,
`telemetry_reader` y `operator@example.invalid` son ejemplos. Sustituirlos por
valores privados en `.env`, que no se versiona. Para ejecutar los comandos desde
la raíz del proyecto, puede definirse `export CHITITO_PROJECT_DIR="$PWD"`.

| Servicio | Puertos |
|---|---|
| Nginx/Vue | 80 HTTP, 443 HTTPS públicos |
| Acceso privado conservado | 8443 solo en loopback |
| Laravel PHP-FPM | 9000 interno |
| MySQL | 3306 interno, volumen persistente |
| Consumidor MQTT | Sin puertos publicados |
| Mosquitto independiente | 8883 TLS existente |
| SSH | 22 existente |

## Variables y archivos privados

Copiar `.env.example` de la raíz a `.env`; configurar `APP_KEY`,
`DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `MQTT_PASSWORD` y mantener
`APP_URL` y `PUBLIC_IP` con la dirección de tu despliegue. Generar secretos propios, sin valores
predeterminados. `backend/.env.example` documenta el mismo contrato para Laravel.

Compose suministra únicamente las variables necesarias a cada contenedor.
Las cookies de sesión son Secure/HttpOnly/SameSite Strict y la API exige CSRF.
Vue usa rutas relativas `/api/` del mismo origen.

Los siguientes archivos no se versionan:

- `.env` real y `main/secrets.h`.
- CA/credenciales MQTT y cualquier clave privada.
- `infrastructure/private/webtls/`: certificado privado de localhost existente.
- `infrastructure/private/acme/production/`: cuentas, configuración y certificados
  de Certbot; `staging/` permanece separado.

La CA del broker se monta solo en el consumidor desde la ruta configurada en
Compose. Mosquitto no pertenece a este Compose; no ejecutar operaciones de
borrado/reconfiguración sobre su despliegue al administrar la web.

## Certificados para HTTPS público

Certbot 5.8.0 obtiene certificados de IP mediante webroot y perfil `shortlived`.
Los cuatro scripts Python conservados son de operación, no herramientas de prueba:

| Archivo | Función |
|---|---|
| `certificate_common.py` | Rutas, cliente Certbot y comprobación TLS |
| `issue_ip_certificate.py` | Emisión staging y producción |
| `renew_certificate.py` | Renovación, recarga y verificación del certificado servido |
| `install_certificate_timer.py` | Instalación del renovador y temporizador systemd |

Para una instalación nueva, antes de iniciar la configuración final de Nginx:

1. Crear los directorios privados `acme/production`, `acme/staging` y sus
   directorios `*-work`, `*-logs`; crear `acme/webroot/.well-known/acme-challenge`.
   Conservar permisos privados en cuentas/claves; webroot debe ser legible por Nginx.
2. Preparar el certificado local privado en `private/webtls/` si se conserva el
   listener de respaldo. No utilizarlo en el listener público 443.
3. Iniciar Nginx con `nginx.bootstrap.conf` como plantilla `/etc/nginx/templates/default.conf.template`,
   `nginx-app.conf` como snippet y publicar solo 80 y el listener privado 8443
   durante la emisión. Conservar una copia de `nginx.conf` final y respaldar la
   configuración antes de cambiarla.
4. Permitir únicamente HTTP/HTTPS adicionales en el firewall y comprobar la
   accesibilidad externa del webroot. Conservar SSH y MQTT.
5. Desde el directorio del proyecto, solicitar staging y luego producción:

```bash
sudo python3 infrastructure/issue_ip_certificate.py staging
sudo python3 infrastructure/issue_ip_certificate.py production
```

6. Restaurar la configuración final `nginx.conf` y publicar 443, con la cadena de
   producción en `/etc/letsencrypt/live/chitito-ip/fullchain.pem`. Nginx monta el
   directorio ACME completo de producción en modo lectura para seguir los enlaces
   `live/` y sus destinos `archive/`.
7. Validar la configuración, comprobar HTTPS desde otra red e instalar renovación:

```bash
docker compose exec -T web nginx -t
sudo python3 infrastructure/install_certificate_timer.py
```

Antes de arrancar una instalación nueva, construir imágenes y preparar la base:

```bash
docker compose build
docker compose up -d db
docker compose run --rm app php artisan migrate --force
```

Configurar el listener web de bootstrap antes de iniciar `web` por primera vez;
después de obtener el certificado, volver a la plantilla final y ejecutar
`docker compose up -d`. Las migraciones anteriores corresponden solo a una
instalación nueva, no a la limpieza del repositorio ni a cambios de configuración.

No repetir esa preparación sobre la instalación vigente: ya está publicada y
renueva automáticamente. Nunca publicar certificados staging ni desactivar la
validación TLS para solventar errores.

## Renovación automática

Los certificados IP duran 160 horas. `chitito-cert-renew.timer` comprueba cada
cuatro horas, con retraso aleatorio hasta cinco minutos y ejecuciones persistentes
tras apagados. Certbot decide cuándo renovar; el proceso no fuerza emisiones
rutinarias.

El deploy hook marca la renovación. El script del host valida y recarga Nginx,
compara la huella servida por TLS con el certificado en disco y comprueba que
reste más de un día de validez. `PUBLIC_IP` se obtiene del entorno o de `.env`; `CHITITO_PROJECT_DIR` permite elegir el directorio del proyecto. El instalador genera un archivo privado de configuración del servicio en `/etc/chitito/cert-renew.env`. Las copias del renovador se instalan root-owned
en `/usr/local/lib/chitito/`; Certbot no recibe el socket Docker.

```bash
systemctl list-timers chitito-cert-renew.timer --no-pager
systemctl is-enabled chitito-cert-renew.timer
systemctl is-active chitito-cert-renew.timer
systemctl show chitito-cert-renew.service -p Result -p ExecMainStatus
sudo journalctl -u chitito-cert-renew.service -n 50 --no-pager
sudo systemctl start chitito-cert-renew.service
```

Mantener el temporizador activo mientras se sirva HTTPS público. Si se actualiza
su código, reinstalar la copia root-owned con `install_certificate_timer.py`.

## Comandos de operación

```bash
cd "$CHITITO_PROJECT_DIR"
docker compose ps
docker compose up -d
docker compose logs --tail 100 app mqtt web
docker compose logs --tail 100 db
docker compose restart app
docker compose stop
```

No ejecutar `down -v` ni borrar volúmenes. Para cambios de Nginx sin alterar
puertos/montajes, validar y recargar sin reiniciar el contenedor:

```bash
docker compose exec -T web nginx -t
docker compose exec -T web nginx -s reload
```

Las modificaciones de código PHP requieren reconstruir la imagen y recrear el
servicio correspondiente porque el código se incorpora con `COPY`.
No ejecutar migraciones como rutina al cambiar únicamente un archivo PHP.

Crear/restablecer el administrador de forma interactiva:

```bash
docker compose exec app php artisan chitito:admin operator@example.invalid
```

El comando exige contraseña no vacía y revoca las sesiones del usuario que se
actualiza. No se usa para verificar disponibilidad ni ejecutar pruebas.

## Persistencia y respaldos

`chitito-web_mysql-data` conserva usuarios, dispositivos y mediciones. No existe
borrado automático del historial. Antes de actualizar, crear un dump MySQL
consistente con `mysqldump --single-transaction --no-tablespaces`, usando las
credenciales únicamente desde el entorno privado del contenedor, sin incluirlas
en argumentos ni logs. Guardar también `.env`, configuración y ACME de producción,
preservando enlaces live/archive, con permisos privados y copia cifrada externa.
Los respaldos y los datos de producción no se almacenan en este repositorio.

Probar restauraciones en una base/volumen independiente. Conservar configuración
e imágenes anteriores para revertir actualizaciones sin borrar datos ni tocar
servicios ajenos. Una migración no se revierte solo cambiando la imagen.

Referencia: [Let's Encrypt: certificados de IP con Certbot](https://letsencrypt.org/2026/03/11/shorter-certs-certbot).
