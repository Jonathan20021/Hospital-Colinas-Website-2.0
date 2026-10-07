import SwiftUI

@main
struct MiHospitalApp: App {

    @UIApplicationDelegateAdaptor(AppDelegate.self) private var appDelegate
    @StateObject private var portal = PortalModel()
    @StateObject private var bloqueo = AppLock.shared

    var body: some Scene {
        WindowGroup {
            PortalView()
                .environmentObject(portal)
                .environmentObject(bloqueo)
        }
    }
}
