import Network
import SafariServices
import SwiftUI
import UIKit
import WebKit

/// Estado del portal dentro de la app y todo lo que la app hace en nombre de
/// la página: abrir documentos, enlaces externos, registrar los avisos.
@MainActor
final class PortalModel: ObservableObject {

    enum Estado: Equatable {
        case cargando
        case listo
        case sinConexion
        case error
    }

    /// Lo que pide la web por el puente `window.HGLCApp.llamar(...)`.
    struct Peticion {
        let accion: String
        let ruta: String?
        let sesion: Bool
    }

    @Published private(set) var estado: Estado = .cargando
    @Published private(set) var cargando = false
    @Published private(set) var progreso: Double = 0
    /// La primera página terminó (o falló): se retira la portada.
    @Published private(set) var primeraCargaLista = false
    @Published var mostrarBienvenida = false
    /// El iPhone tiene red. Sin ella, con el portal ya abierto, se avisa que
    /// lo que se ve puede no estar al día.
    @Published private(set) var hayRed = true
    /// Progreso (0…1) del documento que se está descargando; `nil` si no hay.
    @Published private(set) var progresoDocumento: Double?
    private var cancelarDescarga: (() -> Void)?
    /// Cambia al cerrar sesión: la vista se recrea con una vista web nueva, sin
    /// el historial del paciente anterior (ver `cerrarSesion`).
    @Published private(set) var generacionVistaWeb = 0

    let politica = NavigationPolicy(hostPortal: AppConfig.hostPortal)

    weak var vistaWeb: WKWebView?

    /// Lo informa cada página al cargar (`.portal-shell-app` solo existe con sesión).
    private(set) var haySesion = false
    private var pushRegistradoEnSesion = false
    /// Un intento automático por sesión: si el servidor aún no tiene el
    /// endpoint (404) no se reintenta en cada página (cada intento queda en la
    /// bitácora de auditoría del proxy). Activar desde el perfil sí reintenta.
    private var registroFallidoEnSesion = false
    private var registrandoPush = false
    private var cerrandoSesion = false
    private var urlPendiente: URL?
    private var ultimaURL: URL?
    private let monitorRed = NWPathMonitor()

    private enum Claves {
        static let bienvenida = "hglc.bienvenida.mostrada"
    }

    init() {
        monitorRed.pathUpdateHandler = { [weak self] ruta in
            let conectado = ruta.status == .satisfied
            Task { @MainActor [weak self] in
                self?.redCambio(conectado)
            }
        }
        monitorRed.start(queue: DispatchQueue(label: "hglc.red"))
    }

    // MARK: Carga

    /// URL con la que arranca la vista web: la de una notificación tocada con
    /// la app cerrada o, si no hay, la entrada del portal.
    func urlDeArranque() -> URL {
        if let pendiente = urlPendiente {
            urlPendiente = nil
            return pendiente
        }
        return AppConfig.urlInicial
    }

    /// Abre una sección del portal (desde una notificación).
    func abrir(ruta: String) {
        guard let url = politica.urlParaRuta(ruta) else { return }
        if let vistaWeb {
            vistaWeb.load(URLRequest(url: url))
        } else {
            urlPendiente = url
        }
    }

    func navegacionIntentada(_ url: URL) {
        ultimaURL = url
    }

    func empezoCarga() {
        cargando = true
        progreso = max(progreso, 0.1)
    }

    func progresoCambio(_ valor: Double) {
        progreso = valor
    }

    func terminoCarga() {
        cargando = false
        progreso = 0
        estado = .listo
        primeraCargaLista = true
    }

    func falloCarga(_ error: Error, provisional: Bool) {
        cargando = false
        progreso = 0
        let e = error as NSError
        // Cancelaciones propias: otra navegación, una política o una descarga.
        if e.domain == NSURLErrorDomain && e.code == NSURLErrorCancelled { return }
        if e.domain == "WebKitErrorDomain" && (e.code == 102 || e.code == 204) { return }

        let sinRed = Self.esFaltaDeRed(e)
        // Si la página ya se veía y falla después, solo importa si fue la red.
        guard provisional || sinRed else { return }
        estado = sinRed ? .sinConexion : .error
        primeraCargaLista = true
    }

    func reintentar() {
        estado = .cargando
        guard let vistaWeb else { return }
        vistaWeb.load(URLRequest(url: ultimaURL ?? AppConfig.urlInicial))
    }

    /// iOS cierra el proceso web cuando le falta memoria: sin esto quedaría
    /// una pantalla en blanco.
    func procesoWebTerminado() {
        guard let vistaWeb else { return }
        if vistaWeb.url != nil {
            vistaWeb.reload()
        } else {
            vistaWeb.load(URLRequest(url: ultimaURL ?? AppConfig.urlInicial))
        }
    }

    /// Al volver a la app: si quedó la pantalla sin conexión o de error, se
    /// reintenta sin esperar a que el paciente toque "Reintentar".
    func appActiva() {
        if estado == .sinConexion || estado == .error {
            reintentar()
        }
    }

