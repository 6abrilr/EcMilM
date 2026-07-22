<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/bootstrap.php';
require_login();

$user = current_user() ?: [];
if (strtolower(trim((string)($user['username'] ?? ''))) !== 'nesrojas') {
  http_response_code(403);
  exit('Acceso restringido al administrador nesrojas.');
}

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = (string)($_SERVER['HTTP_HOST'] ?? '10.21.209.49');
$host = preg_replace('/:\d+$/', '', $host);
$scannerUrl = 'http://127.0.0.1:3000';
$backUrl = 'informatica.php';
$syncUrl = 'informatica_sync_scanner_inventory.php';
$registryUrl = 'informatica_scanner_registry.php';
$csrfToken = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Escaner de red - Informatica</title>
  <style>
    html,body{height:100%;margin:0;background:#05070d;color:#e5e7eb;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,sans-serif;}
    .topbar{height:56px;display:flex;align-items:center;gap:12px;padding:0 14px;background:#0b1220;border-bottom:1px solid rgba(148,163,184,.25);}
    .title{font-weight:900;}
    .muted{color:#a8b2c3;font-size:.88rem;}
    .spacer{flex:1;}
    .sync-status{min-width:230px;max-width:420px;color:#bfdbfe;font-size:.84rem;text-align:right;white-space:normal;}
    .sync-status.error{color:#fecaca;}
    a.btn,button.btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:1px solid rgba(148,163,184,.35);border-radius:10px;color:#e5e7eb;background:transparent;padding:.42rem .8rem;font:inherit;font-weight:800;cursor:pointer;}
    a.primary{background:#0ea5e9;color:#021827;border-color:#0ea5e9;}
    button.sync{background:#22c55e;color:#04130a;border-color:#22c55e;}
    button.btn:disabled{opacity:.6;cursor:wait;}
    iframe{width:100%;height:calc(100vh - 57px);border:0;background:#0b1220;display:block;}
    input.location{width:245px;background:#111827;color:#e5e7eb;border:1px solid #475569;border-radius:8px;padding:.42rem .6rem;}
    select.scanner-device{width:220px;background:#111827;color:#e5e7eb;border:1px solid #475569;border-radius:8px;padding:.42rem .6rem;}
    .agent-warning{display:none;min-height:calc(100vh - 57px);box-sizing:border-box;padding:60px 24px;text-align:center;background:#f1f5f9;color:#172033;}
    .agent-warning.show{display:block}.agent-warning h2{margin:0 0 12px}.agent-warning p{max-width:700px;margin:8px auto;line-height:1.45}
    .agent-warning .btn{margin-top:14px;background:#0ea5e9;color:#021827;border-color:#0ea5e9}
    iframe.hidden{display:none}
    .registry{display:none;height:calc(100vh - 57px);overflow:auto;background:#0b1220;padding:18px;box-sizing:border-box}.registry.show{display:block}
    .registry table{width:100%;border-collapse:collapse;font-size:.82rem}.registry th,.registry td{padding:9px;border-bottom:1px solid #263247;text-align:left}.registry th{color:#93c5fd;position:sticky;top:0;background:#0b1220}.online-text{color:#4ade80}.offline-text{color:#94a3b8}
  </style>
</head>
<body>
  <div class="topbar">
    <div>
      <div class="title">Escaner de dispositivos conectados</div>
      <div class="muted">Agente local de esta computadora · usuario autorizado: nesrojas</div>
    </div>
    <div class="spacer"></div>
    <input class="location" id="scanPoint" list="scanPointSuggestions" maxlength="160"
           placeholder="Escribí edificio y dependencia" aria-label="Punto de escaneo">
    <datalist id="scanPointSuggestions">
      <option value="Informática">
      <option value="Plana Mayor - Personal">
      <option value="Plana Mayor - Operaciones">
      <option value="Dirección - SAF">
    </datalist>
    <select class="scanner-device" id="scannerDevice" aria-label="Computadora desde donde se escanea" disabled>
      <option value="">PC de escaneo...</option>
    </select>
    <span class="sync-status" id="syncStatus" aria-live="polite"></span>
    <button class="btn primary" type="button" id="runLocalScan">Escanear</button>
    <button class="btn sync" type="button" id="syncInventoryBtn">Guardar y actualizar inventario</button>
    <a class="btn" href="<?= e($backUrl) ?>">Volver</a>
    <a class="btn primary" href="<?= e($scannerUrl) ?>" target="_blank" rel="noopener">Abrir aparte</a>
  </div>
  <div class="registry show" id="centralRegistry">
    <table><thead><tr><th>Estado</th><th>Nombre de PC</th><th>MAC</th><th>IP</th><th>Responsable</th><th>Área</th><th>Punto escaneado</th><th>Último registro</th></tr></thead><tbody id="registryBody"><tr><td colspan="8">Cargando registro...</td></tr></tbody></table>
  </div>
  <div class="agent-warning" id="agentWarning">
    <h2>El agente de escaneo no está iniciado en esta computadora</h2>
    <p>Para relevar el switch de este lugar, primero debe estar instalado y ejecutándose Network Manager en esta PC.</p>
    <p>Cuando el agente esté abierto en <strong>127.0.0.1:3000</strong>, presioná Reintentar.</p>
    <p><a class="btn" href="network-manager-agent.zip">Descargar agente para esta PC</a></p>
    <p class="muted">Descomprimí el archivo y ejecutá <strong>start-network-manager.bat</strong>. Solo se instala una vez por computadora.</p>
    <button class="btn" type="button" id="retryAgent">Reintentar conexión</button>
  </div>
  <iframe class="hidden" id="scannerFrame" src="about:blank" title="Escaner de red"></iframe>
  <script>
    (() => {
      const btn = document.getElementById('syncInventoryBtn');
      const status = document.getElementById('syncStatus');
      const csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE) ?>;
      const syncUrl = <?= json_encode($syncUrl, JSON_UNESCAPED_UNICODE) ?>;
      const registryUrl = <?= json_encode($registryUrl, JSON_UNESCAPED_UNICODE) ?>;
      const agentUrl = <?= json_encode($scannerUrl, JSON_UNESCAPED_UNICODE) ?>;
      const point = document.getElementById('scanPoint');
      const frame = document.getElementById('scannerFrame');
      const warning = document.getElementById('agentWarning');
      const retry = document.getElementById('retryAgent');
      const scannerDevice = document.getElementById('scannerDevice');
      const registry = document.getElementById('centralRegistry');
      const registryBody = document.getElementById('registryBody');
      const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
      async function loadRegistry() {
        try {
          const response = await fetch(registryUrl, {credentials:'same-origin'});
          const data = await response.json();
          if (!data.ok) throw new Error(data.error || 'No se pudo cargar.');
          registryBody.innerHTML = (data.devices || []).map(d => `<tr>
            <td class="${Number(d.is_online) ? 'online-text' : 'offline-text'}">${Number(d.is_online) ? '● Online' : '○ Offline'}</td>
            <td>${esc(d.equipo_nombre || 'Sin identificar')}</td><td>${esc(d.mac)}</td><td>${esc(d.ip)}</td>
            <td>${esc(d.responsable || 'Sin asignar')}</td><td>${esc(d.area_nombre || '')}</td>
            <td>${esc(d.scan_point)}</td><td>${esc(d.last_seen)}</td></tr>`).join('') || '<tr><td colspan="8">Todavía no hay escaneos guardados.</td></tr>';
        } catch (error) { registryBody.innerHTML = `<tr><td colspan="8">${esc(error.message)}</td></tr>`; }
      }
      loadRegistry();
      point.value = localStorage.getItem('ea_scan_point') || '';
      point.addEventListener('input', () => localStorage.setItem('ea_scan_point', point.value.trim()));

      async function connectAgent() {
        status.classList.remove('error');
        status.textContent = 'Comprobando agente local...';
        try {
          const [statusResponse, infoResponse, devicesResponse] = await Promise.all([
            fetch(`${agentUrl}/api/scans/status`, { credentials: 'omit' }),
            fetch(`${agentUrl}/api/agent-info`, { credentials: 'omit' }),
            fetch(`${agentUrl}/api/devices`, { credentials: 'omit' })
          ]);
          if (!statusResponse.ok || !infoResponse.ok || !devicesResponse.ok) throw new Error();
          const info = await infoResponse.json();
          const deviceData = await devicesResponse.json();
          const localIps = new Set((info.addresses || []).map(item => item.ip));
          const choices = new Map();
          if (info.hostname) choices.set(info.hostname, `${info.hostname} (esta computadora)`);
          for (const device of (deviceData.devices || [])) {
            const name = String(device.custom_name || device.hostname || device.ip || device.mac || '').trim();
            if (!name) continue;
            const label = `${name}${device.ip ? ` · ${device.ip}` : ''}${localIps.has(device.ip) ? ' (esta computadora)' : ''}`;
            choices.set(name, label);
          }
          scannerDevice.innerHTML = '<option value="">Seleccioná la PC de escaneo...</option>';
          for (const [value, label] of choices) scannerDevice.add(new Option(label, value));
          const savedDevice = localStorage.getItem('ea_scanner_device') || '';
          scannerDevice.value = choices.has(savedDevice) ? savedDevice : (info.hostname || '');
          scannerDevice.disabled = false;
          warning.classList.remove('show');
          frame.classList.add('hidden');
          status.textContent = 'Agente local conectado.';
        } catch (_) {
          frame.src = 'about:blank';
          frame.classList.add('hidden');
          warning.classList.remove('show');
          scannerDevice.disabled = true;
          status.classList.add('error');
          status.textContent = 'Agente local no iniciado.';
        }
      }
      retry.addEventListener('click', connectAgent);
      scannerDevice.addEventListener('change', () => localStorage.setItem('ea_scanner_device', scannerDevice.value));
      connectAgent();

      document.getElementById('runLocalScan').addEventListener('click', async event => {
        const scanBtn = event.currentTarget;
        try {
          if (!point.value.trim()) throw new Error('Escribí el edificio y la dependencia.');
          if (!scannerDevice.value) throw new Error('Esperá la detección de esta computadora.');
          scanBtn.disabled = true;
          status.classList.remove('error');
          status.textContent = 'Escaneando la red local...';
          const response = await fetch(`${agentUrl}/api/scans`, {method:'POST', credentials:'omit'});
          const result = await response.json();
          if (!response.ok || !result.success) throw new Error(result.error || 'No se pudo completar el escaneo.');
          status.textContent = 'Escaneo terminado. Guardando y sincronizando...';
          btn.click();
        } catch (error) {
          status.classList.add('error');
          status.textContent = error instanceof TypeError
            ? 'El agente local no está abierto. Ejecutá start-network-manager y volvé a intentar.'
            : error.message;
        } finally { scanBtn.disabled = false; }
      });

      btn?.addEventListener('click', async () => {
        btn.disabled = true;
        status.classList.remove('error');
        status.textContent = 'Sincronizando con inventario...';

        try {
          point.value = point.value.trim();
          if (!point.value) throw new Error('Escribí primero el edificio y la dependencia.');
          if (!scannerDevice.value) throw new Error('Seleccioná la computadora desde donde se realiza el escaneo.');
          status.textContent = 'Leyendo resultados del agente local...';
          const localResponse = await fetch(`${agentUrl}/api/devices`, { credentials: 'omit' });
          const localData = await localResponse.json();
          if (!localResponse.ok || !localData.success) throw new Error('El agente local no está iniciado en esta PC.');

          const form = new FormData();
          form.append('_csrf', csrfToken);
          form.append('scan_point', point.value);
          form.append('computer_name', scannerDevice.value);
          form.append('devices_json', JSON.stringify(localData.devices || []));
          const response = await fetch(syncUrl, {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
          });
          const data = await response.json();
          if (!response.ok || !data.ok) {
            throw new Error(data.error || 'No se pudo sincronizar.');
          }
          frame.contentWindow?.postMessage({
            type: 'ea:inventory-enrichment',
            devices: data.enriquecimientos || {}
          }, agentUrl);
          await Promise.all((localData.devices || []).map(async device => {
            const extra = (data.enriquecimientos || {})[String(device.mac || '').toLowerCase()];
            if (!extra || !device.id) return;
            await fetch(`${agentUrl}/api/devices/${device.id}`, {
              method: 'PUT',
              headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({
                hostname: extra.inventory_display_name || device.hostname,
                tags: [
                  {key:'ea_responsable', value:extra.inventory_owner_display || ''},
                  {key:'ea_area', value:extra.inventory_area || ''},
                  {key:'ea_equipo', value:extra.inventory_display_name || ''}
                ]
              })
            });
          }));
          status.textContent = `Escaneados: ${data.guardados}. Inventario actualizado: ${data.actualizados || 0}. Coincidencias: ${data.coincidencias_por_nombre || 0}.`;
          await loadRegistry();
        } catch (error) {
          status.classList.add('error');
          status.textContent = error instanceof TypeError
            ? 'No se pudo conectar con el agente local. Inicialo y presioná Reintentar.'
            : (error.message || 'Error al sincronizar.');
        } finally {
          btn.disabled = false;
        }
      });
    })();
  </script>
</body>
</html>
