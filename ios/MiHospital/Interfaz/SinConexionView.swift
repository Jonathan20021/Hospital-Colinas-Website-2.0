import SwiftUI

/// Pantalla nativa cuando el portal no carga. Sin ella WKWebView deja una
/// página en blanco. Vuelve a intentarlo sola cuando regresa la red.
struct SinConexionView: View {

    let sinRed: Bool
    let reintentar: () -> Void

    var body: some View {
        ZStack {
            Color.hglcFondo.ignoresSafeArea()

            GeometryReader { geometria in
                ScrollView {
                    contenido
                        .padding(.horizontal, 20)
                        .padding(.vertical, 32)
                        .frame(maxWidth: Medidas.anchoMaximo)
                        .frame(maxWidth: .infinity, minHeight: geometria.size.height)
                }
                .rebotarSoloSiNoCabe()
            }
        }
    }

    private var contenido: some View {
        VStack(spacing: 28) {
            Image("LaunchMark")
                .resizable()
                .scaledToFit()
                .frame(width: 96)
                .accessibilityLabel("Hospital General Las Colinas")

            VStack(spacing: 22) {
                IconoEnCuadro(sistema: sinRed ? "wifi.slash" : "exclamationmark.icloud", lado: 64)

                VStack(spacing: 10) {
                    Text(sinRed ? "Sin conexión a internet" : "No pudimos abrir el portal")
                        .font(.hglcTitulo)
                        .foregroundColor(.hglcNavy)
                        .multilineTextAlignment(.center)
                        .accessibilityAddTraits(.isHeader)

                    Text(sinRed
                         ? "Revisa tu Wi‑Fi o tus datos móviles."
                         : "El servidor no respondió. Inténtalo de nuevo en unos minutos.")
                        .font(.callout)
                        .foregroundColor(.hglcTexto)
                        .multilineTextAlignment(.center)
                        .fixedSize(horizontal: false, vertical: true)
                }

                if sinRed {
                    // La app reintenta sola al volver la red: se dice, para que
                    // el paciente no sienta que tiene que hacer algo.
                    HStack(spacing: 8) {
                        ProgressView()
                            .controlSize(.small)
                            .tint(.hglcVerdeFuerte)
                        Text("Esperando la conexión…")
                            .font(.footnote.weight(.medium))
                            .foregroundColor(.hglcVerdeFuerte)
                    }
                    .padding(.horizontal, 14)
                    .padding(.vertical, 8)
                    .background(Capsule().fill(Color.hglcVerdeSuave))
                    .accessibilityElement(children: .combine)
                }

                VStack(spacing: 12) {
                    Button {
                        Haptica.toque()
                        reintentar()
                    } label: {
                        Label("Reintentar", systemImage: "arrow.clockwise")
                    }
                    .buttonStyle(BotonPrincipal())

                    Link(destination: AppConfig.telefonoMarcable) {
                        Label("Llamar al hospital", systemImage: "phone.fill")
                    }
                    .buttonStyle(BotonSecundario())
                    .accessibilityHint("Llama al \(AppConfig.telefonoVisible)")

                    Text(AppConfig.telefonoVisible)
                        .font(.outfit(.semiBold, 15, como: .footnote))
                        .foregroundColor(.hglcTexto)
                        .monospacedDigit()
                        .accessibilityHidden(true)
                }
                .padding(.top, 4)
            }
            .tarjeta(relleno: 28)
        }
    }
}

extension View {
    /// Sin rebote si el contenido cabe (iOS 16.4+); con letra grande se
    /// puede desplazar.
    @ViewBuilder
    func rebotarSoloSiNoCabe() -> some View {
        if #available(iOS 16.4, *) {
            scrollBounceBehavior(.basedOnSize)
        } else {
            self
        }
    }
}
