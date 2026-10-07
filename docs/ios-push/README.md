# Avisos de la app de iOS (APNs) — traspaso para la API interna (JENOFONTE)

La app de iOS **Mi Hospital** (`ios/`) muestra el Portal del Paciente en una
vista web. Dentro de esa vista no existe Web Push, así que sus avisos van por
**APNs** (el servicio de notificaciones de Apple). La app ya obtiene el token
del iPhone y lo envía; falta que la API interna lo guarde y que `PushNotifier`
también envíe por APNs.

## Estado

| Capa | Ubicación | Estado |
|------|-----------|--------|
| App: permiso, token, baja al cerrar sesión, abrir la sección al tocar | `ios/MiHospital/` | ✅ en el repo, **falta compilar en Xcode** |
| Web: `HGLCPush` dentro de la app, tarjeta de Face ID en el perfil, Mi Ciclo | `assets/js/portal-pwa.js`, `assets/js/portal-ciclo.js`, `portal/perfil.php` | ✅ en el repo, **falta subir a cPanel** |
| Tabla `portal_push_apns` + interruptor | `schema.sql` | ⏳ **aplicar en `medical_call_center`** |
| Endpoints de registro | `ApnsTrait.php` | ⏳ **integrar en PortalController** |
| Envío por APNs | `ApnsSender.php` | ⏳ **copiar a `helpers/` y llamarlo desde `PushNotifier`** |

Mientras la API interna no tenga estos endpoints, la app sigue funcionando: el
registro del token responde 404 y la app lo reintenta en la siguiente sesión.
En cuanto se despliegue, los iPhone se registran solos.

## Arquitectura

```
Registro (una vez por sesión, y al activar avisos en el perfil)
  App ── fetch en la página del portal ──▶ /api/portal-proxy.php (sesión + CSRF + auditoría PHI)
      ──▶ POST /portal/me/push/apns/subscribe ──▶ portal_push_apns

Envío
  PushNotifier (mensaje del médico, recordatorio, prueba…)
      ├──▶ Web Push (lo que ya existe)
      └──▶ ApnsSender::aPaciente() ──HTTP/2──▶ APNs ──▶ iPhone
```

- La app **no** habla con la API interna ni conoce el JWT: el registro sale de
  la propia página del portal, por el proxy de siempre, que ya permite el
  prefijo `/portal/me`. No hay que tocar su allowlist.
- Un token es de un **dispositivo**. Si otro paciente inicia sesión en el mismo
  iPhone, el token pasa a ser suyo (`UNIQUE` en `token`): un teléfono compartido
  nunca recibe avisos de dos pacientes.
- Al cerrar sesión, la app da de baja el token **antes** de que el servidor
  destruya la sesión.

## Despliegue

1. **Llave de Apple** — en developer.apple.com › Certificates, IDs & Profiles ›
   Keys, crear una llave con *Apple Push Notifications service (APNs)*. Se
   descarga UNA sola vez: `AuthKey_XXXXXXXXXX.p8`. Guardarla fuera del webroot
   con permisos `600`. Anotar el **Key ID** y el **Team ID** (Membership).
2. **Configuración** — en el config de la API interna:
   ```php
   define('APNS_KEY_PATH', '/ruta/segura/AuthKey_XXXXXXXXXX.p8');
   define('APNS_KEY_ID',   'XXXXXXXXXX');
   define('APNS_TEAM_ID',  'YYYYYYYYYY');
   define('APNS_TOPIC',    'com.colinashospital.paciente'); // = bundle id de la app
   // define('APNS_CACHE_DIR', '/ruta/escribible');          // opcional
   ```
3. **Tabla** — `mysql medical_call_center < schema.sql` (idempotente). Crea el
   interruptor `settings.patient_push_apns` **apagado**.
4. **Endpoints** — `ApnsTrait.php` en `api/v1/controllers/`, `use ApnsTrait;` en
   `PortalController` y en `index.php` (`$portalRoutes`, grupo con JWT de paciente):

   | Método | Ruta | Función |
   |--------|------|---------|
   | POST | `/portal/me/push/apns/subscribe` | `apnsSubscribe` |
   | POST | `/portal/me/push/apns/unsubscribe` | `apnsUnsubscribe` |

