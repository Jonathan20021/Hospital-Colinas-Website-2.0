import XCTest
@testable import MiHospital

/// La política de navegación decide qué queda dentro de la app con la sesión
/// del paciente y qué se abre fuera. Estas pruebas fijan ese límite.
final class NavigationPolicyTests: XCTestCase {

    private let politica = NavigationPolicy(hostPortal: "colinashospital.com")

    private func decidir(_ texto: String, principal: Bool = true) -> NavigationPolicy.Decision {
        politica.decidir(URL(string: texto)!, esMarcoPrincipal: principal)
    }

    func testElPortalSeQuedaEnLaApp() {
        XCTAssertEqual(decidir("https://colinashospital.com/portal/"), .permitir)
        XCTAssertEqual(decidir("https://colinashospital.com/portal/dashboard.php"), .permitir)
        XCTAssertEqual(decidir("https://colinashospital.com/portal/login"), .permitir)
        XCTAssertEqual(decidir("https://www.colinashospital.com/portal/mensajes.php"), .permitir)
        XCTAssertEqual(decidir("https://colinashospital.com/portal/receta-pdf.php?id=7"), .permitir)
        XCTAssertEqual(decidir("https://colinashospital.com/api/portal-chat-file.php?id=3"), .permitir)
    }

    func testElPortalEnClaroSubeAHTTPS() {
        let url = URL(string: "https://colinashospital.com/portal/mis-citas.php")!
        XCTAssertEqual(decidir("http://colinashospital.com/portal/mis-citas.php"), .permitirSeguro(url))
    }

    func testElRestoDelSitioSeAbreAparte() {
        let publica = "https://colinashospital.com/preparacion-para-tu-cita"
        XCTAssertEqual(decidir(publica), .abrirEnNavegador(URL(string: publica)!))
        let medico = "https://colinashospital.com/portal-medico/agenda.php"
        XCTAssertEqual(decidir(medico), .abrirEnNavegador(URL(string: medico)!))
        let admin = "https://colinashospital.com/admin/"
        XCTAssertEqual(decidir(admin), .abrirEnNavegador(URL(string: admin)!))
    }

    func testSalirDelPortalConPuntosNoCuentaComoPortal() {
        let truco = "https://colinashospital.com/portal/../admin/"
        XCTAssertEqual(decidir(truco), .abrirEnNavegador(URL(string: truco)!))
    }

    func testOtrosDominiosSeAbrenAparte() {
        let mapa = "https://maps.google.com/?q=Hospital+General+Las+Colinas"
        XCTAssertEqual(decidir(mapa), .abrirEnNavegador(URL(string: mapa)!))
        let parecido = "https://colinashospital.com.evil.example/portal/"
        XCTAssertEqual(decidir(parecido), .abrirEnNavegador(URL(string: parecido)!))
    }

    func testTelefonoCorreoYWhatsAppLosManejaElSistema() {
        for texto in ["tel:8098060444", "mailto:info@colinashospital.com", "whatsapp://send?phone=18098060444"] {
            XCTAssertEqual(decidir(texto), .abrirConSistema(URL(string: texto)!), texto)
        }
    }

    func testEsquemasPeligrososSeBloquean() {
        XCTAssertEqual(decidir("javascript:alert(1)"), .bloquear)
        XCTAssertEqual(decidir("file:///etc/passwd"), .bloquear)
        XCTAssertEqual(decidir("ftp://colinashospital.com/portal/"), .bloquear)
    }

    func testLosIframesQuedanBajoLaCSPDelPortal() {
        XCTAssertEqual(decidir("https://otro.example/widget", principal: false), .permitir)
    }

    func testCierreDeSesionConYSinExtension() {
        XCTAssertTrue(politica.esCierreDeSesion(URL(string: "https://colinashospital.com/portal/logout.php")!))
        XCTAssertTrue(politica.esCierreDeSesion(URL(string: "https://colinashospital.com/portal/logout")!))
        XCTAssertFalse(politica.esCierreDeSesion(URL(string: "https://colinashospital.com/portal-medico/logout.php")!))
        XCTAssertFalse(politica.esCierreDeSesion(URL(string: "https://otro.example/portal/logout.php")!))
    }

    func testLasNotificacionesSoloAbrenRutasDelPortal() {
        XCTAssertEqual(politica.urlParaRuta("/portal/mensajes.php")?.absoluteString,
                       "https://colinashospital.com/portal/mensajes.php")
        XCTAssertEqual(politica.urlParaRuta("/portal/mis-citas.php?cita=12")?.absoluteString,
                       "https://colinashospital.com/portal/mis-citas.php?cita=12")
        XCTAssertNil(politica.urlParaRuta(nil))
        XCTAssertNil(politica.urlParaRuta(""))
        XCTAssertNil(politica.urlParaRuta("https://evil.example/portal/"))
        XCTAssertNil(politica.urlParaRuta("//evil.example/portal/"))
        XCTAssertNil(politica.urlParaRuta("/portal//evil.example"))
        XCTAssertNil(politica.urlParaRuta("/portal/@evil.example"))
        XCTAssertNil(politica.urlParaRuta("/admin/"))
        XCTAssertNil(politica.urlParaRuta("/portal/../admin/"))
    }

    func testNombresDeArchivoSeguros() {
        XCTAssertEqual(Descargas.nombreLimpio("receta.pdf"), "receta.pdf")
        XCTAssertEqual(Descargas.nombreLimpio("../../etc/passwd"), "documento.._.._etc_passwd")
        XCTAssertEqual(Descargas.nombreLimpio(""), "documento")
        XCTAssertEqual(Descargas.nombreLimpio(".pdf"), "documento.pdf")
    }

    func testDocumentosVanAQuickLookYPaginasALaVistaWeb() {
        let url = URL(string: "https://colinashospital.com/portal/receta-pdf.php?id=1")!
        let pdf = HTTPURLResponse(url: url, statusCode: 200, httpVersion: nil,
                                  headerFields: ["Content-Type": "application/pdf"])!
        XCTAssertTrue(Descargas.esDocumento(pdf, sePuedeMostrar: true))

        let html = HTTPURLResponse(url: url, statusCode: 200, httpVersion: nil,
                                   headerFields: ["Content-Type": "text/html; charset=utf-8"])!
        XCTAssertFalse(Descargas.esDocumento(html, sePuedeMostrar: true))

        let adjunto = HTTPURLResponse(url: url, statusCode: 200, httpVersion: nil,
                                      headerFields: ["Content-Type": "text/html",
                                                     "Content-Disposition": "attachment; filename=\"x.html\""])!
        XCTAssertTrue(Descargas.esDocumento(adjunto, sePuedeMostrar: true))
    }

    /// Los accesos rápidos del ícono (Info.plist) abren rutas del portal que
    /// la política acepta: una errata en el plist no debe dejar uno muerto.
    func testLosAccesosRapidosAbrenRutasValidas() throws {
        let accesos = try XCTUnwrap(
            Bundle.main.object(forInfoDictionaryKey: "UIApplicationShortcutItems") as? [[String: Any]]
        )
        XCTAssertFalse(accesos.isEmpty)
        let politicaDeLaApp = NavigationPolicy(hostPortal: AppConfig.hostPortal)
        for acceso in accesos {
            let ruta = (acceso["UIApplicationShortcutItemUserInfo"] as? [String: Any])?["ruta"] as? String
            XCTAssertNotNil(politicaDeLaApp.urlParaRuta(ruta), "Ruta no válida: \(ruta ?? "nil")")
        }
    }
}
