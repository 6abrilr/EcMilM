# NetMonitor Stealth — Referencia Técnica Backend

Documentación función por función de todos los módulos del servidor.

---

## src/auth/auth.js

Sistema de autenticación basado en sesiones con tokens HttpOnly.

### `createSession()`
Genera un nuevo token de sesión aleatorio (hex de 32 bytes) con TTL de 8 horas.
```js
// Retorna: { token: string, expiresAt: Date }
```

### `validateSession(token)`
Valida que el token exista y no haya expirado. Si es válido, extiende el TTL 8 horas más (sliding window).
```js
// token: string
// Retorna: boolean
```

### `destroySession(token)`
Elimina la sesión del mapa en memoria.

### `hashPassword(password, salt)`
Genera hash HMAC-SHA256 del password con la sal provista.
```js
// Retorna: string (hex)
```

### `getSalt()`
Lee la sal desde la tabla `settings` (clave `auth_salt`). Si no existe, genera una aleatoria y la persiste.

### `getStoredCredentials()`
Lee `auth_username` y `auth_password_hash` desde `settings`. Fallback a variables de entorno `NETMONITOR_USER` / `NETMONITOR_PASS`.

### `setCredentials(username, plainPassword)`
Hashea el password y persiste usuario + hash en `settings`.

### `verifyPassword(plainPassword)`
Compara el hash del password ingresado contra el almacenado.

### `getTokenFromRequest(req)`
Extrae el token de la cookie `nm_session` del request.

### `setSessionCookie(res, token)`
Setea cookie HttpOnly `nm_session` con el token. Secure en producción.

### `clearSessionCookie(res)`
Elimina la cookie de sesión (maxAge=0).

### `requireAuth(req, res, next)` ← **Middleware**
Verifica autenticación en cada request:
- Si es ruta de API (`/api/`): devuelve `401 Unauthorized` en JSON
- Si es GET de página: redirect a `/auth/login`
- Si está autenticado: llama `next()`

---

## src/db/database.js

Capa de acceso a SQLite. Usa `better-sqlite3` (síncrono).

### `getDb()`
Singleton que abre y retorna la conexión SQLite. Configura:
- `PRAGMA journal_mode = WAL` — mejor concurrencia
- `PRAGMA synchronous = NORMAL` — balance performance/durabilidad
- `PRAGMA foreign_keys = ON`
- `PRAGMA cache_size = -16000` — 16MB cache
Llama `initSchema()` y `runMigrations()` en la primera apertura.

### `initSchema()`
Crea todas las tablas e índices si no existen. Tablas:
`devices`, `connections`, `services`, `switches`, `scan_history`, `device_tags`, `settings`, `credentials`, `device_credentials`, `port_traffic`, `uptime_events`, `device_events`, `power_config`, `power_log`

### `runMigrations()`
Migra esquema legacy al actual. Usa `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` y `CREATE TABLE IF NOT EXISTS` para no romper instancias existentes. Versión de schema almacenada en `settings.db_schema_version`.

### `close()`
Cierra la conexión SQLite limpiamente.

---

## src/db/device-events.js

### `logEvent(deviceId, eventType, detail)`
Inserta un evento en `device_events`. Tipos de evento estándar:
- `new_device` — primer avistamiento
- `hostname_changed` — hostname actualizado
- `ip_changed` — IP cambió (mismo MAC)
- `port_changed` — cambió de puerto en el switch
- `note_updated` — nota editada manualmente
- `online` / `offline` — cambio de estado

`detail` es un objeto JS que se serializa a JSON. Nunca lanza excepción (try/catch interno).

---

## src/notifier/alert-manager.js

Gestiona alertas de dispositivos offline con deduplicación.

### `check(alertFn)`
Itera todos los dispositivos con `is_online = 0`. Para cada uno:
1. Calcula minutos offline desde `last_seen`
2. Lee umbral configurado en `settings.alert_offline_threshold` (default: 5 min)
3. Si supera el umbral y no fue alertado en los últimos 30 min → llama `alertFn(device, minutesOffline)`
4. Registra el MAC en el mapa de dedup con timestamp

