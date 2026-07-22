# NetMonitor Stealth — Referencia Frontend

Documentación de todas las clases y métodos del frontend (Vanilla JS ES6).

La SPA se compone de módulos especializados que se instancian en `app.js` y se comunican entre sí via referencias directas y eventos del DOM.

---

## Arquitectura del frontend

```
App (app.js)
 ├── Api          — cliente HTTP centralizado
 ├── TableManager — tabla de dispositivos
 ├── DetailPanel  — panel lateral de detalles
 ├── ModalManager — todos los modales
 ├── ChartManager — gráficos Chart.js
 ├── BandwidthPanel — widget WAN/SNMP
 ├── ToastManager — notificaciones toast
 └── socket.io    — eventos en tiempo real
```

Todos los módulos son clases ES6 con constructor explícito. No hay bundler — se cargan como `<script>` en orden en `index.html`.

---

## public/js/api.js — clase `Api`

Cliente HTTP centralizado. Todas las llamadas al backend pasan por aquí.

### `_fetch(path, options)`
Helper privado base. Hace `fetch()` con:
- Headers `Content-Type: application/json`
- Manejo de errores HTTP (lanza Error con el mensaje del server)
- Parseo automático de JSON

### Dispositivos
- `getDevices(filters)` — GET `/api/devices` con query params opcionales (status, vendor, switch, search)
- `getDevice(id)` — GET `/api/devices/:id`
- `updateDevice(id, data)` — PUT `/api/devices/:id`
- `deleteDevice(id)` — DELETE `/api/devices/:id`
- `deleteOfflineDevices()` — DELETE `/api/devices/bulk/offline`
- `getDeviceGeo(id)` — GET `/api/devices/:id/geo`
- `getDeviceEvents(id, limit)` — GET `/api/devices/:id/events`
- `getDeviceUptime(id, limit)` — GET `/api/devices/:id/uptime`
- `getDeviceBandwidth(id)` — GET `/api/devices/:id/bandwidth`

### Escaneos
- `triggerScan()` — POST `/api/scans`
- `getScanStatus()` — GET `/api/scans/status`
- `getScanHistory(limit)` — GET `/api/scans/history`

### Topología
- `getTopology()` — GET `/api/topology`

### Switches
- `getSwitches()` — GET `/api/switches`
- `createSwitch(data)` — POST `/api/switches`
- `getSwitchPorts(id)` — GET `/api/switches/:id/ports`
- `getSwitchTraffic(id)` — GET `/api/switches/:id/traffic`
- `pollSwitch(id)` — POST `/api/switches/:id/poll`
- `deleteSwitch(id)` — DELETE `/api/switches/:id`

### Acciones de red
- `pingDevice(ip)` — POST `/api/actions/ping`
- `pingDetail(ip)` — POST `/api/actions/ping-detail`
- `portScan(ip)` — POST `/api/actions/portscan`
- `traceroute(ip)` — POST `/api/actions/traceroute`
- `speedTest()` — GET `/api/actions/speedtest`
- `geoTraceroute(target)` — POST `/api/actions/geo-traceroute`
- `wakeDevice(mac)` — POST `/api/actions/wol`

### Ancho de banda
- `getBandwidthSummary()` — GET `/api/bandwidth/summary`

### Settings
- `getSettings()` — GET `/api/settings`
- `saveSettings(data)` — POST `/api/settings`
- `testTelegram()` — POST `/api/settings/test`
- `getConfig()` — GET `/api/config`
- `resetDatabase()` — POST `/api/config/reset`

### Power management
- `wolDevice(id, options)` — POST `/api/power/wol/:id`
- `wolBulk(filter)` — POST `/api/power/wol/bulk`
- `shutdownDevice(id, options)` — POST `/api/power/shutdown/:id`
- `rebootDevice(id, options)` — POST `/api/power/reboot/:id`
- `shutdownBulk(filter)` — POST `/api/power/shutdown/bulk`
- `rebootBulk(filter)` — POST `/api/power/reboot/bulk`
- `getPowerConfig()` — GET `/api/power/config`
- `savePowerConfig(data)` — PUT `/api/power/config`
- `getPowerLog(limit)` — GET `/api/power/log`

### IPAM
- `getIpamSubnets()` — GET `/api/ipam/subnets`
- `getIpamData(subnet)` — GET `/api/ipam?subnet=X`
- `getIpamPinned()` — GET `/api/ipam/pinned`
- `pinIpam(ip, label, color)` — POST `/api/ipam/pin`
- `unpinIpam(ip)` — DELETE `/api/ipam/pin`

