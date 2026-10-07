import SwiftUI

/// Pantalla nativa cuando el portal no carga. Sin ella WKWebView deja una
/// página en blanco. Vuelve a intentarlo sola cuando regresa la red.
struct SinConexionView: View {

    let sinRed: Bool
    let reintentar: () -> Void

    var body: some View {
        ZStack {
            Color.hglcFondo.ignoresSafeArea()

            VStack(spacing: 18) {
                Image(systemName: sinRed ? "wifi.slash" : "exclamationmark.icloud")
                    .font(.system(size: 44, weight: .semibold))
                    .foregroundColor(.hglcNavy)
                    .accessibilityHidden(true)

                Text(sinRed ? "Sin conexión a internet" : "No pudimos abrir el portal")
                    .font(.title3.weight(.semibold))
                    .foregroundColor(.hglcNavy)
                    .multilineTextAlignment(.center)

                Text(sinRed
                     ? "Revisa tu Wi‑Fi o tus datos móviles. La app vuelve a intentarlo sola cuando regrese la conexión."
                     : "El servidor no respondió. Inténtalo de nuevo en unos minutos.")
                    .font(.callout)
                    .foregroundColor(.secondary)
                    .multilineTextAlignment(.center)

                Button(action: reintentar) {
                    Label("Reintentar", systemImage: "arrow.clockwise")
                        .font(.headline)
                        .frame(maxWidth: 260)
                        .padding(.vertical, 6)
                }
                .buttonStyle(.borderedProminent)
                .tint(.hglcNavy)

                Link(destination: AppConfig.telefonoMarcable) {
                    Label("Llamar al hospital · \(AppConfig.telefonoVisible)", systemImage: "phone.fill")
                        .font(.callout.weight(.semibold))
                }
                .tint(.hglcVerde)
                .padding(.top, 4)
            }
            .padding(32)
            .frame(maxWidth: 440)
        }
    }
}
