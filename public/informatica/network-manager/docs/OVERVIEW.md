# NetMonitor Stealth — Overview General

## ¿Qué es?

NetMonitor Stealth es una aplicación web de **monitoreo y gestión de redes LAN** diseñada para correr como servidor en Linux. Descubre automáticamente todos los dispositivos conectados a la red, los identifica, los monitorea en tiempo real y notifica cuando algo cambia — todo sin instalar agentes en los dispositivos monitoreados.

El nombre "Stealth" refleja su filosofía: opera en modo **pasivo por defecto**, generando el mínimo tráfico posible en la red.

---

## Stack tecnológico

| Capa | Tecnología |
|---|---|
| Backend | Node.js + Express.js |
| Base de datos | SQLite (better-sqlite3, modo WAL) |
| Tiempo real | Socket.io (WebSocket) |
| Frontend | Vanilla JS ES6 (sin frameworks) |
| Topología | vis-network (local, sin CDN) |
| Gráficos | Chart.js (CDN) |
| Geolocalización | geoip-lite (offline) |
| SNMP | net-snmp |
| Notificaciones | Telegram Bot API |

---

## Arquitectura general

```
┌─────────────────────────────────────────────────────────┐
│                        BROWSER                          │
│  App.js → TableManager, DetailPanel, ModalManager       │
│  BandwidthPanel, ChartManager, ToastManager             │
│  Socket.io client ←──────────────────────────────────┐  │
└──────────────────────────────────┬──────────────────┼──┘
                                   │ HTTP/WS          │
┌──────────────────────────────────▼──────────────────┼──┐
│                     EXPRESS SERVER                   │  │
│                                                      │  │
│  /api/devices    /api/switches   /api/topology       │  │
│  /api/actions    /api/bandwidth  /api/power          │  │
│  /api/ipam       /api/credentials /api/settings      │  │
│                                                      │  │
│  Auth middleware → requireAuth()                     │  │
└────────────┬──────────────────────────────┬──────────┘  │
             │                              │             │
┌────────────▼───────────┐   ┌─────────────▼──────────┐  │
│      SCANNER           │   │      NOTIFIER          │  │
│  scheduler.js          │   │  telegram.js           │  │
│  arp-scanner.js        │   │  alert-manager.js      │  │
│  fingerprinter.js      │   └────────────────────────┘  │
│  hostname-resolver.js  │                                │
│  snmp-client.js        │   ┌────────────────────────┐  │
│  wan-monitor.js        │──▶│    Socket.io emit      │──┘
└────────────┬───────────┘   │  scan:log, scan:mode   │
             │               │  dashboard:refresh     │
┌────────────▼───────────┐   │  alert:offline         │
│      SQLite DB         │   │  wan:update            │
│  network.db (WAL)      │   └────────────────────────┘
└────────────────────────┘
```

---

## Features principales

### 1. Descubrimiento de dispositivos (ARP Scanner)
- Detecta todos los dispositivos en la red local via ARP
- En Linux usa `arp-scan` (con SUID bit) para un scan completo de la subred
- En Windows usa ping sweep + `arp -a`
- Filtra automáticamente interfaces VPN/VM (Tailscale, VMware, etc.)
- Soporta múltiples subnets simultáneamente

### 2. Fingerprinting de dispositivos
- **TTL via ICMP** → identifica OS (Windows=128, Linux/Unix=64, Network Device=255)
- **OUI lookup** → 39.000+ entradas de la base IEEE oficial (generada con `oui-update.js`)
- **Vendor normalization** → convierte "TP-LINK TECHNOLOGIES CO., LTD." → "TP-Link"
- **VENDOR_RULES** → mapeo por substring para clasificar tipo de dispositivo
- **mDNS** via `avahi-resolve` → hostnames de iPhones, Macs, impresoras
- **NetBIOS** via `nmblookup` → hostnames de Windows
- **Reverse DNS** via `nslookup` → hostnames registrados en el router
- **Detección de gateway** → IPs `.1`/`.254` + TTL/vendor de red → "Router/Gateway"

### 3. Resolución de hostnames (paralelo)
Corre en paralelo cuatro técnicas:
- **mDNS** (puerto 5353) — Bonjour/Avahi
- **DNS reverso** — PTR record
- **HTTP title** — título de la página web del dispositivo (routers, NAS)
- **SSDP** (puerto 1900) — UPnP device name

### 4. Modos de escaneo

| Modo | Descripción | Frecuencia | Tráfico |
|---|---|---|---|
| **Watch** | Lee tabla ARP del OS, sin pings | Cada 45s | ≈ 0 |
| **Periodic** | Ping sweep + fingerprint completo | Cada ~5min ±jitter | Bajo |
| **Manual** | Trigger desde UI | On-demand | Bajo |

### 5. Monitoreo SNMP
- Polling de switches gestionables cada 5 minutos (independiente del ARP scanner)
- Lee: estado de puertos, velocidad, CAM table (MAC por puerto), tráfico RX/TX
- Construye topología física: qué dispositivo está en qué puerto del switch
- Trigger on-demand desde la UI
- Soporta SNMPv1, v2c

### 6. WAN Monitor (Linux only)
- Lee `/proc/net/dev` para tráfico real de la interfaz WAN
- Detecta ruta default via `ip route show default`
- Mide latencia al gateway con ping (-c 3, calcula packet loss)
- Si el gateway es `192.168.100.x` → consulta API local del dish Starlink
- Emite `wan:update` via Socket.io cada 5 segundos
- Gráficos históricos en frontend (últimos 60 puntos ≈ 5 minutos)

