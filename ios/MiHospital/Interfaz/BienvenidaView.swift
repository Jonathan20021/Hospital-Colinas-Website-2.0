import SwiftUI

/// Se muestra una sola vez, tras el primer inicio de sesión en la app. Explica
/// para qué sirven los avisos y Face ID antes de que el sistema pida permiso:
/// así el paciente decide sabiendo qué acepta.
struct BienvenidaView: View {

    @EnvironmentObject private var portal: PortalModel
    @ObservedObject private var push = PushManager.shared
    @ObservedObject private var bloqueo = AppLock.shared
    @Environment(\.dismiss) private var cerrar

    @State private var ofrecerAvisos = false
    @State private var ofrecerBloqueo = false
    @State private var activandoAvisos = false
    @State private var activandoBloqueo = false

    private var algoActivado: Bool {
        (ofrecerAvisos && push.permiso == .concedido) || (ofrecerBloqueo && bloqueo.activo)
    }

    var body: some View {
        VStack(spacing: 0) {
            ScrollView {
                VStack(alignment: .leading, spacing: 24) {
                    encabezado

                    VStack(spacing: 12) {
                        if ofrecerAvisos {
                            Opcion(
                                icono: "bell.badge.fill",
                                titulo: "Avisos importantes",
                                texto: "Cuando tu médico te escriba o se acerque una cita, sin mostrar detalles médicos en la pantalla bloqueada.",
                                hecho: push.permiso == .concedido,
                                textoHecho: "Avisos activados",
                                nota: push.permiso == .denegado ? "Los avisos están apagados en los Ajustes del iPhone." : nil,
                                accionNota: push.permiso == .denegado ? ("Abrir Ajustes", { push.abrirAjustesDelSistema() }) : nil,
                                boton: "Activar avisos",
                                ocupado: activandoAvisos
                            ) {
                                activandoAvisos = true
                                _ = await portal.activarPush()
                                activandoAvisos = false
                            }
                        }

                        if ofrecerBloqueo {
                            Opcion(
                                icono: bloqueo.iconoBiometria,
                                titulo: "Protege tu información",
                                texto: "Pide \(bloqueo.nombreBiometria) al abrir la app, para que nadie más vea tus datos si otra persona toma tu teléfono.",
                                hecho: bloqueo.activo,
                                textoHecho: "Protección activada",
                                nota: nil,
                                boton: "Activar protección",
                                ocupado: activandoBloqueo
                            ) {
                                activandoBloqueo = true
                                _ = await bloqueo.activar()
                                activandoBloqueo = false
                            }
                        }
                    }
                }
                .padding(.horizontal, 24)
                .padding(.top, 32)
                .padding(.bottom, 16)
            }
            .rebotarSoloSiNoCabe()

            // Fijo abajo: con letra grande el contenido se desplaza y el botón
            // sigue a mano.
            Button {
                cerrar()
            } label: {
                Text(algoActivado ? "Continuar" : "Ahora no")
            }
            .buttonStyle(algoActivado ? AnyButtonStyle(BotonPrincipal()) : AnyButtonStyle(BotonSecundario()))
            .padding(.horizontal, 24)
            .padding(.top, 8)
            .padding(.bottom, 16)
        }
        .background(Color.hglcFondo.ignoresSafeArea())
        .presentationDetents([.large])
        .presentationDragIndicator(.visible)
        .onAppear {
            // Se fija al abrir: al activar algo, su fila se queda y muestra "activado".
            ofrecerAvisos = push.permiso == .pendiente
            ofrecerBloqueo = bloqueo.disponible && !bloqueo.activo
        }
    }

    private var encabezado: some View {
        VStack(alignment: .leading, spacing: 10) {
            Image("Isotipo")
                .resizable()
                .scaledToFit()
                .frame(width: 72)
                .accessibilityHidden(true)
                .padding(.bottom, 6)
            Text("Tu portal, ahora como app")
                .font(.outfit(.extraBold, 30, como: .title))
                .foregroundColor(.hglcNavy)
                .fixedSize(horizontal: false, vertical: true)
                .accessibilityAddTraits(.isHeader)
            Text("Dos ajustes opcionales. Puedes cambiarlos cuando quieras en Mi perfil.")
                .font(.callout)
                .foregroundColor(.hglcTexto)
                .fixedSize(horizontal: false, vertical: true)
        }
    }
}

private struct Opcion: View {
    let icono: String
    let titulo: String
    let texto: String
    let hecho: Bool
    let textoHecho: String
    let nota: String?
    var accionNota: (String, () -> Void)? = nil
    let boton: String
    let ocupado: Bool
    let accion: () async -> Void

    var body: some View {
        HStack(alignment: .top, spacing: 14) {
            IconoEnCuadro(sistema: icono, color: .hglcVerdeFuerte, fondo: .hglcVerdeSuave, lado: 44)

            VStack(alignment: .leading, spacing: 6) {
                Text(titulo)
                    .font(.hglcSubtitulo)
                    .foregroundColor(.hglcNavy)
                Text(texto)
                    .font(.subheadline)
                    .foregroundColor(.hglcTexto)
                    .fixedSize(horizontal: false, vertical: true)

                Group {
                    if hecho {
                        Label(textoHecho, systemImage: "checkmark.circle.fill")
                            .font(.outfit(.bold, 15, como: .subheadline))
                            .foregroundColor(.hglcVerdeFuerte)
                            .padding(.horizontal, 12)
                            .padding(.vertical, 8)
                            .background(Capsule().fill(Color.hglcVerdeSuave))
                            .transition(.scale(scale: 0.9).combined(with: .opacity))
                    } else if let nota {
                        VStack(alignment: .leading, spacing: 10) {
                            Text(nota)
                                .font(.footnote)
                                .foregroundColor(.hglcTexto)
                                .fixedSize(horizontal: false, vertical: true)
                            if let (titulo, accion) = accionNota {
                                Button(titulo, action: accion)
                                    .buttonStyle(BotonSecundario(compacto: true))
                            }
                        }
                    } else {
                        Button {
                            Task { await accion() }
                        } label: {
                            ZStack {
                                Text(boton).opacity(ocupado ? 0 : 1)
                                if ocupado { ProgressView().tint(.hglcNavy) }
                            }
                        }
                        .buttonStyle(BotonSecundario(compacto: true))
                        .disabled(ocupado)
                    }
                }
                .padding(.top, 6)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        .tarjeta(relleno: 18)
        .animation(.spring(response: 0.35, dampingFraction: 0.8), value: hecho)
        .onChange(of: hecho) { activado in
            if activado { Haptica.exito() }
        }
        .accessibilityElement(children: .contain)
    }
}

/// Para cambiar de estilo de botón según el estado.
struct AnyButtonStyle: ButtonStyle {
    private let cuerpo: (Configuration) -> AnyView

    init<S: ButtonStyle>(_ estilo: S) {
        cuerpo = { AnyView(estilo.makeBody(configuration: $0)) }
    }

    func makeBody(configuration: Configuration) -> some View {
        cuerpo(configuration)
    }
}
