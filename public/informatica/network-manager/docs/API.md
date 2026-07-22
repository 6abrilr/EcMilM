# NetMonitor Stealth — Referencia API

Todas las rutas requieren autenticación (cookie `nm_session`). Las rutas `/auth/*` son públicas.

Base URL: `http://localhost:3000`

---

## Autenticación

### `GET /auth/login`
Devuelve la página de login (HTML).

### `POST /auth/login`
```json
// Body
{ "username": "admin", "password": "contraseña" }

// Respuesta exitosa → redirect 302 a /
// Respuesta fallida → redirect 302 a /auth/login?error=1
```

### `POST /auth/logout`
Elimina la sesión. Redirect a `/auth/login`.

### `POST /auth/change-password`
```json
// Body
{ "currentPassword": "actual", "newPassword": "nueva" }

// Respuesta
{ "ok": true }
// o
{ "error": "Contraseña actual incorrecta" }
```

---

## Dispositivos

### `GET /api/devices`
Lista dispositivos con filtros opcionales.

Query params:
| Param | Tipo | Descripción |
|---|---|---|
| `status` | `online\|offline\|all` | Filtrar por estado |
| `vendor` | string | Filtrar por vendor exacto |
| `switch` | string | Filtrar por nombre de switch |
| `search` | string | Búsqueda por hostname/IP/MAC/vendor |

```json
// Respuesta
[
  {
    "id": 1,
    "hostname": "ras-server",
    "ip": "192.168.1.182",
    "mac": "08:00:27:3c:86:9c",
    "vendor": "VirtualBox",
    "device_type": "VM",
    "os": "Linux/Android/iOS",
    "ttl": 64,
    "is_online": 1,
    "is_critical": 0,
    "first_seen": "2026-04-06 12:00:00",
    "last_seen": "2026-04-06 17:30:00",
    "notes": "",
    "switch_name": null,
    "switch_ip": null,
    "port_name": null
  }
]
```

### `GET /api/devices/stats`
```json
{
  "total": 9,
  "online": 7,
  "offline": 2,
  "newToday": 3,
  "vendors": ["TP-Link", "Samsung", "Hikvision"],
  "switches": ["Core-01"]
}
```

### `GET /api/devices/:id`
Detalle completo incluyendo servicios, tags, credenciales vinculadas.

### `PUT /api/devices/:id`
```json
// Body (todos opcionales)
{
  "hostname": "mi-pc",
  "device_type": "PC Windows",
  "notes": "Equipo de gerencia",
  "is_critical": true,
  "switch_name": "Core-01",
  "switch_ip": "192.168.1.1",
  "port_name": "Gi0/1"
}
```

### `DELETE /api/devices/:id`
Elimina el dispositivo y todos sus datos relacionados.

### `DELETE /api/devices/bulk/offline`
Elimina todos los dispositivos con `is_online = 0`.

### `DELETE /api/devices/bulk/all`
Reset completo del inventario (devices, connections, services, tags, scan_history).

### `GET /api/devices/:id/geo`
```json
{
  "type": "private",
  "label": "Red Local",
  "ip": "192.168.1.182"
}
// o para IPs públicas:
{
  "type": "public",
  "country": "AR",
  "countryName": "Argentina",
  "flag": "🇦🇷",
  "city": "Buenos Aires",
  "isp": "Starlink",
  "ll": [-34.6, -58.4]
}
```

### `GET /api/devices/:id/events`
```json
// Query: ?limit=50
[
  {
    "id": 1,
    "event_type": "new_device",
    "detail": { "ip": "192.168.1.182", "vendor": "VirtualBox" },
    "ts": "2026-04-06 12:00:00"
  }
]
```

### `GET /api/devices/:id/uptime`
```json
// Query: ?limit=100
[
  { "event": "online",  "ts": "2026-04-06 09:00:00" },
  { "event": "offline", "ts": "2026-04-06 11:00:00" }
]
```

### `GET /api/devices/:id/bandwidth`
```json
{
  "rxBps": 1500000,
  "txBps": 500000,
  "rxFmt": "1.50 Mbps",
  "txFmt": "500.0 Kbps",
  "port": "Gi0/5",
  "switch": "Core-01"
}
```

---

## Escaneos

### `POST /api/scans`
Dispara escaneo manual. Retorna inmediatamente; el progreso llega via Socket.io.
```json
{ "ok": true, "message": "Escaneo iniciado" }
// o si ya hay uno en curso:
{ "ok": false, "message": "Ya hay un escaneo en progreso" }
```

