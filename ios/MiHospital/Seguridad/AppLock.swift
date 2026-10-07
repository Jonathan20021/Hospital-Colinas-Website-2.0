import LocalAuthentication
import SwiftUI
import UIKit
import WebKit

/// Bloqueo con Face ID / Touch ID y escudo de privacidad.
///
/// - Escudo (siempre): al salir de la app se tapa la pantalla, para que la
///   miniatura del selector de apps no muestre datos clínicos.
/// - Bloqueo (opcional, lo activa el paciente): al abrir la app o al volver
///   tras más de `gracia` segundos fuera, pide Face ID (o el código del
///   dispositivo si falla). Solo bloquea si hay una sesión del portal guardada:
///   sin sesión no hay nada que proteger y sería pedir Face ID para ver el login.
@MainActor
final class AppLock: NSObject, ObservableObject {

    static let shared = AppLock()

    /// Hay que autenticarse para volver a ver el portal.
    @Published private(set) var bloqueada = false
    /// Algo tapa la app (escudo o bloqueo).
    @Published private(set) var cubierta = false
    /// El paciente activó el bloqueo.
    @Published private(set) var activo: Bool
    /// El último intento de desbloqueo falló (no cuenta si el paciente canceló).
    @Published private(set) var falloDesbloqueo = false

    /// Mientras la app muestra una alerta del sistema que pidió ella misma
    /// (permiso de avisos), perder el foco no debe tapar la pantalla.
    var suspenderCubierta = false

    /// Margen para salir un momento (p. ej. a copiar el código del correo) sin
    /// que pida Face ID al volver.
    let gracia: TimeInterval = 60

    private var enFondoDesde: Date?
    private var esArranque = true
    private var autenticando = false
    private let ventana = VentanaCubierta()

    private enum Claves {
        static let activo = "hglc.bloqueo.activo"
    }

    private override init() {
        activo = UserDefaults.standard.bool(forKey: Claves.activo)
        super.init()
        // Con el bloqueo activo la app arranca tapada hasta saber si hay sesión.
        cubierta = activo

        let centro = NotificationCenter.default
        centro.addObserver(self, selector: #selector(perderFoco),
                           name: UIApplication.willResignActiveNotification, object: nil)
        centro.addObserver(self, selector: #selector(irAlFondo),
                           name: UIApplication.didEnterBackgroundNotification, object: nil)
        centro.addObserver(self, selector: #selector(activarse),
                           name: UIApplication.didBecomeActiveNotification, object: nil)
    }

    // MARK: Estado para la web y la interfaz

    var disponible: Bool {
        LAContext().canEvaluatePolicy(.deviceOwnerAuthentication, error: nil)
    }

