import SwiftUI

/// Lo que se ve en la ventana de privacidad: la portada sola (escudo) o, si la
/// app está bloqueada, la portada con el botón para desbloquear.
struct CubiertaView: View {

    @ObservedObject var bloqueo: AppLock

    var body: some View {
        ZStack {
            PortadaView()

            if bloqueo.bloqueada {
                VStack(spacing: 14) {
                    Spacer()
                    Text("Tu portal está protegido")
                        .font(.title3.weight(.semibold))
                        .foregroundColor(.hglcNavy)
                    Text("Desbloquéalo con \(bloqueo.nombreBiometria) para ver tu información médica.")
                        .font(.callout)
                        .foregroundColor(.secondary)
                        .multilineTextAlignment(.center)
                    Button {
                        Task { await bloqueo.desbloquear() }
                    } label: {
                        Label("Desbloquear", systemImage: bloqueo.iconoBiometria)
                            .font(.headline)
                            .frame(maxWidth: 260)
                            .padding(.vertical, 6)
                    }
                    .buttonStyle(.borderedProminent)
                    .tint(.hglcNavy)
                    .padding(.top, 6)
                }
                .padding(.horizontal, 32)
                .padding(.bottom, 56)
                .frame(maxWidth: 440)
                .transition(.opacity)
            }
        }
        .animation(.easeOut(duration: 0.2), value: bloqueo.bloqueada)
        .task(id: bloqueo.bloqueada) {
            // Al aparecer el bloqueo se pide Face ID sin tener que tocar nada.
            if bloqueo.bloqueada {
                await bloqueo.desbloquear()
            }
        }
    }
}