### `GET /api/scans/status`
```json
{
  "mode": "watch",
  "scanning": false,
  "lastScan": "2026-04-06T17:30:00.000Z",
  "nextScan": "2026-04-06T17:35:00.000Z"
}
```

### `GET /api/scans/history`
```json
// Query: ?limit=20
[
  {
    "id": 1,
    "scan_time": "2026-04-06 17:30:00",
    "scan_type": "manual",
    "devices_found": 9,
    "devices_online": 7,
    "new_devices": 2,
    "duration_ms": 5497
  }
]
```

---

## Switches SNMP

### `GET /api/switches`
```json
[
  {
    "id": 1,
    "name": "Core-01",
    "ip": "192.168.88.102",
    "vendor": "MikroTik",
    "model": null,
    "snmp_community": "public",
    "snmp_version": "2c",
    "enabled": 1,
    "last_polled": "2026-04-06 17:25:00"
  }
]
```

### `POST /api/switches`
```json
// Body
{
  "name": "Core-01",
  "ip": "192.168.88.102",
  "community": "public",
  "version": "2c"
}
```

### `POST /api/switches/:id/poll`
Fuerza poll SNMP on-demand. Puede tardar varios segundos.
```json
{ "ok": true, "ports": 24, "macs": 18 }
```

### `GET /api/switches/:id/ports`
```json
{
  "switch": { "id": 1, "name": "Core-01", "ip": "192.168.88.102" },
  "ports": [
    {
      "index": 1,
      "name": "Gi0/1",
      "status": "up",
      "speedMbps": 1000,
      "rxBps": 150000,
      "txBps": 50000,
      "device": {
        "id": 3,
        "hostname": "workstation-01",
        "ip": "192.168.1.50",
        "mac": "aa:bb:cc:dd:ee:ff"
      }
    }
  ]
}
```

### `DELETE /api/switches/:id`
Elimina el switch y sus datos de tráfico histórico.

---

## Topología

### `GET /api/topology`
```json
{
  "nodes": [
    {
      "id": "aa:bb:cc:dd:ee:ff",
      "label": "_gateway",
      "group": "router",
      "level": 1,
      "title": "Gateway: 192.168.1.1\nVendor: TP-Link"
    },
    {
      "id": "net_192.168.1",
      "label": "192.168.1.0/24",
      "group": "subnet",
      "level": 0,
      "shape": "diamond"
    }
  ],
  "edges": [
    {
      "from": "net_192.168.1",
      "to": "aa:bb:cc:dd:ee:ff",
      "width": 2
    }
  ]
}
```

Grupos de nodos: `router`, `switch`, `computer`, `android`, `apple`, `printer`, `generic`, `subnet`.

---

## Acciones de red

### `POST /api/actions/ping`
```json
// Body
{ "ip": "192.168.1.1" }

// Respuesta
{ "alive": true, "latencyMs": 3.4, "ip": "192.168.1.1" }
```

### `POST /api/actions/ping-detail`
```json
// Body
{ "ip": "192.168.1.1" }

// Respuesta
{
  "ip": "192.168.1.1",
  "sent": 4,
  "received": 4,
  "loss": 0,
  "minMs": 2.1,
  "avgMs": 3.4,
  "maxMs": 5.2
}
```

### `POST /api/actions/portscan`
```json
// Body
{ "ip": "192.168.1.50" }

// Respuesta
{
  "ip": "192.168.1.50",
  "ports": [
    { "port": 22,  "open": true,  "service": "SSH" },
    { "port": 80,  "open": true,  "service": "HTTP" },
    { "port": 443, "open": false, "service": "HTTPS" }
  ]
}
```

### `POST /api/actions/traceroute`
```json
// Body
{ "target": "8.8.8.8" }

// Respuesta
{
  "target": "8.8.8.8",
  "hops": [
    { "hop": 1, "ip": "192.168.1.1", "latencyMs": 1.2, "hostname": "_gateway" },
    { "hop": 2, "ip": "100.65.0.1",  "latencyMs": 8.4, "hostname": null }
  ]
}
```

### `GET /api/actions/speedtest`
Descarga 10MB desde Cloudflare y mide throughput.
```json
{
  "downloadMbps": 45.3,
  "durationMs": 1780,
  "bytes": 10485760
}
```