### `clearAlert(mac)`
Limpia el registro de dedup para un MAC específico. Se llama cuando el dispositivo vuelve online.

### `clearAll()`
Vacía todo el estado de dedup. Útil al reiniciar el scheduler.

---

## src/notifier/telegram.js

Integración con Telegram Bot API con rate limiting y circuit breaker.

### Getters de configuración
- `getSetting(key, fallback)` — Lee de tabla `settings`
- `isEnabled()` — `telegram_enabled === 'true'`
- `getToken()` — Token del bot
- `getChatId()` — Chat ID destino
- `eventOn(key)` — Si el tipo de evento está habilitado (ej: `telegram_event_new_device`)

### `getScheduleGroups()`
Parsea ventanas horarias desde `settings.telegram_schedule`. Acepta JSON array `[{from:"09:00", to:"18:00"}]` o formato legacy string. Retorna array de `{from, to}`.

### `timeInWindow(from, to)`
Retorna `true` si la hora actual está dentro del rango HH:MM.

### `isWithinSchedule(isCritical)`
Si no hay schedule configurado → siempre verdadero. Los mensajes `critical` siempre pasan aunque estén fuera de schedule.

### `isDuplicate(key)`
Verifica si ya se envió un mensaje con esa clave en los últimos 5 minutos. Usa un Map en memoria.

### `_sendRaw(token, chatId, text)`
Envía directamente via HTTPS POST a `api.telegram.org`. Retorna `{ok, error}`.

### `_drain()`
Procesador de la cola de mensajes:
- Envía 1 mensaje cada 1000ms (rate limit)
- Si falla 3 veces consecutivas → circuit breaker, espera 5 minutos
- Se autollama con `setTimeout` mientras haya mensajes en cola

### `enqueue(msg, {critical})`
Agrega mensaje a la cola con validación previa:
1. Verifica que Telegram esté habilitado
2. Verifica schedule (o que sea crítico)
3. Verifica deduplicación
4. Agrega a la cola y activa `_drain()` si no está corriendo

### `notifyNewDevice(device)`
Formato: `🆕 Nuevo dispositivo: {hostname} ({ip}) MAC: {mac} Vendor: {vendor}`

### `notifyDeviceOffline(device)`
Formato: `🔴 Dispositivo offline: {hostname} ({ip}) Offline hace {min} min`

### `notifyMacRoaming(device, oldPort, newPort, switchName)`
Formato: `🔀 MAC Roaming: {hostname} ({mac}) se movió del puerto {old} al {new} en {switch}`

### `sendDailySummary(stats)`
Formato multi-línea con: total dispositivos, online/offline, nuevos hoy, uptime promedio.

---

## src/scanner/scheduler.js

Orquestador central de todos los procesos de escaneo.

### `emitLog(type, msg)`
Emite evento `scan:log` via Socket.io con `{type, msg, ts}`. Tipos: `info`, `success`, `error`, `warn`.

### `emitMode(newMode)`
Actualiza la variable `currentMode` y emite `scan:mode` con el nuevo modo. Modos: `watch`, `periodic`, `scanning`.

### `withJitter(intervalMs)`
Retorna `intervalMs ± (random * 30000)` para evitar thundering herd en scans periódicos.

### `runArpScan()`
Escaneo ARP completo:
1. Emite modo `scanning`
2. Llama `arpScanner.scan(emitLog)`
3. Procesa alertas offline via `alertManager.check()`
4. Emite `dashboard:refresh` a todos los clientes
5. Guarda en `scan_history`
6. Emite modo previo al terminar

