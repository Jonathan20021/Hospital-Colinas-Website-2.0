import SwiftUI
import UIKit

/// Pantalla única de la app: el portal, con lo nativo por encima cuando hace
/// falta (progreso, sin conexión, portada de arranque y cubierta de privacidad).
struct PortalView: View {

    @EnvironmentObject private var portal: PortalModel
    @EnvironmentObject private var bloqueo: AppLock
    @ObservedObject private var push = PushManager.shared

    var body: some View {
        ZStack(alignment: .top) {
            Color.hglcFondo.ignoresSafeArea()

            PortalWebView(modelo: portal)
                .ignoresSafeArea()

            if portal.cargando && portal.primeraCargaLista {
                BarraDeProgreso(valor: portal.progreso)
                    .transition(.opacity)
            }

            if portal.estado == .sinConexion || portal.estado == .error {
                SinConexionView(sinRed: portal.estado == .sinConexion) {
                    portal.reintentar()
                }
                .transition(.opacity)
            }

            // Misma imagen y posición que la pantalla de arranque del sistema:
            // el paso de una a otra no se nota.
            if !portal.primeraCargaLista || bloqueo.cubierta {
                PortadaView(cargando: !portal.primeraCargaLista && !bloqueo.cubierta)
                    .transition(.opacity)
            }
        }
        .animation(.easeOut(duration: 0.25), value: portal.primeraCargaLista)
        .animation(.easeOut(duration: 0.2), value: portal.cargando)
        .animation(.easeOut(duration: 0.2), value: portal.estado)
        .sheet(isPresented: $portal.mostrarBienvenida) {
            BienvenidaView()
                .environmentObject(portal)
        }
        .onReceive(push.$rutaPendiente) { ruta in
            guard let ruta else { return }
            push.rutaPendiente = nil
            portal.abrir(ruta: ruta)
        }
        .onReceive(NotificationCenter.default.publisher(for: UIApplication.didBecomeActiveNotification)) { _ in
            Task { await push.appActiva() }
        }
        .task {
            await bloqueo.evaluarArranque()
        }
    }
}

/// Portada: el isotipo del hospital sobre el fondo del portal. Si la primera
/// carga tarda (red lenta), aparece un indicador para que no parezca colgada.
struct PortadaView: View {

    var cargando = false
    @State private var cargaLenta = false

    var body: some View {
        ZStack {
            Color.hglcFondo
            Image("LaunchMark")
                .accessibilityLabel("Hospital General Las Colinas")
            if cargando && cargaLenta {
                ProgressView()
                    .tint(.hglcNavy)
                    .offset(y: 120)
                    .transition(.opacity)
            }
        }
        .ignoresSafeArea()
        .task(id: cargando) {
            guard cargando else { return }
            try? await Task.sleep(nanoseconds: 2_500_000_000)
            withAnimation { cargaLenta = true }
        }
    }
}

/// Barra fina de carga bajo la barra de estado, en el verde del portal.
struct BarraDeProgreso: View {
    let valor: Double

    var body: some View {
        GeometryReader { geometria in
            Rectangle()
                .fill(Color.hglcVerde)
                .frame(width: geometria.size.width * max(0.08, min(1, valor)), height: 3)
                .animation(.easeOut(duration: 0.25), value: valor)
        }
        .frame(height: 3)
        .allowsHitTesting(false)
        .accessibilityHidden(true)
    }
}

extension Color {
    static let hglcNavy = Color("Navy")
    static let hglcVerde = Color("Verde")
    static let hglcFondo = Color("Fondo")
}
