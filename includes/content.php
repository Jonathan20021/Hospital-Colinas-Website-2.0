<?php

function service_pages_catalog(array $services, array $assets): array
{
    $categoryMeta = [
        'consultas' => [
            'group' => 'Consulta Especializada',
            'icon' => 'stethoscope',
            'image' => $assets['doctors'],
            'summary' => 'Consulta médica especializada con enfoque diagnóstico, seguimiento y continuidad clínica.',
        ],
        'diagnostico' => [
            'group' => 'Apoyo Diagnóstico',
            'icon' => 'microscope',
            'image' => $assets['ct'],
            'summary' => 'Pruebas, imágenes y estudios clínicos para decisiones médicas oportunas.',
        ],
        'cirugia' => [
            'group' => 'Bloque Quirúrgico',
            'icon' => 'scissors',
            'image' => $assets['corridor'],
            'summary' => 'Procedimientos quirúrgicos con coordinación clínica, tecnología y recuperación segura.',
        ],
        'clinicos' => [
            'group' => 'Servicios Clínicos',
            'icon' => 'heart-pulse',
            'image' => $assets['room'],
            'summary' => 'Atención hospitalaria continua para pacientes ambulatorios, ingresados y críticos.',
        ],
    ];

    $catalog = [];

    foreach ($services as $key => $group) {
        $meta = $categoryMeta[$key] ?? [
            'group' => $group['label'],
            'icon' => $group['icon'] ?? 'hospital',
            'image' => $assets['hero'],
            'summary' => $group['description'] ?? 'Servicio clínico del Hospital General Las Colinas.',
        ];

        foreach ($group['items'] as $item) {
            $slug = content_slug($item);
            $catalog[$slug] = [
                'slug' => $slug,
                'title' => $item,
                'group' => $meta['group'],
                'icon' => $meta['icon'],
                'image' => $meta['image'],
                'summary' => $meta['summary'],
                'lead' => 'Integramos evaluación médica, orientación al paciente y coordinación con las áreas de apoyo del hospital para una atención clara y oportuna.',
                'bullets' => [
                    'Evaluación y seguimiento según la necesidad clínica del paciente.',
                    'Coordinación con laboratorio, imágenes, hospitalización o cirugía cuando aplica.',
                    'Orientación para citas, seguros y documentos requeridos.',
                ],
                'steps' => [
                    'Solicita orientación o agenda tu cita.',
                    'Nuestro equipo confirma disponibilidad e indicaciones.',
                    'Recibe atención presencial en el área correspondiente.',
                ],
            ];
        }
    }

    $curated = [
        'emergencias-24-7' => [
            'title' => 'Emergencias 24/7',
            'group' => 'Atención inmediata',
            'icon' => 'ambulance',
            'image' => $assets['reception'],
            'summary' => 'Respuesta inmediata para adultos y pacientes pediátricos, todos los días.',
            'lead' => 'La unidad de emergencias conecta triaje, evaluación médica, diagnóstico y soporte hospitalario para actuar con rapidez ante situaciones urgentes.',
            'bullets' => ['Atención adulto y pediátrica diferenciada.', 'Acceso a laboratorio e imágenes según indicación médica.', 'Conexión con UCI, hospitalización y bloque quirúrgico.'],
        ],
        'emergencia-adulto-y-pediatrica' => [
            'title' => 'Emergencia Adulto y Pediátrica',
            'group' => 'Atención inmediata',
            'icon' => 'ambulance',
            'image' => $assets['reception'],
            'summary' => 'Atención de urgencias para adultos, niños y familias con soporte clínico permanente.',
            'lead' => 'Nuestro equipo prioriza cada caso con triaje, evaluación médica y coordinación con las áreas críticas del hospital.',
            'bullets' => ['Evaluación inicial y clasificación de prioridad.', 'Soporte para estudios de laboratorio e imágenes.', 'Derivación interna a UCI, cirugía u hospitalización si el caso lo requiere.'],
        ],
        'cuidados-intensivos' => [
            'title' => 'Cuidados Intensivos',
            'group' => 'Atención inmediata',
            'icon' => 'heart-pulse',
            'image' => $assets['bed'],
            'summary' => 'Monitoreo y soporte para pacientes críticos adultos, pediátricos y neonatales.',
            'lead' => 'La unidad está preparada para vigilancia continua, coordinación interdisciplinaria y respuesta clínica de alta complejidad.',
            'bullets' => ['Monitoreo permanente y protocolos de seguridad.', 'Coordinación con especialistas y servicios de apoyo.', 'Acompañamiento organizado para familiares.'],
        ],
        'hospitalizacion' => [
            'title' => 'Hospitalización',
            'group' => 'Atención inmediata',
            'icon' => 'bed',
            'image' => $assets['room'],
            'summary' => 'Habitaciones modernas para recuperación, observación y cuidado integral.',
            'lead' => 'El internamiento combina seguimiento médico, enfermería, comodidad y acceso a servicios de apoyo dentro del hospital.',
            'bullets' => ['65 habitaciones modernas y confortables.', 'Seguimiento clínico durante la estadía.', 'Coordinación con farmacia, laboratorio e imágenes.'],
        ],
        'farmacia' => [
            'title' => 'Farmacia hospitalaria',
            'group' => 'Atención inmediata',
            'icon' => 'pill',
            'image' => $assets['reception'],
            'summary' => 'Dispensación y soporte farmacéutico para pacientes ambulatorios e ingresados.',
            'lead' => 'La farmacia apoya la continuidad del tratamiento con orientación y disponibilidad integrada al flujo hospitalario.',
            'bullets' => ['Soporte para recetas indicadas por médicos del hospital.', 'Integración con hospitalización y emergencias.', 'Orientación administrativa según disponibilidad y cobertura.'],
        ],
        'laboratorio-clinico' => [
            'title' => 'Laboratorio Clínico',
            'group' => 'Diagnóstico',
            'icon' => 'flask-conical',
            'image' => $assets['ct'],
            'summary' => 'Procesamiento de pruebas clínicas para diagnóstico, control y seguimiento médico.',
            'lead' => 'El laboratorio conecta toma de muestras, procesamiento y entrega de resultados para apoyar decisiones clínicas oportunas.',
            'bullets' => ['Pruebas clínicas para pacientes ambulatorios e ingresados.', 'Soporte para emergencias y áreas críticas.', 'Coordinación con consulta especializada.'],
        ],
        'tomografia' => [
            'title' => 'Tomografía',
            'group' => 'Diagnóstico',
            'icon' => 'scan-line',
            'image' => $assets['ct'],
            'summary' => 'Imágenes diagnósticas avanzadas para evaluación médica rápida y precisa.',
            'lead' => 'La tomografía forma parte del ecosistema de imágenes del hospital, con coordinación para pacientes de emergencia, consulta e internamiento.',
            'bullets' => ['Estudios indicados por médicos tratantes.', 'Soporte para emergencias y seguimiento clínico.', 'Entrega organizada de resultados según el flujo del servicio.'],
        ],
        'mamografia' => [
            'title' => 'Mamografía',
            'group' => 'Diagnóstico',
            'icon' => 'scan-line',
            'image' => $assets['mamography'],
            'summary' => 'Estudios de mama para evaluación preventiva, diagnóstica y seguimiento.',
            'lead' => 'El servicio acompaña la detección oportuna con tecnología de imagen y coordinación con especialistas.',
            'bullets' => ['Estudios indicados para prevención y diagnóstico.', 'Orientación para preparación previa.', 'Coordinación con ginecología, oncología o cirugía si aplica.'],
        ],
        'sonografia' => [
            'title' => 'Sonografía',
            'group' => 'Diagnóstico',
            'icon' => 'activity',
            'image' => $assets['exam'],
            'summary' => 'Ultrasonidos para evaluación abdominal, ginecológica, obstétrica y otras indicaciones.',
            'lead' => 'La sonografía permite estudiar estructuras internas de forma no invasiva y apoyar el plan diagnóstico del médico tratante.',
            'bullets' => ['Estudios por indicación médica.', 'Preparación según el tipo de estudio.', 'Resultados integrados al seguimiento clínico.'],
        ],
        'cardiologia' => [
            'title' => 'Cardiología',
            'group' => 'Especialidades',
            'icon' => 'heart-pulse',
            'image' => $assets['doctors'],
            'summary' => 'Evaluación y seguimiento de la salud cardiovascular.',
            'lead' => 'El equipo de cardiología acompaña prevención, diagnóstico, tratamiento y seguimiento de condiciones cardiovasculares.',
            'bullets' => ['Consulta especializada y seguimiento.', 'Pruebas de apoyo como ecocardiograma, Holter, MAPA y esfuerzo.', 'Coordinación con hemodinamia cuando aplica.'],
        ],
        'ginecologia' => [
            'title' => 'Ginecología',
            'group' => 'Especialidades',
            'icon' => 'venus',
            'image' => $assets['exam'],
            'summary' => 'Atención integral para salud femenina, obstetricia y seguimiento preventivo.',
            'lead' => 'La especialidad acompaña controles, diagnósticos, maternidad y continuidad clínica en distintas etapas de la vida.',
            'bullets' => ['Consulta ginecológica y obstétrica.', 'Apoyo diagnóstico con imágenes y laboratorio.', 'Coordinación quirúrgica cuando el caso lo requiere.'],
        ],
        'pediatria' => [
            'title' => 'Pediatría',
            'group' => 'Especialidades',
            'icon' => 'baby',
            'image' => $assets['doctors'],
            'summary' => 'Atención médica para niños, niñas y adolescentes.',
            'lead' => 'Pediatría coordina prevención, evaluación, urgencias y seguimiento con una experiencia clara para familias.',
            'bullets' => ['Consulta pediátrica programada.', 'Soporte de emergencia pediátrica.', 'Vacunación y seguimiento del crecimiento.'],
        ],
        'medicina-interna' => [
            'title' => 'Medicina Interna',
            'group' => 'Especialidades',
            'icon' => 'stethoscope',
            'image' => $assets['doctors'],
            'summary' => 'Evaluación integral de pacientes adultos y coordinación de condiciones complejas.',
            'lead' => 'Medicina interna ayuda a ordenar síntomas, diagnósticos, enfermedades crónicas y referencias a otras especialidades.',
            'bullets' => ['Consulta integral del paciente adulto.', 'Seguimiento de condiciones crónicas.', 'Coordinación con subespecialidades y servicios de apoyo.'],
        ],
        'cirugia-general' => [
            'title' => 'Cirugía General',
            'group' => 'Procedimientos',
            'icon' => 'scissors',
            'image' => $assets['corridor'],
            'summary' => 'Procedimientos quirúrgicos con evaluación preoperatoria y recuperación coordinada.',
            'lead' => 'El equipo quirúrgico trabaja junto a anestesia, enfermería, recuperación y servicios de apoyo para un proceso ordenado.',
            'bullets' => ['Evaluación y preparación preoperatoria.', 'Bloque quirúrgico equipado.', 'Seguimiento postoperatorio y recuperación.'],
        ],
        'cirugia-laparoscopica' => [
            'title' => 'Cirugía Laparoscópica',
            'group' => 'Procedimientos',
            'icon' => 'scissors',
            'image' => $assets['corridor'],
            'summary' => 'Procedimientos de mínima invasión según indicación y evaluación médica.',
            'lead' => 'La laparoscopía permite resolver casos seleccionados con incisiones menores y recuperación planificada.',
            'bullets' => ['Evaluación de elegibilidad por el cirujano.', 'Tecnología quirúrgica para mínima invasión.', 'Recuperación y seguimiento coordinados.'],
        ],
        'unidad-endoscopica' => [
            'title' => 'Unidad Endoscópica',
            'group' => 'Procedimientos',
            'icon' => 'scan-line',
            'image' => $assets['exam'],
            'summary' => 'Estudios y procedimientos endoscópicos para diagnóstico y tratamiento.',
            'lead' => 'La unidad coordina preparación, procedimiento, recuperación y entrega de resultados para cada indicación.',
            'bullets' => ['Broncoscopía, colonoscopía y gastroscopía según indicación.', 'Preparación previa guiada.', 'Recuperación posterior al procedimiento.'],
        ],
        'hemodinamia' => [
            'title' => 'Hemodinamia',
            'group' => 'Procedimientos',
            'icon' => 'activity',
            'image' => $assets['ct'],
            'summary' => 'Procedimientos cardiovasculares diagnósticos y terapéuticos según evaluación especializada.',
            'lead' => 'Hemodinamia se integra con cardiología, emergencia y cuidados críticos para casos cardiovasculares que requieren intervención.',
            'bullets' => ['Evaluación por equipo cardiovascular.', 'Procedimientos diagnósticos y terapéuticos.', 'Seguimiento clínico posterior.'],
        ],
        'diagnostico-avanzado' => [
            'title' => 'Diagnóstico avanzado',
            'group' => 'Diagnóstico',
            'icon' => 'scan-line',
            'image' => $assets['ct'],
            'summary' => 'Imágenes, laboratorio y pruebas especializadas integradas al proceso clínico.',
            'lead' => 'Nuestro apoyo diagnóstico ayuda a confirmar, descartar o dar seguimiento a condiciones médicas con mayor claridad.',
            'bullets' => ['Laboratorio clínico e imágenes.', 'Pruebas cardiovasculares y estudios especializados.', 'Soporte para consulta, emergencia e internamiento.'],
        ],
        'cirugias' => [
            'title' => 'Cirugías',
            'group' => 'Procedimientos',
            'icon' => 'scissors',
            'image' => $assets['corridor'],
            'summary' => 'Procedimientos quirúrgicos generales, laparoscópicos y de alta complejidad.',
            'lead' => 'El bloque quirúrgico coordina evaluación, preparación, anestesia, recuperación y continuidad clínica.',
            'bullets' => ['Quirófanos modernos.', 'Equipo clínico interdisciplinario.', 'Recuperación y seguimiento postoperatorio.'],
        ],
        'consulta-especializada' => [
            'title' => 'Consulta especializada',
            'group' => 'Especialidades',
            'icon' => 'stethoscope',
            'image' => $assets['doctors'],
            'summary' => 'Red de especialistas para diagnóstico, tratamiento y seguimiento.',
            'lead' => 'Conecta con médicos de distintas áreas clínicas en un entorno hospitalario preparado para resolver y referir cuando sea necesario.',
            'bullets' => ['Más de 28 especialidades.', 'Consultorios especializados.', 'Apoyo de diagnóstico y hospitalización.'],
        ],
    ];

    foreach ($curated as $slug => $data) {
        $catalog[$slug] = array_replace($catalog[$slug] ?? [], $data, [
            'slug' => $slug,
            'steps' => $data['steps'] ?? [
                'Agenda u orienta tu solicitud.',
                'Recibe confirmación e indicaciones del equipo.',
                'Acude al área correspondiente para tu atención.',
            ],
        ]);
    }

    return $catalog;
}

