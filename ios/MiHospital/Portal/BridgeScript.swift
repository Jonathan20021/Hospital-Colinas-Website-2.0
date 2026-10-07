import Foundation

/// JavaScript que la app ejecuta en el portal. Vive en archivos .js del bundle
/// (Resources/) para poder leerlo y validarlo como lo que es:
///
/// - `puente.js`: se inyecta en cada página; define `window.HGLCApp`.
/// - `llamada-proxy.js`: POST al proxy del portal con la sesión de la página.
enum BridgeScript {

    static let fuente: String =
        cargar("puente").replacingOccurrences(of: "__VERSION__", with: AppConfig.version)

    static let llamadaAlProxy: String = cargar("llamada-proxy")

    private static func cargar(_ nombre: String) -> String {
        guard let url = Bundle.main.url(forResource: nombre, withExtension: "js"),
              let texto = try? String(contentsOf: url, encoding: .utf8)
        else {
            assertionFailure("Falta \(nombre).js en el bundle de la app")
            return ""
        }
        return texto
    }
}