### `runQuickCheck()`
Lectura rápida de tabla ARP (modo watch, ~45s):
1. Llama `arpScanner.quickScan(emitLog)`
2. Limpia dedup de alertas para dispositivos que volvieron online
3. Verifica umbrales offline via `alertManager.check()`
4. Si hay cambios → emite `dashboard:refresh`

### `startSnmpPolling()`
Inicia `setInterval` de 5 minutos que llama `snmpClient.scanAllSwitches()`. Independiente del ARP scanner.

### `startWatching()`
Activa modo watch: `setInterval(runQuickCheck, 45000)`.

### `stopWatching()`
Limpia el timer de watch mode.

### `scheduleNext()`
Programa el próximo scan periódico usando `setTimeout` con jitter. Lee el intervalo de `settings.scan_interval` (default: 300000ms).

### `setMode(newMode)`
Cambia entre `'watch'` y `'periodic'`. Inicia/detiene los timers correspondientes.

### `scheduleDailySummary()`
Calcula ms hasta las 09:00 local del día siguiente y usa `setTimeout`. Al disparar, consulta stats y llama `telegram.sendDailySummary()`.

### `start(ioInstance)`
Punto de entrada del scheduler:
1. Guarda referencia a Socket.io
2. Ejecuta primer scan inmediatamente
3. Activa modo watch
4. Inicia SNMP polling
5. Programa resumen diario

### `stop()`
Limpia todos los timers (watch, periodic, SNMP, daily summary).

### `triggerManualScan()`
Si no hay scan en curso → ejecuta `runArpScan()`. Si hay uno → retorna `false`.

### `getStatus()`
```js
// Retorna:
{
  mode: 'watch' | 'periodic' | 'scanning',
  scanning: boolean,
  lastScan: Date | null,
  nextScan: Date | null
}
```

---

## src/scanner/arp-scanner.js

Core del descubrimiento de dispositivos.

### `isVpnAddr(ifaceName, ip)`
Retorna `true` si la interfaz es VPN/VM. Filtra por nombre (regex: tailscale, vmware, virtualbox, tun, etc.) o por prefijo IP (100.x, 172.19-31.x, etc.).

### `getLocalSubnets()`
Lee `os.networkInterfaces()`, filtra las IPv4 no-internas y no-VPN, calcula CIDR desde netmask. Retorna array de `{ip, netmask, cidr, prefix, interface}`.

### `netmaskToCidr(mask)`
Convierte `"255.255.255.0"` → `24`. Cuenta bits a 1 en la representación binaria.

### `buildIpRange(ip, cidr)`
Genera array de todas las IPs de una subred dado IP+CIDR. Usado para ping sweep.

### `pingSweep(subnet)`
Envía pings a todas las IPs del subnet con concurrencia máxima de 50. Usa `exec('ping -c 1 -W 1 {ip}')`. No procesa respuestas — solo popula la tabla ARP del OS.

### `parseArpTable(output)`
Parsea la salida de `arp -a` en Windows. Formato: `Interface: ... Internet Address ... Physical Address`. Retorna array de `{ip, mac, type, interface}`.

### `parseArpScanOutput(output)`
Parsea la salida de `arp-scan --localnet` en Linux. Formato: `ip\tmac\tvendor`. Filtra broadcast, multicast, y MACs inválidas. Deduplica por MAC.

### `appendLocalInterfaces(entries)`
Agrega las interfaces del propio host al array de entries, marcadas como `is_local_host: true`.

### `quickScan(emitFn)`
Modo watch (rápido):
1. En Linux: ejecuta `arp-scan` directo
2. En Windows: ejecuta `arp -a`
3. Compara MACs encontradas con `is_online` en DB
4. Actualiza `is_online` y `last_seen`
5. Retorna `{events: [{type:'online'|'offline', device}], onlineCount}`

### `scan(emitFn)`
Escaneo completo:
1. Detecta subnets locales
2. En Linux: ping sweep asíncrono + `arp-scan --localnet`; en Windows: ping sweep + `arp -a`
3. Para cada dispositivo encontrado (en batches de 5):
   - OUI lookup del vendor
   - Fingerprinting completo (TTL, OS, hostname, tipo)
   - Hostname resolver (mDNS, DNS, HTTP, SSDP)
