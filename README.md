**Aplicación publicada: https://15.235.63.174**

## Arquitectura actual

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
