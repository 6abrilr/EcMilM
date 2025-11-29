<?php
// login_cps.php
// Helpers para autenticar contra el CPS y poblar la sesión local.

declare(strict_types=1);

/**
 * 1) Enviar usuario/clave al CPS y obtener token.
 */
function cps_authenticate(string $username, string $password): array {
    $CPS_LOGIN_URL = "https://apicps.ejercito.mil.ar/api/v1/login";

    $ch = curl_init($CPS_LOGIN_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST            => true,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_POSTFIELDS      => [
            'username' => $username,
            'password' => $password,
        ],
        CURLOPT_SSL_VERIFYPEER  => 0,
        CURLOPT_SSL_VERIFYHOST  => 0,
        CURLOPT_SSLVERSION      => CURL_SSLVERSION_TLSv1_2,
        CURLOPT_FOLLOWLOCATION  => true,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $errno  = curl_errno($ch);
        $errmsg = curl_error($ch);
        curl_close($ch);
        throw new Exception("No se pudo contactar al servidor central ($errno: $errmsg)");
    }

    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpcode < 200 || $httpcode >= 300) {
        $msg = "El usuario o la contraseña son incorrectos.";
        if (is_array($data)) {
            if (!empty($data['message']))      $msg = $data['message'];
            elseif (!empty($data['error']))    $msg = $data['error'];
        }
        throw new Exception($msg);
    }

    if (!is_array($data)) {
        throw new Exception("Respuesta inválida del servidor central (no es JSON).");
    }

    if (
        !isset($data['access_token']) &&
        !isset($data['token']) &&
        !isset($data['jwt'])
    ) {
        throw new Exception("El servidor central no devolvió un token de sesión.");
    }

    return $data;
}

/**
 * 2) Pedir perfil al CPS con el token Bearer.
 */
function cps_get_profile(string $bearerToken): array {
    $CPS_PROFILE_URL = "https://apicps.ejercito.mil.ar/api/v1/user/profile";

    $ch = curl_init($CPS_PROFILE_URL);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET        => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: Bearer ' . $bearerToken,
        ],
        CURLOPT_SSL_VERIFYPEER  => 0,
        CURLOPT_SSL_VERIFYHOST  => 0,
        CURLOPT_SSLVERSION      => CURL_SSLVERSION_TLSv1_2,
        CURLOPT_FOLLOWLOCATION  => true,
    ]);

    $resp = curl_exec($ch);
    if ($resp === false) {
        $errno  = curl_errno($ch);
        $errmsg = curl_error($ch);
        curl_close($ch);
        throw new Exception("No se pudo obtener el perfil del servidor central ($errno: $errmsg)");
    }

    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode < 200 || $httpcode >= 300) {
        throw new Exception("Token inválido o expirado al consultar perfil.");
    }

    $profile = json_decode($resp, true);
    if (!is_array($profile)) {
        throw new Exception("El perfil devuelto no es JSON válido.");
    }

    return $profile;
}

/**
 * 3) Mapear rol local usando la BD `inspecciones`.
 *    Solo permite acceso si el DNI está en roles_locales.
 */
function map_local_role(string $dniOrUser): string {
    require_once __DIR__ . '/config/db.php'; // acá se define $pdo para `inspecciones`
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception("No se pudo obtener la conexión PDO desde config/db.php");
    }

    // ¿existe roles_locales?
    try {
        $check  = $pdo->query("SHOW TABLES LIKE 'roles_locales'");
        $exists = $check && $check->fetchColumn();
    } catch (Throwable $e) {
        $exists = false;
    }

    if (!$exists) {
        // Si la tabla no existe, no dejamos entrar a nadie
        throw new Exception("Configuración local de roles ausente. Contactá al administrador.");
    }

    $stmt = $pdo->prepare("SELECT rol_app FROM roles_locales WHERE dni = ? LIMIT 1");
    $stmt->execute([$dniOrUser]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['rol_app'])) {
        return (string)$row['rol_app'];
    }

    // DNI no listado => sin permiso
    throw new Exception("No tenés autorización local para acceder a esta aplicación.");
}

/**
 * 4) Función principal usada por login.php
 */
function auth_login_cps(string $username, string $password): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // 1) token
    $data = cps_authenticate($username, $password);
    $token = $data['access_token']
        ?? $data['token']
        ?? $data['jwt']
        ?? null;

    if ($token === null) {
        throw new Exception("No se pudo recuperar el token del servidor central.");
    }

    // 2) perfil
    $perfil = cps_get_profile($token);

    $dniRaw = $perfil['dni'] ?? '';
    $dni    = ($dniRaw === null) ? '' : (string)$dniRaw;

    $firstName = $perfil['first_name'] ?? $perfil['nombre']   ?? '';
    $lastName  = $perfil['last_name']  ?? $perfil['apellido'] ?? '';
    $fullName  = trim($firstName . ' ' . $lastName);

    $rank = $perfil['rank'] ?? $perfil['grado'] ?? '';
    $unit = $perfil['unit_description']
         ?? $perfil['unit']
         ?? $perfil['unidad']
         ?? '';

    $emailLab = $perfil['work_email']
             ?? $perfil['email_laboral']
             ?? $perfil['email']
             ?? '';

    $resolvedUsername = $perfil['username'] ?? $perfil['user'] ?? $username;
    $resolvedUsername = (string)$resolvedUsername;

    // 3) rol interno + control por DNI
    $keyForRole = ($dni !== '' ? $dni : $resolvedUsername);
    $rolLocal   = map_local_role($keyForRole);

    // 4) guardar en sesión
    $_SESSION['cps_token'] = $token;
    $_SESSION['user'] = [
        'dni'       => $dni,
        'username'  => $resolvedUsername,
        'full_name' => ($fullName !== '' ? $fullName : $resolvedUsername),
        'rank'      => $rank,
        'unit'      => $unit,
        'email_lab' => $emailLab,
        'role_app'  => $rolLocal,
    ];

    return true;
}