    private func redCambio(_ conectado: Bool) {
        let volvio = conectado && !hayRed
        if hayRed != conectado { hayRed = conectado }
        if volvio && estado == .sinConexion {
            reintentar()
        }
    }

    static func esFaltaDeRed(_ e: NSError) -> Bool {
        guard e.domain == NSURLErrorDomain else { return false }
        return [
            NSURLErrorNotConnectedToInternet, NSURLErrorNetworkConnectionLost, NSURLErrorTimedOut,
            NSURLErrorCannotFindHost, NSURLErrorCannotConnectToHost, NSURLErrorDNSLookupFailed,
            NSURLErrorInternationalRoamingOff, NSURLErrorDataNotAllowed, NSURLErrorCallIsActive,
        ].contains(e.code)
    }

    // MARK: Fuera del portal

    func abrirFuera(_ url: URL, enNavegador: Bool) {
        if enNavegador, let superior = UIApplication.shared.controladorSuperior {
            let safari = SFSafariViewController(url: url)
            safari.preferredControlTintColor = UIColor(named: "Navy")
            safari.dismissButtonStyle = .close
            superior.present(safari, animated: true)
        } else {
            UIApplication.shared.open(url)
        }
    }

    func descargaEmpezo(cancelar: @escaping () -> Void) {
        progresoDocumento = 0
        cancelarDescarga = cancelar
    }

    func descargaAvanzo(_ valor: Double) {
        guard progresoDocumento != nil else { return }
        progresoDocumento = valor
    }

    func descargaTermino() {
        progresoDocumento = nil
        cancelarDescarga = nil
    }

    /// El paciente tocó "Cancelar" en la tarjeta del documento.
    func cancelarDocumento() {
        cancelarDescarga?()
        descargaTermino()
    }

    func mostrarDocumento(_ archivo: URL) {
        guard let superior = UIApplication.shared.controladorSuperior else {
            Descargas.borrar(archivo)
            return
        }
        VistaPreviaDocumento(archivo: archivo).presentar(desde: superior)
    }

    func avisar(_ mensaje: String) {
        guard let superior = UIApplication.shared.controladorSuperior else { return }
        let alerta = UIAlertController(title: nil, message: mensaje, preferredStyle: .alert)
        alerta.addAction(UIAlertAction(title: "Aceptar", style: .default))
        superior.present(alerta, animated: true)
    }

    // MARK: Puente con la web

    /// Respuesta para `HGLCApp.llamar(...)`; `nil` = acción desconocida.
    func atender(_ peticion: Peticion) async -> Any? {
        switch peticion.accion {
        case "app.info":
            return [
                "plataforma": "ios",
                "version": AppConfig.version,
                "build": AppConfig.build,
                "entorno": AppConfig.entornoAPNs,
            ]
        case "pagina.lista":
            paginaLista(sesion: peticion.sesion)
            return true
        case "push.estado":
            return await estadoPush()
        case "push.activar":
            return await activarPush()
        case "push.desactivar":
            return await desactivarPush()
        case "push.ajustes":
            PushManager.shared.abrirAjustesDelSistema()
            return true
        case "bloqueo.estado":
            return AppLock.shared.estado()
        case "bloqueo.activar":
            let ok = await AppLock.shared.activar()
            return ["ok": ok, "estado": AppLock.shared.estado()]
        case "bloqueo.desactivar":
            let ok = await AppLock.shared.desactivar()
            return ["ok": ok, "estado": AppLock.shared.estado()]
        default:
            return nil
        }
    }

    private func paginaLista(sesion: Bool) {
        if !sesion {
            // Sesión cerrada o vencida: el próximo paciente que entre en este
            // iPhone se registra a su nombre (el servidor reasigna el token).
            pushRegistradoEnSesion = false
            registroFallidoEnSesion = false
        }
        haySesion = sesion
        guard sesion else { return }
        Task {
            await registrarPushSiProcede()
            await ofrecerBienvenidaSiCorresponde()
        }
    }

    /// Una sola vez, tras el primer inicio de sesión en la app: ofrece avisos y
    /// Face ID explicando para qué, antes de que aparezcan las alertas del sistema.
    private func ofrecerBienvenidaSiCorresponde() async {
        let preferencias = UserDefaults.standard
        guard !preferencias.bool(forKey: Claves.bienvenida) else { return }
        await PushManager.shared.actualizarPermiso()
        let ofrecePush = PushManager.shared.permiso == .pendiente
        let ofreceBloqueo = AppLock.shared.disponible && !AppLock.shared.activo
        preferencias.set(true, forKey: Claves.bienvenida)
        guard ofrecePush || ofreceBloqueo else { return }
        try? await Task.sleep(nanoseconds: 700_000_000)   // que primero se vea el portal
        mostrarBienvenida = true
    }

    // MARK: Avisos (APNs)