4. Upsert en `devices`:
   - Si existe: actualiza IP, last_seen, hostname (si mejoró), vendor, os, device_type
   - Si no existe: INSERT con todos los campos
5. Marca offline los dispositivos no vistos
6. Retorna stats `{found, online, new, offline}`

---

## src/scanner/snmp-client.js

Cliente SNMP para switches gestionables.

### `snmpWalk(session, oid)`
Ejecuta SNMP walk sobre un OID. Retorna Promise con array de varbinds. Maneja errores de timeout y cierre de sesión.

### `snmpGet(session, oids)`
Ejecuta SNMP get para múltiples OIDs. Retorna objeto `{oid: value}`.

### `readUint(value)`
Parsea valores SNMP que pueden ser Buffer o número. Necesario porque `net-snmp` retorna Counter32/Gauge como Buffer.

### `fmtSpeed(bps)`
Formatea velocidad: `1000000000` → `"1G"`, `100000000` → `"100M"`, etc.

### `getSwitchTopology(ip, community, version)`
Lee completa información del switch via SNMP:
- `ifDescr` (IF-MIB) → nombre de interfaz
- `ifOperStatus` → estado up/down
- `ifHighSpeed` / `ifSpeed` → velocidad en Mbps/bps
- `dot1dTpFdbAddress` + `dot1dTpFdbPort` (BRIDGE-MIB) → tabla CAM (MAC por puerto)
- `dot1dBasePortIfIndex` → mapeo puerto bridge → ifIndex
- `ifInOctets` / `ifOutOctets` (o `ifHCInOctets` para 64-bit) → tráfico
Retorna objeto con `ports[]` y `macTable[]`.

### `_applyTopology(sw, topology, db)`
Persiste en SQLite los datos del switch:
1. Actualiza `connections` (qué device_id está en qué puerto)
2. Inserta muestra en `port_traffic` con los octetos actuales
3. Emite evento `port_changed` si el dispositivo cambió de puerto

### `scanAllSwitches()`
Lee todos los switches con `enabled = 1` desde DB, llama `scanSwitch` para cada uno. Captura errores por switch individualmente.

### `scanSwitch(switchId)`
Scan completo de un switch:
1. Lee config del switch desde DB
2. Abre sesión SNMP
3. Llama `getSwitchTopology()`
4. Llama `_applyTopology()` para persistir
5. Actualiza `last_polled` del switch
6. Cierra sesión SNMP

---

## src/scanner/fingerprinter.js

Identificación de OS, vendor y tipo de dispositivo.

### `normalizeVendor(raw)`
Convierte nombres IEEE largos a nombres cortos legibles.
```
"TP-LINK TECHNOLOGIES CO., LTD." → "TP-Link"
"Hangzhou Hikvision Digital Technology Co." → "Hikvision"
"SAMSUNG ELECTRO-MECHANICS(THAILAND)" → "Samsung"
```
Si no hay match en los ~30 casos conocidos, retorna la primera palabra del string original.

### `getTTL(ip)`
Ejecuta `ping -c 1 -W 1 {ip}` (Linux) o `ping -n 1 -w 1000 {ip}` (Windows).
Parsea la línea `ttl=XX` o `Duración de vida=XX` (Windows ES). Retorna número o `null`.

### `identifyOS(ttl)`
Mapea TTL a OS:
| TTL | OS | Family |
|---|---|---|
| 120-128 | Windows | windows |
| 60-64 | Linux/Android/iOS | unix |
| 240-255 | Network Device | network |
| 30-32 | Embedded | embedded |