5. **Envío** — `ApnsSender.php` en `helpers/`. En `PushNotifier`, donde hoy se
   manda el Web Push a un paciente, agregar la misma notificación por APNs:
   ```php
   require_once __DIR__ . '/ApnsSender.php';
   // … junto al envío Web Push existente:
   try {
       ApnsSender::aPaciente($db, $patientId, [
           'titulo' => 'Hospital Las Colinas',
           'cuerpo' => $cuerpo,           // el mismo texto (sin datos clínicos)
           'url'    => $url,              // ruta del portal: /portal/mensajes.php
           'grupo'  => $tipo,             // mensajes | citas | resultados | ciclo
       ]);
   } catch (Throwable $e) {
       error_log('APNs: ' . $e->getMessage());   // un fallo de APNs no frena el Web Push
   }
   ```
   Incluir también `/portal/me/push/test`, para que el botón **Probar** del
   perfil funcione dentro de la app, y los recordatorios de `cycleReminders`.
6. **Encender** — probar con TestFlight y, con visto bueno, poner
   `settings.patient_push_apns = '1'`. Hasta entonces no sale nada.

## Contrato

### `POST /portal/me/push/apns/subscribe`
```json
{ "token": "3f2a…(64 hex)", "entorno": "production", "bundle_id": "com.colinashospital.paciente",
  "app_version": "1.0.0", "os_version": "18.6", "dispositivo": "iPhone" }
```
→ `{ "success": true, "data": { "subscribed": true } }` · token o app inválidos → 422.
`entorno` es `sandbox` en los builds de desarrollo de Xcode y `production` en
TestFlight y App Store: cada uno se envía a un dominio distinto de APNs.
Máximo 10 dispositivos por paciente (se descartan los menos recientes).

### `POST /portal/me/push/apns/unsubscribe`
`{ "token": "3f2a…" }` → `{ "success": true, "data": { "removed": true } }`. Solo
el dueño puede darlo de baja.

### Lo que recibe la app
```json
{ "aps": { "alert": { "title": "Hospital Las Colinas", "body": "Tienes un mensaje nuevo de tu médico." },
           "sound": "default", "thread-id": "mensajes" },
  "url": "/portal/mensajes.php" }
```
Al tocarlo, la app abre `url` (solo rutas `/portal/…`; cualquier otra cosa se
ignora). Si la sesión venció, el portal pide entrar y después lleva ahí.

## Privacidad — obligatorio

El aviso se ve en la **pantalla bloqueada**, la vea quien la vea. Nunca incluir
diagnósticos, nombres de estudios o medicamentos, resultados, montos, ni la
especialidad del médico. Ejemplos válidos:

- «Tienes un mensaje nuevo de tu médico.»
- «Recordatorio: tienes una cita mañana a las 10:30 a. m.»
- «Hay un resultado nuevo disponible en tu portal.»

La app lo promete al pedir el permiso («sin mostrar detalles médicos en la
pantalla bloqueada»).

## Pruebas

```
bash docs/ios-push/pruebas/correr.sh
```

Levanta dos APNs simulados (HTTP/2) con una llave generada al vuelo y prueba:
la firma ES256 (300 firmas DER → r‖s verificadas), el JWT y su reutilización,
el payload (límite de 4 KB, filtro de `url`) y el envío: el token válido queda
con `last_sent_at`, los rechazados por APNs (410/400) se borran, los de sandbox
van al dominio de sandbox y no se toca a otros pacientes. El simulado verifica
la firma con `crypto` de Node, una implementación independiente de la de PHP.

Con `PRUEBA_MYSQL_DSN` apuntando a una base **desechable** prueba además
`schema.sql` y los endpoints en MariaDB con `EMULATE_PREPARES=false`, como la
API interna. Estado al entregar: 40/40 en MariaDB 10.11.