    /// Registra el token del iPhone a nombre del paciente con sesión, una vez
    /// por sesión. Va por el proxy del portal con la cookie y el CSRF de la
    /// página: la app no maneja credenciales propias.
    func registrarPushSiProcede() async {
        let push = PushManager.shared
        guard haySesion, !pushRegistradoEnSesion, !registroFallidoEnSesion, !registrandoPush,
              !push.desactivadoPorUsuario else { return }
        await push.actualizarPermiso()
        guard push.permiso == .concedido else { return }
        registrandoPush = true
        defer { registrandoPush = false }
        guard let token = await push.obtenerToken() else {
            registroFallidoEnSesion = true
            return
        }
        pushRegistradoEnSesion = await registrarEnServidor(token: token)
        registroFallidoEnSesion = !pushRegistradoEnSesion
    }

    /// Activación pedida por el paciente (perfil del portal o bienvenida).
    func activarPush() async -> [String: Any] {
        let push = PushManager.shared
        push.desactivadoPorUsuario = false
        guard let token = await push.solicitar() else {
            return ["ok": false, "motivo": push.permiso == .denegado ? "denied" : "sin_token"]
        }
        guard haySesion else { return ["ok": false, "motivo": "sin_sesion"] }
        pushRegistradoEnSesion = await registrarEnServidor(token: token)
        registroFallidoEnSesion = !pushRegistradoEnSesion
        return pushRegistradoEnSesion ? ["ok": true] : ["ok": false, "motivo": "save_failed"]
    }

    func desactivarPush() async -> [String: Any] {
        let push = PushManager.shared
        push.desactivadoPorUsuario = true
        pushRegistradoEnSesion = false
        guard let token = push.token else { return ["ok": true] }
        let codigo = await llamarProxy(ruta: "/portal/me/push/apns/unsubscribe", cuerpo: ["token": token])
        return ["ok": (200..<300).contains(codigo)]
    }

    func estadoPush() async -> [String: Any] {
        let push = PushManager.shared
        await push.actualizarPermiso()
        return [
            "supported": true,
            "permission": push.permiso.rawValue,
            "subscribed": push.permiso == .concedido && pushRegistradoEnSesion && !push.desactivadoPorUsuario,
        ]
    }

    private func registrarEnServidor(token: String) async -> Bool {
        let cuerpo: [String: Any] = [
            "token": token,
            "entorno": AppConfig.entornoAPNs,
            "bundle_id": Bundle.main.bundleIdentifier ?? "",
            "app_version": AppConfig.version,
            "os_version": UIDevice.current.systemVersion,
            "dispositivo": UIDevice.current.model,
        ]
        let codigo = await llamarProxy(ruta: "/portal/me/push/apns/subscribe", cuerpo: cuerpo)
        #if DEBUG
        print("[push] Registro del token en el portal: HTTP \(codigo)")
        #endif
        return (200..<300).contains(codigo)
    }

    /// POST al proxy del portal desde la propia página (ver `BridgeScript.llamadaAlProxy`).
    func llamarProxy(ruta: String, cuerpo: [String: Any], tiempoMaximoMs: Int = 8000) async -> Int {
        guard let vistaWeb else { return 0 }
        return await withCheckedContinuation { continuacion in
            vistaWeb.callAsyncJavaScript(
                BridgeScript.llamadaAlProxy,
                arguments: ["path": ruta, "body": cuerpo, "ms": tiempoMaximoMs],
                in: nil,
                in: .page
            ) { resultado in
                switch resultado {
                case .success(let valor):
                    continuacion.resume(returning: (valor as? NSNumber)?.intValue ?? 0)
                case .failure:
                    continuacion.resume(returning: 0)
                }
            }
        }
    }

    // MARK: Cierre de sesión

    /// El paciente tocó "Cerrar sesión". Antes de que el servidor destruya la
    /// sesión se da de baja el token: este iPhone deja de recibir sus avisos.
    func cerrarSesion(url: URL) {
        guard !cerrandoSesion else { return }
        cerrandoSesion = true
        Task {
            if pushRegistradoEnSesion, let token = PushManager.shared.token {
                _ = await llamarProxy(ruta: "/portal/me/push/apns/unsubscribe",
                                      cuerpo: ["token": token], tiempoMaximoMs: 3000)
            }
            pushRegistradoEnSesion = false
            haySesion = false
            // El historial de la vista web guarda las páginas del paciente: con
            // el gesto de volver desde el borde se verían (WebKit muestra una
            // captura de la página anterior) en un iPhone que usa otra persona.
            // WKWebView no deja borrar su historial, así que se cambia por una
            // vista web nueva que arranca en el cierre de sesión. La caché se
            // vacía también; las cookies no (las borra el propio logout).
            await WKWebsiteDataStore.default().removeData(
                ofTypes: [WKWebsiteDataTypeDiskCache, WKWebsiteDataTypeMemoryCache],
                modifiedSince: .distantPast
            )
            urlPendiente = url
            ultimaURL = nil
            primeraCargaLista = false
            generacionVistaWeb += 1
        }
    }

    /// La carga de logout que lanza la propia app (tras dar de baja el token) pasa.
    func dejarPasarCierreDeSesion() -> Bool {
        guard cerrandoSesion else { return false }
        cerrandoSesion = false
        return true
    }
}
