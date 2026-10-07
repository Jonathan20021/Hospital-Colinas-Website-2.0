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
