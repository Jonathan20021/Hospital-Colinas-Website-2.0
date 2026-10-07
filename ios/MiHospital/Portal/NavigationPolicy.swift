import Foundation

/// Decide qué pasa con cada enlace que se toca dentro de la vista web.
///
/// Lógica pura (sin UIKit ni WebKit) para poder probarla con XCTest: aquí se
/// decide qué contenido queda DENTRO de la app con la sesión del paciente, así
/// que conviene que esté cubierto por pruebas (ver NavigationPolicyTests).
struct NavigationPolicy: Equatable {

    enum Decision: Equatable {
        /// Se carga dentro de la app: el portal y sus recursos.
        case permitir
        /// Igual que `permitir`, pero subiendo a HTTPS (el portal nunca va en claro).
        case permitirSeguro(URL)
        /// Web que no es el portal: se abre en una hoja de Safari dentro de la app,
        /// con su botón de cerrar, para que el paciente no se pierda fuera del portal.
        case abrirEnNavegador(URL)
        /// Teléfono, correo, mapas, WhatsApp…: lo resuelve el sistema.
        case abrirConSistema(URL)
        /// Esquemas no admitidos (javascript:, file:, desconocidos).
        case bloquear
    }

    let hostPortal: String

    /// Rutas del mismo dominio que pertenecen a la app. El resto del sitio (las
    /// páginas públicas, el portal médico) se abre aparte: el paciente no tiene
    /// barra de direcciones ni botón "atrás" para volver al portal desde ahí.
    static let prefijosDeLaApp = ["/portal/", "/api/", "/assets/"]

    static let esquemasDelSistema: Set<String> = [
        "tel", "telprompt", "mailto", "sms", "facetime", "facetime-audio",
        "maps", "comgooglemaps", "whatsapp", "itms-apps", "itms-appss",
    ]

    init(hostPortal: String) {
        self.hostPortal = hostPortal.lowercased()
    }

    func decidir(_ url: URL, esMarcoPrincipal: Bool) -> Decision {
        let esquema = url.scheme?.lowercased() ?? ""

        switch esquema {
        case "about", "data", "blob":
            return .permitir
        case "http", "https":
            break
        default:
            return Self.esquemasDelSistema.contains(esquema) ? .abrirConSistema(url) : .bloquear
        }

        // Los iframes (si algún día los hay) quedan bajo la CSP del propio portal.
        guard esMarcoPrincipal else { return .permitir }

        guard let host = url.host?.lowercased(), !host.isEmpty else { return .bloquear }

        guard esHostDelPortal(host), esRutaDeLaApp(url) else {
            return .abrirEnNavegador(url)
        }

        if esquema == "http" {
            var componentes = URLComponents(url: url, resolvingAgainstBaseURL: false)
            componentes?.scheme = "https"
            guard let segura = componentes?.url else { return .bloquear }
            return .permitirSeguro(segura)
        }
        return .permitir
    }

    func esHostDelPortal(_ host: String) -> Bool {
        let h = host.lowercased()
        return h == hostPortal || h == "www." + hostPortal
    }

    func esRutaDeLaApp(_ url: URL) -> Bool {
        // `standardized` resuelve los "..": "/portal/../admin/" no cuenta como portal.
        let ruta = url.standardized.path
        if ruta == "/portal" { return true }   // Foundation quita la barra final de "/portal/"
        return Self.prefijosDeLaApp.contains { ruta.hasPrefix($0) }
    }

    /// Cierre de sesión del paciente, con o sin ".php" (el sitio usa URLs limpias).
    func esCierreDeSesion(_ url: URL) -> Bool {
        guard let host = url.host, esHostDelPortal(host) else { return false }
        let ruta = url.standardized.path
        return ruta == "/portal/logout.php" || ruta == "/portal/logout"
    }

    /// URL segura para una ruta recibida en una notificación. Solo se aceptan
    /// rutas del portal: una notificación nunca puede abrir otro sitio.
    func urlParaRuta(_ ruta: String?) -> URL? {
        guard let ruta = ruta?.trimmingCharacters(in: .whitespacesAndNewlines),
              ruta.hasPrefix("/portal/") || ruta == "/portal",
              !ruta.contains("//"), !ruta.contains("\\"), !ruta.contains("@"),
              let url = URL(string: "https://\(hostPortal)\(ruta)"),
              decidir(url, esMarcoPrincipal: true) == .permitir
        else { return nil }
        return url
    }
}
