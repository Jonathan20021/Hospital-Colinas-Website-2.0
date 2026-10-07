<?php
/**
 * Avisos a la app de iOS "Mi Hospital" por APNs (HTTP/2 + token de proveedor JWT ES256).
 *
 * Va en helpers/ de la API interna, junto a PushNotifier. Se llama en el mismo
 * punto en que PushNotifier manda un Web Push a un paciente (ver README.md):
 *
 *   ApnsSender::aPaciente($db, $patientId, [
 *       'titulo' => 'Hospital Las Colinas',
 *       'cuerpo' => 'Tienes un mensaje nuevo de tu médico.',
 *       'url'    => '/portal/mensajes.php',   // sección que abre al tocarlo
 *       'grupo'  => 'mensajes',               // agrupa en el centro de notificaciones
 *   ]);
 *
 * PRIVACIDAD — el aviso se ve en la pantalla BLOQUEADA del teléfono:
 *   NUNCA diagnósticos, nombres de estudios o medicamentos, resultados, ni la
 *   especialidad del médico. Solo "tienes algo nuevo en tu portal". El detalle
 *   se ve dentro de la app, con sesión (y Face ID si el paciente lo activó).
 *
 * Configuración (constantes en el config de la API interna):
 *   APNS_KEY_PATH  ruta al .p8 de Apple, FUERA del webroot, permisos 600
 *   APNS_KEY_ID    10 caracteres (Apple Developer > Certificates, IDs & Profiles > Keys)
 *   APNS_TEAM_ID   10 caracteres (Apple Developer > Membership)
 *   APNS_TOPIC     identificador de la app: com.colinashospital.paciente
 *   APNS_CACHE_DIR opcional; dónde reutilizar el JWT (por defecto el temporal del sistema)
 *
 * Interruptor general: settings.patient_push_apns = '1'. Si falta o vale otra
 * cosa NO se envía nada (misma política que el resto de avisos a pacientes).
 *
 * Compatible con PHP 7.4+. Requiere curl con HTTP/2 y openssl.
 */
final class ApnsSender
{
    const HOSTS = [
        'production' => 'https://api.push.apple.com',
        'sandbox'    => 'https://api.sandbox.push.apple.com',
    ];

    /** Apple: renovar el JWT entre 20 y 60 minutos. */
    const VIDA_JWT = 3000;

    /** Errores de APNs que significan "este token ya no sirve": se borra. */
    const TOKEN_MUERTO = ['BadDeviceToken', 'DeviceTokenNotForTopic', 'Unregistered', 'ExpiredToken'];

    /** @var array<string, resource|\CurlHandle> conexión HTTP/2 reutilizable por entorno */
    private static $conexiones = [];

    /**
     * Envía un aviso a todos los iPhone/iPad del paciente.
     *
     * $aviso:    titulo, cuerpo (obligatorio), url (ruta /portal/...), grupo,
     *            insignia (número del ícono), colapsar (apns-collapse-id),
     *            caduca (timestamp: después de eso APNs lo descarta).
     * $opciones: config (en vez de las constantes), base (URL por entorno, para
     *            pruebas), curl (opciones extra de curl, para pruebas).
     *
     * @return array{enviados:int, fallidos:int, borrados:int, omitido?:string, detalle?:array}
     */
    public static function aPaciente(PDO $db, int $patientId, array $aviso, array $opciones = []): array
    {
        if (!self::habilitado($db)) {
            return ['enviados' => 0, 'fallidos' => 0, 'borrados' => 0, 'omitido' => 'interruptor_apagado'];
        }
        $st = $db->prepare('SELECT id, token, environment FROM portal_push_apns WHERE patient_id = ?');
        $st->execute([$patientId]);
        $dispositivos = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$dispositivos) {
            return ['enviados' => 0, 'fallidos' => 0, 'borrados' => 0, 'omitido' => 'sin_dispositivos'];
        }

        $cfg = self::config($opciones);
        $payload = self::payload($aviso);
        $resumen = ['enviados' => 0, 'fallidos' => 0, 'borrados' => 0, 'detalle' => []];

