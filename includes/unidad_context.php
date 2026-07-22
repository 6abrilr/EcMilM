<?php
declare(strict_types=1);

if (!function_exists('unidad_web_paths')) {
  function unidad_web_paths(): array {
    $self = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
    $pos = stripos($self, '/public/');

    if ($pos !== false) {
      $appBase = rtrim(substr($self, 0, $pos), '/');
      $publicBase = $appBase . '/public';
    } else {
      $dir = rtrim(str_replace('\\', '/', dirname($self)), '/');
      $appBase = preg_match('#/public(?:/|$)#', $dir) ? preg_replace('#/public.*$#', '', $dir) : $dir;
      $appBase = rtrim((string)$appBase, '/');
      $publicBase = $appBase . '/public';
    }

    if ($appBase === '') $appBase = '';

    return [
      'app' => $appBase,
      'public' => $publicBase,
      'assets' => $appBase . '/assets',
    ];
  }
}

if (!function_exists('unidad_normalize_id')) {
  function unidad_normalize_id($value): int {
    $id = (int)$value;
    return $id > 0 ? $id : 1;
  }
}

if (!function_exists('unidad_activa_id')) {
  function unidad_activa_id(): int {
    $sessionUser = $_SESSION['user'] ?? [];
    return unidad_normalize_id($_SESSION['unidad_id'] ?? ($sessionUser['unidad_id'] ?? 1));
  }
}

if (!function_exists('unidad_asset_endpoint')) {
  function unidad_asset_endpoint(int $unidadId, string $tipo, ?string $updatedAt = null): string {
    $paths = unidad_web_paths();
    $query = http_build_query([
      'id' => $unidadId,
      'tipo' => $tipo,
      'v' => $updatedAt ?: '1',
    ]);
    return $paths['public'] . '/unidad_asset.php?' . $query;
  }
}

if (!function_exists('unidad_context')) {
  function unidad_context(PDO $pdo, ?int $unidadId = null): array {
    static $cache = [];

    $unidadId = unidad_normalize_id($unidadId ?? unidad_activa_id());
    if (isset($cache[$unidadId])) return $cache[$unidadId];

    $paths = unidad_web_paths();
    $ctx = [
      'id' => $unidadId,
      'slug' => 'ecmilm',
      'nombre_corto' => 'EC MIL M',
      'nombre_completo' => 'Escuela Militar de Montaña',
      'subnombre' => 'La montaña nos une',
      'logo_path' => null,
      'escudo_path' => null,
      'bg_path' => null,
      'updated_at' => null,
      'asset_base' => $paths['assets'],
      'logo_url' => $paths['assets'] . '/img/ecmilm.png',
      'escudo_url' => $paths['assets'] . '/img/ecmilm.png',
      'icon_url' => $paths['assets'] . '/img/ecmilm.png',
      'bg_url' => $paths['assets'] . '/img/fondo.png',
      'hero_url' => $paths['assets'] . '/img/ecmilm2026.png',
    ];

    try {
      $st = $pdo->prepare("
        SELECT id, slug, nombre_corto, nombre_completo, subnombre,
               logo_path, escudo_path, bg_path, updated_at
        FROM unidades
        WHERE id = :id AND activa = 1
        LIMIT 1
      ");
      $st->execute([':id' => $unidadId]);
      $row = $st->fetch(PDO::FETCH_ASSOC);
      if (is_array($row) && $row) {
        foreach ($row as $key => $value) {
          if (array_key_exists($key, $ctx) && $value !== null && $value !== '') {
            $ctx[$key] = $value;
          }
        }
      }
    } catch (Throwable $e) {
      $row = null;
    }

    $updated = (string)($ctx['updated_at'] ?? '1');
    if (!empty($ctx['logo_path'])) {
      $ctx['logo_url'] = unidad_asset_endpoint((int)$ctx['id'], 'logo', $updated);
      $ctx['hero_url'] = $ctx['logo_url'];
    }
    if (!empty($ctx['escudo_path'])) {
      $ctx['escudo_url'] = unidad_asset_endpoint((int)$ctx['id'], 'escudo', $updated);
      $ctx['icon_url'] = $ctx['escudo_url'];
    } elseif (!empty($ctx['logo_path'])) {
      $ctx['escudo_url'] = $ctx['logo_url'];
      $ctx['icon_url'] = $ctx['logo_url'];
    }
    if (!empty($ctx['bg_path'])) {
      $ctx['bg_url'] = unidad_asset_endpoint((int)$ctx['id'], 'fondo', $updated);
    }

    $cache[$unidadId] = $ctx;
    return $ctx;
  }
}

if (!function_exists('unidad_register_branding_output_filter')) {
  function unidad_register_branding_output_filter(PDO $pdo): void {
    if (PHP_SAPI === 'cli' || !empty($GLOBALS['UNIDAD_BRANDING_FILTER_ON'])) return;
    $GLOBALS['UNIDAD_BRANDING_FILTER_ON'] = true;

    ob_start(static function (string $html) use ($pdo): string {
      if ($html === '') return $html;
      $ctx = unidad_context($pdo);
      $paths = unidad_web_paths();
      $asset = $paths['assets'];

      $replacements = [
        $asset . '/img/ecmilm.png' => $ctx['escudo_url'],
        $asset . '/img/ecmilm2026.png' => $ctx['hero_url'],
        $asset . '/img/ecmilm2026.PNG' => $ctx['hero_url'],
        $asset . '/img/fondo.png' => $ctx['bg_url'],
        $paths['public'] . '/../assets/img/ecmilm.png' => $ctx['escudo_url'],
        $paths['public'] . '/../assets/img/fondo.png' => $ctx['bg_url'],
        $paths['public'] . '/assets/../../assets/img/ecmilm.png' => $ctx['escudo_url'],
        $paths['public'] . '/assets/../../assets/img/fondo.png' => $ctx['bg_url'],
        '../../assets/img/ecmilm.png' => $ctx['escudo_url'],
        '../../assets/img/fondo.png' => $ctx['bg_url'],
        '../../../assets/img/ecmilm.png' => $ctx['escudo_url'],
        '../../../assets/img/fondo.png' => $ctx['bg_url'],
        '../assets/img/ecmilm.png' => $ctx['escudo_url'],
        '../assets/img/fondo.png' => $ctx['bg_url'],
      ];

      return str_replace(array_keys($replacements), array_values($replacements), $html);
    });
  }
}
