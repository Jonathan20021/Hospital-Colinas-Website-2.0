import UIKit
import UserNotifications

/// Lo que en SwiftUI todavía pasa por el delegado de la app: el token de APNs y
/// las notificaciones.
final class AppDelegate: NSObject, UIApplicationDelegate, UNUserNotificationCenterDelegate {

    func application(_ application: UIApplication,
                     didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]? = nil) -> Bool {
        // Antes de volver de este método, para recibir el toque que abrió la app.
        UNUserNotificationCenter.current().delegate = self
        // Documentos de una sesión anterior (datos clínicos): no se conservan.
        Descargas.vaciar()

        Task { @MainActor in
            _ = AppLock.shared
            let push = PushManager.shared
            await push.actualizarPermiso()
            // Apple recomienda registrarse en cada arranque: el token puede cambiar.
            if push.permiso == .concedido {
                UIApplication.shared.registerForRemoteNotifications()
            }
        }
        return true
    }

    /// Delegado de escena propio solo para los accesos rápidos del ícono
    /// (mantener pulsado en la pantalla de inicio). SwiftUI sigue manejando la ventana.
    func application(_ application: UIApplication,
                     configurationForConnecting connectingSceneSession: UISceneSession,
                     options: UIScene.ConnectionOptions) -> UISceneConfiguration {
        let configuracion = UISceneConfiguration(name: nil, sessionRole: connectingSceneSession.role)
        configuracion.delegateClass = EscenaDelegate.self
        return configuracion
    }

    func application(_ application: UIApplication,
                     didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data) {
        Task { @MainActor in
            PushManager.shared.tokenRecibido(deviceToken)
        }
    }

    func application(_ application: UIApplication,
                     didFailToRegisterForRemoteNotificationsWithError error: Error) {
        Task { @MainActor in
            PushManager.shared.tokenFallido(error)
        }
    }

    // Con la app abierta, el aviso se muestra igual (banner y sonido).
    func userNotificationCenter(_ center: UNUserNotificationCenter,
                                willPresent notification: UNNotification,
                                withCompletionHandler completionHandler:
                                    @escaping (UNNotificationPresentationOptions) -> Void) {
        completionHandler([.banner, .list, .sound])
    }

    // Al tocar el aviso se abre la sección del portal que indica su campo "url".
    func userNotificationCenter(_ center: UNUserNotificationCenter,
                                didReceive response: UNNotificationResponse,
                                withCompletionHandler completionHandler: @escaping () -> Void) {
        let ruta = response.notification.request.content.userInfo["url"] as? String
        Task { @MainActor in
            PushManager.shared.abrirDesdeNotificacion(ruta: ruta)
        }
        completionHandler()
    }
}

/// Accesos rápidos del ícono (Agendar cita, Mis citas, Mensajes, Recetas, en
/// `UIApplicationShortcutItems` del Info.plist). Cada uno lleva la ruta del
/// portal en su `userInfo`; se abre igual que una notificación tocada, con la
/// misma validación (`NavigationPolicy.urlParaRuta`).
final class EscenaDelegate: NSObject, UIWindowSceneDelegate {

    /// App cerrada: el acceso llega con la conexión de la escena.
    func scene(_ scene: UIScene, willConnectTo session: UISceneSession,
               options connectionOptions: UIScene.ConnectionOptions) {
        if let acceso = connectionOptions.shortcutItem {
            abrir(acceso)
        }
    }

    /// App en segundo plano.
    func windowScene(_ windowScene: UIWindowScene, performActionFor shortcutItem: UIApplicationShortcutItem,
                     completionHandler: @escaping (Bool) -> Void) {
        completionHandler(abrir(shortcutItem))
    }

    @discardableResult
    private func abrir(_ acceso: UIApplicationShortcutItem) -> Bool {
        guard let ruta = acceso.userInfo?["ruta"] as? String else { return false }
        Task { @MainActor in
            PushManager.shared.abrirDesdeNotificacion(ruta: ruta)
        }
        return true
    }
}
