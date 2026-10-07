# Mi Hospital — app de iOS del Portal del Paciente

App nativa que muestra el Portal del Paciente (`portal/`) en una vista web y le
suma lo que una página no puede hacer en el iPhone:

| | Qué hace |
|---|---|
| **Avisos (APNs)** | Mensajes del médico, recordatorios y Mi Ciclo llegan aunque la app esté cerrada. Al tocarlos abre la sección indicada. |
| **Face ID** | Opcional. Pide Face ID al abrir la app o al volver tras más de un minuto fuera, si hay sesión abierta. |
| **Escudo de privacidad** | Al salir de la app se tapa la pantalla: el selector de apps no muestra datos clínicos. |
| **Documentos** | Recetas, resúmenes e imágenes se abren en Quick Look (compartir, imprimir, guardar en Archivos), con su progreso y la opción de cancelar mientras bajan. Se borran al cerrarlos. |
| **Accesos rápidos** | Mantener pulsado el ícono: Agendar cita, Mis citas, Mensajes, Recetas. |
| **Sin conexión** | Pantalla propia con reintento automático cuando vuelve la red, y el teléfono del hospital. |
| **Enlaces** | Teléfono y correo van al sistema; páginas que no son el portal se abren en una hoja de Safari. |

La lógica clínica sigue en el portal web: cada mejora del portal llega a la app
sin pasar por la App Store.

## Compilar en la Mac

Requiere Xcode 15 o más reciente. La app es para iOS 16 o más reciente.

1. Traer esta rama y abrir `ios/MiHospital.xcodeproj`.
2. Firmar: en `ios/Config/Base.xcconfig` poner el `DEVELOPMENT_TEAM` (10
   caracteres, en developer.apple.com › Membership) o elegir el equipo en
   *Signing & Capabilities*. Xcode crea solo el App ID con *Push Notifications*
   y *Data Protection*.
   Con un Apple ID gratis (*Personal Team*) la app se firma sin avisos push
   (Apple no los permite en esas cuentas). Con la cuenta de pago, cambiar
   `HGLC_ENTITLEMENTS` en el mismo `.xcconfig` a `Support/MiHospital.entitlements`.
   El identificador es `com.colinashospital.paciente`; si ya existe en otra
   cuenta, cambiarlo en el mismo `.xcconfig` (y en `APNS_TOPIC` del servidor).
3. **⌘R** en un simulador o en un iPhone conectado. **⌘U** corre las pruebas.

Para otro servidor (pruebas), cambiar `HGLC_PORTAL_HOST` en el `.xcconfig`.

### Ver cada pantalla nativa

En depuración, la app abre una pantalla sola con `-pantalla` (`sinConexion`,
`error`, `bloqueo`, `bloqueoFallido`, `bienvenida`, `portada`, `avisoSinRed`,
`documento`),
sin tener que cortar la red ni activar Face ID:

```
xcrun simctl launch --terminate-running-process booted com.colinashospital.paciente -pantalla bloqueo
```

En Xcode: *Product › Scheme › Edit Scheme › Run › Arguments*.

### Probar avisos sin servidor

Arrastrar un archivo de `ios/Pruebas/` sobre el simulador, o:

```
xcrun simctl push booted com.colinashospital.paciente ios/Pruebas/aviso-mensaje.apns
```

`aviso-mensaje.apns` y `aviso-cita.apns` deben abrir Mensajes y Mis citas al
tocarlos. `aviso-url-externa.apns` NO debe sacar la app del portal.

## Qué revisar la primera vez en un iPhone

Esta app se escribió y verificó sin Xcode (ver *Verificación*). Antes de
TestFlight conviene recorrer esto en un dispositivo real:

- [ ] Arranque: la portada pasa al login sin parpadeo blanco.
- [ ] Entrar con contraseña y con código (iOS 17+ ofrece el código del correo).
- [ ] Barra inferior del portal y gesto de volver desde el borde.
- [ ] Una receta en PDF abre Quick Look; *Compartir › Guardar en Archivos*; al cerrar, vuelve donde estaba.
- [ ] Adjuntar una foto en Mensajes, con la cámara y desde la galería.
- [ ] El teléfono del pie del portal abre la llamada.
- [ ] Un enlace a una página pública del sitio abre la hoja de Safari.
- [ ] Modo avión → pantalla *Sin conexión*; al volver la red se recupera sola.
- [ ] Tras el primer inicio de sesión aparece la bienvenida: activar avisos y Face ID.
- [ ] Con Face ID activo: salir más de un minuto y volver lo pide; el selector de apps muestra la portada.
- [ ] Con una receta abierta en Quick Look, salir y volver: el bloqueo la tapa también.
- [ ] Mi perfil muestra las tarjetas *Notificaciones* y *Proteger la app*.
- [ ] Mantener pulsado el ícono: cada acceso rápido abre su sección, con la app cerrada y en segundo plano.
- [ ] Una receta grande muestra la tarjeta *Abriendo el documento…*; *Cancelar* no deja ningún aviso de error.

## Avisos: falta el servidor

La app ya pide permiso, obtiene el token y lo registra por el proxy del portal.
Para que lleguen avisos, la API interna (JENOFONTE) tiene que guardar ese token
y enviar por APNs: todo está listo para integrar en
[`docs/ios-push/`](../docs/ios-push/README.md), con su tabla, sus endpoints, el
emisor y sus pruebas. Hasta entonces la app funciona igual, sin avisos.

## Antes de enviarla a la App Store

1. **Cuenta de organización.** Apple exige que las apps de salud las publique
   la institución que presta el servicio, no una persona (guía 5.1.1(ix)). El
   hospital necesita su propia cuenta de Apple Developer como organización,
   con número D‑U‑N‑S.
