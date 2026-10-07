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
                .id(portal.generacionVistaWeb)
                .ignoresSafeArea()

            if portal.cargando && portal.primeraCargaLista {
                BarraDeProgreso(valor: portal.progreso)
                    .transition(.opacity)
            }

            // Se cayó la red con el portal ya abierto: la página sigue ahí,
            // pero hay que decir que puede no estar al día.
            if portal.estado == .listo && !portal.hayRed && portal.primeraCargaLista {
                AvisoSinRed()
                    .padding(.top, 6)
                    .transition(.move(edge: .top).combined(with: .opacity))
            }

            if let progreso = portal.progresoDocumento {
                TarjetaDocumento(progreso: progreso) {
                    portal.cancelarDocumento()
                }
                .frame(maxHeight: .infinity, alignment: .bottom)
                .transition(.move(edge: .bottom).combined(with: .opacity))
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
                PortadaView(cargando: !portal.primeraCargaLista && !bloqueo.cubierta) {
                    portal.reintentar()
                }
                .transition(.opacity)
            }
        }
        // También aquí, en la raíz, para que valga desde el primer cuadro: si
        // solo lo dice la portada, la barra asoma un instante al arrancar.
        .statusBarHidden(!portal.primeraCargaLista || bloqueo.cubierta
                         || portal.estado == .sinConexion || portal.estado == .error)
        .animation(.easeOut(duration: 0.25), value: portal.primeraCargaLista)
        .animation(.easeOut(duration: 0.2), value: portal.cargando)
        .animation(.easeOut(duration: 0.2), value: portal.estado)
        .animation(.spring(response: 0.4, dampingFraction: 0.85), value: portal.hayRed)
        .animation(.spring(response: 0.4, dampingFraction: 0.85), value: portal.progresoDocumento == nil)
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
            portal.appActiva()
            Task { await push.appActiva() }
        }
        .task {
            await bloqueo.evaluarArranque()
        }
    }
}

/// Portada: el logo del hospital sobre el navy de la marca, igual que la
/// pantalla de arranque del sistema (`UILaunchScreen` en Info.plist): mismo
/// color, misma imagen, misma posición, así el paso no se nota. Si la primera
/// carga tarda (red lenta), aparece un indicador para que no parezca colgada
/// y, si tarda mucho, la opción de reintentar.
struct PortadaView: View {

    var cargando = false
    var reintentar: (() -> Void)?

    private enum Espera { case normal, lenta, muyLenta }
    @State private var espera = Espera.normal

    var body: some View {
        ZStack {
            Color.hglcNavy.ignoresSafeArea()
            Image("LaunchMark")
                .accessibilityLabel("Hospital General Las Colinas")
                .ignoresSafeArea()

            if cargando && espera != .normal {
                VStack(spacing: 14) {
                    HStack(spacing: 10) {
                        ProgressView().tint(.white)
                        Text(espera == .lenta ? "Conectando con tu portal…" : "La conexión está lenta…")
                            .font(.outfit(.semiBold, 16, como: .callout))
                            .foregroundColor(.white.opacity(0.85))
                    }
                    .accessibilityElement(children: .combine)

                    if espera == .muyLenta, let reintentar {
                        Button("Reintentar", action: reintentar)
                            .buttonStyle(BotonSecundario(compacto: true))
                            .transition(.opacity)
                    }
                }
                .frame(maxHeight: .infinity, alignment: .bottom)
                .padding(.bottom, 48)
                .transition(.opacity)
            }
        }
        // Sobre el navy, la barra de estado (texto oscuro) no se lee. Mientras
        // la portada esté en pantalla, no hay barra; al irse, vuelve.
        .statusBarHidden(true)
        .task(id: cargando) {
            espera = .normal
            guard cargando else { return }
            try? await Task.sleep(nanoseconds: 2_500_000_000)
            guard !Task.isCancelled else { return }
            withAnimation { espera = .lenta }
            try? await Task.sleep(nanoseconds: 10_000_000_000)
            guard !Task.isCancelled else { return }
            withAnimation { espera = .muyLenta }
        }
    }
}

/// Mientras se descarga una receta, un resultado o un adjunto: el paciente ve
/// que algo pasa y puede cancelarlo. Al terminar se abre en Quick Look.
struct TarjetaDocumento: View {
    let progreso: Double
    let cancelar: () -> Void

    var body: some View {
        HStack(spacing: 14) {
            IconoEnCuadro(sistema: "doc.text.fill", lado: 44)

            VStack(alignment: .leading, spacing: 8) {
                Text("Abriendo el documento…")
                    .font(.outfit(.bold, 16, como: .headline))
                    .foregroundColor(.hglcNavy)
                // Sin tamaño conocido (el servidor no lo dice) la barra se mueve sola.
                Group {
                    if progreso > 0 {
                        ProgressView(value: min(progreso, 1))
                    } else {
                        ProgressView(value: nil as Double?)
                    }
                }
                .progressViewStyle(.linear)
                .tint(.hglcVerde)
            }
            .frame(maxWidth: .infinity, alignment: .leading)

            Button("Cancelar", action: cancelar)
                .font(.outfit(.semiBold, 15, como: .subheadline))
                .foregroundColor(.hglcNavy)
                .frame(minHeight: 44)
        }
        .tarjeta(relleno: 16)
        .dynamicTypeSize(...DynamicTypeSize.accessibility1)
        .frame(maxWidth: Medidas.anchoMaximo)
        .padding(.horizontal, 16)
        .padding(.bottom, 12)
        .accessibilityElement(children: .contain)
    }
}

/// Pastilla bajo la barra de estado cuando se pierde la red con el portal
/// abierto. Desaparece sola al volver la conexión.
struct AvisoSinRed: View {
    var body: some View {
        Label("Sin conexión · lo que ves puede no estar al día", systemImage: "wifi.slash")
            .font(.outfit(.semiBold, 14, como: .footnote))
            .foregroundColor(.white)
            .lineLimit(2)
            .multilineTextAlignment(.center)
            .padding(.horizontal, 16)
            .padding(.vertical, 10)
            .background(Capsule().fill(Color.hglcNavy))
            .shadow(color: Color.hglcNavy.opacity(0.25), radius: 12, x: 0, y: 6)
            .padding(.horizontal, 16)
            .dynamicTypeSize(...DynamicTypeSize.accessibility1)
            .allowsHitTesting(false)
            .accessibilityAddTraits(.updatesFrequently)
            .onAppear {
                UIAccessibility.post(notification: .announcement,
                                     argument: "Sin conexión. Lo que ves puede no estar al día.")
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
