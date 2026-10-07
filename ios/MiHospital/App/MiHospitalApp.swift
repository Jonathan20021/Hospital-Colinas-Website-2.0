import SwiftUI

@main
struct MiHospitalApp: App {

    @UIApplicationDelegateAdaptor(AppDelegate.self) private var appDelegate
    @StateObject private var portal = PortalModel()
    @StateObject private var bloqueo = AppLock.shared

    var body: some Scene {
        WindowGroup {
            #if DEBUG
            if let pantalla = PantallaDePrueba.pedida {
                pantalla.vista.environmentObject(portal)
            } else {
                PortalView()
                    .environmentObject(portal)
                    .environmentObject(bloqueo)
            }
            #else
            PortalView()
                .environmentObject(portal)
                .environmentObject(bloqueo)
            #endif
        }
    }
}

#if DEBUG
/// Abre una pantalla nativa sola, para revisarla sin provocar su situación
/// (sin red, bloqueo, primer inicio de sesión). Solo en depuración:
///
///   xcrun simctl launch booted com.colinashospital.paciente -pantalla sinConexion
///
/// Valores: sinConexion, error, bloqueo, bloqueoFallido, bienvenida, portada,
/// avisoSinRed.
enum PantallaDePrueba: String {
    case sinConexion, error, bloqueo, bloqueoFallido, bienvenida, portada, avisoSinRed

    static var pedida: PantallaDePrueba? {
        UserDefaults.standard.string(forKey: "pantalla").flatMap(PantallaDePrueba.init(rawValue:))
    }

    @ViewBuilder @MainActor
    var vista: some View {
        switch self {
        case .sinConexion:
            SinConexionView(sinRed: true) {}
        case .error:
            SinConexionView(sinRed: false) {}
        case .bloqueo, .bloqueoFallido:
            ZStack(alignment: .bottom) {
                PortadaView()
                PanelDeBloqueo(nombreBiometria: "Face ID", icono: "faceid",
                               fallo: self == .bloqueoFallido) {}
            }
        case .bienvenida:
            Color.hglcFondo.ignoresSafeArea()
                .sheet(isPresented: .constant(true)) { BienvenidaView() }
        case .portada:
            PortadaView(cargando: true) {}
        case .avisoSinRed:
            ZStack(alignment: .top) {
                Color.hglcFondo.ignoresSafeArea()
                AvisoSinRed().padding(.top, 6)
            }
        }
    }
}
#endif
