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

    var body: some View {
        VStack(alignment: .leading, spacing: 22) {
            VStack(alignment: .leading, spacing: 6) {
                Text("Tu portal, ahora como app")
                    .font(.title2.bold())
                    .foregroundColor(.hglcNavy)
                Text("Dos ajustes opcionales. Puedes cambiarlos cuando quieras en Mi perfil.")
                    .font(.callout)
                    .foregroundColor(.secondary)
            }

            if ofrecerAvisos {
                Opcion(
                    icono: "bell.badge.fill",
                    titulo: "Avisos importantes",
                    texto: "Cuando tu médico te escriba o se acerque una cita, sin mostrar detalles médicos en la pantalla bloqueada.",
                    hecho: push.permiso == .concedido,
                    textoHecho: "Avisos activados",
                    nota: push.permiso == .denegado ? "Puedes activarlos luego en Ajustes del iPhone." : nil,
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

            Spacer(minLength: 0)

            Button {
                cerrar()
            } label: {
                Text("Listo")
                    .font(.headline)
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 6)
            }
            .buttonStyle(.borderedProminent)
            .tint(.hglcNavy)
        }
        .padding(24)
        .presentationDetents([.medium, .large])
        .onAppear {
            // Se fija al abrir: al activar algo, su fila se queda y muestra "activado".
            ofrecerAvisos = push.permiso == .pendiente
            ofrecerBloqueo = bloqueo.disponible && !bloqueo.activo
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
    let boton: String
    let ocupado: Bool
    let accion: () async -> Void

    var body: some View {
        HStack(alignment: .top, spacing: 14) {
            Image(systemName: icono)
                .font(.system(size: 22, weight: .semibold))
                .foregroundColor(.hglcVerde)
                .frame(width: 32)
                .accessibilityHidden(true)

            VStack(alignment: .leading, spacing: 6) {
                Text(titulo)
                    .font(.headline)
                    .foregroundColor(.hglcNavy)
                Text(texto)
                    .font(.subheadline)
                    .foregroundColor(.secondary)
                    .fixedSize(horizontal: false, vertical: true)

                if hecho {
                    Label(textoHecho, systemImage: "checkmark.circle.fill")
                        .font(.subheadline.weight(.semibold))
                        .foregroundColor(.hglcVerde)
                } else if let nota {
                    Text(nota)
                        .font(.footnote)
                        .foregroundColor(.secondary)
                } else {
                    Button {
                        Task { await accion() }
                    } label: {
                        if ocupado {
                            ProgressView()
                        } else {
                            Text(boton).font(.subheadline.weight(.semibold))
                        }
                    }
                    .buttonStyle(.bordered)
                    .tint(.hglcNavy)
                    .disabled(ocupado)
                }
            }
        }
    }
}
