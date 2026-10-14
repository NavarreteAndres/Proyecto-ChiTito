# ChiTito Smart Dispenser

Proyecto IoT para mostrar y almacenar las lecturas del ESP32 mediante Laravel,
MySQL y Vue 3. El peso del HX711 se conserva como lectura cruda; el VL53L1X
informa distancia y nivel de alimento.

La dirección web se configura con `APP_URL`; la IP del despliegue, con `PUBLIC_IP`.
Los archivos de esta versión usan ejemplos; los datos del servidor se proporcionan
de forma privada mediante variables de entorno y `.env`.

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

## Configuración

Copiar `.env.example` a `.env` y completar los valores privados. Las direcciones
`192.0.2.10` y `example.invalid` son ejemplos de documentación, no servicios reales.
El administrador se especifica con `ADMIN_EMAIL` o como argumento del comando
`chitito:admin`; no hay una cuenta administrativa incorporada en el código.

Docker conserva el entorno necesario para reproducir la aplicación, sin incluir
certificados, contraseñas ni datos de producción. La renovación HTTPS usa Certbot
y configuración privada del servidor.

- [Despliegue y operación](docs/OPERACION.md).
- [API](docs/API.md).
- [Firmware y contrato MQTT](docs/FIRMWARE-MQTT.md).
