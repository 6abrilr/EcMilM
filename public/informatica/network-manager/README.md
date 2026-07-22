# NetMonitor Stealth

> Monitor de red LAN pasivo, en tiempo real, con fingerprinting automático de dispositivos, alertas Telegram, topología visual y gestión remota — desplegable en cualquier servidor Linux en minutos.

![Node.js](https://img.shields.io/badge/Node.js-20%2B-339933?style=flat-square&logo=node.js)
![SQLite](https://img.shields.io/badge/SQLite-WAL-003B57?style=flat-square&logo=sqlite)
![Socket.io](https://img.shields.io/badge/Socket.io-4.x-010101?style=flat-square&logo=socket.io)
![License](https://img.shields.io/badge/License-MIT-blue?style=flat-square)

---

## ¿Qué hace?

NetMonitor Stealth descubre, identifica y monitorea todos los dispositivos conectados a tu red LAN — sin instalar agentes, sin configuración inicial, sin depender de servicios externos.

- **Descubrimiento automático** via ARP scan + fingerprinting (TTL, OUI, mDNS, NetBIOS)
- **39.000+ vendors** identificados desde la base oficial IEEE
- **Topología visual** interactiva con vis-network
- **Alertas en tiempo real** via Socket.io + Telegram
- **Monitor WAN** con latencia, throughput y soporte Starlink
- **Gestión remota** — Wake-on-LAN, SSH shutdown/reboot, SNMP polling
- **Zero traffic mode** — modo watch pasivo cada 45s sin generar tráfico

---

## Instalación rápida (Linux)

```bash
# 1. Clonar e instalar dependencias
git clone https://github.com/La-cueva-de-los-Snowden/Network-Manager-Mamalon.git
cd Network-Manager-Mamalon
npm install

# 2. Prerrequisitos del sistema
sudo apt install arp-scan avahi-utils net-tools
sudo chmod u+s $(which arp-scan)

# 3. Generar base de datos OUI (39k vendors IEEE)
node src/utils/oui-update.js

# 4. Levantar con PM2
npm install -g pm2
pm2 start server.js --name netmonitor
pm2 startup && pm2 save

# 5. Nginx reverse proxy (opcional)
# proxy_pass http://127.0.0.1:3000
```

Acceder en `http://localhost:3000` — usuario/contraseña default: `admin` / `admin`.

---

## Stack

| Capa | Tecnología |
|---|---|
| Backend | Node.js + Express.js |
| Base de datos | SQLite (better-sqlite3, WAL) |
| Tiempo real | Socket.io |
| Frontend | Vanilla JS ES6 |
| Topología | vis-network (local) |
| Gráficos | Chart.js |
| Geo IP | geoip-lite (offline) |
| SNMP | net-snmp |

---

## Documentación

| Documento | Descripción |
|---|---|
| [docs/OVERVIEW.md](docs/OVERVIEW.md) | Arquitectura, features, modos de operación y limitaciones |
| [docs/REFERENCE.md](docs/REFERENCE.md) | Referencia técnica backend — función por función |
| [docs/FRONTEND.md](docs/FRONTEND.md) | Clases y métodos del frontend |
| [docs/API.md](docs/API.md) | Rutas REST, payloads, respuestas y eventos Socket.io |

---

## Características principales

### Descubrimiento y fingerprinting
- ARP scan con `arp-scan` (Linux) o ping sweep + `arp -a` (Windows)
- Identificación de OS por TTL (Windows/Linux/Network Device/Embedded)
- Vendor lookup offline con 39k+ entradas IEEE
- Resolución de hostnames: mDNS, NetBIOS, reverse DNS, HTTP title, SSDP — en paralelo
- Detección automática de gateways, cámaras IP, IoT, VMs

### Monitoreo
- Modo **Watch**: lectura pasiva de tabla ARP cada 45s (tráfico ≈ 0)
- Modo **Periodic**: escaneo completo con fingerprinting cada ~5 min
- SNMP polling de switches: CAM table, tráfico por puerto, estado de interfaces
- Monitor WAN: RX/TX en tiempo real desde `/proc/net/dev`, latencia, packet loss

### Alertas
- Toast en browser via Socket.io
- Notificaciones Telegram con rate limiting y circuit breaker
- Umbral configurable de minutos offline
- Schedule horario (bypass para dispositivos críticos)

### Gestión
- Wake-on-LAN individual y masivo
- Shutdown/Reboot remoto via SSH
- Vault de credenciales SSH
- IPAM con pins y etiquetas por subred

### Exportación
- CSV, XLSX, PDF del inventario

---

## Limitaciones operativas

- ARP no cruza routers — solo ve el broadcast domain donde corre el servidor
- WAN monitor requiere Linux (`/proc/net/dev`)
- Regulación de BW requiere integración con switch L3 (MikroTik API, pendiente)
- En VM con bridge, el contexto de red es el del host físico

---

## Licencia

MIT