2. **Eliminar la cuenta desde la app (guía 5.1.1(v)).** El portal permite crear
   cuentas (`portal/registro.php`), así que Apple exige poder *iniciar* su
   eliminación desde la app. Hoy el portal no lo tiene. Es una decisión del
   hospital (el expediente clínico se conserva por ley; lo que se elimina es el
   acceso al portal) y necesita un endpoint en la API interna.
3. **Cuenta de prueba para la revisión.** El revisor de Apple no recibe el
   código por correo: hay que darle un paciente de prueba que entre con
   contraseña, sin datos reales.
4. **Etiquetas de privacidad** en App Store Connect, iguales a
   `MiHospital/Resources/PrivacyInfo.xcprivacy`: Salud; nombre, correo y
   teléfono; ID de usuario; fotos y otro contenido (mensajes), todo vinculado
   al paciente y solo para el funcionamiento de la app; interacción con el
   producto (analítica) sin vincular. Sin rastreo.
5. **Nombre en la tienda.** «Mi Hospital» seguramente está ocupado: en la
   tienda puede llamarse, por ejemplo, «Hospital Las Colinas»; en la pantalla
   de inicio sigue diciendo «Mi Hospital».
6. **iPad.** La app es universal: la tienda pide capturas de iPad. Para
   publicarla solo para iPhone, `TARGETED_DEVICE_FAMILY = 1`.

Subir a TestFlight: *Product › Archive › Distribute App › App Store Connect*.
Al archivar, Xcode firma con `aps-environment = production` y la app informa
`entorno: production` al registrar su token.

## Cómo está hecha

| Archivo | Responsabilidad |
|---|---|
| `App/MiHospitalApp.swift`, `App/AppDelegate.swift` | Arranque, token de APNs, toque en una notificación, accesos rápidos del ícono. |
| `App/AppConfig.swift` | Servidor, versión, User-Agent (`HGLCApp-iOS/x.y`), entorno de APNs. |
| `Portal/PortalWebView.swift` | WKWebView y sus delegados: navegación, descargas, diálogos JS, puente. |
| `Portal/NavigationPolicy.swift` | Qué queda dentro de la app y qué se abre fuera. Con pruebas. |
| `Portal/PortalModel.swift` | Estado de carga, sin conexión, registro de avisos, cierre de sesión. |
| `Portal/Documentos.swift` | Descargas temporales cifradas y Quick Look. |
| `Resources/puente.js` | Se inyecta en cada página: `window.HGLCApp`. |
| `Resources/llamada-proxy.js` | POST al proxy del portal con la sesión y el CSRF de la página. |
| `Push/PushManager.swift` | Permiso y token del dispositivo. |
| `Seguridad/AppLock.swift` | Face ID, escudo y la ventana que lo pone por encima de todo. |
| `Interfaz/` | Portada, sin conexión, bloqueo, bienvenida. |
| `Interfaz/Estilo.swift` | Colores, letra Outfit (la del portal), botones y tarjetas de las pantallas nativas. |
| `Resources/Fuentes/` | Outfit 600/700/800 en TTF, sacadas de `assets/fonts/` del sitio (licencia OFL). |
| `generar-proyecto.rb` | Regenera `MiHospital.xcodeproj` (gema `xcodeproj`). |

Del lado web, el portal reconoce la app por `window.HGLCApp` (lo inyecta
`puente.js`): no ofrece «Instalar la app», `HGLCPush` pide los avisos a la app
en vez de usar Web Push, y Mi perfil muestra la tarjeta de Face ID.

### Decisiones

- **La sesión es la del portal.** La app no guarda credenciales ni el JWT, que
  sigue solo en el servidor. Face ID protege la sesión abierta; no reemplaza el
  inicio de sesión. Mantener la sesión días seguidos requeriría tokens de
  renovación en la API interna: es una decisión de seguridad aparte.
- **Sin «tirar para recargar».** Se quitó a propósito de la PWA porque delata
  que es una web; aquí tampoco.
- **De borde a borde.** El CSS del portal ya respeta el notch y la barra de
  inicio con `env(safe-area-inset-*)`.
- **Las páginas públicas del sitio se abren aparte**: dentro de la vista web
  no hay barra de direcciones para volver al portal.
- **Sin detección de datos**: una cédula como 402‑1234567‑8 no debe volverse un
  enlace de teléfono.
- **Siguiente paso posible**: *Universal Links* (los enlaces del portal en los
  correos abren la app) y autollenado de contraseñas del llavero. Necesitan el
  Team ID en un `apple-app-site-association` del sitio.

## Verificación hecha sin Xcode

El código se escribió en un entorno Linux, sin compilador de Swift. Se verificó:

- Sintaxis de los 15 archivos Swift con un analizador de Swift (no compila ni
  revisa tipos: eso lo hace Xcode al compilar).
- `puente.js` y `llamada-proxy.js` con `node --check`.
- El proyecto generado: cada referencia apunta a un archivo existente, las
  fases son correctas, `Info.plist` y entitlements no se copian al bundle, el
  esquema lanza la app y corre las pruebas.
- `Info.plist`, entitlements y manifiesto de privacidad, como plist válidos.
- El ícono: 1024×1024 sin canal alfa, sacado del logo en alta resolución.
- El puente y la web, de punta a punta en Chromium simulando la app (26
  comprobaciones): el `puente.js` real inyectado, el registro del token por el
  `portal-proxy.php` real (sesión y CSRF) hasta los endpoints reales de
  `docs/ios-push` sobre MariaDB, la bitácora de auditoría PHI, el CSRF falso
  rechazado, Mi Ciclo, Face ID en el perfil, y que en Safari nada cambie.
- El envío por APNs contra un APNs simulado por HTTP/2 (ver `docs/ios-push/`).
