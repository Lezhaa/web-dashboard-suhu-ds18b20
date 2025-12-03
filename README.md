
# 🌡️ Server Temperature Monitoring Dashboard

A Laravel-based server room temperature monitoring system with ThingSpeak and ESP32 integration for real-time and scheduled data collection.

https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white
https://img.shields.io/badge/ThingSpeak-00A0E3?style=for-the-badge&logo=thingspeak&logoColor=white
https://img.shields.io/badge/Bootstrap-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white
https://img.shields.io/badge/Chart.js-FF6384?style=for-the-badge&logo=chart.js&logoColor=white


## Features

- 📡 Real-time Monitoring: Live temperature data from ThingSpeak (updated every 40 seconds)
- ⏰ Auto-scheduler: Automatic data collection at specific times (08:00, 13:00, 18:00)
- 📊 Interactive Dashboard: Daily/monthly temperature charts and tables
- 📁 Data Export: Export to Excel and PDF formats
- 🔧 Manual Input: Web-based manual temperature input
-  📱 Responsive Design: Optimized for desktop and mobile devices
-  🔄 Dual Source: Data from ThingSpeak (automatic) and manual input
-  🔔 Notifications: Success/error alerts for user actions

## System Architecture

 ESP32 Sensor (DS18B20) -> ThingSpeak Cloud -> Laravel Backend -> MySQL Database -> Dashboard ->  Web Browser
## 📦 Prerequisites

- PHP 8.0 or higher
- Composer
- MySQL 5.7 or higher
- Node.js & NPM
- Git
## Installation

1. Clone Repository
```
git clone https://github.com/Leezha/dashboard-suhu-fix.git
cd dashboard-suhu-fix
```
2. Install Dependencies
```
composer install
npm install
npm run build
```
3. Setup Environment
```
cp .env.example .env
php artisan key:generate
```
4. Configure .env File
```
APP_NAME="Server Temperature Monitoring"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE="YOUR_DB"
DB_USERNAME=root
DB_PASSWORD=

# ThingSpeak Configuration
THINGSPEAK_CHANNEL_ID="YOUR_CHANNEL_ID"
THINGSPEAK_READ_API_KEY="YOUR_READ_API_KEY"

# Scheduler Configuration
SCHEDULE_MORNING_TIME=08:00
SCHEDULE_NOON_TIME=13:00
SCHEDULE_EVENING_TIME=18:00
```
5. Setup Database
```
php artisan migrate
```
6. Setup Scheduler (Cron Job)
```
# Edit crontab
crontab -e

# Add this line (replace /path/to/project with your actual path)
* * * * * cd /path/to/server-temperature-monitoring && php artisan schedule:run >> /dev/null 2>&1
```
7. Run Development Server
```
# Development
php artisan serve

# Production (with specific host)
php artisan serve --host=0.0.0.0 --port=8000
```
## Database Structure
```
CREATE TABLE temperatures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    time_period ENUM('morning', 'noon', 'evening') NOT NULL,
    temperature DECIMAL(4,1) NOT NULL,
    source VARCHAR(20) DEFAULT 'manual',
    thingspeak_timestamp TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_date_time_period (date, time_period)
);
```
## 🔧 ESP32 Configuration
### Hardware Requirements
- ESP32 Development Board
- DS18B20 Temperature Sensor
- Breadboard & Jumper Wires
-