### `getNetBIOSName(ip)`
- **Windows**: `nbtstat -A {ip}` → busca línea `HOSTNAME <00> UNIQUE`
- **Linux**: `nmblookup -A {ip}` → busca línea `HOSTNAME <00> - B <ACTIVE>`
Retorna hostname o string vacío.

### `inferDeviceType(vendorRaw, osFamily)`
Recorre `VENDOR_RULES` (array de `{match, type, icon}`) buscando substring del vendor raw en minúsculas. Reglas especiales para Apple (unix=iPhone/iPad, windows=Boot Camp, network=AirPort). Fallback por `osFamily`.

### `getReverseDNS(ip)`
Ejecuta `nslookup {ip}`, parsea línea `name = hostname`. Limpia trailing dot y sufijo `.local`. Retorna primer label del hostname o string vacío.

### `getMdnsName(ip)`
Ejecuta `avahi-resolve -a {ip}` (Linux only). Parsea respuesta `IP hostname.local`. Retorna hostname sin `.local`.

### `fingerprint(ip, vendorRaw)`
Función principal — combina todas las técnicas:
1. TTL → OS + osFamily
2. En paralelo: NetBIOS (si Windows), reverse DNS, mDNS
3. Normaliza vendor
4. Infiere tipo de dispositivo
5. Override: si IP es `.1`/`.254` AND (vendor de red OR TTL de network OR tipo genérico) → "Router/Gateway"
```js
// Retorna:
{
  os: string,           // "Windows", "Linux/Android/iOS", etc.
  osFamily: string,     // "windows", "unix", "network", "embedded"
  ttl: number | null,
  hostname: string,     // mejor hostname encontrado
  vendor: string,       // vendor normalizado
  deviceType: string,   // "Router/Gateway", "Cámara IP", "PC Windows", etc.
  deviceIcon: string    // emoji
}
```

---

## src/scanner/hostname-resolver.js

Resolución de hostnames en paralelo por múltiples técnicas.

### `fromCache(ip)` / `toCache(ip, hostname)`
Caché en Map con TTL de 1 hora. Evita resolver el mismo IP repetidamente.

### `withTimeout(promise, ms)`
Wrapper que rechaza la promesa si no resuelve en `ms` milisegundos.

### `firstTruthy(promises)`
Ejecuta todas las promesas en paralelo y retorna el primer resultado no-vacío. Similar a `Promise.race` pero ignorando fallos y strings vacíos.

### `_buildPtrQuery(ip)` / `_readDnsName(buf, offset)` / `_parsePtrResponse(buf)`
Helpers internos para construir y parsear consultas DNS PTR manualmente via UDP (sin depender del resolver del sistema).

### `queryMdns(ip)`
Envía query mDNS PTR al grupo multicast `224.0.0.251:5353`. Espera respuesta por 1.5 segundos. Retorna hostname `.local` sin el sufijo.

### `_fetchFriendlyName(url)`
HTTP GET a la URL, extrae el contenido de `<title>`. Limpia el string (elimina "- Admin", "Router", etc.).

### `querySsdp(ip)`
Envía `M-SEARCH` SSDP a `{ip}:1900`. Parsea header `LOCATION` de la respuesta, hace GET al XML descriptor, extrae `<friendlyName>`.

### `queryDnsReverse(ip)`
Reverse DNS usando el resolver del sistema via `dns.reverse()`.

### `queryHttpTitle(ip)`
Intenta `http://{ip}` y `http://{ip}:8080`. Retorna el primer título HTTP no-vacío.

### `resolve(ip, osFamily)`
Función principal. Usa `firstTruthy()` con todas las técnicas en paralelo:
- mDNS (timeout: 1.5s)
- DNS reverso (timeout: 2s)
- HTTP title (timeout: 3s) — solo si no es Windows (evita intentos innecesarios)
- SSDP (timeout: 2s) — solo en unix/network devices
Guarda resultado en caché. Retorna el mejor hostname o string vacío.

### `clearCache()`
Vacía el Map de caché.

---

## src/scanner/wan-monitor.js

