import SwiftUI

/// Lo que se ve en la ventana de privacidad: la portada sola (escudo) o, si la
/// app está bloqueada, la portada con el panel para desbloquear.
struct CubiertaView: View {

    @ObservedObject var bloqueo: AppLock

    var body: some View {
        ZStack(alignment: .bottom) {
            PortadaView()

            if bloqueo.bloqueada {
                PanelDeBloqueo(nombreBiometria: bloqueo.nombreBiometria,
                               icono: bloqueo.iconoBiometria,
                               fallo: bloqueo.falloDesbloqueo) {
                    Task { await bloqueo.desbloquear() }
                }
                .transition(.move(edge: .bottom).combined(with: .opacity))
            }
        }
        .animation(.spring(response: 0.4, dampingFraction: 0.9), value: bloqueo.bloqueada)
        .task(id: bloqueo.bloqueada) {
            // Al aparecer el bloqueo se pide Face ID sin tener que tocar nada.
            if bloqueo.bloqueada {
                await bloqueo.desbloquear()
            }
        }
    }
}

/// Panel inferior del bloqueo. El isotipo queda en el centro, en la misma
/// posición que en el arranque, y el panel sube desde abajo.
struct PanelDeBloqueo: View {

    let nombreBiometria: String
    let icono: String
    var fallo = false
    let desbloquear: () -> Void

    var body: some View {
        VStack(spacing: 18) {
            IconoEnCuadro(sistema: "lock.fill", lado: 52)

            VStack(spacing: 8) {
                Text("Tu portal está protegido")
                    .font(.outfit(.extraBold, 22, como: .title3))
                    .foregroundColor(.hglcNavy)
                    .multilineTextAlignment(.center)
                    .fixedSize(horizontal: false, vertical: true)
                    .accessibilityAddTraits(.isHeader)
                Text("Desbloquéalo con \(nombreBiometria) para ver tu información médica.")
                    .font(.callout)
                    .foregroundColor(.hglcTexto)
                    .multilineTextAlignment(.center)
                    .fixedSize(horizontal: false, vertical: true)
            }

            if fallo {
                Label("No pudimos confirmar que eres tú. Inténtalo de nuevo.",
                      systemImage: "exclamationmark.circle.fill")
                    .font(.footnote.weight(.semibold))
                    .foregroundColor(.hglcAlerta)
                    .multilineTextAlignment(.center)
                    .fixedSize(horizontal: false, vertical: true)
                    .transition(.opacity)
            }

            Button(action: desbloquear) {
                Label("Desbloquear", systemImage: icono)
            }
            .buttonStyle(BotonPrincipal())
            .padding(.top, 4)
        }
        .animation(.easeOut(duration: 0.2), value: fallo)
        .tarjeta(relleno: 24)
        // El panel no se desplaza: con la letra más grande no cabría en
        // pantalla. Hasta este tamaño se lee completo.
        .dynamicTypeSize(...DynamicTypeSize.accessibility2)
        .frame(maxWidth: Medidas.anchoMaximo)
        .padding(.horizontal, 16)
        .padding(.bottom, 12)
    }
}
