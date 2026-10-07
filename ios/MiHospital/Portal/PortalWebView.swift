import SwiftUI
import UIKit
import WebKit

/// La vista web del portal. Va de borde a borde: el CSS del portal ya respeta
/// el notch y la barra de inicio con `env(safe-area-inset-*)`, igual que la
/// PWA instalada.
struct PortalWebView: UIViewRepresentable {

    let modelo: PortalModel

    func makeCoordinator() -> Coordinador {
        Coordinador(modelo: modelo)
    }

    func makeUIView(context: Context) -> WKWebView {
        context.coordinator.crearVistaWeb()
    }

    func updateUIView(_ vistaWeb: WKWebView, context: Context) {}
}

/// Delegado de navegación, de interfaz, de descargas y del puente con la web.
@MainActor
final class Coordinador: NSObject, WKNavigationDelegate, WKUIDelegate, WKDownloadDelegate,
    WKScriptMessageHandlerWithReply {

    private let modelo: PortalModel
    private var observadorProgreso: NSKeyValueObservation?
    private var destinosDeDescarga: [ObjectIdentifier: URL] = [:]
    private var progresoDeDescargas: [ObjectIdentifier: NSKeyValueObservation] = [:]
    /// Descargas que canceló el paciente: su fallo no se avisa como error.
    private var descargasCanceladas: Set<ObjectIdentifier> = []

    init(modelo: PortalModel) {
        self.modelo = modelo
    }

    func crearVistaWeb() -> WKWebView {
        let contenido = WKUserContentController()
        contenido.addUserScript(WKUserScript(source: BridgeScript.fuente,
                                             injectionTime: .atDocumentStart,
                                             forMainFrameOnly: true))
        contenido.addScriptMessageHandler(PuenteDebil(destino: self), contentWorld: .page, name: "hglc")

        let configuracion = WKWebViewConfiguration()
        configuracion.userContentController = contenido
        configuracion.websiteDataStore = .default()
        configuracion.applicationNameForUserAgent = AppConfig.agenteDeUsuario
        configuracion.allowsInlineMediaPlayback = true
        // Sin detectores: una cédula como 402-1234567-8 no debe volverse un teléfono.
        configuracion.dataDetectorTypes = []
        // window.open() del portal llega a createWebViewWith y se abre en la misma vista.
        configuracion.preferences.javaScriptCanOpenWindowsAutomatically = true

        let vista = WKWebView(frame: .zero, configuration: configuracion)
        vista.navigationDelegate = self
        vista.uiDelegate = self
        vista.allowsBackForwardNavigationGestures = true
        // La vista previa al mantener pulsado ofrece "Abrir en Safari", donde no hay sesión.
        vista.allowsLinkPreview = false
        vista.scrollView.contentInsetAdjustmentBehavior = .never
        vista.isOpaque = false
        vista.backgroundColor = UIColor(named: "Fondo")
        vista.scrollView.backgroundColor = UIColor(named: "Fondo")
        #if DEBUG
        if #available(iOS 16.4, *) {
            vista.isInspectable = true   // Safari > Desarrollo, solo en depuración
        }
        #endif

        observadorProgreso = vista.observe(\.estimatedProgress, options: [.new]) { [weak self] _, cambio in
            guard let valor = cambio.newValue else { return }
            Task { @MainActor [weak self] in
                self?.modelo.progresoCambio(valor)
            }
        }

        modelo.vistaWeb = vista
        vista.load(URLRequest(url: modelo.urlDeArranque()))
        return vista
    }

    // MARK: Política de navegación

    func webView(_ webView: WKWebView, decidePolicyFor navigationAction: WKNavigationAction,
                 decisionHandler: @escaping (WKNavigationActionPolicy) -> Void) {
        guard let url = navigationAction.request.url else {
            decisionHandler(.cancel)
            return
        }
        let nuevaVentana = navigationAction.targetFrame == nil
        let esPrincipal = nuevaVentana || navigationAction.targetFrame?.isMainFrame == true
        let politica = modelo.politica

        if esPrincipal && politica.esCierreDeSesion(url) {
            if modelo.dejarPasarCierreDeSesion() {
                decisionHandler(.allow)
            } else {
                decisionHandler(.cancel)
                modelo.cerrarSesion(url: url)
            }
            return
        }

        switch politica.decidir(url, esMarcoPrincipal: esPrincipal) {
        case .permitir:
            if nuevaVentana {
                // target="_blank": como en la PWA instalada, en la misma vista.
                decisionHandler(.cancel)
                webView.load(navigationAction.request)
            } else {
                if esPrincipal { modelo.navegacionIntentada(url) }
                decisionHandler(.allow)
            }
        case .permitirSeguro(let segura):
            decisionHandler(.cancel)
            webView.load(URLRequest(url: segura))
        case .abrirEnNavegador(let externa):
            decisionHandler(.cancel)
            modelo.abrirFuera(externa, enNavegador: true)
        case .abrirConSistema(let externa):
            decisionHandler(.cancel)
            modelo.abrirFuera(externa, enNavegador: false)
        case .bloquear:
            decisionHandler(.cancel)
        }
    }

    /// PDF, imágenes y adjuntos no se muestran "pelados" en la vista web (sin
    /// botón para volver): se descargan a un temporal y se abren en Quick Look,
    /// que trae compartir, imprimir y guardar en Archivos.
    func webView(_ webView: WKWebView, decidePolicyFor navigationResponse: WKNavigationResponse,
                 decisionHandler: @escaping (WKNavigationResponsePolicy) -> Void) {
        if navigationResponse.isForMainFrame
            && Descargas.esDocumento(navigationResponse.response,
                                     sePuedeMostrar: navigationResponse.canShowMIMEType) {
            decisionHandler(.download)
        } else {
            decisionHandler(.allow)
        }
    }

    func webView(_ webView: WKWebView, navigationResponse: WKNavigationResponse, didBecome download: WKDownload) {
        download.delegate = self
    }

    func webView(_ webView: WKWebView, navigationAction: WKNavigationAction, didBecome download: WKDownload) {
        download.delegate = self
    }

    // MARK: Ciclo de carga

    func webView(_ webView: WKWebView, didStartProvisionalNavigation navigation: WKNavigation!) {
        modelo.empezoCarga()
    }

    func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
        modelo.terminoCarga()
    }

    func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
        modelo.falloCarga(error, provisional: false)
    }

    func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!,
                 withError error: Error) {
        modelo.falloCarga(error, provisional: true)
    }

    func webViewWebContentProcessDidTerminate(_ webView: WKWebView) {
        modelo.procesoWebTerminado()
    }

    // MARK: Ventanas nuevas y diálogos de JavaScript

    func webView(_ webView: WKWebView, createWebViewWith configuration: WKWebViewConfiguration,
                 for navigationAction: WKNavigationAction, windowFeatures: WKWindowFeatures) -> WKWebView? {
        guard let url = navigationAction.request.url else { return nil }
        switch modelo.politica.decidir(url, esMarcoPrincipal: true) {
        case .permitir:
            webView.load(navigationAction.request)
        case .permitirSeguro(let segura):
            webView.load(URLRequest(url: segura))
        case .abrirEnNavegador(let externa):
            modelo.abrirFuera(externa, enNavegador: true)
        case .abrirConSistema(let externa):
            modelo.abrirFuera(externa, enNavegador: false)
        case .bloquear:
            break
        }
        return nil
    }

    // Sin estos tres, WKWebView ignora alert/confirm/prompt en silencio y un
    // confirm() devuelve false: la acción que pedía confirmación no ocurre nunca.

    func webView(_ webView: WKWebView, runJavaScriptAlertPanelWithMessage message: String,
                 initiatedByFrame frame: WKFrameInfo, completionHandler: @escaping () -> Void) {
        let alerta = UIAlertController(title: nil, message: message, preferredStyle: .alert)
        alerta.addAction(UIAlertAction(title: "Aceptar", style: .default) { _ in completionHandler() })
        presentar(alerta) { completionHandler() }
    }

    func webView(_ webView: WKWebView, runJavaScriptConfirmPanelWithMessage message: String,
                 initiatedByFrame frame: WKFrameInfo, completionHandler: @escaping (Bool) -> Void) {
        let alerta = UIAlertController(title: nil, message: message, preferredStyle: .alert)
        alerta.addAction(UIAlertAction(title: "Cancelar", style: .cancel) { _ in completionHandler(false) })
        alerta.addAction(UIAlertAction(title: "Aceptar", style: .default) { _ in completionHandler(true) })
        presentar(alerta) { completionHandler(false) }
    }

    func webView(_ webView: WKWebView, runJavaScriptTextInputPanelWithPrompt prompt: String,
                 defaultText: String?, initiatedByFrame frame: WKFrameInfo,
                 completionHandler: @escaping (String?) -> Void) {
        let alerta = UIAlertController(title: nil, message: prompt, preferredStyle: .alert)
        alerta.addTextField { campo in campo.text = defaultText }
        alerta.addAction(UIAlertAction(title: "Cancelar", style: .cancel) { _ in completionHandler(nil) })
        alerta.addAction(UIAlertAction(title: "Aceptar", style: .default) { [weak alerta] _ in
            completionHandler(alerta?.textFields?.first?.text ?? "")
        })
        presentar(alerta) { completionHandler(nil) }
    }

    /// WebKit exige que el completionHandler se llame siempre: si no hay dónde
    /// presentar la alerta, se responde de inmediato.
    private func presentar(_ alerta: UIAlertController, siNoSePuede: () -> Void) {
        guard let superior = UIApplication.shared.controladorSuperior else {
            siNoSePuede()
            return
        }
        superior.present(alerta, animated: true)
    }

    // MARK: Descargas

    func download(_ download: WKDownload, decideDestinationUsing response: URLResponse,
                  suggestedFilename: String, completionHandler: @escaping (URL?) -> Void) {
        guard let destino = Descargas.nuevoDestino(nombreSugerido: suggestedFilename,
                                                   tipo: response.mimeType) else {
            completionHandler(nil)
            return
        }
        let id = ObjectIdentifier(download)
        destinosDeDescarga[id] = destino
        modelo.descargaEmpezo { [weak self, weak download] in
            guard let download else { return }
            self?.descargasCanceladas.insert(ObjectIdentifier(download))
            download.cancel(nil)
        }
        progresoDeDescargas[id] = download.progress.observe(\.fractionCompleted, options: [.new]) { [weak self] progreso, _ in
            let valor = progreso.fractionCompleted
            Task { @MainActor [weak self] in
                self?.modelo.descargaAvanzo(valor)
            }
        }
        completionHandler(destino)
    }

    func downloadDidFinish(_ download: WKDownload) {
        let id = ObjectIdentifier(download)
        progresoDeDescargas.removeValue(forKey: id)
        guard let archivo = destinosDeDescarga.removeValue(forKey: id) else {
            modelo.descargaTermino()
            return
        }
        if descargasCanceladas.remove(id) != nil {
            Descargas.borrar(archivo)
            return
        }
        modelo.descargaTermino()
        modelo.mostrarDocumento(archivo)
    }

    func download(_ download: WKDownload, didFailWithError error: Error, resumeData: Data?) {
        let id = ObjectIdentifier(download)
        progresoDeDescargas.removeValue(forKey: id)
        if let archivo = destinosDeDescarga.removeValue(forKey: id) {
            Descargas.borrar(archivo)
        }
        if descargasCanceladas.remove(id) != nil { return }
        modelo.descargaTermino()
        modelo.avisar("No se pudo abrir el documento. Revisa tu conexión e inténtalo de nuevo.")
    }

    // MARK: Puente con la web

    func userContentController(_ userContentController: WKUserContentController,
                               didReceive message: WKScriptMessage,
                               replyHandler: @escaping (Any?, String?) -> Void) {
        // Solo el portal, en el marco principal y por HTTPS, puede hablar con la app.
        let origen = message.frameInfo.securityOrigin
        guard message.frameInfo.isMainFrame,
              origen.protocol == "https",
              modelo.politica.esHostDelPortal(origen.host),
              let datos = message.body as? [String: Any],
              let accion = datos["accion"] as? String
        else {
            replyHandler(nil, "Mensaje no permitido")
            return
        }
        let peticion = PortalModel.Peticion(
            accion: accion,
            ruta: datos["ruta"] as? String,
            sesion: datos["sesion"] as? Bool ?? false
        )
        Task { @MainActor in
            if let respuesta = await self.modelo.atender(peticion) {
                replyHandler(respuesta, nil)
            } else {
                replyHandler(nil, "Acción desconocida: \(accion)")
            }
        }
    }
}

/// WKUserContentController retiene a su manejador: este intermediario débil
/// evita el ciclo vista web → controlador → coordinador → vista web.
@MainActor
final class PuenteDebil: NSObject, WKScriptMessageHandlerWithReply {

    weak var destino: Coordinador?

    init(destino: Coordinador) {
        self.destino = destino
    }

    func userContentController(_ userContentController: WKUserContentController,
                               didReceive message: WKScriptMessage,
                               replyHandler: @escaping (Any?, String?) -> Void) {
        guard let destino else {
            replyHandler(nil, "La app ya no escucha")
            return
        }
        destino.userContentController(userContentController, didReceive: message, replyHandler: replyHandler)
    }
}
