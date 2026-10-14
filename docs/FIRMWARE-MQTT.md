# Firmware y contrato MQTT verificados

Archivo analizado completo: `main/main.ino`. No se modificó ni compiló.
`secrets.h` no se leyó y no fue necesario para el dashboard.

## Conexión

- Wi-Fi estación con SSID/contraseña de `secrets.h`. Inicio bloqueante hasta
  conexión; en pérdida de Wi-Fi, `WiFi.reconnect()` y retorno del ciclo.
- Sincronización NTP con `pool.ntp.org` y `time.google.com`, timeout 15 segundos.
- `WiFiClientSecure.setCACert(MQTT_CA_CERT)`. MQTT host/puerto/cuenta del archivo
  privado. Keepalive 30 segundos; buffer de mensajes 256 bytes.
- Reintento MQTT cada 5 segundos; client ID `device01`.
- LWT `offline`, QoS 1, retained, en `chitito/device01/status`.
- Tras conexión publica `online` retained. Telemetría cada 2000 ms, QoS 0 y
  **sin retained** en `chitito/device01/telemetry`.

Formato exacto:

```json
{"device":"device01","peso_raw":1955,"distancia_mm":172,"nivel_pct":52}
```

Los números anteriores son ilustrativos. No son lecturas reales verificadas.

## Sensores

HX711: GPIO DT 32 y SCK 33. Espera inicial 1500 ms, tara de 20 muestras; cada
300 ms, si está listo, `read_average(3) - get_offset()`. Valor entero crudo con
signo, sin factor de calibración ni conversión a masa.

VL53L1X: I2C SDA 21 / SCL 22 a 400 kHz, modo Short, presupuesto 50000 µs,
medición continua cada 100 ms, consulta de firmware cada 500 ms. Distancia en mm.
El nivel enviado proviene del firmware: ≤50 mm →100%, ≥300 mm →0%, entre ambos
`map()` entero descendente y limitado a 0–100. El backend almacena el porcentaje
recibido, sin recalcularlo.

## Limitaciones que conserva esta fase

- Si un sensor falla al iniciar, sus variables permanecen inicialmente en cero;
  si luego no está listo o hay timeout, se conservan valores anteriores. El JSON
  no comunica salud del sensor ni antigüedad individual. El dashboard no puede
  distinguir esos ceros/valores anteriores de mediciones válidas.
- Si NTP falla, `setup()` retorna antes de configurar CA y servidor MQTT; el ciclo
  continúa, pero la configuración MQTT queda incompleta. No se corrigió porque
  el firmware estaba fuera del alcance autorizado.
- No hay timestamp ni identificador de publicación en el JSON. Las fechas de la
  base son de recepción en el VPS, no de adquisición del sensor.
- QoS 0 no garantiza recuperar mensajes durante cortes. No se reconstruyen huecos
  ni se promete entrega sin pérdidas.

## Consumidor Laravel

Proceso independiente `php artisan mqtt:consume`, un único contenedor/una réplica.
Client ID distinto del ESP32. Suscribe los dos tópicos exactos con QoS 0, con CA
original, validación de cadena y nombre/IP activas. Conecta a `15.235.63.174:8883`,
IP presente en el SAN del certificado MQTT.

Valida longitud, JSON, dispositivo, campos obligatorios y enteros o nulos:
`peso_raw` firmado de 32 bits, `distancia_mm` 0–65535, `nivel_pct` 0–100.
Las inserciones y actualización del dispositivo son transaccionales. Valores
idénticos de publicaciones distintas generan filas distintas.

Telemetría retained se ignora por carecer de fecha de origen. Estado retained se
almacena, pero no renueva `last_seen_at`; recibir un replay `online` no demuestra
una comunicación reciente. `offline` significa desconectado; sin telemetría ni
estado reciente confiable, se muestra sin información o sin datos recientes.
Umbral de antigüedad: 15 segundos. El LWT puede llegar después de ese umbral.

Reconexión de 2 hasta 30 segundos ante fallos MQTT/DB, y nueva suscripción en cada
conexión. Errores de mensajes se registran con límite temporal, sin payloads.
Heartbeat del consumidor cada 5 segundos; se distingue de la conexión del ESP32.

La cuenta `chitito-reader` solo puede leer los dos tópicos del dispositivo. La ACL
creada conserva `readwrite #` y lectura de `$SYS/#` para las cuentas preexistentes. Contraseñas antiguas,
certificados, puertos, red y contenedor MQTT se conservaron. Se aplicó SIGHUP con
autorización del usuario y respaldo anterior.