### ESP32 Code (Visual Studio Code + Platform.io)
```
#include <Arduino.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ESPmDNS.h>
#include <WiFiUdp.h>
#include <ArduinoOTA.h> 

// =========================
// SENSOR CONFIGURATION
// =========================
#define TEMPERATURE_SENSOR_PIN 15

OneWire oneWire(TEMPERATURE_SENSOR_PIN);
DallasTemperature temperatureSensor(&oneWire);

// =========================
// WIFI HOTSPOT CONFIGURATION
// =========================
const char* HOTSPOT_SSID     = "YOUR_SSID";
const char* HOTSPOT_PASSWORD = "YOUR_PASSWORD";

// =========================
// THINGSPEAK CONFIGURATION
// =========================
const char* thingspeakHost = "api.thingspeak.com";
const int   thingspeakPort = 80;
const char* thingspeakPath = "/update";
const char* thingspeakApiKey = "YOUR_WRITE_API_KEY"; // Write API key

const unsigned long SEND_INTERVAL = 40000; // 40 seconds (>15s) 

// =========================
// SYSTEM VARIABLES
// =========================
unsigned long previousSendTime = 0;
unsigned long lastWiFiReconnectAttempt = 0;
const unsigned long WIFI_RECONNECT_INTERVAL = 30000;

// =========================
// WIFI FUNCTIONS
// =========================
void connectToWiFi() {
    Serial.println("Connecting to WiFi hotspot...");

    WiFi.disconnect(true);
    delay(1000);

    WiFi.mode(WIFI_STA);
    WiFi.begin(HOTSPOT_SSID, HOTSPOT_PASSWORD);

    int attemptCount = 0;
    while (WiFi.status() != WL_CONNECTED && attemptCount < 20) {
        delay(1000);
        Serial.print(".");
        attemptCount++;
    }

    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("\nSuccessfully connected to WiFi");
        Serial.print("IP Address: ");
        Serial.println(WiFi.localIP());
    } else {
        Serial.println("\nFailed to connect to WiFi");
    }
}

void reconnectWiFi() {
    if (WiFi.status() != WL_CONNECTED &&
        millis() - lastWiFiReconnectAttempt > WIFI_RECONNECT_INTERVAL) {

        Serial.println("Attempting WiFi reconnection...");
        
        WiFi.disconnect();
        delay(500);
        WiFi.begin(HOTSPOT_SSID, HOTSPOT_PASSWORD);
        lastWiFiReconnectAttempt = millis();

        int attempts = 0;
        while (WiFi.status() != WL_CONNECTED && attempts < 15) {
            delay(500);
            Serial.print(".");
            attempts++;
        }

        if (WiFi.status() == WL_CONNECTED) {
            Serial.println("\nWiFi reconnected successfully");
        } else {
            Serial.println("\nWiFi reconnection failed");
        }
    }
}

// =========================
// SEND DATA TO THINGSPEAK
// =========================
void sendToThingSpeak(float temperature) {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("WiFi not connected, data not sent");
        return;
    }

    WiFiClient client;

    Serial.println("Connecting to ThingSpeak (TCP)...");
    if (!client.connect(thingspeakHost, thingspeakPort)) {
        Serial.println("Failed to connect to api.thingspeak.com:80");
        return;
    }

    String postData = "api_key=" + String(thingspeakApiKey) +
                      "&field1=" + String(temperature, 2);   // field1 = temperature

    String request =
      String("POST ") + thingspeakPath + " HTTP/1.1\r\n" +
      "Host: " + thingspeakHost + "\r\n" +
      "Content-Type: application/x-www-form-urlencoded\r\n" +
      "Connection: close\r\n" +
      "Content-Length: " + String(postData.length()) + "\r\n\r\n" +
      postData + "\r\n";

    Serial.println("Sending request:");
    Serial.println(request);

    client.print(request);

    // Wait for brief response
    unsigned long startTime = millis();
    while (client.connected() && !client.available() && millis() - startTime < 5000) {
        delay(10);
    }

    Serial.println("ThingSpeak response:");
    while (client.available()) {
        String line = client.readStringUntil('\n');
        Serial.println(line);
    }

    client.stop();
}

// =========================
// READ SENSOR & SEND DATA
// =========================
void readAndSendData() {
    temperatureSensor.requestTemperatures();
    float temperature = temperatureSensor.getTempCByIndex(0);

    if (temperature == DEVICE_DISCONNECTED_C) {
        Serial.println("Error: Sensor not detected");
        return;
    }

    Serial.print("Temperature: ");
    Serial.print(temperature);
    Serial.println(" °C");

    sendToThingSpeak(temperature);
}

// =========================
// SETUP FUNCTION
// =========================
void setup() {
    Serial.begin(115200);
    delay(2000);

    Serial.println("========================================");
    Serial.println("   ESP32 TEMPERATURE MONITORING SYSTEM");
    Serial.println("========================================");
    Serial.print("Data Interval: ");
    Serial.print(SEND_INTERVAL / 1000);
    Serial.println(" seconds");
    Serial.println("WiFi Hotspot: " + String(HOTSPOT_SSID));
    Serial.println("----------------------------------------");

    temperatureSensor.begin();

    int sensorCount = temperatureSensor.getDeviceCount();
    Serial.print("Sensors detected: ");
    Serial.println(sensorCount);

    connectToWiFi();

    if (sensorCount > 0 && WiFi.status() == WL_CONNECTED) {
        Serial.println("System operating normally");
    } else {
        Serial.println("Warning: Check sensor or WiFi connection");
    }

    // Optional: DNS test
    if (WiFi.status() == WL_CONNECTED) {
        IPAddress ip;
        if (WiFi.hostByName(thingspeakHost, ip)) {
            Serial.print("DNS api.thingspeak.com -> ");
            Serial.println(ip);
        } else {
            Serial.println("Failed to resolve api.thingspeak.com DNS");
        }
    }

    Serial.println("----------------------------------------");

    previousSendTime = millis();
}

// =========================
// MAIN LOOP
// =========================
void loop() {
    unsigned long currentTime = millis();

    reconnectWiFi();

    if (currentTime - previousSendTime >= SEND_INTERVAL) {
        previousSendTime = currentTime;
        readAndSendData();
    }

    delay(100);
}
```
## Support
- Issues: [Github Issues](https://github.com/Leezha/server-temperature-monitoring/issues)
- Documention: [wiki](https://github.com/Leezha/server-temperature-monitoring/wiki)
For support, email leezhaamail@gmail.com.


## Acknowledgements

 - [Laravel](https://laravel.com/) - PHP Framework 
 - [ThingSpeak](https://thingspeak.mathworks.com/) - IoT Platform
 - [Chartjs](https://www.chartjs.org/) - Charting Library
- [Bootstrap](https://getbootstrap.com/) - CSS Framework
- [ESP32](https://www.espressif.com/) - Microcontroller

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](https://github.com/Lezhaa/dashboard-suhu-fix/blob/main/LICENSE) file for details.