### `POST /api/actions/wol`
```json
// Body
{ "mac": "aa:bb:cc:dd:ee:ff", "broadcast": "192.168.1.255" }

// Respuesta
{ "ok": true, "message": "Magic packet enviado" }
```

### `POST /api/actions/geo-traceroute`
```json
// Body
{ "target": "8.8.8.8" }

// Respuesta
{
  "hops": [
    {
      "hop": 1,
      "ip": "192.168.1.1",
      "latencyMs": 1.2,
      "hostname": "_gateway",
      "geo": null,
      "localDevice": { "hostname": "Starlink Router" }
    },
    {
      "hop": 3,
      "ip": "200.1.2.3",
      "latencyMs": 12.5,
      "hostname": "hop3.isp.net",
      "geo": {
        "country": "AR",
        "countryName": "Argentina",
        "flag": "🇦🇷",
        "city": "Buenos Aires"
      },
      "localDevice": null
    }
  ]
}
```

---

## Settings

### `GET /api/settings`
```json
{
  "telegram_enabled": "true",
  "telegram_token": "123:ABC...",
  "telegram_chat_id": "-100123456",
  "telegram_event_new_device": "true",
  "telegram_event_offline": "true",
  "telegram_event_roaming": "false",
  "telegram_schedule": "[{\"from\":\"09:00\",\"to\":\"20:00\"}]",
  "alert_offline_threshold": "5",
  "scan_interval": "300000"
}
```

### `POST /api/settings`
```json
// Body: cualquier subset de keys
{
  "telegram_enabled": "true",
  "alert_offline_threshold": "10"
}
// Respuesta
{ "ok": true }
```

### `POST /api/settings/test`
Envía mensaje de prueba a Telegram.
```json
{ "ok": true, "message": "Mensaje de prueba enviado" }
// o
{ "ok": false, "error": "Token inválido" }
```

---

## IPAM

### `GET /api/ipam/subnets`
```json
[
  {
    "prefix": "192.168.1",
    "total": 9,
    "online": 7,
    "offline": 2
  }
]
```

### `GET /api/ipam?subnet=192.168.1`
```json
{
  "subnet": "192.168.1",
  "slots": [
    {
      "host": 1,
      "ip": "192.168.1.1",
      "status": "online",
      "device": { "id": 1, "hostname": "_gateway", "mac": "...", "vendor": "TP-Link" },
      "pin": null
    },
    {
      "host": 50,
      "ip": "192.168.1.50",
      "status": "offline",
      "device": null,
      "pin": { "label": "Impresora piso 2", "color": "#f59e0b" }
    }
  ]
}
```

### `POST /api/ipam/pin`
```json
// Body
{ "ip": "192.168.1.50", "label": "Impresora piso 2", "color": "#f59e0b" }
```

### `DELETE /api/ipam/pin`
```json
// Body
{ "ip": "192.168.1.50" }
```

---

## Credenciales

### `GET /api/credentials`
```json
[
  {
    "id": 1,
    "name": "SSH Admin",
    "type": "ssh_password",
    "username": "admin",
    "password": "••••••••",
    "private_key": null,
    "created_at": "2026-04-06 10:00:00"
  }
]
```

### `POST /api/credentials`
```json
// Body
{
  "name": "SSH Admin",
  "type": "ssh_password",     // "ssh_password" | "ssh_key"
  "username": "admin",
  "password": "secreto",
  "private_key": null
}
```

### `POST /api/credentials/:id/link/:deviceId`
Vincula credencial a dispositivo. Sin body.

---

## Power Management

### `POST /api/power/wol/:id`
```json
// Body (opcional)
{ "broadcast": "192.168.1.255" }

// Respuesta
{ "ok": true, "mac": "aa:bb:cc:dd:ee:ff", "message": "Magic packet enviado" }
```

### `POST /api/power/wol/bulk`
```json
// Body
{
  "filter": {
    "type": "all",          // "all" | "type" | "range" | "ids"
    "device_type": "PC",    // si type="type"
    "ip_from": "192.168.1.10", // si type="range"
    "ip_to": "192.168.1.50",
    "ids": [1, 2, 3]        // si type="ids"
  }
}

// Respuesta
{ "ok": true, "sent": 5, "failed": 0 }
```

### `POST /api/power/shutdown/:id`
```json
// Body (opcional)
{ "delay": 0 }    // segundos antes de apagar

// Respuesta
{ "ok": true, "message": "Comando enviado" }
// o
{ "ok": false, "error": "SSH connection refused" }
```