Monitoreo de conectividad WAN en tiempo real. **Solo funciona en Linux.**

### `readProcNetDev()`
Lee `/proc/net/dev`. Parsea cada línea para extraer bytes recibidos (`rxBytes`) y transmitidos (`txBytes`) por interfaz. Retorna objeto `{ifaceName: {rxBytes, txBytes}}`.

### `detectWanRoutes()`
Ejecuta `ip route show default`. Parsea cada línea buscando `dev {iface}`, `via {gateway}`, `metric {n}`. Retorna array ordenado por métrica (primero = WAN primaria).

### `pingGateway(gateway)`
Ejecuta `ping -c 3 -W 1 {gateway}`. Parsea:
- Línea RTT: `min/avg/max/mdev = X/X/X/X ms` → toma el promedio
- Línea de pérdida: `X% packet loss`
Retorna `{latencyMs, packetLoss}`.

### `queryStarlink()`
POST a `http://192.168.100.1:9201/SpaceX.API.Device.Device/Handle` con body `{getDeviceInfo: {}}`. Extrae de la respuesta JSON:
- `uplinkThroughputBps` / `downlinkThroughputBps`
- `popPingLatencyMs`
- `obstructionStats.fractionObstructed`
- `alerts` (keys con valor `true`)
Retorna `null` si no es Starlink o no responde.

### `isStarlinkGateway(gateway)`
Retorna `true` si el gateway comienza con `192.168.100.`.

### `fmtBps(bps)`
Formatea bps a string legible: `≥1e9` → Gbps, `≥1e6` → Mbps, `≥1e3` → Kbps.

### `poll()` / `_poll()`
Ciclo principal (cada 5 segundos):
1. Detecta rutas WAN
2. Lee `/proc/net/dev`
3. Calcula delta RX/TX desde muestra anterior (bps = bytes_delta * 8 / segundos)
4. Corre ping + Starlink en paralelo
5. Emite `wan:update` con payload completo

### `start(ioInstance)`
Guarda referencia Socket.io, ejecuta primer poll inmediato, inicia `setInterval(poll, 5000)`.

### `stop()`
Limpia el interval.

---

## src/utils/oui-lookup.js

Lookup de vendor por MAC address. Base de datos offline.

### `getDb()`
Singleton. Intenta cargar `oui-db.json` (generado por `oui-update.js`). Si no existe, usa `OUI_FALLBACK` (tabla mínima de ~20 vendors comunes). Loguea en consola la cantidad de entradas cargadas.

### `lookup(mac)`
Extrae los primeros 8 caracteres del MAC (`xx:xx:xx`), los busca en el Map. Retorna nombre del vendor o string vacío.

---

## src/utils/oui-update.js

Script de mantenimiento para regenerar la base OUI.

### `fetchCsv(url)`
Descarga el CSV desde la URL con soporte para redirects y descompresión gzip. Retorna el contenido como string.

### `parseCsv(csv)`
Parsea el formato CSV de IEEE:
```
Registry,Assignment,Organization Name,Organization Address
MA-L,AABBCC,Vendor Name,...
```
Convierte `AABBCC` → `aa:bb:cc`. Retorna objeto `{prefix: vendor}`.

### `main()`
Descarga, parsea y escribe `oui-db.json`. Reporta cantidad de entradas generadas.
```bash
node src/utils/oui-update.js
# [OUI] Generado oui-db.json con 39178 entradas
```

---

## src/scanner/vendor-profiles.js

Perfiles SNMP por fabricante. Define qué OIDs usar según el vendor del switch para obtener datos más precisos. Usado por `snmp-client.js` para adaptar las queries según el hardware.

---

## src/scanner/dns-resolver.js

Caché ligero de reverse DNS con TTL configurable. Wrapper sobre `dns.reverse()` del módulo nativo de Node.js con deduplicación de requests en vuelo (no hace dos lookups del mismo IP simultáneamente).
