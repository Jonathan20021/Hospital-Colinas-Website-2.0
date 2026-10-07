import UIKit
import UserNotifications

/// Permiso y token de notificaciones (APNs) del dispositivo.
///
/// Aquí solo vive lo que es del iPhone: el permiso y el token. Registrar el
/// token a nombre del paciente se hace por el proxy del portal, con la sesión
/// de la página (ver `PortalModel.registrarPushSiProcede`).
@MainActor
final class PushManager: ObservableObject {

    static let shared = PushManager()

    enum Permiso: String {
        case concedido = "granted"
        case denegado = "denied"
        case pendiente = "default"
    }

    @Published private(set) var permiso: Permiso = .pendiente
    @Published private(set) var token: String?

    /// Ruta del portal que pidió abrir una notificación tocada. La consume la vista.
    @Published var rutaPendiente: String?

    /// El paciente apagó los avisos desde su perfil: no se vuelve a registrar
    /// el token solo, aunque el permiso del sistema siga concedido.
    var desactivadoPorUsuario: Bool {
        get { UserDefaults.standard.bool(forKey: Claves.desactivado) }
        set { UserDefaults.standard.set(newValue, forKey: Claves.desactivado) }
    }

    private enum Claves {
        static let desactivado = "hglc.push.desactivadoPorUsuario"
    }

    private var esperandoToken: [CheckedContinuation<String?, Never>] = []

    private init() {}

    // MARK: Permiso

    func actualizarPermiso() async {
        let ajustes = await UNUserNotificationCenter.current().notificationSettings()
        switch ajustes.authorizationStatus {
        case .authorized, .provisional, .ephemeral:
            permiso = .concedido
        case .denied:
            permiso = .denegado
        case .notDetermined:
            permiso = .pendiente
        @unknown default:
            permiso = .pendiente
        }
    }

    /// Pide permiso (si nunca se pidió) y devuelve el token. `nil` si no hay
    /// permiso o APNs no respondió (simulador sin cuenta, sin red…).
    func solicitar() async -> String? {
        await actualizarPermiso()
        if permiso == .pendiente {
            AppLock.shared.suspenderCubierta = true   // la alerta del sistema no debe tapar la app
            let concedido = (try? await UNUserNotificationCenter.current()
                .requestAuthorization(options: [.alert, .sound, .badge])) ?? false
            AppLock.shared.suspenderCubierta = false
            permiso = concedido ? .concedido : .denegado
        }
        guard permiso == .concedido else { return nil }
        return await obtenerToken()
    }

    /// Token de APNs, pidiéndoselo al sistema si hace falta. Apple recomienda
    /// registrarse en cada arranque: el token puede cambiar.
    func obtenerToken(espera segundos: Double = 10) async -> String? {
        if let token { return token }
        UIApplication.shared.registerForRemoteNotifications()
        return await withCheckedContinuation { (continuacion: CheckedContinuation<String?, Never>) in
            esperandoToken.append(continuacion)
            Task { @MainActor [weak self] in
                try? await Task.sleep(nanoseconds: UInt64(segundos * 1_000_000_000))
                self?.liberarEsperas()
            }
        }
    }

    // MARK: Callbacks del AppDelegate

    func tokenRecibido(_ datos: Data) {
        token = datos.map { String(format: "%02x", $0) }.joined()
        liberarEsperas()
    }

    func tokenFallido(_ error: Error) {
        #if DEBUG
        print("[push] No se obtuvo token de APNs: \(error.localizedDescription)")
        #endif
        liberarEsperas()
    }

    /// Toque en una notificación (o en un acceso rápido del ícono). Solo se
    /// aceptan rutas del portal; la validación final la hace
    /// `NavigationPolicy.urlParaRuta`.
    func abrirDesdeNotificacion(ruta: String?) {
        guard let ruta, !ruta.isEmpty else { return }
        rutaPendiente = ruta
    }

    func appActiva() async {
        await actualizarPermiso()
        try? await UNUserNotificationCenter.current().setBadgeCount(0)
    }

    func abrirAjustesDelSistema() {
        guard let url = URL(string: UIApplication.openSettingsURLString) else { return }
        UIApplication.shared.open(url)
    }

    private func liberarEsperas() {
        let pendientes = esperandoToken
        esperandoToken.removeAll()
        for continuacion in pendientes {
            continuacion.resume(returning: token)
        }
    }
}