### `GET /api/power/log`
```json
// Query: ?limit=50
[
  {
    "id": 1,
    "timestamp": "2026-04-06 15:00:00",
    "action": "wol",
    "device_name": "workstation-01",
    "ip": "192.168.1.50",
    "status": "success",
    "detail": "Magic packet enviado a aa:bb:cc:dd:ee:ff"
  }
]
```

---

## Ancho de banda

### `GET /api/bandwidth/summary`
```json
{
  "hasSwitches": true,
  "hasData": true,
  "updatedAt": "2026-04-06T17:30:00.000Z",
  "network": {
    "rxBps": 5000000,
    "txBps": 1500000,
    "totalBps": 6500000,
    "rxFmt": "5.00 Mbps",
    "txFmt": "1.50 Mbps",
    "totalFmt": "6.50 Mbps"
  },
  "switches": [
    {
      "id": 1,
      "name": "Core-01",
      "rxBps": 5000000,
      "txBps": 1500000,
      "rxFmt": "5.00 Mbps",
      "txFmt": "1.50 Mbps",
      "upPorts": 18,
      "totalPorts": 24,
      "utilizationPct": 65
    }
  ],
  "topConsumers": [
    {
      "id": 3,
      "hostname": "workstation-01",
      "ip": "192.168.1.50",
      "rxBps": 2000000,
      "txBps": 500000,
      "totalBps": 2500000,
      "rxFmt": "2.00 Mbps",
      "txFmt": "500.0 Kbps",
      "is_critical": 0
    }
  ],
  "allDevices": [ /* mismo formato que topConsumers */ ]
}
```

---

## Configuración

### `GET /api/config`
Devuelve configuración no sensible del servidor.
```json
{
  "host": "0.0.0.0",
  "port": 3000,
  "scanInterval": 300000,
  "snmpEnabled": true,
  "version": "1.0.0"
}
```

### `POST /api/config/reset`
Elimina: devices, connections, services, scan_history, device_tags, port_traffic, uptime_events, device_events. Preserva: settings, switches, credentials, power_config.
```json
{ "ok": true, "message": "Base de datos reseteada" }
```

---

## Eventos Socket.io

### Servidor → Cliente

#### `scan:log`
Línea de log durante escaneo activo.
```json
{
  "type": "info" | "success" | "error" | "warn",
  "msg": "arp-scan en enp0s3 (192.168.1.182/24)…",
  "ts": "17:30:45"
}
```

#### `scan:complete`
Fin del escaneo.
```json
{
  "found": 9,
  "online": 7,
  "new": 2,
  "offline": 0,
  "duration": 5497
}
```

#### `scan:mode`
Cambio de modo del scanner.
```json
{ "mode": "watch" | "periodic" | "scanning" }
```

#### `dashboard:refresh`
Sin payload. Indica al cliente que recargue la lista de dispositivos.

#### `alert:offline`
Dispositivo offline superó el umbral.
```json
{
  "device": {
    "id": 3,
    "hostname": "workstation-01",
    "ip": "192.168.1.50",
    "mac": "aa:bb:cc:dd:ee:ff"
  },
  "offlineMin": 12
}
```

#### `wan:update`
Actualización WAN cada 5 segundos (solo Linux).
```json
{
  "iface": "enp0s3",
  "gateway": "192.168.1.1",
  "rxBps": 36000,
  "txBps": 92000,
  "rxFmt": "36.0 Kbps",
  "txFmt": "92.0 Kbps",
  "latencyMs": 3.4,
  "packetLoss": 0,
  "isStarlink": false,
  "starlink": null,
  "allRoutes": [
    { "iface": "enp0s3", "gateway": "192.168.1.1", "metric": 100, "active": true }
  ],
  "updatedAt": "2026-04-06T17:30:05.000Z"
}
// Si es Starlink (gateway 192.168.100.x):
{
  "isStarlink": true,
  "starlink": {
    "uplinkThroughputBps": 5000000,
    "downlinkThroughputBps": 45000000,
    "popPingLatencyMs": 28.5,
    "obstructionPct": 0,
    "alerts": []
  }
}
// Si hay error:
{ "error": "No se detectó ruta WAN", "updatedAt": "..." }
```

### Cliente → Servidor

#### `set:mode`
Cambia el modo del scanner.
```json
{ "mode": "watch" | "periodic" }
```
