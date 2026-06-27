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
            'title' => 'Política de privacidad y protección de datos personales',
            'nav' => 'Privacidad',
            'active' => '',
            'type' => 'legal',
            'kicker' => 'Información institucional',
            'summary' => 'Cómo el Hospital General Las Colinas recopila, usa, protege, conserva y comparte sus datos personales y de salud, conforme a la legislación dominicana.',
            'image' => $assets['hero'],
            'updated' => '26 de junio de 2026',
            'version' => '1.0',
            'legal_intro' => 'El Hospital General Las Colinas (en lo adelante, «el Hospital», «nosotros» o «el responsable») reconoce la privacidad y la protección de los datos personales como un derecho fundamental consagrado en el artículo 44 de la Constitución de la República Dominicana y desarrollado por la Ley No. 172-13 sobre protección integral de los datos personales. La presente Política describe de forma transparente cómo recopilamos, utilizamos, protegemos, conservamos y comunicamos los datos personales de pacientes, usuarios del sitio web, familiares y visitantes, así como los derechos que le asisten y la manera de ejercerlos. Le recomendamos leerla con atención.',
            'articles' => [
                [
                    'id' => 'responsable', 'num' => '1', 'title' => 'Responsable del tratamiento',
                    'blocks' => [
                        ['p' => 'El responsable del tratamiento de los datos personales recolectados a través de este sitio web y de nuestros servicios asistenciales es:'],
                        ['deflist' => [
                            'Razón social' => 'HOSPITAL GENERAL LAS COLINAS, SAS',
                            'RNC' => '131293281',
                            'Domicilio' => $contact['address'],
                            'Teléfono' => $contact['phone'],
                            'Correo electrónico' => $contact['email'],
                            'Sitio web' => 'colinashospital.com',
                        ]],
                        ['p' => 'Para cualquier asunto relacionado con esta Política o con el tratamiento de sus datos personales, puede comunicarse con nosotros al correo ' . $contact['email'] . '.'],
                    ],
                ],
                [
                    'id' => 'marco-legal', 'num' => '2', 'title' => 'Marco legal aplicable',
                    'blocks' => [
                        ['p' => 'El tratamiento de datos personales que realiza el Hospital se rige por la legislación dominicana vigente, en particular:'],
                        ['list' => [
                            'Constitución de la República Dominicana, artículo 44 (derecho a la intimidad, al honor personal y a la protección de los datos personales, incluida la acción de habeas data).',
                            'Ley No. 172-13, sobre la protección integral de los datos personales asentados en archivos, registros públicos, bancos de datos u otros medios técnicos de tratamiento de datos.',
                            'Ley General de Salud No. 42-01 y sus reglamentos, en lo relativo a la confidencialidad de la información de salud y al manejo del expediente clínico.',
                            'Ley No. 53-07, sobre Crímenes y Delitos de Alta Tecnología.',
                            'Ley No. 358-05, General de Protección de los Derechos del Consumidor o Usuario.',
                            'Ley No. 126-02, sobre Comercio Electrónico, Documentos y Firmas Digitales.',
                            'Las normas y disposiciones del Ministerio de Salud Pública (MSP), el Servicio Nacional de Salud (SNS) y la Superintendencia de Salud y Riesgos Laborales (SISALRIL) aplicables a los establecimientos de salud.',
                        ]],
                        ['p' => 'El personal de salud que interviene en su atención está sujeto, además, al deber de secreto profesional médico, de conformidad con la Ley General de Salud y la ética profesional.'],
                    ],
                ],
                [
                    'id' => 'definiciones', 'num' => '3', 'title' => 'Definiciones',
                    'blocks' => [
                        ['deflist' => [
                            'Datos personales' => 'Cualquier información de cualquier tipo referida a personas físicas identificadas o identificables.',
                            'Datos sensibles' => 'Datos que revelan origen racial o étnico, opiniones políticas, convicciones religiosas, filosóficas o morales, afiliación sindical, información referente a la salud o a la vida sexual. Reciben protección reforzada.',
                            'Datos de salud' => 'Información relativa a la salud física o mental, pasada, presente o futura, de una persona, incluida la contenida en el expediente clínico.',
                            'Tratamiento' => 'Toda operación o procedimiento aplicado a los datos: recolección, registro, organización, conservación, uso, modificación, consulta, comunicación o supresión.',
                            'Titular' => 'La persona física a quien corresponden los datos objeto de tratamiento.',
                            'Consentimiento' => 'Manifestación de voluntad libre, expresa e informada mediante la cual el titular acepta el tratamiento de sus datos.',
                            'Encargado del tratamiento' => 'La persona o entidad que trata datos personales por cuenta del responsable, bajo contrato y obligación de confidencialidad.',
                        ]],
                    ],
                ],
                [
                    'id' => 'datos-recopilados', 'num' => '4', 'title' => 'Datos personales que recopilamos',
                    'blocks' => [
                        ['p' => 'Según su interacción con el Hospital, podemos recopilar las siguientes categorías de datos:'],
                        ['sub' => 'a) Datos de identificación y contacto'],
                        ['list' => [
                            'Nombres y apellidos, número de cédula o pasaporte, fecha de nacimiento y sexo.',
                            'Teléfono, correo electrónico y dirección.',
                        ]],
                        ['sub' => 'b) Datos de salud (datos sensibles)'],
                        ['list' => [
                            'Motivo de consulta, especialidad solicitada y antecedentes que usted nos suministre.',
                            'Diagnósticos, resultados de estudios, prescripciones e información contenida en el expediente clínico.',
                        ]],
                        ['p' => 'Estos datos solo se recaban cuando usted solicita o recibe atención, y reciben protección reforzada.'],
                        ['sub' => 'c) Datos de seguro y facturación'],
                        ['list' => [
                            'Aseguradora (ARS), número de afiliado y autorizaciones de cobertura.',
                            'Datos necesarios para la facturación de los servicios.',
                        ]],
                        ['sub' => 'd) Datos de terceros'],
                        ['list' => [
                            'Datos de familiares, contactos de emergencia o representantes legales de menores y personas bajo tutela.',
                        ]],
                        ['p' => 'Al aportar datos de terceros, usted declara contar con su autorización para ello.'],
                        ['sub' => 'e) Datos de navegación'],
                        ['list' => [
                            'Dirección IP, tipo de dispositivo y navegador, páginas visitadas, fecha y hora de acceso.',
                            'Cookies y registros (logs) generados por nuestros servidores.',
                        ]],
                    ],
                ],
                [
                    'id' => 'finalidades', 'num' => '5', 'title' => 'Finalidades del tratamiento',
                    'blocks' => [
                        ['p' => 'Tratamos sus datos personales para las siguientes finalidades:'],
                        ['list' => [
                            'Prestar atención médica y dar seguimiento clínico.',
                            'Gestionar, confirmar y recordar citas y estudios.',
                            'Tramitar la cobertura con las ARS y gestionar la facturación.',
                            'Comunicarnos con usted para responder solicitudes y orientar procesos administrativos.',
                            'Cumplir obligaciones legales, sanitarias, contables y fiscales.',
                            'Velar por la seguridad de las personas, las instalaciones y los sistemas de información.',
                            'Operar el Portal del Paciente y el Portal Médico.',
                            'Mejorar la calidad de nuestros servicios y gestionar quejas y reclamaciones.',
                        ]],
                        ['p' => 'No utilizamos sus datos para finalidades distintas o incompatibles con las aquí descritas sin informarle previamente.'],
                    ],
                ],
                [
                    'id' => 'base-licitud', 'num' => '6', 'title' => 'Base de licitud y consentimiento',
                    'blocks' => [
                        ['p' => 'El tratamiento de sus datos se fundamenta, según el caso, en:'],
                        ['list' => [
                            'Su consentimiento libre, expreso e informado.',
                            'La ejecución de la relación asistencial que usted solicita.',
                            'El cumplimiento de una obligación legal a cargo del Hospital.',
                            'La protección del interés vital del paciente, especialmente en situaciones de emergencia.',
                            'El interés legítimo del Hospital, cuando sea compatible con sus derechos y libertades.',
                        ]],
                        ['p' => 'El tratamiento de datos sensibles de salud se realiza con su consentimiento informado o en el marco de la atención sanitaria prestada por profesionales sujetos al deber de secreto.'],
                    ],
                ],
                [
                    'id' => 'datos-sensibles', 'num' => '7', 'title' => 'Datos sensibles y secreto profesional',
                    'blocks' => [
                        ['p' => 'Los datos de salud son datos sensibles y gozan de protección reforzada conforme a la Ley No. 172-13 y a la Ley General de Salud No. 42-01. Únicamente acceden a ellos los profesionales y el personal autorizado que interviene en su atención, todos sujetos al deber de confidencialidad y secreto profesional. El expediente clínico es confidencial y solo se comparte en los supuestos permitidos por la ley.'],
                    ],
                ],
                [
                    'id' => 'cookies', 'num' => '8', 'title' => 'Cookies y tecnologías similares',
                    'blocks' => [
                        ['p' => 'Utilizamos cookies y tecnologías similares para el correcto funcionamiento del sitio y de los portales:'],
                        ['list' => [
                            'Cookies técnicas o esenciales: mantienen la sesión, refuerzan la seguridad y permiten el funcionamiento de los formularios y portales.',
                            'Servicios de terceros embebidos (por ejemplo, mapas y fuentes tipográficas) que pueden establecer sus propias cookies, regidas por las políticas de dichos proveedores.',
                        ]],
                        ['p' => 'Usted puede configurar o eliminar las cookies desde su navegador. Tenga en cuenta que deshabilitar las cookies técnicas puede afectar el funcionamiento de algunas secciones del sitio.'],
                    ],
                ],
                [
                    'id' => 'conservacion', 'num' => '9', 'title' => 'Plazo de conservación',
                    'blocks' => [
                        ['p' => 'Conservamos sus datos personales durante el tiempo necesario para cumplir las finalidades para las que fueron recabados y para atender las responsabilidades legales que de ellos se deriven. En particular:'],
                        ['list' => [
                            'El expediente clínico se conserva durante el plazo que establece la normativa sanitaria dominicana vigente.',
                            'Los datos de contacto y de gestión se conservan mientras exista la relación con el Hospital y durante los plazos legales (contables, fiscales y administrativos) aplicables.',
                            'Los registros técnicos (logs) se conservan por un período limitado con fines de seguridad.',
                        ]],
                        ['p' => 'Cumplidos dichos plazos, los datos se suprimen o se anonimizan de forma segura.'],
                    ],
                ],
                [
                    'id' => 'cesion', 'num' => '10', 'title' => 'Comunicación y cesión a terceros',
                    'blocks' => [
                        ['p' => 'El Hospital no vende ni comercializa sus datos personales. Podemos comunicarlos, cuando sea necesario y conforme a la ley, a:'],
                        ['list' => [
                            'Aseguradoras y ARS, para la autorización y gestión de la cobertura de los servicios.',
                            'Laboratorios, centros de imágenes y centros de referencia, para la continuidad de su atención.',
                            'Autoridades sanitarias (MSP, SNS, SISALRIL) en cumplimiento de obligaciones legales y reportes oficiales.',
                            'Autoridades judiciales o administrativas competentes, cuando exista un requerimiento legal.',
                            'Proveedores tecnológicos que actúan como encargados del tratamiento, bajo contrato y obligación de confidencialidad y seguridad.',
                        ]],
                    ],
                ],
                [
                    'id' => 'transferencia', 'num' => '11', 'title' => 'Transferencia internacional de datos',
                    'blocks' => [
                        ['p' => 'Algunos de nuestros proveedores de servicios (alojamiento web, correo electrónico o servicios en la nube) pueden encontrarse fuera de la República Dominicana. En esos casos adoptamos las garantías razonables y exigimos a dichos proveedores niveles de confidencialidad y seguridad equivalentes a los previstos por la legislación dominicana.'],
                    ],
                ],
                [
                    'id' => 'seguridad', 'num' => '12', 'title' => 'Medidas de seguridad',
                    'blocks' => [
                        ['p' => 'Aplicamos medidas técnicas y organizativas razonables para proteger sus datos contra el acceso no autorizado, la pérdida, la alteración o la divulgación indebida, entre ellas:'],
                        ['list' => [
                            'Control de acceso por roles y credenciales personales.',
                            'Cifrado de las comunicaciones (HTTPS) y de las credenciales sensibles.',
                            'Separación de los sistemas internos del hospital respecto del sitio web público.',
                            'Registros de auditoría y trazabilidad de accesos.',
                            'Copias de respaldo y procedimientos de recuperación.',
                            'Capacitación del personal y deberes de confidencialidad.',
                        ]],
                        ['p' => 'Ningún sistema es completamente infalible. En caso de un incidente de seguridad que afecte sus datos, actuaremos conforme a la legislación aplicable.'],
                    ],
                ],
                [
                    'id' => 'derechos', 'num' => '13', 'title' => 'Sus derechos (ARCO y habeas data)',
                    'blocks' => [
                        ['p' => 'Como titular de los datos, usted puede ejercer los siguientes derechos:'],
                        ['deflist' => [
                            'Acceso' => 'Conocer qué datos suyos tratamos y con qué finalidad.',
                            'Rectificación' => 'Solicitar la corrección de datos inexactos o incompletos.',
                            'Cancelación o supresión' => 'Solicitar la eliminación de sus datos cuando proceda legalmente.',
                            'Oposición' => 'Oponerse al tratamiento de sus datos por motivos legítimos.',
                            'Revocación del consentimiento' => 'Retirar el consentimiento otorgado, sin efecto retroactivo.',
                        ]],
                        ['p' => 'Para ejercer estos derechos, escriba a ' . $contact['email'] . ' acreditando su identidad y describiendo su solicitud. Atenderemos su petición dentro de un plazo razonable conforme a la ley.'],
                        ['p' => 'Asimismo, le asiste la acción constitucional de habeas data ante los tribunales competentes (artículo 44 de la Constitución y Ley No. 172-13).'],
                    ],
                ],
                [
                    'id' => 'menores', 'num' => '14', 'title' => 'Datos de menores y personas bajo tutela',
                    'blocks' => [
                        ['p' => 'El tratamiento de datos de menores de edad o de personas bajo tutela se realiza a través de sus padres, tutores o representantes legales, quienes son responsables de aportar la información y de autorizar su tratamiento en el marco de la atención sanitaria.'],
                    ],
                ],
                [
                    'id' => 'portales', 'num' => '15', 'title' => 'Portal del Paciente y Portal Médico',
                    'blocks' => [
                        ['p' => 'El Hospital ofrece portales digitales que manejan información clínica y operan de forma aislada y reforzada respecto del sitio público:'],
                        ['sub' => 'Portal del Paciente'],
                        ['p' => 'El acceso se realiza mediante credenciales personales y, cuando aplica, códigos de un solo uso (OTP). El paciente es responsable de la confidencialidad de sus credenciales, de no compartirlas y de cerrar sesión al finalizar.'],
                        ['sub' => 'Portal Médico'],
                        ['p' => 'El acceso está reservado a profesionales autorizados, sujetos al deber de secreto profesional. El uso de la información clínica se limita a la relación asistencial y queda sujeto a registros de trazabilidad.'],
                    ],
                ],
                [
                    'id' => 'enlaces', 'num' => '16', 'title' => 'Enlaces y servicios de terceros',
                    'blocks' => [
                        ['p' => 'Este sitio puede contener enlaces a redes sociales, mapas u otros servicios de terceros que cuentan con sus propias políticas de privacidad. El Hospital no se responsabiliza por el tratamiento de datos que dichos terceros realicen.'],
                    ],
                ],
                [
                    'id' => 'cambios', 'num' => '17', 'title' => 'Cambios a esta Política',
                    'blocks' => [
                        ['p' => 'Podemos actualizar esta Política para reflejar cambios legales, técnicos u operativos. Publicaremos siempre la versión vigente con su fecha de actualización. Cuando los cambios sean sustanciales, los comunicaremos por medios razonables.'],
                    ],
                ],
                [
                    'id' => 'contacto', 'num' => '18', 'title' => 'Contacto y reclamaciones',
                    'blocks' => [
                        ['p' => 'Para ejercer sus derechos, realizar consultas o presentar reclamaciones sobre el tratamiento de sus datos, puede contactarnos:'],
                        ['deflist' => [
                            'Correo electrónico' => $contact['email'],
                            'Teléfono' => $contact['phone'],
                            'Dirección' => $contact['address'],
                        ]],
                        ['p' => 'Sin perjuicio de lo anterior, usted puede acudir a las autoridades competentes en materia de protección de datos y de salud.'],
                    ],
                ],
            ],
        ],
        'terminos-de-uso' => [
            'title' => 'Términos y condiciones de uso',
            'nav' => 'Términos',
            'active' => '',
            'type' => 'legal',
            'kicker' => 'Uso del sitio web',
            'summary' => 'Condiciones que regulan el acceso y la utilización del sitio web y los servicios digitales del Hospital General Las Colinas.',
            'image' => $assets['hero'],
            'updated' => '26 de junio de 2026',
            'version' => '1.0',
            'legal_intro' => 'Los presentes Términos y Condiciones de Uso regulan el acceso y la utilización del sitio web colinashospital.com y de los servicios digitales del Hospital General Las Colinas. Al acceder o utilizar este sitio, usted declara haber leído, comprendido y aceptado estos términos. Si no está de acuerdo con ellos, le solicitamos abstenerse de utilizar el sitio.',
            'articles' => [
                [
                    'id' => 'identificacion', 'num' => '1', 'title' => 'Identificación y aceptación',
                    'blocks' => [
                        ['p' => 'El titular de este sitio es el Hospital General Las Colinas:'],
                        ['deflist' => [
                            'Razón social' => 'HOSPITAL GENERAL LAS COLINAS, SAS',
                            'RNC' => '131293281',
                            'Domicilio' => $contact['address'],
                            'Teléfono' => $contact['phone'],
                            'Correo electrónico' => $contact['email'],
                        ]],
                        ['p' => 'El acceso y uso del sitio implica la aceptación plena de estos Términos y de la Política de Privacidad.'],
                    ],
                ],
                [
                    'id' => 'objeto', 'num' => '2', 'title' => 'Objeto y alcance del sitio',
                    'blocks' => [
                        ['p' => 'Este sitio tiene carácter informativo e institucional. Permite conocer los servicios del Hospital, consultar el directorio médico y las noticias, solicitar citas y acceder a los portales digitales del paciente y del médico.'],
                    ],
                ],
                [
                    'id' => 'aviso-medico', 'num' => '3', 'title' => 'Aviso médico importante',
                    'blocks' => [
                        ['note' => 'La información de este sitio tiene fines informativos y educativos, y NO sustituye la consulta, el diagnóstico ni el tratamiento de un profesional de la salud.'],
                        ['p' => 'El contenido del sitio no crea una relación médico-paciente. Usted no debe tomar decisiones sobre su salud basándose únicamente en la información publicada aquí; consulte siempre a un profesional.'],
                        ['note' => 'En caso de emergencia, llame al ' . $contact['phone'] . ' o acuda de inmediato al área de Emergencias 24/7.'],
                    ],
                ],
                [
                    'id' => 'citas', 'num' => '4', 'title' => 'Solicitudes de cita y formularios',
                    'blocks' => [
                        ['p' => 'El envío de un formulario de cita constituye una solicitud y no una confirmación. La cita queda sujeta a la validación de disponibilidad y a la confirmación por parte del Hospital. El usuario se compromete a aportar información veraz, exacta y actualizada.'],
                    ],
                ],
                [
                    'id' => 'portales-uso', 'num' => '5', 'title' => 'Uso de los portales (Paciente y Médico)',
                    'blocks' => [
                        ['p' => 'El acceso a los portales requiere credenciales personales e intransferibles. El usuario es responsable de mantener la confidencialidad de sus credenciales y de toda actividad realizada bajo su cuenta, y debe notificar de inmediato cualquier uso no autorizado.'],
                        ['p' => 'El Hospital podrá suspender o restringir el acceso por razones de seguridad o uso indebido. El Portal Médico es de uso exclusivo de profesionales autorizados.'],
                    ],
                ],
                [
                    'id' => 'conducta', 'num' => '6', 'title' => 'Uso permitido y conducta prohibida',
                    'blocks' => [
                        ['p' => 'Usted se compromete a usar el sitio de forma lícita y conforme a estos Términos. En particular, queda prohibido:'],
                        ['list' => [
                            'Acceder sin autorización a sistemas, cuentas o datos ajenos.',
                            'Intentar vulnerar, probar o eludir las medidas de seguridad del sitio o de los portales.',
                            'Introducir virus, código malicioso o cualquier elemento que dañe los sistemas.',
                            'Realizar extracción masiva de datos (scraping) o acciones que sobrecarguen el servicio.',
                            'Suplantar la identidad de terceros o falsear información.',
                            'Utilizar el sitio con fines ilícitos o contrarios a la buena fe.',
                        ]],
                        ['p' => 'Las conductas descritas pueden constituir infracciones sancionadas por la Ley No. 53-07 sobre Crímenes y Delitos de Alta Tecnología.'],
                    ],
                ],
                [
                    'id' => 'propiedad', 'num' => '7', 'title' => 'Propiedad intelectual',
                    'blocks' => [
                        ['p' => 'La marca, el logotipo y la identidad institucional del Hospital General Las Colinas, así como los textos, imágenes, diseño, código y demás contenidos del sitio, son propiedad del Hospital o de sus licenciantes y están protegidos por la legislación aplicable. Queda prohibida su reproducción, distribución o uso sin autorización previa y por escrito.'],
                    ],
                ],
                [
                    'id' => 'disponibilidad', 'num' => '8', 'title' => 'Disponibilidad del servicio',
                    'blocks' => [
                        ['p' => 'Procuramos mantener el sitio disponible de forma continua, pero no garantizamos su disponibilidad ininterrumpida. Pueden producirse interrupciones por mantenimiento, actualizaciones o causas técnicas. El Hospital no será responsable por los daños derivados de la indisponibilidad temporal del sitio.'],
                    ],
                ],
                [
                    'id' => 'enlaces-terceros', 'num' => '9', 'title' => 'Enlaces y contenidos de terceros',
                    'blocks' => [
                        ['p' => 'El sitio puede incluir enlaces a sitios y servicios de terceros que se encuentran fuera de nuestro control. El Hospital no asume responsabilidad por el contenido, las prácticas o las políticas de dichos terceros.'],
                    ],
                ],
                [
                    'id' => 'responsabilidad', 'num' => '10', 'title' => 'Limitación de responsabilidad',
                    'blocks' => [
                        ['p' => 'En la medida permitida por la ley, el Hospital no será responsable por daños o perjuicios derivados del uso o la imposibilidad de uso del sitio, ni por la exactitud de la información que el propio usuario introduzca. Lo anterior no limita las responsabilidades que la ley no permita excluir, en especial las relativas a la atención asistencial.'],
                    ],
                ],
                [
                    'id' => 'datos-personales', 'num' => '11', 'title' => 'Protección de datos personales',
                    'blocks' => [
                        ['p' => 'El tratamiento de los datos personales recabados a través del sitio se rige por nuestra Política de Privacidad y por la Ley No. 172-13. Le recomendamos revisar dicha Política para conocer sus derechos y la forma de ejercerlos.'],
                    ],
                ],
                [
                    'id' => 'comunicaciones', 'num' => '12', 'title' => 'Comunicaciones electrónicas',
                    'blocks' => [
                        ['p' => 'Al utilizar nuestros formularios y canales electrónicos, usted acepta comunicarse con el Hospital por medios electrónicos, los cuales tienen validez conforme a la Ley No. 126-02 sobre Comercio Electrónico, Documentos y Firmas Digitales.'],
                    ],
                ],
                [
                    'id' => 'veracidad', 'num' => '13', 'title' => 'Veracidad de la información',
                    'blocks' => [
                        ['p' => 'El usuario es el único responsable de la veracidad, exactitud y actualización de los datos que aporta a través del sitio. La información incorrecta puede afectar la gestión de su solicitud o de su atención.'],
                    ],
                ],
                [
                    'id' => 'modificaciones', 'num' => '14', 'title' => 'Modificaciones de los términos',
                    'blocks' => [
                        ['p' => 'El Hospital podrá modificar estos Términos en cualquier momento. Regirá la versión publicada en el sitio con su fecha de actualización. El uso continuado del sitio tras una modificación implica la aceptación de los nuevos términos.'],
                    ],
                ],
                [
                    'id' => 'ley-aplicable', 'num' => '15', 'title' => 'Legislación aplicable y jurisdicción',
                    'blocks' => [
                        ['p' => 'Estos Términos se rigen por las leyes de la República Dominicana. Para cualquier controversia, las partes se someten a los tribunales competentes de Santiago de los Caballeros, sin perjuicio de los fueros que la ley reconozca al consumidor o usuario.'],
                    ],
                ],
                [
                    'id' => 'contacto', 'num' => '16', 'title' => 'Contacto',
                    'blocks' => [
                        ['p' => 'Para consultas relacionadas con estos Términos puede comunicarse con nosotros:'],
                        ['deflist' => [
                            'Correo electrónico' => $contact['email'],
                            'Teléfono' => $contact['phone'],
                            'Dirección' => $contact['address'],
                        ]],
                    ],
                ],
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