        foreach ($dispositivos as $d) {
            $r = self::enviar($cfg, (string) $d['environment'], (string) $d['token'], $payload, $aviso, $opciones);
            $resumen['detalle'][] = ['id' => (int) $d['id'], 'estado' => $r['estado'], 'motivo' => $r['motivo']];

            if ($r['estado'] === 200) {
                $resumen['enviados']++;
                $db->prepare('UPDATE portal_push_apns SET last_sent_at = ?, last_error = NULL WHERE id = ?')
                   ->execute([date('Y-m-d H:i:s'), (int) $d['id']]);
                continue;
            }
            $resumen['fallidos']++;
            if (in_array($r['motivo'], self::TOKEN_MUERTO, true)) {
                $db->prepare('DELETE FROM portal_push_apns WHERE id = ?')->execute([(int) $d['id']]);
                $resumen['borrados']++;
                continue;
            }
            $db->prepare('UPDATE portal_push_apns SET last_error = ? WHERE id = ?')
               ->execute([substr((string) $r['motivo'], 0, 60), (int) $d['id']]);
            if (in_array($r['motivo'], ['InvalidProviderToken', 'ExpiredProviderToken'], true)) {
                self::olvidarJwt($cfg);   // el próximo intento firma uno nuevo
            }
            if ($r['estado'] === 429 || $r['estado'] >= 500) {
                break;   // APNs pide calma o está caído: no insistir con el resto ahora
            }
        }
        return $resumen;
    }

    public static function habilitado(PDO $db): bool
    {
        $st = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $st->execute(['patient_push_apns']);
        return (string) $st->fetchColumn() === '1';
    }

    /** JSON del aviso. Lanza si no cabe en los 4 KB que admite APNs. */
    public static function payload(array $aviso): string
    {
        $cuerpo = trim((string) ($aviso['cuerpo'] ?? ''));
        if ($cuerpo === '') {
            throw new InvalidArgumentException('El aviso necesita un cuerpo.');
        }
        $aps = [
            'alert' => [
                'title' => (string) ($aviso['titulo'] ?? 'Hospital Las Colinas'),
                'body'  => $cuerpo,
            ],
            'sound' => 'default',
        ];
        if (isset($aviso['grupo'])) {
            $aps['thread-id'] = (string) $aviso['grupo'];
        }
        if (isset($aviso['insignia'])) {
            $aps['badge'] = (int) $aviso['insignia'];
        }
        $datos = ['aps' => $aps];
        // La app solo abre rutas del portal; se filtra también aquí.
        $url = (string) ($aviso['url'] ?? '');
        if ($url !== '' && strncmp($url, '/portal/', 8) === 0 && strpos($url, '//') === false) {
            $datos['url'] = $url;
        }
        $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || strlen($json) > 4096) {
            throw new InvalidArgumentException('El aviso supera los 4 KB que admite APNs.');
        }
        return $json;
    }

    /**
     * Token de proveedor (JWT ES256) para la cabecera authorization. Se
     * reutiliza VIDA_JWT segundos entre procesos: Apple rechaza (429) a quien
     * firma tokens nuevos con demasiada frecuencia.
     */
    public static function tokenDeProveedor(array $cfg): string
    {
        $cache = self::rutaCache($cfg);
        $guardado = is_file($cache) ? json_decode((string) @file_get_contents($cache), true) : null;
        if (is_array($guardado) && isset($guardado['jwt'], $guardado['iat'])
            && (time() - (int) $guardado['iat']) < self::VIDA_JWT) {
            return (string) $guardado['jwt'];
        }

        $ahora = time();
        $datos = self::b64url(json_encode(['alg' => 'ES256', 'kid' => $cfg['key_id']]))
            . '.' . self::b64url(json_encode(['iss' => $cfg['team_id'], 'iat' => $ahora]));
        $pem = @file_get_contents($cfg['key_path']);
        $llave = $pem !== false ? openssl_pkey_get_private($pem) : false;
        if ($llave === false || !openssl_sign($datos, $firmaDer, $llave, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar el token de APNs: revisa APNS_KEY_PATH.');
        }
        $jwt = $datos . '.' . self::b64url(self::derAFirmaCruda($firmaDer));

        if (@file_put_contents($cache, json_encode(['jwt' => $jwt, 'iat' => $ahora]), LOCK_EX) !== false) {
            @chmod($cache, 0600);
        }
        return $jwt;
    }

    /**
     * openssl_sign() devuelve la firma ECDSA en DER (SEQUENCE de dos INTEGER);
     * JWS/ES256 exige r||s crudos de 32 bytes cada uno (64 en total).
     */
    public static function derAFirmaCruda(string $der): string
    {
        $pos = 0;
        $leerLargo = function () use ($der, &$pos): int {
            $largo = ord($der[$pos++]);
            if ($largo & 0x80) {
                $bytes = $largo & 0x7f;
                $largo = 0;
                for ($i = 0; $i < $bytes; $i++) {
                    $largo = ($largo << 8) | ord($der[$pos++]);
                }
            }
            return $largo;
        };
        if (strlen($der) < 8 || ord($der[$pos++]) !== 0x30) {
            throw new RuntimeException('Firma ECDSA con formato inesperado.');
        }
        $leerLargo();
        $crudo = '';
        for ($i = 0; $i < 2; $i++) {
            if (ord($der[$pos++]) !== 0x02) {
                throw new RuntimeException('Firma ECDSA con formato inesperado.');
            }
            $largo = $leerLargo();
            $entero = ltrim(substr($der, $pos, $largo), "\x00");   // fuera el byte de signo
            $pos += $largo;
            if (strlen($entero) > 32) {
                throw new RuntimeException('Firma ECDSA con formato inesperado.');
            }
            $crudo .= str_pad($entero, 32, "\x00", STR_PAD_LEFT);
        }
        return $crudo;
    }

    // ── Internos ──────────────────────────────────────────────────────────

    private static function enviar(array $cfg, string $entorno, string $token, string $payload, array $aviso, array $opciones): array
    {
        $entorno = $entorno === 'sandbox' ? 'sandbox' : 'production';
        $base = isset($opciones['base'][$entorno]) ? $opciones['base'][$entorno] : self::HOSTS[$entorno];

        $cabeceras = [
            'authorization: bearer ' . self::tokenDeProveedor($cfg),
            'apns-topic: ' . $cfg['topic'],
            'apns-push-type: alert',
            'apns-priority: 10',
            'content-type: application/json',
        ];
        if (!empty($aviso['caduca'])) {
            $cabeceras[] = 'apns-expiration: ' . (int) $aviso['caduca'];
        }
        if (!empty($aviso['colapsar'])) {
            $cabeceras[] = 'apns-collapse-id: ' . substr((string) $aviso['colapsar'], 0, 64);
        }

        $ch = self::conexion($entorno, $opciones);
        curl_setopt_array($ch, [
            CURLOPT_URL            => $base . '/3/device/' . rawurlencode($token),
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $cabeceras,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
        ]);
        $respuesta = curl_exec($ch);
        $estado = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($respuesta === false || $estado === 0) {
            return ['estado' => 0, 'motivo' => 'red: ' . curl_error($ch)];
        }
        $json = json_decode((string) $respuesta, true);
        $motivo = is_array($json) && isset($json['reason']) ? (string) $json['reason'] : null;
        return ['estado' => $estado, 'motivo' => $motivo];
    }

    /** Una conexión por entorno y proceso: HTTP/2 multiplexa todos los envíos por ella. */
    private static function conexion(string $entorno, array $opciones)
    {
        if (!isset(self::$conexiones[$entorno])) {
            $version = curl_version();
            if (!defined('CURL_VERSION_HTTP2') || !($version['features'] & CURL_VERSION_HTTP2)) {
                throw new RuntimeException('El curl de este servidor no soporta HTTP/2, que APNs exige.');
            }
            $ch = curl_init();
            if (!empty($opciones['curl'])) {
                curl_setopt_array($ch, $opciones['curl']);
            }
            self::$conexiones[$entorno] = $ch;
        }
        return self::$conexiones[$entorno];
    }

    private static function config(array $opciones): array
    {
        if (isset($opciones['config'])) {
            $cfg = $opciones['config'];
        } else {
            $cfg = [
                'key_path'  => defined('APNS_KEY_PATH') ? APNS_KEY_PATH : '',
                'key_id'    => defined('APNS_KEY_ID') ? APNS_KEY_ID : '',
                'team_id'   => defined('APNS_TEAM_ID') ? APNS_TEAM_ID : '',
                'topic'     => defined('APNS_TOPIC') ? APNS_TOPIC : '',
                'cache_dir' => defined('APNS_CACHE_DIR') ? APNS_CACHE_DIR : '',
            ];
        }
        foreach (['key_path', 'key_id', 'team_id', 'topic'] as $clave) {
            if (empty($cfg[$clave])) {
                throw new RuntimeException('Falta la configuración de APNs: ' . strtoupper('apns_' . $clave));
            }
        }
        return $cfg;
    }

    private static function rutaCache(array $cfg): string
    {
        $dir = !empty($cfg['cache_dir']) ? rtrim($cfg['cache_dir'], '/') : sys_get_temp_dir();
        return $dir . '/apns-jwt-' . preg_replace('/[^A-Za-z0-9]/', '', $cfg['key_id'] . $cfg['team_id']) . '.json';
    }

    private static function olvidarJwt(array $cfg): void
    {
        @unlink(self::rutaCache($cfg));
    }

    private static function b64url(string $datos): string
    {
        return rtrim(strtr(base64_encode($datos), '+/', '-_'), '=');
    }
}