### 7. Notificaciones Telegram
- Nuevo dispositivo detectado
- Dispositivo offline (con umbral configurable en minutos)
- MAC roaming (mismo MAC en otro puerto del switch)
- Resumen diario a las 09:00
- Circuit breaker: 3 fallos → 5 min cooldown
- Rate limit: 1 mensaje/segundo
- Deduplicación: no repite el mismo evento en 5 minutos
- Schedule horario configurable (con bypass para críticos)

### 8. Alertas en tiempo real
- Toast notifications en el browser via Socket.io
- `alert:offline` emitido cuando un dispositivo supera el umbral
- Deduplicación de 30 minutos por MAC

### 9. Mapa de topología
- Grafo interactivo con vis-network (local, sin CDN)
- Nodos jerárquicos: Subnets → Gateways → Switches → Dispositivos
- Iconos por tipo de dispositivo
- Click derecho: ping, detalle, WoL, copiar IP
- Aristas con info de puerto SNMP cuando está disponible

### 10. Network Tools
- **Ping** — single y con estadísticas (min/max/avg/loss)
- **Port scan** — TCP a puertos comunes
- **Traceroute** — con reverse DNS por hop
- **Geo-traceroute** — traceroute + geolocalización offline por hop
- **Speedtest** — descarga 10MB desde Cloudflare, mide throughput real

### 11. IPAM (IP Address Management)
- Vista de 254 slots por subred /24
- Estado online/offline por IP
- Pins con etiquetas y colores personalizados
- Múltiples subnets detectadas automáticamente

### 12. Power Management
- **Wake-on-LAN** — individual y masivo (por tipo, rango IP, IDs)
- **Shutdown/Reboot remoto** — via SSH
- Configuración centralizada de credenciales SSH
- Log de todas las acciones

### 13. Vault de credenciales
- Almacena credenciales SSH (usuario/contraseña/clave privada)
- Vinculación credencial ↔ dispositivo
- Contraseñas oscurecidas en la API

### 14. Exportación
- **CSV** — tabla de dispositivos filtrada
- **XLSX** — tabla con formato Excel
- **PDF** — inventario con estadísticas (usando `window.print`)

### 15. Autenticación
- Login con usuario/contraseña
- Sesiones con token HttpOnly cookie (TTL 8 horas, renovable)
- Hash HMAC-SHA256 con sal persistente
- Middleware `requireAuth` en todas las rutas API y páginas

---

## Base de datos

SQLite en `data/network.db` con modo WAL (Write-Ahead Logging) para mejor performance en lecturas concurrentes.

### Tablas principales

| Tabla | Descripción |
|---|---|
| `devices` | Inventario de dispositivos (MAC único) |
| `connections` | Conexión switch/puerto por dispositivo |
| `switches` | Switches SNMP configurados |
| `settings` | Configuración clave-valor |
| `scan_history` | Historial de escaneos |
| `device_events` | Historial rico de eventos por dispositivo |
| `uptime_events` | Eventos online/offline |
| `port_traffic` | Muestras de tráfico SNMP por puerto |
| `credentials` | Vault de credenciales SSH |
| `device_credentials` | Vinculación credencial ↔ dispositivo |
| `device_tags` | Tags extensibles por dispositivo |
| `power_config` | Configuración WoL/SSH |
| `power_log` | Log de acciones power |

---

## Modos de despliegue

### Desarrollo (Windows)
```bash
npm install
node server.js
# Acceder en http://localhost:3000
# WAN monitor deshabilitado (requiere Linux)
# ARP scanner usa ping sweep + arp -a
```

### Producción (Linux)
```bash
# Prerrequisitos
sudo apt install arp-scan avahi-utils net-tools
sudo chmod u+s $(which arp-scan)   # SUID para correr sin sudo
node src/utils/oui-update.js       # Genera oui-db.json (~39k entradas)

# Con PM2
pm2 start server.js --name netmonitor
pm2 startup && pm2 save

# Nginx reverse proxy
# proxy_pass http://127.0.0.1:3000
```

---

## Limitaciones operativas

1. **ARP no cruza routers** — Solo ve dispositivos en el mismo broadcast domain
2. **Sin VLANs** — Si la red no tiene inter-VLAN routing hacia el server, los otros segmentos son invisibles
3. **Fortigate upstream** — La app no puede controlar tráfico que pasa por un firewall que no gestiona
4. **Regulación de BW** — Requiere estar en el path del tráfico (gateway) o integración con switch L3 via API (MikroTik RouterOS API)
5. **VM con bridge** — En virtualización, siempre ve la red a través de la placa física del host
6. **WAN monitor** — Solo Linux (`/proc/net/dev`); en Windows muestra "Detectando WAN..."

---

## Posibilidades de extensión

- **MikroTik RouterOS API** (`node-routeros`) — ARP table completa, Simple Queues para limitar BW por IP, sin tocar el Fortigate
- **SSH terminal** — `ssh2` fue removido pero la infraestructura está preparada
- **Multi-site** — Múltiples instancias con Tailscale mesh
- **VLAN awareness** — Con switch L3 configurado, SNMP puede revelar VLANs
