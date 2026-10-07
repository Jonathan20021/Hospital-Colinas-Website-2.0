import QuickLook
import UIKit
import UniformTypeIdentifiers

/// Archivos temporales de recetas, resultados y adjuntos.
///
/// Son datos clínicos: viven en una carpeta temporal con protección completa
/// (cifrados mientras el iPhone está bloqueado), se borran al cerrar la vista
/// previa y la carpeta entera se vacía en cada arranque.
enum Descargas {

    static var carpeta: URL {
        FileManager.default.temporaryDirectory.appendingPathComponent("documentos", isDirectory: true)
    }

    /// ¿Esta respuesta es un documento para abrir en Quick Look y no una página?
    static func esDocumento(_ respuesta: URLResponse, sePuedeMostrar: Bool) -> Bool {
        if let http = respuesta as? HTTPURLResponse,
           let disposicion = http.value(forHTTPHeaderField: "Content-Disposition"),
           disposicion.lowercased().hasPrefix("attachment") {
            return true
        }
        let tipo = (respuesta.mimeType ?? "").lowercased()
        if tipo.hasPrefix("text/") || tipo == "application/xhtml+xml" { return false }
        if tipo == "application/pdf" || tipo.hasPrefix("image/") { return true }
        return !sePuedeMostrar
    }

    /// Ruta nueva y única para un archivo descargado (WebKit exige que no exista).
    static func nuevoDestino(nombreSugerido: String, tipo: String?) -> URL? {
        var nombre = nombreLimpio(nombreSugerido)
        // Quick Look reconoce el formato por la extensión: si el servidor no la
        // mandó (los adjuntos del chat se llaman "archivo"), se deduce del tipo MIME.
        if (nombre as NSString).pathExtension.isEmpty,
           let tipo,
           let sufijo = UTType(mimeType: tipo)?.preferredFilenameExtension {
            nombre += "." + sufijo
        }
        let carpetaUnica = carpeta.appendingPathComponent(UUID().uuidString, isDirectory: true)
        do {
            try FileManager.default.createDirectory(
                at: carpetaUnica,
                withIntermediateDirectories: true,
                attributes: [.protectionKey: FileProtectionType.complete]
            )
        } catch {
            return nil
        }
        return carpetaUnica.appendingPathComponent(nombre)
    }

    static func nombreLimpio(_ nombre: String) -> String {
        let prohibidos = CharacterSet(charactersIn: "/\\:?%*|\"<>")
            .union(.newlines)
            .union(.controlCharacters)
        let limpio = nombre.components(separatedBy: prohibidos)
            .joined(separator: "_")
            .trimmingCharacters(in: .whitespaces)
        let base = (limpio.isEmpty || limpio.hasPrefix(".")) ? "documento" + limpio : limpio
        return String(base.prefix(120))
    }

    static func borrar(_ archivo: URL) {
        try? FileManager.default.removeItem(at: archivo.deletingLastPathComponent())
    }

    static func vaciar() {
        try? FileManager.default.removeItem(at: carpeta)
    }
}

/// Vista previa de Quick Look para un documento descargado. Se mantiene viva
/// mientras está en pantalla (Quick Look no retiene su fuente de datos) y borra
/// el archivo al cerrarse.
@MainActor
final class VistaPreviaDocumento: NSObject, QLPreviewControllerDataSource, @preconcurrency QLPreviewControllerDelegate {

    private let archivo: URL
    private var retencion: VistaPreviaDocumento?

    init(archivo: URL) {
        self.archivo = archivo
    }

    func presentar(desde controlador: UIViewController) {
        let vista = QLPreviewController()
        vista.dataSource = self
        vista.delegate = self
        retencion = self
        controlador.present(vista, animated: true)
    }

    func numberOfPreviewItems(in controller: QLPreviewController) -> Int {
        1
    }

    func previewController(_ controller: QLPreviewController, previewItemAt index: Int) -> QLPreviewItem {
        archivo as NSURL
    }

    func previewControllerDidDismiss(_ controller: QLPreviewController) {
        Descargas.borrar(archivo)
        retencion = nil
    }
}

extension UIApplication {

    /// Controlador visible más alto de la ventana principal, para presentar
    /// encima (alertas, Safari, Quick Look). La ventana de la cubierta de
    /// privacidad nunca es la ventana clave, así que no se elige.
    var controladorSuperior: UIViewController? {
        let ventanas = connectedScenes
            .compactMap { $0 as? UIWindowScene }
            .flatMap { $0.windows }
        let principal = ventanas.first { $0.isKeyWindow } ?? ventanas.first { $0.windowLevel == .normal }
        var actual = principal?.rootViewController
        while let siguiente = actual?.presentedViewController, !siguiente.isBeingDismissed {
            actual = siguiente
        }
        return actual
    }
}
