/**
 * Guías clínicas de las herramientas del portal médico.
 * window.DM_GUIDES[id] = { what, use, interpret, when, caveat, ref }
 * Material de APOYO/formación; el médico debe validar con las guías oficiales.
 * El texto puede incluir HTML simple (es contenido propio, no entrada de usuario).
 */
window.DM_GUIDES = {
    // ===== Generales =====
    imc: {
        what: 'El Índice de Masa Corporal (IMC) relaciona el peso con la talla para estimar el estado nutricional en personas adultas.',
        use: 'Introduce el peso (kg) y la talla (cm). Se calcula como peso ÷ (talla en metros)². Se pre-llena con los signos vitales del paciente.',
        interpret: '&lt;18.5 bajo peso · 18.5–24.9 peso normal · 25–29.9 sobrepeso · 30–34.9 obesidad I · 35–39.9 obesidad II · ≥40 obesidad III.',
        when: 'Tamizaje nutricional, estimación del riesgo cardiometabólico y seguimiento de la evolución del peso.',
        caveat: 'No distingue masa grasa de masa muscular: sobrestima en personas musculosas e infraestima en ancianos. Poco fiable en embarazo, edema o ascitis. Complementar con el perímetro abdominal.',
        ref: 'OMS — Clasificación internacional del estado nutricional del adulto según el IMC.'
    },
    bsa: {
        what: 'La Superficie Corporal (SC) estima el área total del cuerpo; es más fisiológica que el peso para dosificar ciertos fármacos.',
        use: 'Introduce peso (kg) y talla (cm). Fórmula de Mosteller: SC = √((talla × peso) / 3600).',
        interpret: 'Resultado en m². Valor de referencia del adulto ≈ 1.7 m². Se usa como factor multiplicador de la dosis (mg/m²).',
        when: 'Dosificación de quimioterapia y de algunos fármacos, cálculo de índices (índice cardíaco, TFG indexada).',
        caveat: 'En obesidad mórbida puede sobreestimar la dosis; varias guías oncológicas limitan la SC o ajustan. Verifica el protocolo del fármaco.',
        ref: 'Mosteller RD. N Engl J Med. 1987.'
    },
    pesoideal: {
        what: 'El peso ideal (corporal) es una referencia teórica del peso según la talla y el sexo, útil para ajustar dosis y volúmenes.',
        use: 'Introduce talla (cm) y sexo. Fórmula de Devine: hombres 50 + 2.3 kg por cada pulgada sobre 152 cm; mujeres 45.5 + 2.3 kg.',
        interpret: 'Resultado en kg. Se emplea para calcular dosis basadas en peso ideal o ajustado (p. ej. aminoglucósidos, ventilación protectora).',
        when: 'Ajuste de dosis en obesidad, cálculo del volumen corriente en ventilación mecánica (6–8 mL/kg de peso ideal).',
        caveat: 'Es una estimación; en tallas extremas pierde exactitud. Para algunos fármacos se usa el peso ajustado, no el ideal.',
        ref: 'Devine BJ. Drug Intell Clin Pharm. 1974.'
    },
    pam: {
        what: 'La Presión Arterial Media (PAM) es la presión promedio durante el ciclo cardíaco y refleja la perfusión de los órganos.',
        use: 'Introduce la presión sistólica y diastólica. PAM = (PAS + 2 × PAD) / 3.',
        interpret: 'Objetivo habitual de perfusión: PAM ≥ 65 mmHg. Por debajo hay riesgo de hipoperfusión orgánica.',
        when: 'Manejo de shock, sepsis y paciente crítico; titulación de vasopresores y fluidoterapia.',
        caveat: 'La fórmula asume FC normal; en taquicardia marcada subestima la PAM. La medición invasiva (línea arterial) es más exacta.',
        ref: 'Surviving Sepsis Campaign — objetivos hemodinámicos.'
    },

    // ===== Cardiología =====
    chads: {
        what: 'CHA₂DS₂-VASc estima el riesgo anual de ictus/embolia en pacientes con fibrilación auricular no valvular.',
        use: 'Marca los factores presentes; la edad y el sexo suman automáticamente (≥75 = 2, 65–74 = 1; sexo femenino = 1).',
        interpret: 'Riesgo (sin contar el punto por sexo): 0 bajo; 1 intermedio; ≥2 alto. Guías: anticoagular en hombres ≥2 y mujeres ≥3; considerar en hombres =1 / mujeres =2.',
        when: 'Decidir anticoagulación en fibrilación o flutter auricular no valvular.',
        caveat: 'No aplica a FA valvular (estenosis mitral/prótesis: anticoagular siempre). Valora el riesgo de sangrado con HAS-BLED antes de decidir.',
        ref: 'ESC 2020 — Guía de fibrilación auricular.'
    },
    hasbled: {
        what: 'HAS-BLED estima el riesgo de sangrado mayor en pacientes anticoagulados por fibrilación auricular.',
        use: 'Marca los factores presentes (hipertensión, función renal/hepática alterada, ictus, sangrado previo, INR lábil, edad >65, fármacos/alcohol).',
        interpret: '0–2 riesgo bajo-moderado; ≥3 riesgo alto de sangrado: no contraindica anticoagular, pero exige vigilancia y corregir factores modificables.',
        when: 'Junto con CHA₂DS₂-VASc al decidir e iniciar anticoagulación.',
        caveat: 'Un puntaje alto NO es razón para no anticoagular; sirve para identificar y corregir factores de riesgo (TA, INR lábil, AINE, alcohol).',
        ref: 'Pisters R et al. Chest 2010; ESC 2020.'
    },
    qtc: {
        what: 'El QT corregido (QTc) ajusta el intervalo QT por la frecuencia cardíaca para detectar riesgo de arritmias.',
        use: 'Introduce el QT medido (ms) y la frecuencia cardíaca. Fórmula de Bazett: QTc = QT / √(RR), con RR = 60/FC.',
        interpret: 'Prolongado: hombres >450 ms, mujeres >470 ms. >500 ms: alto riesgo de torsade de pointes.',
        when: 'Antes y durante fármacos que prolongan el QT, alteraciones electrolíticas, síncope o palpitaciones.',
        caveat: 'Bazett sobrecorrige a FC altas e infracorrige a FC bajas. Mide el QT en derivación II o V5. Corrige K⁺, Mg²⁺ y Ca²⁺.',
        ref: 'AHA/ACCF/HRS — recomendaciones sobre QT.'
    },

    // ===== Neumología =====
    curb: {
        what: 'CURB-65 estima la gravedad y mortalidad de la neumonía adquirida en la comunidad para decidir el ámbito de tratamiento.',
        use: 'Marca confusión, urea elevada, FR ≥30 y TA baja; la edad ≥65 suma automáticamente.',
        interpret: '0–1 bajo riesgo (ambulatorio) · 2 ingreso/observación · 3–5 alto riesgo (ingreso; valorar UCI).',
        when: 'Neumonía adquirida en la comunidad confirmada, al decidir manejo ambulatorio vs. hospitalario.',
        caveat: 'No sustituye el juicio clínico ni factores como hipoxemia, comorbilidad o soporte social. Si no hay laboratorio, usa CRB-65.',
        ref: 'Lim WS et al. Thorax 2003; guías BTS/IDSA.'
    },
    wellstep: {
        what: 'El score de Wells para TEP estima la probabilidad clínica de tromboembolia pulmonar.',
        use: 'Marca los criterios presentes (clínica de TVP, TEP como diagnóstico más probable, FC>100, inmovilización/cirugía, TEP/TVP previo, hemoptisis, cáncer).',
        interpret: 'Modelo dicotómico: ≤4 TEP improbable (solicitar dímero-D); >4 TEP probable (angio-TC pulmonar).',
        when: 'Sospecha de embolia pulmonar, para orientar el uso de dímero-D vs. imagen.',
        caveat: 'El dímero-D es útil solo en probabilidad baja/intermedia. En alta probabilidad, ir directo a imagen. Considera PERC en muy baja sospecha.',
        ref: 'Wells PS et al. Ann Intern Med 2001; ESC 2019.'
    },
    crb65: {
        what: 'CRB-65 es la versión simplificada de CURB-65, sin necesidad de laboratorio (sin urea).',
        use: 'Marca confusión, FR ≥30 y TA baja; la edad ≥65 suma automáticamente.',
        interpret: '0 bajo riesgo (manejo ambulatorio) · 1–2 intermedio (valorar ingreso) · 3–4 alto riesgo (ingreso urgente).',
        when: 'Neumonía comunitaria en atención primaria o donde no haya analítica disponible.',
        caveat: 'Menos sensible que CURB-65 por omitir la urea. Reevaluar si hay hipoxemia o deterioro.',
        ref: 'Lim WS et al. Thorax 2003.'
    },

    // ===== Nefrología =====
    cg: {
        what: 'Cockcroft-Gault estima el aclaramiento de creatinina (función renal) para ajustar dosis de fármacos.',
        use: 'Introduce edad, sexo, peso y creatinina sérica. ClCr = ((140−edad) × peso × [0.85 si mujer]) / (72 × creatinina).',
        interpret: '≥90 normal (G1) · 60–89 leve (G2) · 45–59 G3a · 30–44 G3b · 15–29 severo (G4) · <15 falla renal (G5).',
        when: 'Ajuste de dosis renal de fármacos (es la fórmula que usan muchas fichas técnicas).',
        caveat: 'Usa peso real; sobreestima en obesidad/edema (considerar peso ideal o ajustado). No fiable en función renal inestable o cambios rápidos de creatinina.',
        ref: 'Cockcroft DW, Gault MH. Nephron 1976.'
    },
    ckdepi: {
        what: 'CKD-EPI 2021 estima el filtrado glomerular (TFG) para clasificar la enfermedad renal crónica.',
        use: 'Introduce edad, sexo y creatinina sérica. Usa la ecuación CKD-EPI 2021 (sin coeficiente de raza).',
        interpret: 'G1 ≥90 · G2 60–89 · G3a 45–59 · G3b 30–44 · G4 15–29 · G5 <15 mL/min/1.73m². Confirmar ERC con dos medidas separadas ≥3 meses.',
        when: 'Diagnóstico y estadificación de enfermedad renal crónica; preferida para clasificar (no para dosificar fármacos).',
        caveat: 'Menos fiable en extremos de masa muscular, embarazo y lesión renal aguda. Para dosis de fármacos suele usarse Cockcroft-Gault.',
        ref: 'Inker LA et al. N Engl J Med 2021; KDIGO.'
    },
    fena: {
        what: 'La fracción de excreción de sodio (FeNa) ayuda a diferenciar la causa de la lesión renal aguda.',
        use: 'Introduce sodio y creatinina en orina y en plasma. FeNa = (NaO × CrP) / (NaP × CrO) × 100.',
        interpret: '<1% sugiere causa prerrenal (hipoperfusión) · >2% sugiere causa renal (necrosis tubular aguda).',
        when: 'Lesión renal aguda con oliguria, para orientar el origen prerrenal vs. renal.',
        caveat: 'Pierde valor con diuréticos (usar FeUrea), en ERC, glucosuria o medios de contraste. Interpretar junto al contexto clínico.',
        ref: 'Espinel CH. JAMA 1976.'
    },
    aniongap: {
        what: 'El anión gap evalúa el equilibrio ácido-base e identifica acidosis metabólica con brecha aniónica elevada.',
        use: 'Introduce Na, Cl y HCO₃ (y albúmina opcional para corregir). Anión gap = Na − (Cl + HCO₃).',
        interpret: 'Normal 8–12 mEq/L. Elevado: cetoacidosis, acidosis láctica, uremia, tóxicos. Corregir +2.5 por cada 1 g/dL de albúmina por debajo de 4.',
        when: 'Evaluación de trastornos ácido-base, sospecha de acidosis metabólica.',
        caveat: 'La hipoalbuminemia reduce el gap y enmascara acidosis: corrige siempre por albúmina. Calcula también el gap osmolar si sospechas tóxicos.',
        ref: 'Emmett M, Narins RG. Medicine 1977.'
    },

    // ===== Medicina interna =====
    wells: {
        what: 'El score de Wells para TVP estima la probabilidad clínica de trombosis venosa profunda.',
        use: 'Marca los criterios presentes (cáncer, inmovilización, edema, dolor en trayecto venoso…) y resta 2 si hay un diagnóstico alternativo más probable.',
        interpret: '≥2 puntos: TVP probable (eco-Doppler) · <2: improbable (dímero-D para descartar).',
        when: 'Sospecha de trombosis venosa profunda en miembro inferior.',
        caveat: 'El dímero-D solo descarta en probabilidad baja. En alta sospecha, ir directo a eco-Doppler aunque el dímero sea negativo.',
        ref: 'Wells PS et al. N Engl J Med 2003.'
    },
    qsofa: {
        what: 'qSOFA es un cribado rápido a pie de cama para identificar pacientes con infección y riesgo de mala evolución (sepsis).',
        use: 'Marca: frecuencia respiratoria ≥22, alteración del estado mental (Glasgow <15) y TA sistólica ≤100.',
        interpret: '≥2 criterios: alto riesgo de mortalidad/estancia prolongada; valorar sepsis, lactato y nivel de cuidado.',
        when: 'Cribado fuera de UCI ante sospecha de infección.',
        caveat: 'Es una alerta, no diagnóstico de sepsis (que requiere SOFA y disfunción orgánica). Un qSOFA negativo no descarta sepsis.',
        ref: 'Sepsis-3, Singer M et al. JAMA 2016.'
    },
    glasgow: {
        what: 'La Escala de Coma de Glasgow (GCS) valora el nivel de conciencia mediante la respuesta ocular, verbal y motora.',
        use: 'Selecciona la mejor respuesta ocular (1–4), verbal (1–5) y motora (1–6). El total va de 3 a 15.',
        interpret: '13–15 traumatismo leve · 9–12 moderado · ≤8 grave (considerar protección de la vía aérea/intubación).',
        when: 'Traumatismo craneoencefálico, alteración del estado de conciencia, seguimiento neurológico.',
        caveat: 'Difícil de aplicar con sedación, intubación o afasia; registra el componente no evaluable. Reevalúa con frecuencia: la tendencia importa más que un valor aislado.',
        ref: 'Teasdale G, Jennett B. Lancet 1974.'
    },

    // ===== Gastroenterología =====
    childpugh: {
        what: 'La clasificación de Child-Pugh estima la gravedad y el pronóstico de la cirrosis hepática.',
        use: 'Selecciona la categoría de bilirrubina, albúmina, INR, ascitis y encefalopatía. El total va de 5 a 15.',
        interpret: 'Clase A 5–6 (compensada) · B 7–9 · C 10–15 (descompensada, peor supervivencia).',
        when: 'Pronóstico de cirrosis, evaluación de riesgo quirúrgico y de candidatura a procedimientos.',
        caveat: 'Incluye variables subjetivas (ascitis, encefalopatía). Para priorización de trasplante se usa MELD.',
        ref: 'Pugh RNH et al. Br J Surg 1973.'
    },
    meld: {
        what: 'El MELD predice la mortalidad a 90 días en la enfermedad hepática avanzada y prioriza el trasplante.',
        use: 'Introduce bilirrubina, INR y creatinina. Los valores menores de 1.0 se ajustan a 1.0; la creatinina se limita a 4.',
        interpret: 'A mayor puntuación, mayor mortalidad. ≥15 suele ser el umbral para valorar trasplante hepático.',
        when: 'Estratificación de cirrosis avanzada y asignación en lista de trasplante.',
        caveat: 'No incluye sodio (existe MELD-Na, que es más preciso). Afectado por anticoagulación (INR) y causas no hepáticas de creatinina alta.',
        ref: 'Kamath PS et al. Hepatology 2001.'
    },

    // ===== Endocrinología =====
    hba1c: {
        what: 'Convierte la hemoglobina glucosilada (HbA1c) en glucosa media estimada de los últimos 2–3 meses.',
        use: 'Introduce la HbA1c (%). Glucosa media estimada = 28.7 × HbA1c − 46.7 (mg/dL).',
        interpret: 'Meta general en diabetes: HbA1c <7%. Individualiza (más estricta en jóvenes, más laxa en ancianos/frágiles).',
        when: 'Control y seguimiento de la diabetes; educación del paciente sobre su glucosa promedio.',
        caveat: 'Poco fiable con anemia, hemoglobinopatías, transfusión reciente o embarazo. No refleja la variabilidad ni las hipoglucemias.',
        ref: 'ADA — Standards of Care; estudio ADAG (Nathan 2008).'
    },

    // ===== Hematología =====
    anc: {
        what: 'El recuento absoluto de neutrófilos (RAN/ANC) cuantifica los neutrófilos para evaluar el riesgo de infección (neutropenia).',
        use: 'Introduce leucocitos totales (×10³) y el porcentaje de segmentados y bandas. ANC = leucocitos × (% segmentados + % bandas) / 100.',
        interpret: '≥1500 normal · 1000–1499 neutropenia leve · 500–999 moderada · <500 grave (riesgo alto de infección).',
        when: 'Quimioterapia, fármacos mielotóxicos, fiebre en paciente oncológico, seguimiento hematológico.',
        caveat: '<500 (o <1000 en descenso) con fiebre = neutropenia febril: urgencia, antibiótico empírico inmediato.',
        ref: 'NCCN — Manejo de neutropenia febril.'
    },

    // ===== Obstetricia =====
    ga: {
        what: 'Calcula la fecha probable de parto (FPP) y la edad gestacional actual a partir de la última menstruación.',
        use: 'Introduce la fecha de la última menstruación (FUM). Regla de Naegele: FUM + 7 días − 3 meses + 1 año.',
        interpret: 'Devuelve la edad gestacional (semanas + días) y la FPP. A término: 37–42 semanas.',
        when: 'Control prenatal, datación del embarazo, programación de estudios y del parto.',
        caveat: 'Asume ciclos regulares de 28 días; en ciclos irregulares o FUM incierta, la datación por ecografía del primer trimestre es más fiable.',
        ref: 'Naegele FK; guías de control prenatal.'
    },
};
