import Foundation

/// Configuración de la app. Los valores vienen del Info.plist, que a su vez los
/// toma de `Config/Base.xcconfig`: para apuntar a otro servidor (pruebas) se
/// cambia allí, no aquí.
enum AppConfig {

    /// Host del portal, sin esquema ni barras (p. ej. "colinashospital.com").
    static let hostPortal: String = {
        let valor = Bundle.main.object(forInfoDictionaryKey: "HGLCPortalHost") as? String
        let limpio = (valor ?? "").trimmingCharacters(in: .whitespacesAndNewlines)
        return limpio.isEmpty ? "colinashospital.com" : limpio.lowercased()
    }()

    /// Primera página. El portal decide a dónde ir: al inicio si hay sesión,
    /// al login si no. `source=ios` es el equivalente al `source=pwa` de la PWA.
    static var urlInicial: URL {
        URL(string: "https://\(hostPortal)/portal/?source=ios")!
    }

    static let version: String =
        Bundle.main.object(forInfoDictionaryKey: "CFBundleShortVersionString") as? String ?? "1.0"

    static let build: String =
        Bundle.main.object(forInfoDictionaryKey: "CFBundleVersion") as? String ?? "1"

    /// Se agrega al User-Agent. Con él el servidor distingue el tráfico de la app
    /// (analítica) y la web puede saber que corre dentro de ella.
    static var agenteDeUsuario: String {
        "Mobile/15E148 HGLCApp-iOS/\(version)"
    }

    /// Entorno de APNs. Un build de depuración lanzado desde Xcode se firma con
    /// `aps-environment = development` (sandbox); al archivar para TestFlight o
    /// App Store, Xcode lo cambia a `production`. El servidor necesita saberlo
    /// porque cada entorno tiene su propio dominio de envío.
    static var entornoAPNs: String {
        #if DEBUG
        return "sandbox"
        #else
        return "production"
        #endif
    }

    /// Nombre de la cookie de sesión del portal (`portal_session_start()` en PHP).
    static let cookieDeSesion = "HGLC_PORTAL"

    /// Teléfono del hospital, para la pantalla sin conexión.
    static let telefonoVisible = "(809) 806-0444"
    static let telefonoMarcable = URL(string: "tel:8098060444")!
}
