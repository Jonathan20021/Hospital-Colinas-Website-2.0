#!/usr/bin/env ruby
# frozen_string_literal: true
#
# Genera MiHospital.xcodeproj a partir de las carpetas de ios/.
#
# El proyecto generado ya está en el repositorio: este script solo hace falta
# para regenerarlo (por ejemplo, tras agregar archivos desde fuera de Xcode).
#
#   gem install xcodeproj
#   ruby ios/generar-proyecto.rb
#
# Usa la gema de CocoaPods en vez de escribir el .pbxproj a mano.

require 'fileutils'
require 'xcodeproj'

RAIZ = __dir__
RUTA_PROYECTO = File.join(RAIZ, 'MiHospital.xcodeproj')
DESPLIEGUE = '16.0'
# El XCTest de Xcode reciente está hecho para iOS 17: con 16.0 las pruebas
# avisan al enlazar. Solo afecta a las pruebas; la app sigue en iOS 16.
DESPLIEGUE_PRUEBAS = '17.0'

RECURSOS = %w[.xcassets .js .xcprivacy .ttf .txt].freeze
NO_COPIAR = %w[Info.plist .entitlements .xcconfig .md].freeze

FileUtils.rm_rf(RUTA_PROYECTO)
proyecto = Xcodeproj::Project.new(RUTA_PROYECTO, false, 56)
proyecto.root_object.development_region = 'es'
proyecto.root_object.known_regions = %w[es Base]
proyecto.root_object.attributes['LastSwiftUpdateCheck'] = '1600'
proyecto.root_object.attributes['LastUpgradeCheck'] = '1600'
proyecto.root_object.attributes['ORGANIZATIONNAME'] = 'Hospital General Las Colinas'

app = proyecto.new_target(:application, 'MiHospital', :ios, DESPLIEGUE, nil, :swift)
pruebas = proyecto.new_target(:unit_test_bundle, 'MiHospitalTests', :ios, DESPLIEGUE_PRUEBAS, nil, :swift)
pruebas.add_dependency(app)

# La gema enlaza Foundation.framework con una ruta del SDK que lleva número de
# versión (iPhoneOS26.0.sdk): en otro Xcode quedaría en rojo. Swift ya la enlaza
# sola, así que se quita.
proyecto.files.select { |f| f.path.to_s.end_with?('Foundation.framework') }.each do |marco|
  marco.build_files.each(&:remove_from_project)
  marco.remove_from_project
end
proyecto.frameworks_group.recursive_children_groups.reverse_each do |g|
  g.remove_from_project if g.children.empty?
end
proyecto.frameworks_group.remove_from_project if proyecto.frameworks_group.children.empty?

principal = proyecto.main_group

# Agrega una carpeta del disco como grupo, con cada archivo en su fase.
def agregar_carpeta(grupo, carpeta, objetivo)
  Dir.children(carpeta).sort.each do |nombre|
    next if nombre.start_with?('.')

    ruta = File.join(carpeta, nombre)
    if File.directory?(ruta) && !nombre.end_with?('.xcassets')
      agregar_carpeta(grupo.new_group(nombre, nombre), ruta, objetivo)
      next
    end

    referencia = grupo.new_file(ruta)
    if nombre.end_with?('.swift')
      objetivo.source_build_phase.add_file_reference(referencia)
    elsif RECURSOS.any? { |ext| nombre.end_with?(ext) }
      objetivo.resources_build_phase.add_file_reference(referencia)
    elsif NO_COPIAR.none? { |ext| nombre.end_with?(ext) }
      abort "No sé en qué fase va #{ruta}: agrégalo a RECURSOS o NO_COPIAR."
    end
  end
end

agregar_carpeta(principal.new_group('MiHospital', 'MiHospital'), File.join(RAIZ, 'MiHospital'), app)
agregar_carpeta(principal.new_group('MiHospitalTests', 'MiHospitalTests'), File.join(RAIZ, 'MiHospitalTests'), pruebas)