function service_page_by_slug(string $slug, array $services, array $assets): ?array
{
    $catalog = service_pages_catalog($services, $assets);
    return $catalog[$slug] ?? null;
}

function site_pages_catalog(array $services, array $assets, array $contact, array $patientRights, array $patientDuties, array $floors): array
{
    return [
        'nosotros' => [
            'title' => 'Sobre Hospital General Las Colinas',
            'nav' => 'Nosotros',
            'active' => 'hospital',
            'kicker' => 'Hospital',
            'summary' => 'Un centro hospitalario en Santiago que integra especialistas, emergencia, diagnóstico, cirugía y hospitalización en una experiencia médica clara y humana.',
            'image' => $assets['hero'],
            'stats' => [
                ['value' => '24/7', 'label' => 'Emergencias'],
                ['value' => '55+', 'label' => 'Consultorios'],
                ['value' => '65+', 'label' => 'Habitaciones'],
            ],
            'sections' => [
                ['icon' => 'heart-handshake', 'title' => 'Enfoque humano', 'text' => 'Atendemos a pacientes y familias con orientación, respeto y comunicación clara durante cada etapa del proceso.'],
                ['icon' => 'building-2', 'title' => 'Infraestructura integrada', 'text' => 'El hospital reúne áreas clínicas, diagnóstico, farmacia, internamiento y soporte administrativo en una misma sede.'],
                ['icon' => 'badge-check', 'title' => 'Calidad asistencial', 'text' => 'Nuestros procesos priorizan seguridad del paciente, continuidad clínica y coordinación entre equipos.'],
            ],
            'links' => [
                ['label' => 'Ver liderazgo institucional', 'href' => base_url('liderazgo-institucional'), 'icon' => 'users-round'],
                ['label' => 'Conocer instalaciones', 'href' => base_url('instalaciones'), 'icon' => 'hospital'],
            ],
        ],
        'liderazgo-institucional' => [
            'title' => 'Liderazgo institucional',
            'nav' => 'Liderazgo',
            'active' => 'hospital',
            'kicker' => 'Gobernanza clínica',
            'summary' => 'La operación del hospital se apoya en liderazgo médico, gestión administrativa y equipos responsables de calidad, planificación y servicios.',
            'image' => $assets['doctors'],
            'stats' => [
                ['value' => '5', 'label' => 'Gerencias clave'],
                ['value' => '1', 'label' => 'Dirección general'],
                ['value' => '24/7', 'label' => 'Operación clínica'],
            ],
            'sections' => [
                ['icon' => 'crown', 'title' => 'Dirección general', 'text' => 'Coordina la visión clínica, administrativa y humana del hospital.'],
                ['icon' => 'stethoscope', 'title' => 'Gestión médica', 'text' => 'Alinea especialidades, servicios, calidad asistencial y seguridad del paciente.'],
                ['icon' => 'target', 'title' => 'Planificación', 'text' => 'Impulsa mejora continua, proyectos institucionales y expansión responsable.'],
            ],
            'links' => [
                ['label' => 'Conocer el hospital', 'href' => base_url('nosotros'), 'icon' => 'building-2'],
                ['label' => 'Directorio médico', 'href' => base_url('directorio-medico'), 'icon' => 'user-round-search'],
            ],
        ],
        'servicios' => [
            'title' => 'Servicios médicos',
            'nav' => 'Servicios',
            'active' => 'servicios',
            'type' => 'services-index',
            'kicker' => 'Directorio clínico',
            'summary' => 'Explora especialidades, diagnóstico, procedimientos y servicios clínicos del Hospital General Las Colinas.',
            'image' => $assets['ct'],
            'stats' => [
                ['value' => (string) service_count($services), 'label' => 'Servicios y especialidades'],
                ['value' => '28+', 'label' => 'Especialidades'],
                ['value' => '24/7', 'label' => 'Emergencias'],
            ],
            'sections' => [
                ['icon' => 'search', 'title' => 'Encuentra rápido', 'text' => 'Cada servicio cuenta con una página individual con orientación, preparación y enlaces de acción.'],
                ['icon' => 'calendar-check', 'title' => 'Agenda coordinada', 'text' => 'Solicita cita y nuestro equipo te confirma disponibilidad e indicaciones.'],
                ['icon' => 'shield-check', 'title' => 'Soporte al paciente', 'text' => 'Te orientamos con seguros, documentos y flujo de atención.'],
            ],
        ],
        'instalaciones' => [
            'title' => 'Instalaciones y tecnología',
            'nav' => 'Instalaciones',
            'active' => 'hospital',
            'kicker' => 'Infraestructura clínica',
            'summary' => 'Áreas modernas, equipos de apoyo diagnóstico y espacios diseñados para seguridad, eficiencia y bienestar.',
            'image' => $assets['corridor'],
            'stats' => [
                ['value' => '6', 'label' => 'Niveles operativos'],
                ['value' => '65+', 'label' => 'Habitaciones'],
                ['value' => '55+', 'label' => 'Consultorios'],
            ],
            'sections' => array_map(static fn (array $floor): array => [
                'icon' => 'layers-3',
                'title' => $floor['level'],
                'text' => $floor['content'],
            ], $floors),
            'links' => [
                ['label' => 'Ver galería en la landing', 'href' => base_url('#galeria'), 'icon' => 'images'],
                ['label' => 'Servicios diagnósticos', 'href' => base_url('servicios/diagnostico-avanzado'), 'icon' => 'scan-line'],
            ],
        ],
        'pacientes' => [
            'title' => 'Pacientes y visitantes',
            'nav' => 'Pacientes',
            'active' => 'hospital',
            'kicker' => 'Guía de atención',
            'summary' => 'Información práctica para preparar tu visita, entender derechos y deberes, y coordinar tu atención con mayor tranquilidad.',
            'image' => $assets['reception'],
            'stats' => [
                ['value' => '5', 'label' => 'Guías rápidas'],
                ['value' => '24/7', 'label' => 'Emergencias'],
                ['value' => '1', 'label' => 'Sede principal'],
            ],
            'sections' => [
                ['icon' => 'clipboard-check', 'title' => 'Admisión y registro', 'text' => 'Ten a mano documento de identidad, seguro, referimiento si aplica y estudios previos.'],
                ['icon' => 'calendar-clock', 'title' => 'Antes de tu cita', 'text' => 'Confirma fecha, indicaciones de preparación y requisitos del servicio solicitado.'],
                ['icon' => 'circle-help', 'title' => 'Orientación', 'text' => 'Nuestro equipo puede ayudarte por teléfono, WhatsApp o en el área de recepción.'],
            ],
            'links' => [
                ['label' => 'Tu visita', 'href' => base_url('tu-visita'), 'icon' => 'map'],
                ['label' => 'Preparación para tu cita', 'href' => base_url('preparacion-para-tu-cita'), 'icon' => 'calendar-clock'],
                ['label' => 'Derechos y deberes', 'href' => base_url('derechos-y-deberes'), 'icon' => 'scale'],
            ],
        ],
        'contacto' => [
            'title' => 'Contacto y ubicación',
            'nav' => 'Contacto',
            'active' => 'hospital',
            'kicker' => 'Estamos en Santiago',
            'summary' => 'Comunícate con el Hospital General Las Colinas o visita nuestra sede en Av. 27 de Febrero, Plaza Colinas Mall.',
            'image' => $assets['hero'],
            'stats' => [
                ['value' => $contact['phone'], 'label' => 'Teléfono'],
                ['value' => 'WhatsApp', 'label' => 'Canal de contacto'],
                ['value' => '24/7', 'label' => 'Emergencias'],
            ],
            'sections' => [
                ['icon' => 'map-pin', 'title' => 'Dirección', 'text' => $contact['address']],
                ['icon' => 'phone', 'title' => 'Teléfono', 'text' => $contact['phone']],
                ['icon' => 'mail', 'title' => 'Correo', 'text' => $contact['email']],
            ],
            'links' => [
                ['label' => 'Abrir en Google Maps', 'href' => $contact['maps'], 'icon' => 'navigation', 'external' => true],
                ['label' => 'Escribir por WhatsApp', 'href' => $contact['whatsapp'], 'icon' => 'message-circle', 'external' => true],
            ],
        ],
        'tu-visita' => [
            'title' => 'Tu visita',
            'nav' => 'Tu visita',
            'active' => 'hospital',
            'kicker' => 'Pacientes y visitantes',
            'summary' => 'Una guía breve para llegar preparado al hospital y hacer tu proceso de atención más ágil.',
            'image' => $assets['reception'],
            'sections' => [
                ['icon' => 'id-card', 'title' => 'Documentos', 'text' => 'Trae documento de identidad, carnet de seguro, autorización si aplica y estudios médicos previos.'],
                ['icon' => 'clock', 'title' => 'Llegada', 'text' => 'Procura llegar con tiempo suficiente para admisión, validación de seguro y orientación al área correspondiente.'],
                ['icon' => 'users-round', 'title' => 'Acompañantes', 'text' => 'Sigue las indicaciones del personal según el área clínica y condición del paciente.'],
            ],
        ],
        'preparacion-para-tu-cita' => [
            'title' => 'Preparación para tu cita',
            'nav' => 'Preparación para tu cita',
            'active' => 'hospital',
            'kicker' => 'Antes de venir',
            'summary' => 'Revisa los pasos básicos para que tu consulta, estudio o procedimiento ocurra sin retrasos evitables.',
            'image' => $assets['exam'],
            'sections' => [
                ['icon' => 'calendar-check', 'title' => 'Confirma tu cita', 'text' => 'Verifica fecha, hora, especialista o servicio solicitado antes de trasladarte.'],
                ['icon' => 'file-check-2', 'title' => 'Lleva tus resultados', 'text' => 'Presenta análisis, imágenes, recetas o reportes previos relacionados con tu motivo de consulta.'],
                ['icon' => 'utensils', 'title' => 'Indicaciones especiales', 'text' => 'Algunos estudios requieren ayuno, preparación o suspensión temporal de medicamentos por indicación médica.'],
            ],
        ],
        'seguros-aceptados' => [
            'title' => 'Seguros aceptados',
            'nav' => 'Seguros aceptados',
            'active' => 'hospital',
            'kicker' => 'Soporte administrativo',
            'summary' => 'Te orientamos con autorizaciones, cobertura y procesos administrativos según tu aseguradora.',
            'image' => $assets['reception'],
            'sections' => [
                ['icon' => 'shield-check', 'title' => 'Validación', 'text' => 'El equipo administrativo verifica cobertura y requisitos según el servicio solicitado.'],
                ['icon' => 'file-text', 'title' => 'Autorizaciones', 'text' => 'Algunos procedimientos o estudios pueden requerir autorización previa de la aseguradora.'],
                ['icon' => 'phone-call', 'title' => 'Consulta antes de venir', 'text' => 'Puedes llamar para recibir orientación sobre documentos y pasos básicos.'],
            ],
        ],
        'derechos-y-deberes' => [
            'title' => 'Derechos y deberes del paciente',
            'nav' => 'Derechos y deberes',
            'active' => 'hospital',
            'kicker' => 'Información para el paciente',
            'summary' => 'Conoce las bases de una relación asistencial clara, respetuosa y segura durante tu atención.',
            'image' => $assets['room'],
            'sections' => [
                ['icon' => 'shield-check', 'title' => 'Derechos del paciente', 'text' => implode(' ', array_slice($patientRights, 0, 4))],
                ['icon' => 'scale', 'title' => 'Deberes del paciente', 'text' => implode(' ', array_slice($patientDuties, 0, 4))],
                ['icon' => 'message-circle', 'title' => 'Comunicación', 'text' => 'Pregunta si no comprendes tu diagnóstico, tratamiento, indicaciones o responsabilidades administrativas.'],
            ],
        ],
        'preguntas-frecuentes' => [
            'title' => 'Preguntas frecuentes',
            'nav' => 'Preguntas frecuentes',
            'active' => 'hospital',
            'kicker' => 'Orientación rápida',
            'summary' => 'Respuestas prácticas para pacientes, familiares y visitantes del Hospital General Las Colinas.',
            'image' => $assets['reception'],
            'sections' => [
                ['icon' => 'calendar-days', 'title' => '¿Cómo agendo una cita?', 'text' => 'Puedes solicitarla desde el botón de agendar, por teléfono o por WhatsApp. El equipo confirma disponibilidad.'],
                ['icon' => 'ambulance', 'title' => '¿Atienden emergencias?', 'text' => 'Sí. Emergencias adulto y pediátrica está disponible 24/7.'],
                ['icon' => 'map-pin', 'title' => '¿Dónde están ubicados?', 'text' => $contact['address']],
                ['icon' => 'file-text', 'title' => '¿Qué llevo a mi consulta?', 'text' => 'Documento de identidad, seguro, estudios previos y cualquier indicación o referimiento disponible.'],
            ],
        ],
        'politica-de-privacidad' => [
            'title' => 'Política de privacidad',
            'nav' => 'Privacidad',
            'active' => '',
            'kicker' => 'Información institucional',
            'summary' => 'Resumen de cómo tratamos la información enviada por formularios, canales de contacto y solicitudes de cita.',
            'image' => $assets['hero'],
            'sections' => [
                ['icon' => 'lock', 'title' => 'Datos recibidos', 'text' => 'Podemos recibir nombre, teléfono, correo, fecha preferida, especialidad solicitada y mensaje enviado por el paciente.'],
                ['icon' => 'shield-check', 'title' => 'Uso de la información', 'text' => 'La información se utiliza para responder solicitudes, coordinar atención y orientar procesos administrativos.'],
                ['icon' => 'mail', 'title' => 'Contacto', 'text' => 'Para dudas sobre privacidad puedes escribir a ' . $contact['email'] . '.'],
            ],
        ],
        'terminos-de-uso' => [
            'title' => 'Términos de uso',
            'nav' => 'Términos',
            'active' => '',
            'kicker' => 'Uso del sitio web',
            'summary' => 'Condiciones generales para navegar este sitio y utilizar sus formularios de contacto y solicitud de citas.',
            'image' => $assets['hero'],
            'sections' => [
                ['icon' => 'info', 'title' => 'Contenido informativo', 'text' => 'La información del sitio orienta al usuario y no sustituye la evaluación médica presencial.'],
                ['icon' => 'calendar-check', 'title' => 'Solicitudes de cita', 'text' => 'Enviar un formulario no confirma automáticamente una cita; el equipo del hospital debe validar disponibilidad.'],
                ['icon' => 'alert-triangle', 'title' => 'Emergencias', 'text' => 'Ante una urgencia, llama al ' . $contact['phone'] . ' o acude al área de Emergencias 24/7.'],
            ],
        ],
        'mapa-del-sitio' => [
            'title' => 'Mapa del sitio',
            'nav' => 'Mapa del sitio',
            'active' => '',
            'type' => 'sitemap',
            'kicker' => 'Navegación',
            'summary' => 'Acceso directo a las páginas principales, guías de pacientes y servicios individuales.',
            'image' => $assets['corridor'],
            'sections' => [
                ['icon' => 'building-2', 'title' => 'Hospital', 'text' => 'Nosotros, liderazgo, instalaciones, pacientes y contacto.'],
                ['icon' => 'stethoscope', 'title' => 'Servicios', 'text' => 'Especialidades, diagnóstico, procedimientos y atención inmediata.'],
                ['icon' => 'newspaper', 'title' => 'Contenido', 'text' => 'Directorio médico, noticias y páginas informativas.'],
            ],
        ],
    ];
}

function site_page_by_slug(string $slug, array $services, array $assets, array $contact, array $patientRights, array $patientDuties, array $floors): ?array
{
    $catalog = site_pages_catalog($services, $assets, $contact, $patientRights, $patientDuties, $floors);
    return $catalog[$slug] ?? null;
}