### Credenciales
- `getCredentials()` — GET `/api/credentials`
- `createCred(data)` — POST `/api/credentials`
- `updateCred(id, data)` — PUT `/api/credentials/:id`
- `deleteCred(id)` — DELETE `/api/credentials/:id`
- `linkCred(credId, deviceId)` — POST `/api/credentials/:id/link/:deviceId`
- `unlinkCred(credId, deviceId)` — DELETE `/api/credentials/:id/link/:deviceId`

### Auth
- `changePassword(data)` — POST `/auth/change-password`
- `logout()` — POST `/auth/logout`

---

## public/js/app.js — clase `App`

Controlador principal de la SPA. Instancia todos los módulos y coordina el flujo de datos.

### `init()`
Bootstrap de la aplicación:
1. Crea instancias de todos los módulos (Api, TableManager, DetailPanel, etc.)
2. Conecta Socket.io
3. Registra listeners de socket
4. Registra event delegation del DOM
5. Carga dashboard inicial

### `_bindSocketEvents()`
Registra todos los listeners de Socket.io:
- `scan:log` → agrega línea a la consola de escaneo en vivo
- `scan:complete` → cierra consola, actualiza stats, refresca tabla
- `scan:mode` → actualiza el botón y label de modo en el navbar
- `dashboard:refresh` → recarga dispositivos y stats
- `alert:offline` → muestra toast rojo + resalta fila en la tabla

### `_appendScanLog(msg, type, ts)`
Agrega una línea al panel de consola de escaneo (visible durante scans manuales). Aplica color según tipo: `success`=verde, `error`=rojo, `warn`=amarillo, `info`=cyan. Auto-scroll al final.

### `_hideScanConsole()`
Oculta el panel de consola con animación fade-out tras 3 segundos del fin del scan.

### `_applyModeUI(mode)`
Actualiza el botón de toggle modo y los labels según el modo activo:
- `watch` → ícono 👁, tooltip "Modo Watch activo"
- `periodic` → ícono 🕐, tooltip "Modo periódico"
- `scanning` → ícono spinner, botón deshabilitado

### `_bindEvents()`
Event delegation global en `document`. Captura clicks en `[data-action]`:
| data-action | Método llamado |
|---|---|
| `scan` | `triggerScan()` |
| `openSettings` | `openSettings()` |
| `openSwitches` | `openSwitches()` |
| `openTopology` | `openTopology()` |
| `openIPAM` | `openIPAM()` |
| `openVault` | `openVault()` |
| `openNetworkTools` | `openNetworkTools()` |
| `openScanHistory` | `openScanHistory()` |
| `openPowerPanel` | `openPowerPanel()` |
| `exportCSV` | `exportCSV()` |
| `exportXLSX` | `exportXLSX()` |
| `exportPDF` | `exportPDF()` |
| `resetDb` | `resetDb()` |
| `logout` | `logout()` |
| `toggleScanMode` | `toggleScanMode()` |
| `closeModal` | `closeModal()` |
| `closeDetail` | `closeDetail()` |

### `refreshDashboard()`
Secuencia de carga del dashboard:
1. `api.getDevices()` → pasa a `tableManager.renderTable()`
2. `api.getDevices({stats:true})` → actualiza contadores (total, online, offline, nuevos)
3. `_populateFilterDropdowns()` → llena selects de vendor/switch
4. `_applyFilters()` → aplica filtros activos

### `_populateFilterDropdowns()`
Lee los dispositivos actuales, extrae vendors y switches únicos, actualiza los `<select>` de filtro sin perder la selección actual.

### `_applyFilters()`
Lee los valores actuales de búsqueda, status, vendor y switch. Llama a los métodos de `tableManager` para filtrar la vista.

### `triggerScan()`
Muestra la consola de escaneo, deshabilita el botón, llama `api.triggerScan()`. El resultado llega via socket `scan:complete`.

### `exportXLSX()` / `exportCSV()`
Lee los dispositivos filtrados actuales de `tableManager`, construye la hoja/CSV, dispara la descarga.

### `exportPDF()`
Llama a `PdfExport.exportInventory(devices, stats)` que genera HTML auto-contenido y llama `window.print()`.

### `resetDb()`
Muestra confirmación. Si confirma: `api.resetDatabase()` + `refreshDashboard()`.

### `toggleScanMode()`
Lee el modo actual del status, alterna entre `watch` y `periodic`, emite `set:mode` via socket.

---

## public/js/table.js — clase `TableManager`

Gestiona la tabla principal de dispositivos.