soporte = principal.new_group('Support', 'Support')
soporte.new_file(File.join(RAIZ, 'Support', 'Info.plist'))
soporte.new_file(File.join(RAIZ, 'Support', 'MiHospital.entitlements'))
soporte.new_file(File.join(RAIZ, 'Support', 'MiHospital-basico.entitlements'))

config = principal.new_group('Config', 'Config')
xcconfig = config.new_file(File.join(RAIZ, 'Config', 'Base.xcconfig'))

# Lo que define Base.xcconfig no puede repetirse en el objetivo: ganaría el
# valor del objetivo y el .xcconfig dejaría de mandar.
DEL_XCCONFIG = %w[PRODUCT_BUNDLE_IDENTIFIER DEVELOPMENT_TEAM MARKETING_VERSION CURRENT_PROJECT_VERSION].freeze

app.build_configurations.each do |c|
  c.base_configuration_reference = xcconfig
  ajustes = c.build_settings
  DEL_XCCONFIG.each { |clave| ajustes.delete(clave) }
  ajustes.merge!(
    'ASSETCATALOG_COMPILER_APPICON_NAME' => 'AppIcon',
    'ASSETCATALOG_COMPILER_GLOBAL_ACCENT_COLOR_NAME' => 'AccentColor',
    'CODE_SIGN_ENTITLEMENTS' => '$(HGLC_ENTITLEMENTS)',
    'CODE_SIGN_STYLE' => 'Automatic',
    'ENABLE_PREVIEWS' => 'NO',
    'GENERATE_INFOPLIST_FILE' => 'NO',
    'INFOPLIST_FILE' => 'Support/Info.plist',
    'IPHONEOS_DEPLOYMENT_TARGET' => DESPLIEGUE,
    'LD_RUNPATH_SEARCH_PATHS' => ['$(inherited)', '@executable_path/Frameworks'],
    'PRODUCT_NAME' => '$(TARGET_NAME)',
    'SWIFT_STRICT_CONCURRENCY' => 'minimal',
    'SWIFT_VERSION' => '5.0',
    'TARGETED_DEVICE_FAMILY' => '1,2'
  )
  ajustes['ENABLE_TESTABILITY'] = 'YES' if c.name == 'Debug'
end

pruebas.build_configurations.each do |c|
  c.base_configuration_reference = xcconfig
  ajustes = c.build_settings
  DEL_XCCONFIG.each { |clave| ajustes.delete(clave) }
  ajustes.merge!(
    'BUNDLE_LOADER' => '$(TEST_HOST)',
    'CODE_SIGN_STYLE' => 'Automatic',
    'GENERATE_INFOPLIST_FILE' => 'YES',
    'IPHONEOS_DEPLOYMENT_TARGET' => DESPLIEGUE_PRUEBAS,
    'PRODUCT_BUNDLE_IDENTIFIER' => '$(HGLC_BUNDLE_ID).tests',
    'PRODUCT_NAME' => '$(TARGET_NAME)',
    'SWIFT_STRICT_CONCURRENCY' => 'minimal',
    'SWIFT_VERSION' => '5.0',
    'TARGETED_DEVICE_FAMILY' => '1,2',
    'TEST_HOST' => '$(BUILT_PRODUCTS_DIR)/MiHospital.app/$(BUNDLE_EXECUTABLE_FOLDER_PATH)/MiHospital'
  )
end

proyecto.sort(groups_position: :above)
# Identificadores derivados de las rutas: al regenerar solo cambian los de la
# dependencia app ↔ pruebas (la gema no los estabiliza), no todo el archivo.
proyecto.predictabilize_uuids
proyecto.save

# Esquema compartido: ⌘R abre la app y ⌘U corre las pruebas.
esquema = Xcodeproj::XCScheme.new
esquema.configure_with_targets(app, pruebas, launch_target: true)
esquema.save_as(RUTA_PROYECTO, 'MiHospital', true)

puts "Generado #{RUTA_PROYECTO}"