    var nombreBiometria: String {
        let contexto = LAContext()
        _ = contexto.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: nil)
        switch contexto.biometryType {
        case .faceID: return "Face ID"
        case .touchID: return "Touch ID"
        default: return "el código del dispositivo"
        }
    }

    var iconoBiometria: String {
        let contexto = LAContext()
        _ = contexto.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: nil)
        switch contexto.biometryType {
        case .faceID: return "faceid"
        case .touchID: return "touchid"
        default: return "lock.fill"
        }
    }

    func estado() -> [String: Any] {
        ["disponible": disponible, "activo": activo, "tipo": nombreBiometria]
    }

    // MARK: Activar / desactivar (pide autenticación en ambos casos)

    func activar() async -> Bool {
        guard disponible else { return false }
        guard await autenticar(razon: "Confirma que eres tú para proteger el portal en este dispositivo.") == .ok else {
            return false
        }
        activo = true
        UserDefaults.standard.set(true, forKey: Claves.activo)
        return true
    }

    func desactivar() async -> Bool {
        guard await autenticar(razon: "Confirma que eres tú para quitar la protección del portal.") == .ok else {
            return false
        }
        activo = false
        UserDefaults.standard.set(false, forKey: Claves.activo)
        return true
    }

    func desbloquear() async {
        guard bloqueada, !autenticando else { return }
        switch await autenticar(razon: "Desbloquea el portal para ver tu información médica.") {
        case .ok:
            falloDesbloqueo = false
            Haptica.exito()
            bloqueada = false
            descubrir()
        case .fallo:
            falloDesbloqueo = true
            Haptica.error()
        case .cancelado:
            break
        }
    }

    // MARK: Ciclo de vida de la app

    /// Arranque en frío. La llama la vista principal al aparecer (no depende de
    /// que este objeto ya existiera cuando el sistema avisó que la app se activó).
    func evaluarArranque() async {
        guard esArranque else { return }
        esArranque = false
        guard activo else {
            descubrir()
            return
        }
        if await Self.haySesionGuardada() {
            bloqueada = true
            cubrir()
        } else {
            descubrir()
        }
    }

    @objc private func perderFoco() {
        guard !suspenderCubierta, !autenticando else { return }
        cubrir()
    }

    @objc private func irAlFondo() {
        if enFondoDesde == nil { enFondoDesde = Date() }
        cubrir()
    }

    @objc private func activarse() {
        let fuera = enFondoDesde.map { Date().timeIntervalSince($0) } ?? 0
        enFondoDesde = nil
        // El arranque lo resuelve evaluarArranque(); el bloqueo ya visible se
        // queda hasta que el paciente se autentique.
        if esArranque || bloqueada || autenticando { return }
        guard activo, fuera >= gracia else {
            descubrir()
            return
        }
        Task { @MainActor in
            if await Self.haySesionGuardada() {
                bloqueada = true
                cubrir()
            } else {
                descubrir()
            }
        }
    }

    private func cubrir() {
        cubierta = true
        ventana.mostrar()
    }

    private func descubrir() {
        guard !bloqueada else { return }
        cubierta = false
        ventana.ocultar()
    }

    private enum Resultado { case ok, fallo, cancelado }

    private func autenticar(razon: String) async -> Resultado {
        let contexto = LAContext()
        contexto.localizedCancelTitle = "Cancelar"
        var error: NSError?
        guard contexto.canEvaluatePolicy(.deviceOwnerAuthentication, error: &error) else { return .fallo }
        autenticando = true
        defer { autenticando = false }
        do {
            let ok = try await contexto.evaluatePolicy(.deviceOwnerAuthentication, localizedReason: razon)
            return ok ? .ok : .fallo
        } catch let error as LAError where [.userCancel, .systemCancel, .appCancel].contains(error.code) {
            return .cancelado
        } catch {
            return .fallo
        }
    }

    /// ¿Queda una cookie de sesión del portal? Es la única señal fiable antes de
    /// cargar ninguna página (la cookie la borra `logout.php`).
    static func haySesionGuardada() async -> Bool {
        let cookies: [HTTPCookie] = await withCheckedContinuation { continuacion in
            WKWebsiteDataStore.default().httpCookieStore.getAllCookies { lista in
                continuacion.resume(returning: lista)
            }
        }
        let host = AppConfig.hostPortal
        return cookies.contains { cookie in
            let dominio = cookie.domain.trimmingCharacters(in: CharacterSet(charactersIn: ".")).lowercased()
            return cookie.name == AppConfig.cookieDeSesion
                && (dominio == host || host.hasSuffix("." + dominio))
        }
    }
}

/// Ventana propia por encima de todo (incluidas las hojas de Quick Look o
/// Safari y las alertas): una hoja abierta con una receta no debe quedar
/// visible detrás del bloqueo.
@MainActor
final class VentanaCubierta {

    private var ventana: UIWindow?

    func mostrar() {
        if ventana == nil {
            let escenas = UIApplication.shared.connectedScenes.compactMap { $0 as? UIWindowScene }
            let escena = escenas.first { $0.activationState == .foregroundActive }
                ?? escenas.first { $0.activationState == .foregroundInactive }
                ?? escenas.first
            guard let escena else { return }
            let nueva = UIWindow(windowScene: escena)
            nueva.windowLevel = UIWindow.Level(rawValue: UIWindow.Level.alert.rawValue + 1)
            nueva.rootViewController = ControladorCubierta(rootView: CubiertaView(bloqueo: AppLock.shared))
            ventana = nueva
        }
        ventana?.isHidden = false
    }

    func ocultar() {
        ventana?.isHidden = true
    }
}

/// Sin barra de estado sobre la portada navy: su texto oscuro no se leería.
final class ControladorCubierta: UIHostingController<CubiertaView> {
    override var prefersStatusBarHidden: Bool { true }
}