### `renderTable(devices)`
Genera HTML de todas las filas. Cada fila tiene:
- Indicador de estado (punto verde/rojo animado)
- Hostname + MAC
- IP (clickeable para copiar)
- Tipo de dispositivo (badge coloreado según tipo)
- OS
- Switch:Puerto
- Último visto (tiempo relativo)
- Botón de editar

### `search(term)`
Filtra filas por substring en: hostname, IP, MAC, vendor, notas. Case-insensitive.

### `filterByStatus(status)`
Muestra solo `online`, `offline`, o `all`.

### `filterByVendor(vendor)` / `filterBySwitch(switchName)`
Filtra por coincidencia exacta del campo correspondiente.

### `sortBy(column)`
Ordena la tabla por la columna indicada. Toggle ASC/DESC en clicks sucesivos.

### `selectRow(mac)`
Resalta la fila del dispositivo con ese MAC y abre el `DetailPanel`.

### `highlightRow(mac)`
Aplica clase `flash-alert` a la fila (animación de parpadeo rojo para alertas offline).

---

## public/js/detail.js — clase `DetailPanel`

Panel lateral derecho con información detallada de un dispositivo.

### `open(device)`
Abre el panel con la info del dispositivo. Llama a todos los métodos `_render*()` en paralelo para cargar las distintas secciones.

### `close()`
Cierra el panel con animación slide-out.

### `_renderDevice()`
Sección superior: hostname, IP, MAC, vendor, tipo, OS, TTL, primera vez visto, último visto, notas.

### `_renderConnection()`
Sección "Conexión Física": switch, IP del switch, puerto, VLAN, velocidad, duplex.

### `_renderGeo()`
Sección "Ubicación IP": llama `api.getDeviceGeo(id)`. Muestra bandera, país, ciudad, ISP, coordenadas. Para IPs privadas muestra "Red Local".

### `_renderSystem()`
OS, tipo de dispositivo, TTL.

### `_renderUptime()`
Sección "Disponibilidad 24H": 48 slots de 30 minutos cada uno. Verde = online, rojo = offline, gris = sin datos. Muestra % de uptime.

### `_renderBandwidth()`
Sección "Tráfico": llama `api.getDeviceBandwidth(id)`. Muestra RX/TX del puerto SNMP. Solo disponible si el dispositivo tiene conexión a switch configurado.

### `_renderEvents()`
Sección "Historial de Eventos": lista cronológica de eventos con ícono, descripción y timestamp relativo.

### `_renderTags()`
Sección "Tags": tags editables clave-valor. Botón para agregar/eliminar.

### Edición inline
Los campos hostname, device_type y notes son editables inline con doble click. Al perder el foco o presionar Enter → llama `api.updateDevice()` y actualiza la vista.

### Diagnóstico
Botones: Ping, Port Scan, Traceroute, Speedtest. Cada uno llama el endpoint correspondiente y muestra el resultado en un panel expandible dentro del detalle.

---

## public/js/modal.js — clase `ModalManager`

Gestiona todos los modales de la aplicación. Un solo modal activo a la vez.

### `open(type, data)` / `close()`
Abre/cierra el modal del tipo indicado. Aplica animación fade+scale.

### Modal: Settings (`openSettings()`)
Tabs: General, Telegram, Alertas, Power, Cuenta.
- **General**: intervalo de scan, modo por defecto
- **Telegram**: token, chat ID, eventos habilitados, schedule horario
- **Alertas**: umbral offline en minutos
- **Power**: usuario SSH, puerto, dirección broadcast WoL
- **Cuenta**: cambio de contraseña, logout

### Modal: Switches SNMP (`openSwitches()`)
Tabla de switches configurados con: nombre, IP, community, vendor, último poll, estado.
- Formulario para agregar switch (nombre, IP, community)
- Botón Poll on-demand por switch
- Vista de puertos: tabla con estado, velocidad, tráfico, dispositivo conectado
- Eliminar switch

### Modal: IPAM (`openIpam()`)
Grid de 254 slots (IPs .1 a .254) para la subnet seleccionada.
- Verde = online, rojo = offline, gris = no visto
- Click en slot → detalle del dispositivo o formulario de pin
- Pins con etiqueta y color custom

### Modal: Vault de credenciales (`openVault()`)
CRUD de credenciales SSH:
- Tipo: usuario/contraseña o clave privada
- Lista con contraseñas oscurecidas
- Vinculación a dispositivos del inventario

### Modal: Network Tools (`openNetworkTools()`)
Tabs: Ping, Port Scan, Traceroute, Geo-Traceroute, Speedtest.
Cada tab tiene formulario de input (IP/hostname) y área de resultado.

