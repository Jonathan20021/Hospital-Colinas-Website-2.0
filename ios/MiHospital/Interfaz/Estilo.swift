import SwiftUI
import UIKit

/// Lenguaje visual del portal llevado a las pantallas nativas: la misma letra
/// (Outfit en títulos y botones), los mismos colores y radios. Así el paso de
/// la web a una pantalla de la app no se nota.

extension Color {
    static let hglcNavy = Color("Navy")
    static let hglcVerde = Color("Verde")
    static let hglcFondo = Color("Fondo")

    /// `--portal-navy-soft` y `--portal-green-soft` del portal.
    static let hglcNavySuave = Color(red: 0xEF / 255, green: 0xEF / 255, blue: 0xF8 / 255)
    static let hglcVerdeSuave = Color(red: 0xEE / 255, green: 0xF7 / 255, blue: 0xE9 / 255)
    /// `--portal-green-strong`: verde para texto sobre blanco (contraste AA).
    static let hglcVerdeFuerte = Color(red: 0x39 / 255, green: 0x7B / 255, blue: 0x22 / 255)
    /// Texto secundario y bordes de tarjeta, como en el portal.
    static let hglcTexto = Color(red: 0x47 / 255, green: 0x55 / 255, blue: 0x69 / 255)
    static let hglcBorde = Color(red: 0xE2 / 255, green: 0xE8 / 255, blue: 0xF0 / 255)
    /// Errores: rojo con contraste AA sobre blanco.
    static let hglcAlerta = Color(red: 0xB9 / 255, green: 0x1C / 255, blue: 0x1C / 255)
}

/// Vibraciones de confirmación: el paciente siente que algo salió bien (o no)
/// sin tener que leer.
@MainActor
enum Haptica {
    static func exito() {
        UINotificationFeedbackGenerator().notificationOccurred(.success)
    }

    static func error() {
        UINotificationFeedbackGenerator().notificationOccurred(.error)
    }

    static func toque() {
        UIImpactFeedbackGenerator(style: .light).impactOccurred()
    }
}

extension Font {
    /// Outfit con Dynamic Type: crece con el tamaño de texto del sistema
    /// tomando como referencia el estilo indicado.
    static func outfit(_ peso: PesoOutfit, _ tamano: CGFloat, como estilo: Font.TextStyle) -> Font {
        .custom(peso.rawValue, size: tamano, relativeTo: estilo)
    }

    static let hglcTitulo = outfit(.extraBold, 26, como: .title2)
    static let hglcSubtitulo = outfit(.bold, 18, como: .headline)
    static let hglcBoton = outfit(.bold, 17, como: .headline)
}

enum PesoOutfit: String {
    case semiBold = "Outfit-SemiBold"
    case bold = "Outfit-Bold"
    case extraBold = "Outfit-ExtraBold"
}

enum Medidas {
    /// `--portal-radius` y `--portal-radius-lg`.
    static let radio: CGFloat = 14
    static let radioTarjeta: CGFloat = 18
    /// Ancho máximo del contenido en iPad y en horizontal.
    static let anchoMaximo: CGFloat = 440
}

/// Botón principal: verde lleno, como "Enviarme un código" en el login.
struct BotonPrincipal: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        Contenido(configuration: configuration)
    }

    private struct Contenido: View {
        let configuration: Configuration
        @Environment(\.isEnabled) private var habilitado

        var body: some View {
            configuration.label
                .font(.hglcBoton)
                .lineLimit(1)
                .minimumScaleFactor(0.6)   // con letra enorme, encoger antes que partir palabras
                .foregroundColor(.white)
                .frame(maxWidth: .infinity, minHeight: 52)
                .padding(.horizontal, 16)
                .background(
                    RoundedRectangle(cornerRadius: Medidas.radio, style: .continuous)
                        .fill(Color.hglcVerde)
                )
                .opacity(habilitado ? (configuration.isPressed ? 0.85 : 1) : 0.5)
                .scaleEffect(configuration.isPressed ? 0.98 : 1)
                .animation(.easeOut(duration: 0.12), value: configuration.isPressed)
                .contentShape(Rectangle())
        }
    }
}

/// Botón secundario: blanco con borde y texto navy.
struct BotonSecundario: ButtonStyle {
    var compacto = false

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(compacto ? .outfit(.bold, 15, como: .subheadline) : .hglcBoton)
            .lineLimit(1)
            .minimumScaleFactor(0.6)
            .foregroundColor(.hglcNavy)
            .frame(maxWidth: compacto ? nil : .infinity, minHeight: compacto ? 40 : 52)
            .padding(.horizontal, 16)
            .background(
                RoundedRectangle(cornerRadius: Medidas.radio, style: .continuous)
                    .fill(configuration.isPressed ? Color.hglcNavySuave : Color.white)
            )
            .overlay(
                RoundedRectangle(cornerRadius: Medidas.radio, style: .continuous)
                    .strokeBorder(Color.hglcBorde, lineWidth: 1.5)
            )
            .scaleEffect(configuration.isPressed ? 0.98 : 1)
            .animation(.easeOut(duration: 0.12), value: configuration.isPressed)
            .contentShape(Rectangle())
    }
}

/// Tarjeta blanca del portal: borde fino y sombra navy muy suave.
struct Tarjeta: ViewModifier {
    var relleno: CGFloat = 24

    func body(content: Content) -> some View {
        content
            .padding(relleno)
            .frame(maxWidth: .infinity)
            .background(
                RoundedRectangle(cornerRadius: Medidas.radioTarjeta, style: .continuous)
                    .fill(Color.white)
                    .shadow(color: Color.hglcNavy.opacity(0.10), radius: 18, x: 0, y: 10)
            )
            .overlay(
                RoundedRectangle(cornerRadius: Medidas.radioTarjeta, style: .continuous)
                    .strokeBorder(Color.hglcBorde, lineWidth: 1)
            )
    }
}

extension View {
    func tarjeta(relleno: CGFloat = 24) -> some View {
        modifier(Tarjeta(relleno: relleno))
    }
}

/// Ícono dentro de un cuadro suave, como los íconos de las tarjetas del portal.
struct IconoEnCuadro: View {
    let sistema: String
    var color: Color = .hglcNavy
    var fondo: Color = .hglcNavySuave
    var lado: CGFloat = 56

    var body: some View {
        Image(systemName: sistema)
            .font(.system(size: lado * 0.42, weight: .semibold))
            .foregroundColor(color)
            .frame(width: lado, height: lado)
            .background(
                RoundedRectangle(cornerRadius: lado * 0.3, style: .continuous)
                    .fill(fondo)
            )
            .accessibilityHidden(true)
    }
}
