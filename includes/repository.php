<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Repositorio Digital — catálogo de protocolos médicos nacionales (MISPAS)
 * e internacionales. Mismo patrón que news.php: el módulo crea su esquema
 * y se siembra solo; el admin puede agregar documentos (enlace o PDF local).
 */

const REPO_MSP_BASE = 'https://repositorio.msp.gob.do/handle/123456789/';

function repo_ensure_schema(): bool
{
    $pdo = db();
    if (!$pdo) {
        return false;
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS repo_documents (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(220) NOT NULL UNIQUE,
            title VARCHAR(280) NOT NULL,
            summary VARCHAR(500) NULL,
            category VARCHAR(80) NOT NULL DEFAULT 'Medicina interna',
            scope ENUM('nacional','internacional') NOT NULL DEFAULT 'nacional',
            org VARCHAR(140) NOT NULL DEFAULT 'MISPAS',
            doc_type VARCHAR(40) NOT NULL DEFAULT 'Protocolo',
            year SMALLINT UNSIGNED NULL,
            language VARCHAR(10) NOT NULL DEFAULT 'es',
            external_url VARCHAR(500) NULL,
            file_path VARCHAR(255) NULL,
            tags VARCHAR(400) NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'published',
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX repo_status_idx (status),
            INDEX repo_scope_idx (scope),
            INDEX repo_category_idx (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        repo_seed_if_empty();
        return true;
    } catch (Throwable) {
        return false;
    }
}

function repo_categories(): array
{
    return [
        'Emergencias y trauma' => 'siren',
        'Materno-neonatal' => 'baby',
        'Pediatría' => 'blocks',
        'Medicina interna' => 'stethoscope',
        'Cardiología y reanimación' => 'heart-pulse',
        'Endocrinología y nutrición' => 'apple',
        'Infectología y epidemiología' => 'microscope',
        'Neumología' => 'wind',
        'Hematología y oncología' => 'test-tube',
        'Cirugía y anestesia' => 'scissors',
        'Nefrología y trasplante' => 'droplets',
        'Salud mental y neurodesarrollo' => 'brain',
        'Enfermería' => 'cross',
        'Farmacia y medicamentos' => 'pill',
    ];
}

function repo_category_icon(string $category): string
{
    return repo_categories()[$category] ?? 'file-text';
}

function repo_doc_types(): array
{
    return ['Protocolo', 'Guía clínica', 'Manual', 'Directriz', 'Norma'];
}

function repo_languages(): array
{
    return ['es' => 'Español', 'en' => 'Inglés', 'es-en' => 'Español / Inglés'];
}

/**
 * Catálogo semilla. URLs verificadas contra el repositorio institucional del
 * Ministerio de Salud Pública de RD (DSpace, handles estables) y las páginas
 * oficiales de guías de cada organización internacional.
 */
function repo_seed_data(): array
{
    $msp = static fn(int $handle): string => REPO_MSP_BASE . $handle;

    // [title, summary, category, scope, org, type, year, lang, url, tags, featured]
    $rows = [
        // ——— Emergencias y trauma (MISPAS) ———
        ['Protocolo de atención para el manejo del paciente politraumatizado en emergencia',
            'Evaluación primaria y secundaria, priorización ABCDE y criterios de traslado del paciente con múltiples traumas.',
            'Emergencias y trauma', 'nacional', 'MISPAS', 'Protocolo', 2017, 'es', $msp(946),
            'trauma, politrauma, emergencia, ABCDE, triage', 1],
        ['Protocolo de atención en pacientes con trauma craneoencefálico en emergencia',
            'Clasificación por escala de Glasgow, indicaciones de imagen y manejo inicial del TCE en sala de urgencias.',
            'Emergencias y trauma', 'nacional', 'MISPAS', 'Protocolo', 2017, 'es', $msp(944),
            'TCE, trauma craneal, Glasgow, neurocirugía', 0],
        ['Protocolo de atención en pacientes con trauma torácico en emergencia',
            'Diagnóstico y manejo de neumotórax, hemotórax y lesiones de pared torácica en el servicio de emergencia.',
            'Emergencias y trauma', 'nacional', 'MISPAS', 'Protocolo', 2017, 'es', $msp(973),
            'trauma torácico, neumotórax, hemotórax', 0],
        ['Protocolo de atención para pacientes adultos con dolor torácico en emergencia',
            'Estratificación del dolor torácico agudo: síndrome coronario, disección aórtica y otras causas que amenazan la vida.',
            'Cardiología y reanimación', 'nacional', 'MISPAS', 'Protocolo', 2017, 'es', $msp(947),
            'dolor torácico, infarto, síndrome coronario agudo, ECG', 1],
        ['Protocolo de atención para pacientes con paro cardíaco en sala de emergencia',
            'Algoritmos de reanimación cardiopulmonar y cuidados posparo para el equipo de emergencia.',
            'Cardiología y reanimación', 'nacional', 'MISPAS', 'Protocolo', 2017, 'es', $msp(952),
            'paro cardíaco, RCP, reanimación, desfibrilación', 0],
        ['Protocolo de atención para el manejo de abdomen agudo en emergencia',
            'Abordaje sistemático del dolor abdominal agudo: diagnóstico diferencial y criterios quirúrgicos.',
            'Emergencias y trauma', 'nacional', 'MISPAS', 'Protocolo', 2017, 'es', $msp(945),
            'abdomen agudo, dolor abdominal, apendicitis', 0],
        ['Protocolo de atención para traumas de cuello por heridas penetrantes',
            'Manejo por zonas anatómicas del cuello, indicaciones de exploración quirúrgica y control de vía aérea.',
            'Emergencias y trauma', 'nacional', 'MISPAS', 'Protocolo', 2017, 'es', $msp(940),
            'trauma de cuello, herida penetrante, vía aérea', 0],
        ['Instrucciones en atención extrahospitalaria. Tomo I: soporte vital básico (SVB)',
            'Cadena de supervivencia, RCP básica y manejo inicial extrahospitalario para primeros respondientes.',
            'Cardiología y reanimación', 'nacional', 'MISPAS', 'Manual', 2017, 'es', $msp(138),
            'soporte vital básico, SVB, RCP, prehospitalario', 0],
        ['Instrucciones en atención extrahospitalaria. Tomo II: soporte vital avanzado (SVA)',
            'Manejo avanzado de vía aérea, fármacos y algoritmos de soporte vital avanzado en el entorno prehospitalario.',
            'Cardiología y reanimación', 'nacional', 'MISPAS', 'Manual', 2017, 'es', $msp(125),
            'soporte vital avanzado, SVA, prehospitalario', 0],

        // ——— Materno-neonatal (MISPAS) ———
        ['Protocolo de atención en embarazo de bajo riesgo (actualizado octubre 2020)',
            'Controles prenatales, exámenes por trimestre y criterios de referencia durante el embarazo de bajo riesgo.',
            'Materno-neonatal', 'nacional', 'MISPAS', 'Protocolo', 2020, 'es', $msp(2198),
            'embarazo, control prenatal, obstetricia', 1],
        ['Protocolo de atención al puerperio de bajo riesgo (actualizado octubre 2020)',
            'Vigilancia del posparto inmediato y tardío, signos de alarma y consejería en el puerperio normal.',
            'Materno-neonatal', 'nacional', 'MISPAS', 'Protocolo', 2020, 'es', $msp(2196),
            'puerperio, posparto, lactancia', 0],
        ['Protocolo de atención preconcepcional (actualizado octubre 2020)',
            'Evaluación de riesgo reproductivo, suplementación y preparación de la mujer antes del embarazo.',
            'Materno-neonatal', 'nacional', 'MISPAS', 'Protocolo', 2020, 'es', $msp(2194),
            'preconcepcional, ácido fólico, riesgo reproductivo', 0],
        ['Protocolo para la prevención, diagnóstico y tratamiento de la sepsis materna (actualizado octubre 2020)',
            'Detección temprana y manejo escalonado de la infección y la sepsis durante el embarazo, parto y puerperio.',
            'Materno-neonatal', 'nacional', 'MISPAS', 'Protocolo', 2020, 'es', $msp(2195),
            'sepsis materna, infección puerperal, mortalidad materna', 0],
        ['Protocolo de actuación para reducción de cesáreas innecesarias',
            'Criterios clínicos y estrategias institucionales para disminuir cesáreas sin indicación médica.',
            'Materno-neonatal', 'nacional', 'MISPAS', 'Protocolo', 2020, 'es', $msp(2010),
            'cesárea, parto vaginal, obstetricia', 0],
        ['Protocolo de evaluación y atención inmediata del recién nacido (actualizado octubre 2020)',
            'Pasos de la atención inmediata: secado, valoración de Apgar, apego precoz y tamizajes del recién nacido sano.',
            'Materno-neonatal', 'nacional', 'MISPAS', 'Protocolo', 2020, 'es', $msp(2197),
            'recién nacido, Apgar, atención inmediata, neonato', 0],
        ['Protocolo de atención del recién nacido prematuro (actualizado octubre 2020)',
            'Estabilización, termorregulación, soporte respiratorio y nutrición del neonato pretérmino.',
            'Materno-neonatal', 'nacional', 'MISPAS', 'Protocolo', 2020, 'es', $msp(2199),
            'prematuro, pretérmino, neonatología', 1],
        ['Protocolo de atención para reanimación neonatal',
            'Algoritmo de reanimación del recién nacido en sala de parto: ventilación, masaje y medicación.',
            'Materno-neonatal', 'nacional', 'MISPAS', 'Protocolo', 2019, 'es', $msp(1519),
            'reanimación neonatal, asfixia, sala de parto', 0],
        ['Protocolo de atención del síndrome de dificultad respiratoria (SDR)',
            'Diagnóstico y manejo del SDR neonatal: surfactante, CPAP y ventilación mecánica.',
            'Materno-neonatal', 'nacional', 'MISPAS', 'Protocolo', 2020, 'es', $msp(2007),
            'SDR, dificultad respiratoria, surfactante, neonato', 0],
        ['Protocolo de atención al recién nacido con asfixia perinatal y encefalopatía hipóxico-isquémica',
            'Identificación de la asfixia perinatal y criterios de hipotermia terapéutica en encefalopatía.',
            'Materno-neonatal', 'nacional', 'MISPAS', 'Protocolo', 2018, 'es', $msp(893),
            'asfixia perinatal, encefalopatía, hipotermia', 0],
        ['Protocolo de atención para el manejo de la enterocolitis necrotizante en neonatos',
            'Estadios de Bell, manejo médico y criterios quirúrgicos de la enterocolitis necrotizante.',
            'Materno-neonatal', 'nacional', 'MISPAS', 'Protocolo', 2018, 'es', $msp(901),
            'enterocolitis, NEC, neonato, prematuro', 0],
        ['Protocolo de estabilización y traslado neonatal',
            'Preparación, comunicación y cuidados durante el transporte del recién nacido crítico entre centros.',
            'Materno-neonatal', 'nacional', 'MISPAS', 'Protocolo', 2020, 'es', $msp(2039),
            'traslado neonatal, transporte, referencia', 0],
        ['Protocolo de atención para el manejo integral del embarazo, el parto y el puerperio en adolescentes menores de 15 años',
            'Atención diferenciada y multidisciplinaria de la adolescente embarazada menor de 15 años.',
            'Materno-neonatal', 'nacional', 'MISPAS', 'Protocolo', 2017, 'es', $msp(964),
            'embarazo adolescente, parto, puerperio', 0],
        ['Protocolo de anticoncepción',
            'Métodos anticonceptivos disponibles, criterios de elegibilidad y consejería en planificación familiar.',
            'Materno-neonatal', 'nacional', 'MISPAS', 'Protocolo', 2019, 'es', $msp(1516),
            'anticoncepción, planificación familiar', 0],
        ['Protocolos de atención para obstetricia y ginecología: Volumen I',
            'Compendio oficial de protocolos obstétricos y ginecológicos para los servicios de salud del país.',
            'Materno-neonatal', 'nacional', 'MISPAS', 'Protocolo', 2016, 'es', $msp(90),
            'obstetricia, ginecología, compendio', 0],
        ['Protocolos de atención de 2do y 3er nivel: gineco-obstetricia',
            'Protocolos de atención gineco-obstétrica para hospitales de segundo y tercer nivel.',
            'Materno-neonatal', 'nacional', 'MISPAS', 'Protocolo', 2016, 'es', $msp(204),
            'gineco-obstetricia, segundo nivel, tercer nivel', 0],

        // ——— Pediatría (MISPAS) ———
        ['Protocolo de atención de neumonía en niños y adolescentes',
            'Diagnóstico, clasificación de gravedad y tratamiento antibiótico de la neumonía pediátrica.',
            'Pediatría', 'nacional', 'MISPAS', 'Protocolo', 2025, 'es', $msp(2368),
            'neumonía, pediatría, antibióticos, infección respiratoria', 1],
        ['Protocolo clínico de diagnóstico y tratamiento de la diabetes mellitus tipo 1 en niños, niñas y adolescentes',
            'Insulinización, educación diabetológica y seguimiento del paciente pediátrico con diabetes tipo 1.',
            'Pediatría', 'nacional', 'MISPAS', 'Protocolo', 2021, 'es', $msp(2269),
            'diabetes tipo 1, insulina, pediatría', 0],
        ['Protocolo de manejo de la otitis media aguda',
            'Criterios diagnósticos, observación versus antibioticoterapia y prevención de la otitis media aguda.',
            'Pediatría', 'nacional', 'MISPAS', 'Protocolo', 2024, 'es', $msp(2347),
            'otitis, infección de oído, pediatría', 0],
        ['Protocolo de evaluación, detección y atención temprana de las alteraciones en el crecimiento y desarrollo en niños y niñas de 0 a 5 años',
            'Tamizaje del desarrollo infantil, señales de alerta y rutas de referencia en la primera infancia.',
            'Pediatría', 'nacional', 'MISPAS', 'Protocolo', 2023, 'es', $msp(2326),
            'crecimiento, desarrollo infantil, primera infancia', 0],
        ['Protocolo de atención para el diagnóstico y manejo del hipotiroidismo congénito (actualizado julio 2020)',
            'Tamizaje neonatal, confirmación diagnóstica y tratamiento sustitutivo del hipotiroidismo congénito.',
            'Pediatría', 'nacional', 'MISPAS', 'Protocolo', 2020, 'es', $msp(2130),
            'hipotiroidismo congénito, tamizaje neonatal, tiroides', 0],
        ['Protocolo de manejo clínico de la pubertad precoz',
            'Evaluación hormonal y por imágenes, criterios de tratamiento y seguimiento de la pubertad precoz.',
            'Pediatría', 'nacional', 'MISPAS', 'Protocolo', 2021, 'es', $msp(2264),
            'pubertad precoz, endocrinología pediátrica', 0],
        ['Protocolo de atención para el manejo de niños/as con síndrome congénito asociado a virus Zika',
            'Seguimiento multidisciplinario del niño con microcefalia y otras manifestaciones congénitas por Zika.',
            'Pediatría', 'nacional', 'MISPAS', 'Protocolo', 2018, 'es', $msp(902),
            'zika, síndrome congénito, microcefalia', 0],
        ['Protocolo de atención de casos de violencia sexual en niños, niñas y adolescentes',
            'Atención integral, profilaxis y ruta de protección ante la violencia sexual en población pediátrica.',
            'Pediatría', 'nacional', 'MISPAS', 'Protocolo', 2017, 'es', $msp(957),
            'violencia sexual, protección infantil, profilaxis', 0],
        ['Protocolos de atención para pediatría: Volumen I',
            'Compendio oficial de protocolos pediátricos del Ministerio de Salud Pública.',
            'Pediatría', 'nacional', 'MISPAS', 'Protocolo', 2016, 'es', $msp(177),
            'pediatría, compendio, protocolos', 0],

        // ——— Medicina interna (MISPAS) ———
        ['Protocolos de atención para medicina interna: Volumen I',
            'Compendio oficial de protocolos de medicina interna para los servicios de salud dominicanos.',
            'Medicina interna', 'nacional', 'MISPAS', 'Protocolo', 2016, 'es', $msp(178),
            'medicina interna, compendio, protocolos', 1],
        ['Protocolos de atención para medicina interna: Volumen II',
            'Segundo volumen del compendio oficial de protocolos de medicina interna.',
            'Medicina interna', 'nacional', 'MISPAS', 'Protocolo', 2016, 'es', $msp(176),
            'medicina interna, compendio, protocolos', 0],
        ['Protocolo de atención para el manejo de hipertensión arterial del adulto en condiciones de no emergencia',
            'Diagnóstico, metas terapéuticas y escalamiento farmacológico de la hipertensión arterial en el adulto.',
            'Cardiología y reanimación', 'nacional', 'MISPAS', 'Protocolo', 2019, 'es', $msp(1525),
            'hipertensión, presión arterial, antihipertensivos', 1],
        ['Protocolo de atención para el manejo de la cetoacidosis diabética en adultos',
            'Reposición de fluidos, insulinoterapia y corrección electrolítica en la cetoacidosis diabética.',
            'Endocrinología y nutrición', 'nacional', 'MISPAS', 'Protocolo', 2018, 'es', $msp(889),
            'cetoacidosis, diabetes, insulina, emergencia', 0],
        ['Protocolo de manejo del síndrome hiperglucémico hiperosmolar no cetósico',
            'Diagnóstico y manejo del estado hiperosmolar: hidratación, insulina y vigilancia neurológica.',
            'Endocrinología y nutrición', 'nacional', 'MISPAS', 'Protocolo', 2018, 'es', $msp(892),
            'hiperglucemia, hiperosmolar, diabetes', 0],
        ['Protocolo de manejo del síndrome metabólico en la población adulta en atención primaria',
            'Criterios diagnósticos e intervenciones sobre estilo de vida y farmacológicas en el síndrome metabólico.',
            'Endocrinología y nutrición', 'nacional', 'MISPAS', 'Protocolo', 2021, 'es', $msp(2267),
            'síndrome metabólico, obesidad, dislipidemia', 0],
        ['Protocolo de manejo nutricional e integral del sobrepeso y la obesidad en el adulto',
            'Evaluación nutricional, plan de alimentación y seguimiento del adulto con sobrepeso u obesidad.',
            'Endocrinología y nutrición', 'nacional', 'MISPAS', 'Protocolo', 2021, 'es', $msp(2268),
            'obesidad, sobrepeso, nutrición', 0],
        ['Protocolo de manejo de colitis ulcerativa',
            'Inducción y mantenimiento de la remisión en la colitis ulcerativa, y criterios de referencia.',
            'Medicina interna', 'nacional', 'MISPAS', 'Protocolo', 2020, 'es', $msp(2037),
            'colitis ulcerativa, enfermedad inflamatoria intestinal', 0],
        ['Protocolo de manejo de la enfermedad de Crohn en el paciente ambulatorio',
            'Tratamiento escalonado y seguimiento ambulatorio del paciente con enfermedad de Crohn.',
            'Medicina interna', 'nacional', 'MISPAS', 'Protocolo', 2020, 'es', $msp(2036),
            'crohn, enfermedad inflamatoria intestinal', 0],
        ['Protocolo de atención: síndrome de Guillain-Barré',
            'Diagnóstico clínico, inmunoterapia y soporte ventilatorio del síndrome de Guillain-Barré.',
            'Medicina interna', 'nacional', 'MISPAS', 'Protocolo', 2016, 'es', $msp(44),
            'guillain-barré, polineuropatía, neurología', 0],

        // ——— Infectología y epidemiología (MISPAS) ———
        ['Protocolo de atención para el manejo del dengue (actualización)',
            'Clasificación OMS, signos de alarma y manejo por grupos del dengue. Referencia nacional vigente.',
            'Infectología y epidemiología', 'nacional', 'MISPAS', 'Protocolo', 2023, 'es', $msp(2316),
            'dengue, arbovirosis, signos de alarma, fiebre', 1],
        ['Protocolo de atención para el diagnóstico y tratamiento de leptospirosis',
            'Sospecha clínica, confirmación de laboratorio y antibioticoterapia de la leptospirosis.',
            'Infectología y epidemiología', 'nacional', 'MISPAS', 'Protocolo', 2017, 'es', $msp(953),
            'leptospirosis, zoonosis, antibióticos', 0],
        ['Protocolos nacionales de atención clínica y esquemas terapéuticos del programa nacional de atención integral de VIH/SIDA',
            'Esquemas antirretrovirales, seguimiento inmunovirológico y manejo de coinfecciones del programa nacional de VIH.',
            'Infectología y epidemiología', 'nacional', 'MISPAS', 'Protocolo', 2016, 'es', $msp(1366),
            'VIH, SIDA, antirretrovirales, TAR', 0],
        ['Protocolo de vigilancia y control de cólera',
            'Definiciones de caso, manejo de la deshidratación y medidas de control epidemiológico del cólera.',
            'Infectología y epidemiología', 'nacional', 'MISPAS', 'Protocolo', 2011, 'es', $msp(1293),
            'cólera, vigilancia epidemiológica, deshidratación', 0],
        ['Protocolo de vigilancia de difteria',
            'Detección, notificación y respuesta ante casos sospechosos de difteria.',
            'Infectología y epidemiología', 'nacional', 'MISPAS', 'Protocolo', 2018, 'es', $msp(203),
            'difteria, vigilancia, inmunoprevenibles', 0],
        ['Protocolo de vigilancia de tosferina',
            'Definiciones de caso, toma de muestras y acciones de control ante la tosferina.',
            'Infectología y epidemiología', 'nacional', 'MISPAS', 'Protocolo', 2019, 'es', $msp(1401),
            'tosferina, pertussis, vigilancia', 0],
        ['Protocolo de vigilancia de infección respiratoria aguda (ETI, IRAG y evento respiratorio inusitado)',
            'Vigilancia centinela de la enfermedad tipo influenza e infección respiratoria aguda grave.',
            'Infectología y epidemiología', 'nacional', 'MISPAS', 'Protocolo', 2020, 'es', $msp(1671),
            'IRAG, ETI, influenza, vigilancia respiratoria', 0],

        // ——— Neumología (MISPAS) ———
        ['Protocolo de diagnóstico y tratamiento de las enfermedades pulmonares intersticiales (EPI) y fibrosis pulmonar progresiva (FPP)',
            'Abordaje diagnóstico multidisciplinario y terapia antifibrótica de las enfermedades intersticiales.',
            'Neumología', 'nacional', 'MISPAS', 'Protocolo', 2025, 'es', $msp(2370),
            'fibrosis pulmonar, intersticial, EPI', 0],
        ['Protocolo de diagnóstico y tratamiento de fibrosis quística',
            'Tamizaje, confirmación diagnóstica y manejo multidisciplinario de la fibrosis quística.',
            'Neumología', 'nacional', 'MISPAS', 'Protocolo', 2025, 'es', $msp(2392),
            'fibrosis quística, tamizaje, neumología', 0],

        // ——— Hematología y oncología (MISPAS) ———
        ['Protocolo de diagnóstico y manejo de la anemia falciforme en pacientes adultos',
            'Manejo de crisis vasooclusivas, hidroxiurea y prevención de complicaciones en el adulto con drepanocitosis.',
            'Hematología y oncología', 'nacional', 'MISPAS', 'Protocolo', 2023, 'es', $msp(2290),
            'anemia falciforme, drepanocitosis, crisis vasooclusiva', 0],
        ['Protocolo de diagnóstico y manejo de la anemia falciforme en paciente pediátrico',
            'Profilaxis con penicilina, vacunación y manejo de eventos agudos en el niño con anemia falciforme.',
            'Hematología y oncología', 'nacional', 'MISPAS', 'Protocolo', 2020, 'es', $msp(2035),
            'anemia falciforme, pediatría, drepanocitosis', 0],
        ['Protocolo de atención para el diagnóstico y manejo de las talasemias en pediatría',
            'Diagnóstico diferencial de las talasemias, soporte transfusional y quelación de hierro.',
            'Hematología y oncología', 'nacional', 'MISPAS', 'Protocolo', 2020, 'es', $msp(2041),
            'talasemia, transfusión, hierro', 0],
        ['Protocolo de diagnóstico y tratamiento de hemofilia y otros trastornos congénitos de coagulación',
            'Reposición de factores, manejo de sangrados y profilaxis en hemofilia y coagulopatías congénitas.',
            'Hematología y oncología', 'nacional', 'MISPAS', 'Protocolo', 2023, 'es', $msp(2320),
            'hemofilia, coagulación, factor VIII', 0],
        ['Protocolo de manejo de linfoma Hodgkin en adultos',
            'Estadificación, esquemas de quimioterapia y seguimiento del linfoma de Hodgkin en el adulto.',
            'Hematología y oncología', 'nacional', 'MISPAS', 'Protocolo', 2024, 'es', $msp(2358),
            'linfoma hodgkin, quimioterapia, oncología', 0],
        ['Protocolo de manejo de linfomas no Hodgkin de estirpe B en adultos',
            'Clasificación, inmunoquimioterapia y evaluación de respuesta de los linfomas no Hodgkin B.',
            'Hematología y oncología', 'nacional', 'MISPAS', 'Protocolo', 2023, 'es', $msp(2295),
            'linfoma no hodgkin, rituximab, oncología', 0],
        ['Protocolo de manejo y tratamiento de leucemia mieloide crónica (LMC)',
            'Diagnóstico molecular, inhibidores de tirosina quinasa y monitoreo de respuesta en la LMC.',
            'Hematología y oncología', 'nacional', 'MISPAS', 'Protocolo', 2023, 'es', $msp(2327),
            'leucemia mieloide crónica, imatinib, BCR-ABL', 0],
        ['Protocolo de manejo de anemia aplásica (AA)',
            'Criterios de gravedad, inmunosupresión y trasplante en la anemia aplásica.',
            'Hematología y oncología', 'nacional', 'MISPAS', 'Protocolo', 2023, 'es', $msp(2293),
            'anemia aplásica, médula ósea', 0],
        ['Procedimiento para trasplante alogénico y autólogo de células progenitoras hematopoyéticas en el adulto',
            'Selección de candidatos, acondicionamiento y cuidados del trasplante de progenitores hematopoyéticos.',
            'Hematología y oncología', 'nacional', 'MISPAS', 'Protocolo', 2025, 'es', $msp(2377),
            'trasplante de médula, células hematopoyéticas', 0],

        // ——— Cirugía y anestesia (MISPAS) ———
        ['Protocolo de atención para abdomen agudo quirúrgico',
            'Indicaciones quirúrgicas, preparación preoperatoria y vías de abordaje del abdomen agudo.',
            'Cirugía y anestesia', 'nacional', 'MISPAS', 'Protocolo', 2017, 'es', $msp(938),
            'abdomen agudo, cirugía general, laparotomía', 0],
        ['Protocolo de atención para el manejo de vía aérea difícil',
            'Predicción, algoritmos y dispositivos para el manejo de la vía aérea difícil en anestesia.',
            'Cirugía y anestesia', 'nacional', 'MISPAS', 'Protocolo', 2017, 'es', $msp(932),
            'vía aérea difícil, intubación, anestesia', 0],
        ['Protocolo de atención para el manejo anestésico del paciente con trauma',
            'Inducción de secuencia rápida, manejo hemodinámico y consideraciones anestésicas en trauma.',
            'Cirugía y anestesia', 'nacional', 'MISPAS', 'Protocolo', 2017, 'es', $msp(929),
            'anestesia, trauma, secuencia rápida', 0],
        ['Protocolo de atención para anestesia general',
            'Valoración preanestésica, monitorización estándar y conducción de la anestesia general.',
            'Cirugía y anestesia', 'nacional', 'MISPAS', 'Protocolo', 2017, 'es', $msp(928),
            'anestesia general, preanestesia, monitorización', 0],
        ['Protocolo de atención para el manejo de las fracturas de cadera en el adulto mayor',
            'Manejo perioperatorio, momento quirúrgico óptimo y prevención de complicaciones en fractura de cadera.',
            'Cirugía y anestesia', 'nacional', 'MISPAS', 'Protocolo', 2017, 'es', $msp(936),
            'fractura de cadera, ortopedia, adulto mayor', 0],

        // ——— Nefrología y trasplante (MISPAS) ———
        ['Protocolo de estudio y seguimiento del donante vivo renal',
            'Evaluación médica, quirúrgica y psicosocial del donante renal vivo, y su seguimiento posterior.',
            'Nefrología y trasplante', 'nacional', 'MISPAS', 'Protocolo', 2023, 'es', $msp(2292),
            'trasplante renal, donante vivo, nefrología', 0],
        ['Protocolo de manejo nutricional del paciente con insuficiencia renal crónica',
            'Requerimientos proteicos y electrolíticos por estadio de la enfermedad renal crónica.',
            'Nefrología y trasplante', 'nacional', 'MISPAS', 'Protocolo', 2019, 'es', $msp(1520),
            'insuficiencia renal, nutrición renal, ERC', 0],

        // ——— Salud mental y neurodesarrollo (MISPAS) ———
        ['Protocolo de atención para niños, niñas y adolescentes con trastorno del espectro autista (actualización)',
            'Detección temprana, diagnóstico e intervenciones basadas en evidencia para el TEA.',
            'Salud mental y neurodesarrollo', 'nacional', 'MISPAS', 'Protocolo', 2025, 'es', $msp(2369),
            'autismo, TEA, neurodesarrollo', 1],
        ['Protocolo de atención a niñas, niños y adolescentes con trastorno por déficit de atención e hiperactividad',
            'Criterios diagnósticos, manejo conductual y farmacológico del TDAH en población pediátrica.',
            'Salud mental y neurodesarrollo', 'nacional', 'MISPAS', 'Protocolo', 2018, 'es', $msp(919),
            'TDAH, déficit de atención, hiperactividad', 0],
        ['Protocolo de atención a niños, niñas y adolescentes con trastorno de depresión',
            'Tamizaje, psicoterapia y criterios de referencia de la depresión en niños y adolescentes.',
            'Salud mental y neurodesarrollo', 'nacional', 'MISPAS', 'Protocolo', 2018, 'es', $msp(920),
            'depresión, salud mental, adolescentes', 0],

        // ——— Enfermería (MISPAS) ———
        ['Protocolos de atención para enfermería: Volumen I',
            'Compendio oficial de protocolos del cuidado de enfermería en los servicios de salud.',
            'Enfermería', 'nacional', 'MISPAS', 'Protocolo', 2016, 'es', $msp(30),
            'enfermería, cuidados, compendio', 0],
        ['Protocolo de enfermería para la prevención de neumonía asociada a la ventilación mecánica en el paciente adulto',
            'Paquete de medidas de enfermería para prevenir la neumonía asociada al ventilador en UCI.',
            'Enfermería', 'nacional', 'MISPAS', 'Protocolo', 2021, 'es', $msp(2265),
            'neumonía, ventilación mecánica, UCI, enfermería', 0],
        ['Procedimientos de cuidados de enfermería en la canalización umbilical',
            'Técnica aséptica y cuidados de enfermería en la canalización de vasos umbilicales del neonato.',
            'Enfermería', 'nacional', 'MISPAS', 'Norma', 2024, 'es', $msp(2345),
            'canalización umbilical, neonato, enfermería', 0],
        ['Manual de estándares de calidad y humanización de la atención materna y neonatal',
            'Estándares de calidad y humanización para los servicios materno-neonatales del país.',
            'Enfermería', 'nacional', 'MISPAS', 'Manual', 2019, 'es', $msp(1600),
            'calidad, humanización, materno-neonatal', 0],

        // ——— Farmacia y medicamentos (MISPAS) ———
        ['Cuadro básico de medicamentos esenciales de la República Dominicana 2024',
            'Listado oficial vigente de medicamentos esenciales del sistema de salud dominicano.',
            'Farmacia y medicamentos', 'nacional', 'MISPAS', 'Norma', 2024, 'es', $msp(2335),
            'medicamentos esenciales, cuadro básico, farmacia', 1],
        ['Protocolos de atención de salud pública: Volumen I',
            'Primer compendio nacional de protocolos de atención del Ministerio de Salud Pública.',
            'Medicina interna', 'nacional', 'MISPAS', 'Protocolo', 2016, 'es', $msp(124),
            'salud pública, compendio, protocolos', 0],

        // ——— Internacionales ———
        ['GOLD Report: estrategia global para el diagnóstico, manejo y prevención de la EPOC',
            'Informe anual de referencia mundial para el diagnóstico, tratamiento y prevención de la EPOC.',
            'Neumología', 'internacional', 'GOLD', 'Guía clínica', 2026, 'en', 'https://goldcopd.org/gold-reports/',
            'EPOC, COPD, broncodilatadores, espirometría', 1],
        ['GINA: estrategia global para el manejo y la prevención del asma',
            'Guía internacional anual para el diagnóstico, control y tratamiento escalonado del asma.',
            'Neumología', 'internacional', 'GINA', 'Guía clínica', 2025, 'en', 'https://ginasthma.org/reports/',
            'asma, GINA, inhaladores, control', 1],
        ['Guías de práctica clínica de la Sociedad Europea de Cardiología (ESC)',
            'Colección oficial de guías ESC: síndromes coronarios, insuficiencia cardíaca, arritmias, valvulopatías y más.',
            'Cardiología y reanimación', 'internacional', 'ESC', 'Guía clínica', 2025, 'en', 'https://www.escardio.org/Guidelines',
            'cardiología, ESC, insuficiencia cardíaca, arritmias', 1],
        ['AHA Guidelines for CPR and Emergency Cardiovascular Care',
            'Guías de la American Heart Association para reanimación cardiopulmonar y atención cardiovascular de emergencia.',
            'Cardiología y reanimación', 'internacional', 'AHA', 'Guía clínica', 2025, 'en', 'https://cpr.heart.org/en/resuscitation-science/cpr-and-ecc-guidelines',
            'RCP, CPR, reanimación, AHA, ACLS, BLS', 1],
        ['Surviving Sepsis Campaign: guías internacionales para el manejo de sepsis y shock séptico',
            'Recomendaciones internacionales para la identificación, reanimación y tratamiento de la sepsis.',
            'Emergencias y trauma', 'internacional', 'SCCM / ESICM', 'Guía clínica', 2021, 'en', 'https://www.sccm.org/clinical-resources/guidelines',
            'sepsis, shock séptico, lactato, antibióticos', 1],
        ['ADA Standards of Care in Diabetes',
            'Estándares anuales de la American Diabetes Association para el diagnóstico y tratamiento de la diabetes.',
            'Endocrinología y nutrición', 'internacional', 'ADA', 'Guía clínica', 2026, 'en', 'https://professional.diabetes.org/standards-of-care',
            'diabetes, ADA, HbA1c, insulina', 1],
        ['KDIGO Clinical Practice Guidelines (enfermedad renal)',
            'Guías internacionales KDIGO: enfermedad renal crónica, lesión renal aguda, glomerulopatías y trasplante.',
            'Nefrología y trasplante', 'internacional', 'KDIGO', 'Guía clínica', 2024, 'en', 'https://kdigo.org/guidelines/',
            'KDIGO, enfermedad renal, nefrología, diálisis', 0],
        ['CDC Sexually Transmitted Infections Treatment Guidelines',
            'Guías de tratamiento de infecciones de transmisión sexual de los CDC de Estados Unidos.',
            'Infectología y epidemiología', 'internacional', 'CDC', 'Guía clínica', 2021, 'en', 'https://www.cdc.gov/std/treatment-guidelines/default.htm',
            'ITS, ETS, sífilis, gonorrea, CDC', 0],
        ['ATLS: Advanced Trauma Life Support (American College of Surgeons)',
            'Programa internacional de referencia para la atención inicial del paciente traumatizado.',
            'Emergencias y trauma', 'internacional', 'ACS', 'Manual', 2018, 'en', 'https://www.facs.org/quality-programs/trauma/education/advanced-trauma-life-support/',
            'ATLS, trauma, soporte vital, cirugía', 0],
        ['Directrices y guías de la Organización Mundial de la Salud',
            'Repositorio oficial de directrices OMS aprobadas por su comité de revisión, en todas las áreas clínicas.',
            'Medicina interna', 'internacional', 'OMS', 'Directriz', 2026, 'es-en', 'https://www.who.int/publications/who-guidelines',
            'OMS, WHO, directrices, salud global', 1],
        ['OPS: dengue — recursos técnicos y guías para las Américas',
            'Página técnica de la OPS con las guías regionales de diagnóstico y manejo clínico del dengue.',
            'Infectología y epidemiología', 'internacional', 'OPS', 'Directriz', 2024, 'es', 'https://www.paho.org/es/temas/dengue',
            'dengue, OPS, Américas, arbovirosis', 0],
        ['ACOG Practice Bulletins (obstetricia y ginecología)',
            'Boletines de práctica clínica del American College of Obstetricians and Gynecologists.',
            'Materno-neonatal', 'internacional', 'ACOG', 'Guía clínica', 2025, 'en', 'https://www.acog.org/clinical/clinical-guidance/practice-bulletin',
            'ACOG, obstetricia, ginecología, embarazo', 0],
        ['IDSA Practice Guidelines (enfermedades infecciosas)',
            'Guías de práctica de la Infectious Diseases Society of America: neumonía, endocarditis, candidiasis y más.',
            'Infectología y epidemiología', 'internacional', 'IDSA', 'Guía clínica', 2025, 'en', 'https://www.idsociety.org/practice-guideline/practice-guidelines/',
            'IDSA, infectología, antibióticos', 0],
        ['NICE Guidance (Reino Unido)',
            'Guías del National Institute for Health and Care Excellence basadas en evidencia y costo-efectividad.',
            'Medicina interna', 'internacional', 'NICE', 'Guía clínica', 2026, 'en', 'https://www.nice.org.uk/guidance',
            'NICE, evidencia, guías clínicas', 0],
        ['EAU Guidelines (urología)',
            'Guías de la European Association of Urology: urolitiasis, HBP, cáncer urológico e infecciones urinarias.',
            'Cirugía y anestesia', 'internacional', 'EAU', 'Guía clínica', 2025, 'en', 'https://uroweb.org/guidelines',
            'urología, EAU, próstata, litiasis', 0],
    ];

    return array_map(static fn(array $r): array => [
        'title' => $r[0],
        'summary' => $r[1],
        'category' => $r[2],
        'scope' => $r[3],
        'org' => $r[4],
        'doc_type' => $r[5],
        'year' => $r[6],
        'language' => $r[7],
        'external_url' => $r[8],
        'tags' => $r[9],
        'is_featured' => $r[10],
    ], $rows);
}

function repo_seed_if_empty(): void
{
    $pdo = db();
    if (!$pdo) return;
    $count = (int) $pdo->query('SELECT COUNT(*) FROM repo_documents')->fetchColumn();
    if ($count > 0) return;

    $stmt = $pdo->prepare(
        'INSERT INTO repo_documents (slug, title, summary, category, scope, org, doc_type, year, language, external_url, tags, status, is_featured)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach (repo_seed_data() as $item) {
        $slug = unique_slug($pdo, 'repo_documents', $item['title']);
        $stmt->execute([
            $slug,
            $item['title'],
            $item['summary'],
            $item['category'],
            $item['scope'],
            $item['org'],
            $item['doc_type'],
            $item['year'],
            $item['language'],
            $item['external_url'],
            $item['tags'],
            'published',
            $item['is_featured'],
        ]);
    }
}

/** Documentos publicados para la página pública; cae al seed si no hay DB. */
function repo_all_public(): array
{
    $pdo = db();
    if ($pdo) {
        try {
            return $pdo->query(
                "SELECT * FROM repo_documents WHERE status = 'published'
                 ORDER BY is_featured DESC, year DESC, title ASC"
            )->fetchAll();
        } catch (Throwable) {
            // sigue al fallback
        }
    }

    $rows = repo_seed_data();
    foreach ($rows as $i => &$row) {
        $row['id'] = $i + 1;
        $row['slug'] = content_slug($row['title']);
        $row['file_path'] = null;
        $row['status'] = 'published';
    }
    unset($row);
    usort($rows, static fn(array $a, array $b): int =>
        [$b['is_featured'], $b['year'], $a['title']] <=> [$a['is_featured'], $a['year'], $b['title']]);
    return $rows;
}

function repo_all_admin(?string $search = null): array
{
    $pdo = db();
    if (!$pdo) return [];
    $sql = 'SELECT * FROM repo_documents';
    $params = [];
    if ($search) {
        $sql .= ' WHERE title LIKE ? OR summary LIKE ? OR org LIKE ? OR tags LIKE ?';
        $like = '%' . $search . '%';
        $params = [$like, $like, $like, $like];
    }
    $sql .= ' ORDER BY updated_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function repo_by_id(int $id): ?array
{
    $pdo = db();
    if (!$pdo) return null;
    $stmt = $pdo->prepare('SELECT * FROM repo_documents WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function repo_save(array $data, ?int $id = null): int
{
    $pdo = db();
    if (!$pdo) {
        throw new RuntimeException('Base de datos no disponible.');
    }

    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') {
        throw new RuntimeException('El título es obligatorio.');
    }

    $externalUrl = trim((string) ($data['external_url'] ?? ''));
    if ($externalUrl !== '' && !filter_var($externalUrl, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('La URL del documento no es válida.');
    }

    $year = (int) ($data['year'] ?? 0);
    $categories = array_keys(repo_categories());

    $payload = [
        'slug' => unique_slug($pdo, 'repo_documents', $title, $id),
        'title' => mb_substr($title, 0, 280),
        'summary' => mb_substr(trim((string) ($data['summary'] ?? '')), 0, 500) ?: null,
        'category' => in_array($data['category'] ?? '', $categories, true) ? $data['category'] : 'Medicina interna',
        'scope' => ($data['scope'] ?? 'nacional') === 'internacional' ? 'internacional' : 'nacional',
        'org' => mb_substr(trim((string) ($data['org'] ?? '')), 0, 140) ?: 'MISPAS',
        'doc_type' => in_array($data['doc_type'] ?? '', repo_doc_types(), true) ? $data['doc_type'] : 'Protocolo',
        'year' => ($year >= 1950 && $year <= 2100) ? $year : null,
        'language' => array_key_exists($data['language'] ?? '', repo_languages()) ? $data['language'] : 'es',
        'external_url' => $externalUrl ?: null,
        'tags' => mb_substr(trim((string) ($data['tags'] ?? '')), 0, 400) ?: null,
        'status' => ($data['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
        'is_featured' => !empty($data['is_featured']) ? 1 : 0,
    ];

    if (!empty($_FILES['document_file']['tmp_name'])) {
        $payload['file_path'] = repo_handle_pdf_upload($_FILES['document_file']);
    }

    if (!$payload['external_url'] && empty($payload['file_path']) && !$id) {
        throw new RuntimeException('Agrega un enlace oficial o sube el PDF del documento.');
    }

    if ($id) {
        $existing = repo_by_id($id);
        if (!$existing) throw new RuntimeException('Documento no encontrado.');
        if (!isset($payload['file_path'])) {
            $payload['file_path'] = $existing['file_path'];
        } elseif ($existing['file_path']) {
            $old = __DIR__ . '/../' . $existing['file_path'];
            if (is_file($old)) @unlink($old);
        }
        $sets = [];
        $values = [];
        foreach ($payload as $key => $value) {
            $sets[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $id;
        $pdo->prepare('UPDATE repo_documents SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
        return $id;
    }

    $payload['file_path'] = $payload['file_path'] ?? null;
    $columns = array_keys($payload);
    $sql = 'INSERT INTO repo_documents (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
    $pdo->prepare($sql)->execute(array_values($payload));
    return (int) $pdo->lastInsertId();
}

function repo_handle_pdf_upload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Error al subir el archivo PDF.');
    }
    if (($file['size'] ?? 0) > 25 * 1024 * 1024) {
        throw new RuntimeException('El PDF no puede superar 25 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime !== 'application/pdf') {
        throw new RuntimeException('Solo se aceptan archivos PDF.');
    }

    $dir = __DIR__ . '/../storage/uploads/repository';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('No se pudo crear la carpeta de documentos.');
    }

    $name = 'doc-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('No se pudo guardar el PDF.');
    }

    return 'storage/uploads/repository/' . $name;
}

function repo_delete(int $id): void
{
    $pdo = db();
    if (!$pdo) return;
    $item = repo_by_id($id);
    if ($item && $item['file_path']) {
        $file = __DIR__ . '/../' . $item['file_path'];
        if (is_file($file)) @unlink($file);
    }
    $pdo->prepare('DELETE FROM repo_documents WHERE id = ?')->execute([$id]);
}

/** URL pública del documento: PDF local si existe, si no el enlace oficial. */
function repo_document_url(array $doc): string
{
    if (!empty($doc['file_path'])) {
        return base_url($doc['file_path']);
    }
    return (string) ($doc['external_url'] ?? '#');
}