### Modal: Historial de escaneos (`openScanHistory()`)
Tabla de últimos N scans con: fecha, tipo, duración, dispositivos encontrados/online/nuevos.
Mini gráfico de línea (Chart.js) con evolución de online/total a lo largo del tiempo.

### Modal: Power Management (`openPower()`)
Tabs: WoL, Shutdown/Reboot.
- **WoL**: selector de dispositivos (individual o bulk por tipo/rango), botón enviar magic packet
- **Shutdown/Reboot**: selector con confirmación, log de acciones

### Modal: Topología (`openTopology()`)
Canvas full-size con grafo vis-network:
- Nodos: subnets (diamante), gateways (ícono router), switches (ícono switch), dispositivos
- Edges: conexiones físicas (SNMP) en línea sólida, lógicas en punteado
- Botones de zoom, fit, layout
- Click en nodo → abre `DetailPanel` del dispositivo
- Click derecho → `ContextMenu` con acciones rápidas

---

## public/js/bandwidth.js — clase `BandwidthPanel`

Widget de ancho de banda en el dashboard principal.

### `start(socket)`
Inicia polling HTTP cada 30s para datos SNMP + listener de Socket.io `wan:update` para datos WAN en tiempo real. Registra botón de refresh manual.

### `stop()`
Limpia el interval de polling.

### `refresh()`
Fuerza recarga de `api.getBandwidthSummary()` y renderiza.

### `_render(data)`
Renderiza la sección SNMP:
- **Totales**: RX/TX/Total de toda la red (suma de switches)
- **Barras por switch**: utilización % con color warn si >70%
- **Top consumers**: lista de top 10 por tráfico con mini-sparkbar
- **Donut chart**: distribución de tráfico por dispositivo via `ChartManager`

### `_renderWan(data)`
Renderiza la sección WAN (actualizada cada 5s via socket):
- Dot de estado (verde=online, rojo=error)
- Label: `interfaz via gateway ▼rxFmt ▲txFmt`
- Latencia con color coding: verde <30ms, amarillo <80ms, rojo >80ms
- Packet loss: amarillo si >0%, rojo si ≥10%
- Badge Starlink con obstrucción y alertas (si aplica)
- Actualiza los gráficos históricos

### `_pushHistory(arr, t, val)`
Agrega punto al array de historial, mantiene máximo `HISTORY_N` (60) puntos (FIFO).

### `_initWanCharts()`
Inicializa dos gráficos Chart.js:
- **Latencia**: line chart cyan, área rellena, sin animación
- **Throughput**: line chart doble (RX verde, TX amarillo), área rellena

### `_updateWanCharts()`
Actualiza los datos de ambos charts con el historial acumulado. Usa `chart.update('none')` para evitar animaciones en actualizaciones frecuentes.

---

## public/js/charts.js — clase `ChartManager`

Gestiona los gráficos Chart.js del dashboard.

### `renderVendorChart(vendorMap)`
Donut chart de distribución por vendor. Genera colores automáticamente con espaciado en el espectro HSL.

### `renderTrafficChart(trafficMap)`
Donut chart de tráfico por dispositivo (top 10). Misma paleta de colores.

### `destroyAll()`
Destruye todas las instancias Chart.js activas para evitar memory leaks al rerender.

---

## public/js/toast.js — clase `ToastManager`

Sistema de notificaciones toast en la esquina inferior derecha.

### `show(message, type, duration)`
Crea y muestra un toast. Tipos: `info` (cyan), `success` (verde), `warning` (amarillo), `error` (rojo). Duración default: 4000ms. Retorna ID del toast.

### `hide(id)`
Oculta y elimina el toast con ese ID con animación slide-out.

Los toasts se apilan verticalmente y se eliminan automáticamente al vencer el timeout.

---

## public/js/context-menu.js — clase `ContextMenu`

Menú contextual reutilizable (click derecho).

### `show(x, y, items)`
Muestra el menú en las coordenadas `(x, y)` con los items provistos. Cada item: `{label, icon, action, disabled}`. Se posiciona automáticamente para no salir de la pantalla.

### `hide()`
Oculta el menú. Se llama automáticamente en click fuera del menú o al ejecutar una acción.

Usado principalmente en el mapa de topología para acciones rápidas sobre nodos.

---

## public/js/pdf-export.js — `PdfExport`

### `PdfExport.exportInventory(devices, stats)`
Método estático. Genera un documento HTML auto-contenido con:
- Header con logo, fecha y hora de generación
- Cards de estadísticas (total, online, offline, nuevos)
- Tabla completa de dispositivos con estilos embebidos
- Llama `window.print()` en una nueva ventana

El CSS está embebido directamente en el HTML para que el PDF generado sea independiente.
