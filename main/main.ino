#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>
#include <Wire.h>
#include <HX711.h>
#include <VL53L1X.h>
#include <time.h>

#include "secrets.h"

#define HX_DT 32
#define HX_SCK 33

#define I2C_SDA 21
#define I2C_SCL 22

#define TOLVA_LLENA_MM 50
#define TOLVA_VACIA_MM 300

#define SCALE_INTERVAL 300
#define LEVEL_INTERVAL 500
#define MQTT_INTERVAL 2000
#define MQTT_RETRY_INTERVAL 5000

#define DEVICE_ID "device01"

HX711 scale;
VL53L1X tof;

WiFiClientSecure secureClient;
PubSubClient mqtt(secureClient);

bool scaleReady = false;
bool tofReady = false;

long pesoRaw = 0;
uint16_t distanciaTolva = 0;
uint8_t nivelTolva = 0;

unsigned long lastScaleRead = 0;
unsigned long lastLevelRead = 0;
unsigned long lastPublish = 0;
unsigned long lastMqttAttempt = 0;

const char *TOPIC_STATUS =
    "chitito/device01/status";

const char *TOPIC_TELEMETRY =
    "chitito/device01/telemetry";


uint8_t calcularNivel(uint16_t distancia) {
  if (distancia <= TOLVA_LLENA_MM)
    return 100;

  if (distancia >= TOLVA_VACIA_MM)
    return 0;

  return constrain(
    map(
      distancia,
      TOLVA_LLENA_MM,
      TOLVA_VACIA_MM,
      100,
      0
    ),
    0,
    100
  );
}


void iniciarBascula() {
  scale.begin(HX_DT, HX_SCK);

  if (!scale.wait_ready_timeout(1500)) {
    Serial.println("HX711: ERROR");
    return;
  }

  scale.tare(20);

  scaleReady = true;

  Serial.println("HX711: OK");
}


void iniciarNivel() {
  Wire.begin(I2C_SDA, I2C_SCL);
  Wire.setClock(400000);

  tof.setTimeout(500);

  if (!tof.init()) {
    Serial.println("VL53L1X: ERROR");
    return;
  }

  tof.setDistanceMode(VL53L1X::Short);
  tof.setMeasurementTimingBudget(50000);
  tof.startContinuous(100);

  tofReady = true;

  Serial.println("VL53L1X: OK");
}


void conectarWiFi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  Serial.print("WiFi");

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println();
  Serial.print("IP ESP32: ");
  Serial.println(WiFi.localIP());
}


bool sincronizarHora() {
  configTime(
    0,
    0,
    "pool.ntp.org",
    "time.google.com"
  );

  Serial.print("Sincronizando hora");

  time_t now = time(nullptr);
  unsigned long start = millis();

  while (now < 1700000000) {
    if (millis() - start > 15000) {
      Serial.println();
      Serial.println("NTP: ERROR");
      return false;
    }

    delay(250);
    Serial.print(".");
    now = time(nullptr);
  }

  Serial.println();
  Serial.println("NTP: OK");

  return true;
}


void conectarMQTT() {
  if (mqtt.connected())
    return;

  if (millis() - lastMqttAttempt < MQTT_RETRY_INTERVAL)
    return;

  lastMqttAttempt = millis();

  Serial.print("MQTT... ");

  bool connected = mqtt.connect(
    DEVICE_ID,
    MQTT_USER,
    MQTT_PASSWORD,
    TOPIC_STATUS,
    1,
    true,
    "offline"
  );

  if (!connected) {
    Serial.print("ERROR ");
    Serial.println(mqtt.state());
    return;
  }

  mqtt.publish(
    TOPIC_STATUS,
    "online",
    true
  );

  Serial.println("OK");
}


void actualizarBascula() {
  if (!scaleReady)
    return;

  if (millis() - lastScaleRead < SCALE_INTERVAL)
    return;

  lastScaleRead = millis();

  if (!scale.is_ready())
    return;

  pesoRaw =
      scale.read_average(3) -
      scale.get_offset();
}


void actualizarNivel() {
  if (!tofReady)
    return;

  if (millis() - lastLevelRead < LEVEL_INTERVAL)
    return;

  lastLevelRead = millis();

  uint16_t distancia = tof.read();

  if (tof.timeoutOccurred())
    return;

  distanciaTolva = distancia;
  nivelTolva = calcularNivel(distancia);
}


void publicarTelemetria() {
  if (!mqtt.connected())
    return;

  if (millis() - lastPublish < MQTT_INTERVAL)
    return;

  lastPublish = millis();

  char payload[160];

  snprintf(
    payload,
    sizeof(payload),
    "{\"device\":\"%s\",\"peso_raw\":%ld,"
    "\"distancia_mm\":%u,\"nivel_pct\":%u}",
    DEVICE_ID,
    pesoRaw,
    distanciaTolva,
    nivelTolva
  );

  if (mqtt.publish(
        TOPIC_TELEMETRY,
        payload,
        false
      )) {
    Serial.print("MQTT -> ");
    Serial.println(payload);
  }
}


void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println();
  Serial.println("=== ChiTito V0.2 Cloud ===");

  iniciarBascula();
  iniciarNivel();

  conectarWiFi();

  if (!sincronizarHora())
    return;

  secureClient.setCACert(MQTT_CA_CERT);

  mqtt.setServer(
    MQTT_HOST,
    MQTT_PORT
  );

  mqtt.setKeepAlive(30);
  mqtt.setBufferSize(256);
}


void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    WiFi.reconnect();
    delay(100);
    return;
  }

  conectarMQTT();

  if (mqtt.connected())
    mqtt.loop();

  actualizarBascula();
  actualizarNivel();
  publicarTelemetria();
}