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

    // ===== Lote 2 =====
    goteo: {
        what: 'Calcula la velocidad de infusión intravenosa en gotas por minuto cuando no se usa bomba.',
        use: 'Introduce volumen (mL), tiempo (horas) y el factor del equipo (macrogotero 20/15, microgotero 60). gotas/min = (volumen × factor) / (tiempo × 60).',
        interpret: 'Devuelve las gotas por minuto y el equivalente en mL/h.',
        when: 'Programar sueros o medicación intravenosa por gravedad.',
        caveat: 'Verifica el factor de goteo del equipo (varía según el fabricante). Con bomba de infusión usa directamente mL/h.',
        ref: 'Estándares de enfermería en terapia intravenosa.'
    },
    osmol: {
        what: 'Estima la osmolaridad plasmática (concentración total de solutos en sangre).',
        use: 'Introduce Na, glucosa y BUN. Osmolaridad = 2 × Na + glucosa/18 + BUN/2.8.',
        interpret: 'Normal 275–295 mOsm/kg. Elevada en deshidratación, hiperglucemia o tóxicos; baja en hiponatremia.',
        when: 'Trastornos del sodio, estado hiperosmolar, sospecha de intoxicación (gap osmolar).',
        caveat: 'Compárala con la osmolaridad medida: una brecha (gap) >10 sugiere tóxicos como metanol o etilenglicol.',
        ref: 'Fórmula estándar de osmolaridad calculada.'
    },
    calcioCorr: {
        what: 'Corrige el calcio total según la albúmina, ya que el calcio circula unido a ella.',
        use: 'Introduce calcio total y albúmina. Ca corregido = Ca + 0.8 × (4 − albúmina).',
        interpret: 'Normal 8.5–10.5 mg/dL. La hipoalbuminemia baja el calcio total sin alterar el calcio iónico (el activo).',
        when: 'Interpretar la calcemia en hipoalbuminemia (cirrosis, desnutrición, paciente crítico).',
        caveat: 'Es una aproximación; el calcio iónico es el patrón de oro cuando está disponible.',
        ref: 'Payne RB et al. BMJ 1973.'
    },
    heart: {
        what: 'El HEART score estratifica el riesgo de eventos cardíacos mayores (MACE) a 6 semanas en el dolor torácico de urgencias.',
        use: 'Puntúa (0–2 cada uno) Historia, ECG, Edad, factores de Riesgo y Troponina.',
        interpret: '0–3 bajo (≈1.7% MACE, posible alta) · 4–6 moderado (observación) · ≥7 alto (manejo invasivo).',
        when: 'Dolor torácico no traumático en urgencias, para decidir alta vs. ingreso.',
        caveat: 'No aplica ante IAMCEST evidente (manejo inmediato). Requiere troponina; combínalo con seriación.',
        ref: 'Six AJ et al. Neth Heart J 2008.'
    },
    timi: {
        what: 'El score TIMI estima el riesgo a 14 días en el síndrome coronario agudo sin elevación del ST.',
        use: 'Marca los 7 criterios (edad ≥65, ≥3 factores de riesgo, estenosis conocida, desviación ST, anginas, AAS reciente, marcadores).',
        interpret: '0–2 bajo · 3–4 intermedio · 5–7 alto riesgo de muerte/IAM/revascularización.',
        when: 'Angina inestable / IAMSEST, para guiar la intensidad del tratamiento.',
        caveat: 'Para IAMCEST se usan otras escalas (GRACE, Killip). Complementa con troponina seriada.',
        ref: 'Antman EM et al. JAMA 2000.'
    },
    killip: {
        what: 'La clasificación de Killip estima la gravedad de la insuficiencia cardíaca en el infarto agudo y predice mortalidad.',
        use: 'Selecciona la clase clínica (I sin IC, II estertores/S3, III edema agudo de pulmón, IV shock).',
        interpret: 'Mortalidad creciente: I ≈6%, II ≈17%, III ≈38%, IV ≈67%.',
        when: 'Evaluación pronóstica al ingreso por infarto agudo de miocardio.',
        caveat: 'Es clínica (no requiere estudios); reevalúa según evolución y soporte.',
        ref: 'Killip T, Kimball JT. Am J Cardiol 1967.'
    },
    rcri: {
        what: 'El índice de Lee (RCRI) estima el riesgo cardíaco en cirugía no cardíaca.',
        use: 'Marca los 6 factores (cirugía de alto riesgo, cardiopatía isquémica, IC, ACV, diabetes en insulina, creatinina >2).',
        interpret: 'Riesgo de evento cardíaco mayor: 0 ≈0.4% · 1 ≈1% · 2 ≈2.4% · ≥3 ≈5.4%.',
        when: 'Evaluación preoperatoria del riesgo cardíaco.',
        caveat: 'Subestima en cirugía vascular; complementa con la capacidad funcional y biomarcadores según guías.',
        ref: 'Lee TH et al. Circulation 1999.'
    },
    perc: {
        what: 'Los criterios PERC permiten descartar TEP sin pruebas en pacientes de baja probabilidad clínica.',
        use: 'Aplica solo si la sospecha de TEP es baja. Marca los criterios presentes (de los 8).',
        interpret: '0 criterios = PERC negativo: TEP descartado sin dímero-D. ≥1 = no se descarta, continuar estudio.',
        when: 'Baja probabilidad de embolia pulmonar, para evitar pruebas innecesarias.',
        caveat: 'Válido solo en baja probabilidad (p. ej. Wells ≤4). No usar en probabilidad moderada o alta.',
        ref: 'Kline JA et al. J Thromb Haemost 2008.'
    },
    corrna: {
        what: 'Corrige el sodio según la glucemia (la hiperglucemia diluye el sodio sérico).',
        use: 'Introduce el sodio medido y la glucosa. Na corregido = Na + 1.6 × ((glucosa − 100) / 100).',
        interpret: 'Estima el sodio real una vez corregida la glucosa; orienta el manejo de la hiponatremia.',
        when: 'Hiponatremia con hiperglucemia (cetoacidosis, estado hiperosmolar).',
        caveat: 'Algunos usan factor 2.4 con glucosa muy alta (>400). Corrige el sodio sin descender la glucosa de forma brusca.',
        ref: 'Katz MA. NEJM 1973; Hillier TA 1999.'
    },
    sofa: {
        what: 'El SOFA cuantifica la disfunción de 6 órganos; se usa para definir sepsis y estimar mortalidad.',
        use: 'Selecciona el grado (0–4) de respiratorio, coagulación, hígado, cardiovascular, SNC y renal.',
        interpret: 'A mayor puntuación, mayor disfunción y mortalidad. Un aumento ≥2 sobre el basal define sepsis ante infección.',
        when: 'Paciente crítico/UCI; definición de sepsis (Sepsis-3) y seguimiento.',
        caveat: 'Requiere laboratorio y gasometría. qSOFA es el cribado rápido inicial fuera de UCI.',
        ref: 'Vincent JL et al. Intensive Care Med 1996; Sepsis-3.'
    },
    padua: {
        what: 'El score de Padua estima el riesgo de tromboembolia venosa en pacientes médicos hospitalizados.',
        use: 'Marca los factores presentes (cáncer, TVP previa, movilidad reducida, trombofilia, etc.).',
        interpret: '≥4 alto riesgo (indicar tromboprofilaxis); <4 bajo riesgo.',
        when: 'Pacientes médicos ingresados, para decidir profilaxis antitrombótica.',
        caveat: 'Para pacientes quirúrgicos usa Caprini. Equilibra con el riesgo de sangrado.',
        ref: 'Barbar S et al. J Thromb Haemost 2010.'
    },
    blatchford: {
        what: 'El Glasgow-Blatchford predice la necesidad de intervención (transfusión/endoscopia) en hemorragia digestiva alta.',
        use: 'Selecciona urea, hemoglobina y TA, y marca FC≥100, melena, síncope, hepatopatía e IC.',
        interpret: '0 = riesgo muy bajo (posible manejo ambulatorio). ≥1 = valorar endoscopia/ingreso.',
        when: 'Hemorragia digestiva alta en urgencias, para el triage inicial.',
        caveat: 'Identifica mejor el bajo riesgo; no sustituye la endoscopia. Para pronóstico post-endoscopia usa Rockall.',
        ref: 'Blatchford O et al. Lancet 2000.'
    },
    ranson: {
        what: 'Los criterios de Ranson estiman la gravedad de la pancreatitis aguda.',
        use: 'Marca los criterios al ingreso (edad, leucocitos, glucosa, LDH, AST); existen 6 criterios adicionales a las 48 h.',
        interpret: '≥3 criterios sugiere pancreatitis grave y mayor mortalidad.',
        when: 'Pancreatitis aguda, evaluación de gravedad al ingreso.',
        caveat: 'Requiere reevaluación a las 48 h para completarse. APACHE-II o BISAP son alternativas más ágiles.',
        ref: 'Ranson JH et al. Surg Gynecol Obstet 1974.'
    },
    maddrey: {
        what: 'La función discriminante de Maddrey identifica la hepatitis alcohólica grave que se beneficia de corticoides.',
        use: 'Introduce TP del paciente, TP control y bilirrubina. FD = 4.6 × (TP − TP control) + bilirrubina.',
        interpret: '≥32 indica hepatitis alcohólica grave (alta mortalidad a corto plazo; valorar corticoides).',
        when: 'Hepatitis alcohólica, para decidir tratamiento con corticoides.',
        caveat: 'Descarta infección y hemorragia antes de los corticoides. El score de Lille a los 7 días evalúa la respuesta.',
        ref: 'Maddrey WC et al. Gastroenterology 1978.'
    },
    fib4: {
        what: 'FIB-4 estima de forma no invasiva la probabilidad de fibrosis hepática avanzada.',
        use: 'Introduce edad, AST, ALT y plaquetas. FIB-4 = (edad × AST) / (plaquetas × √ALT).',
        interpret: '<1.45 fibrosis avanzada improbable · >3.25 probable · intermedio: ampliar estudio (elastografía).',
        when: 'Cribado de fibrosis en hígado graso y hepatitis crónica.',
        caveat: 'Menos fiable por debajo de 35 y por encima de 65 años. Confirma con elastografía o biopsia si es intermedio/alto.',
        ref: 'Sterling RK et al. Hepatology 2006.'
    },
    harris: {
        what: 'Estima el gasto energético (requerimiento calórico) a partir de la tasa metabólica basal y la actividad.',
        use: 'Introduce peso, talla, edad, sexo y nivel de actividad. Usa Harris-Benedict revisada × factor de actividad.',
        interpret: 'Resultado en kcal/día: calorías de mantenimiento estimadas.',
        when: 'Planificación y soporte nutricional, manejo del peso.',
        caveat: 'Es una estimación; ajústala según evolución. En el paciente crítico se prefieren ecuaciones específicas o calorimetría.',
        ref: 'Harris JA, Benedict FG 1919 (rev. Roza & Shizgal 1984).'
    },
    homair: {
        what: 'HOMA-IR estima la resistencia a la insulina con la glucosa e insulina en ayuno.',
        use: 'Introduce glucosa e insulina en ayuno. HOMA-IR = (glucosa × insulina) / 405.',
        interpret: '>2.5–3 sugiere resistencia a la insulina (el umbral varía según la población).',
        when: 'Evaluación de síndrome metabólico, prediabetes, síndrome de ovario poliquístico.',
        caveat: 'No validado en diabetes establecida (función beta deteriorada). Requiere ayuno y un ensayo de insulina fiable.',
        ref: 'Matthews DR et al. Diabetologia 1985.'
    },
    findrisc: {
        what: 'FINDRISC estima el riesgo de desarrollar diabetes tipo 2 en 10 años, sin necesidad de laboratorio.',
        use: 'Selecciona edad, IMC, perímetro abdominal, actividad, dieta, antihipertensivos, glucosa alta previa y antecedentes familiares.',
        interpret: '<7 bajo · 7–11 ligeramente elevado · 12–14 moderado · 15–20 alto · >20 muy alto.',
        when: 'Cribado poblacional del riesgo de diabetes, educación y prevención.',
        caveat: 'Es de cribado, no diagnóstico; confirma con glucemia/HbA1c si el riesgo es alto.',
        ref: 'Lindström J, Tuomilehto J. Diabetes Care 2003.'
    },
    bishop: {
        what: 'El índice de Bishop valora la madurez cervical para predecir el éxito de la inducción del parto.',
        use: 'Selecciona dilatación, borramiento, estación, consistencia y posición del cuello.',
        interpret: '≥8 cuello favorable (alta probabilidad de éxito) · ≤6 desfavorable (considerar maduración cervical).',
        when: 'Antes de inducir el trabajo de parto.',
        caveat: 'Exploración subjetiva con variabilidad entre observadores. Interprétalo junto a la indicación y la paridad.',
        ref: 'Bishop EH. Obstet Gynecol 1964.'
    },
    dosispeso: {
        what: 'Calcula la dosis total y por toma de un fármaco según el peso del paciente.',
        use: 'Introduce peso, dosis en mg/kg y número de tomas/día. Dosis diaria = peso × mg/kg.',
        interpret: 'Devuelve la dosis diaria total y la dosis aproximada por toma.',
        when: 'Dosificación pediátrica y de fármacos basados en peso.',
        caveat: 'Verifica SIEMPRE la dosis máxima del fármaco y la concentración de la presentación; no excedas la dosis del adulto.',
        ref: 'Vademécum / ficha técnica del fármaco.'
    },
    holliday: {
        what: 'El método de Holliday-Segar calcula los líquidos de mantenimiento diarios, sobre todo en pediatría.',
        use: 'Introduce el peso. Regla 100/50/20: 100 mL/kg los primeros 10 kg + 50 mL/kg los siguientes 10 + 20 mL/kg por kg adicional.',
        interpret: 'Devuelve el volumen diario y el equivalente por hora (regla 4-2-1).',
        when: 'Fluidoterapia de mantenimiento en niños hospitalizados.',
        caveat: 'Es el aporte basal: suma el déficit y las pérdidas. Vigila el sodio (riesgo de hiponatremia con sueros hipotónicos).',
        ref: 'Holliday MA, Segar WE. Pediatrics 1957.'
    },
    apgar: {
        what: 'El test de Apgar evalúa la adaptación del recién nacido al 1.º y 5.º minuto de vida.',
        use: 'Puntúa (0–2) apariencia (color), pulso, gesto (reflejos), actividad (tono) y respiración.',
        interpret: '7–10 normal · 4–6 depresión moderada · 0–3 depresión grave.',
        when: 'Valoración del recién nacido al minuto 1 y 5 (y cada 5 min si es bajo).',
        caveat: 'No predice por sí solo el pronóstico neurológico ni guía la reanimación (que se inicia antes, según FC y respiración).',
        ref: 'Apgar V. Curr Res Anesth Analg 1953.'
    },
    tet: {
        what: 'Estima el calibre del tubo endotraqueal y la profundidad de inserción en pediatría según la edad.',
        use: 'Introduce la edad. TET sin balón (DI) = (edad/4) + 4; con balón resta 0.5; profundidad ≈ DI × 3 cm.',
        interpret: 'Devuelve el diámetro interno sugerido y la profundidad de inserción aproximada.',
        when: 'Preparación de la vía aérea pediátrica (intubación).',
        caveat: 'Es una estimación: ten preparado un tubo medio número por encima y por debajo. No aplica a neonatos (usar peso/tablas).',
        ref: 'Fórmula de Cole; guías PALS.'
    },
    silverman: {
        what: 'La escala de Silverman-Andersen valora la dificultad respiratoria del recién nacido.',
        use: 'Puntúa (0–2) quejido, aleteo nasal, tiraje intercostal, retracción xifoidea y disociación toracoabdominal.',
        interpret: '0 sin dificultad · 1–3 leve · 4–6 moderada · ≥7 grave (soporte ventilatorio).',
        when: 'Evaluación del distrés respiratorio neonatal.',
        caveat: 'A diferencia de Apgar, mayor puntuación = peor. Reevalúa de forma seriada.',
        ref: 'Silverman WA, Andersen DH. Pediatrics 1956.'
    },
    abcd2: {
        what: 'ABCD² estima el riesgo de ictus en los días posteriores a un accidente isquémico transitorio (AIT).',
        use: 'Puntúa edad, TA, clínica (debilidad/habla), duración y diabetes.',
        interpret: '0–3 bajo · 4–5 moderado · 6–7 alto riesgo de ictus a 2 días.',
        when: 'Tras un AIT, para priorizar el estudio y el manejo.',
        caveat: 'No sustituye la evaluación urgente: muchos protocolos estudian todo AIT de forma precoz con neuroimagen y estudio vascular.',
        ref: 'Johnston SC et al. Lancet 2007.'
    },
    mrs: {
        what: 'La escala de Rankin modificada (mRS) mide el grado de discapacidad o dependencia tras un ictus.',
        use: 'Selecciona el grado: 0 (sin síntomas) a 5 (discapacidad grave); 6 = fallecido.',
        interpret: '0–2 independiente · 3–5 dependiente · 6 fallecido. Es el desenlace funcional estándar en ictus.',
        when: 'Evaluación del resultado funcional tras ictus, ensayos clínicos y seguimiento.',
        caveat: 'Tiene variabilidad entre evaluadores; una entrevista estructurada mejora la fiabilidad.',
        ref: 'van Swieten JC et al. Stroke 1988.'
    },

    // ===== Lote 3 =====
    hunthess: {
        what: 'La escala de Hunt-Hess gradúa la gravedad clínica de la hemorragia subaracnoidea por aneurisma.',
        use: 'Selecciona el grado clínico (I asintomático/cefalea leve … V coma/descerebración).',
        interpret: 'A mayor grado, peor pronóstico y mayor riesgo quirúrgico. I–II buen pronóstico; IV–V alto riesgo.',
        when: 'Hemorragia subaracnoidea, para pronóstico y decisión quirúrgica.',
        caveat: 'Es clínica y subjetiva; la escala de Fisher (TC) complementa el riesgo de vasoespasmo.',
        ref: 'Hunt WE, Hess RM. J Neurosurg 1968.'
    },
    nihss: {
        what: 'La NIHSS cuantifica la gravedad del déficit neurológico en el ictus agudo.',
        use: 'Puntúa 15 ítems: conciencia, mirada, campos, cara, fuerza de las 4 extremidades, ataxia, sensibilidad, lenguaje, disartria y extinción.',
        interpret: '0 sin déficit · 1–4 leve · 5–15 moderado · 16–20 moderado-grave · 21–42 grave.',
        when: 'Ictus isquémico agudo: evaluación inicial, selección de trombólisis/trombectomía y seguimiento.',
        caveat: 'Requiere entrenamiento para puntuar de forma fiable y subestima el ictus de circulación posterior. La tendencia es clave.',
        ref: 'Brott T et al. Stroke 1989 (NIH Stroke Scale).'
    },
    das28: {
        what: 'DAS28 mide la actividad de la artritis reumatoide combinando articulaciones, reactante de fase aguda y valoración global.',
        use: 'Introduce articulaciones dolorosas e inflamadas (0–28 cada una), VSG y la valoración global del paciente (EVA 0–100).',
        interpret: '<2.6 remisión · ≤3.2 actividad baja · ≤5.1 moderada · >5.1 alta.',
        when: 'Seguimiento de la artritis reumatoide y ajuste del tratamiento (treat-to-target).',
        caveat: 'Esta versión usa VSG; DAS28-PCR da valores ligeramente distintos. No aplicable a otras artritis.',
        ref: 'Prevoo ML et al. Arthritis Rheum 1995.'
    },
    phq9: {
        what: 'El PHQ-9 es un cuestionario de cribado y seguimiento de la depresión.',
        use: 'El paciente puntúa (0–3) cada uno de los 9 síntomas en las últimas 2 semanas.',
        interpret: '0–4 mínima · 5–9 leve · 10–14 moderada · 15–19 moderada-grave · 20–27 grave.',
        when: 'Cribado y monitorización de la depresión.',
        caveat: 'El ítem 9 (ideas de muerte/autolesión) obliga a valorar el riesgo suicida. Es de apoyo, no diagnóstico por sí solo.',
        ref: 'Kroenke K, Spitzer RL. J Gen Intern Med 2001.'
    },
    gad7: {
        what: 'El GAD-7 es un cuestionario de cribado del trastorno de ansiedad generalizada.',
        use: 'El paciente puntúa (0–3) cada uno de los 7 síntomas en las últimas 2 semanas.',
        interpret: '0–4 mínima · 5–9 leve · 10–14 moderada · 15–21 grave.',
        when: 'Cribado y seguimiento de la ansiedad.',
        caveat: 'Es cribado, no diagnóstico; valora también depresión, que suele coexistir.',
        ref: 'Spitzer RL et al. Arch Intern Med 2006.'
    },
    cage: {
        what: 'El cuestionario CAGE es un cribado breve del consumo problemático de alcohol.',
        use: 'Cuatro preguntas: necesidad de reducir, molestia por críticas, culpa y beber al despertar. Marca las afirmativas.',
        interpret: '≥2 respuestas afirmativas: alta probabilidad de consumo problemático.',
        when: 'Cribado de alcoholismo en consulta.',
        caveat: 'Menos sensible para consumo de riesgo reciente; AUDIT es más completo. Una sola respuesta positiva ya justifica explorar.',
        ref: 'Ewing JA. JAMA 1984.'
    },
    ciwa: {
        what: 'La escala CIWA-Ar cuantifica la gravedad del síndrome de abstinencia alcohólica y guía el tratamiento.',
        use: 'Puntúa 10 ítems (náuseas, temblor, sudoración, ansiedad, agitación, alteraciones táctiles/auditivas/visuales, cefalea y orientación).',
        interpret: '<8 leve · 8–15 moderada · >15 grave (riesgo de delirium tremens y convulsiones).',
        when: 'Manejo de la abstinencia alcohólica (pauta de benzodiacepinas guiada por síntomas).',
        caveat: 'Requiere paciente comunicativo; poco fiable con sedación o barrera idiomática. Reevalúa con frecuencia.',
        ref: 'Sullivan JT et al. Br J Addict 1989.'
    },
    ipss: {
        what: 'El IPSS valora la gravedad de los síntomas del tracto urinario inferior (hiperplasia prostática benigna).',
        use: 'El paciente puntúa (0–5) 7 síntomas miccionales del último mes.',
        interpret: '0–7 leve · 8–19 moderado · 20–35 grave.',
        when: 'Evaluación y seguimiento de síntomas prostáticos y respuesta al tratamiento.',
        caveat: 'Mide síntomas, no el tamaño prostático ni descarta cáncer. La pregunta de calidad de vida se valora aparte.',
        ref: 'Barry MJ et al. J Urol 1992.'
    },
    psadens: {
        what: 'La densidad de PSA relaciona el PSA con el volumen prostático para afinar el riesgo de cáncer.',
        use: 'Introduce el PSA (ng/mL) y el volumen prostático ecográfico (cc). Densidad = PSA / volumen.',
        interpret: '>0.15 ng/mL/cc aumenta la sospecha de cáncer, sobre todo con PSA en zona gris (4–10).',
        when: 'PSA elevado con próstata grande, para apoyar la decisión de biopsia.',
        caveat: 'Depende de la exactitud del volumen ecográfico. Es un dato más, no una decisión única.',
        ref: 'Benson MC et al. J Urol 1992.'
    },
    iief5: {
        what: 'El IIEF-5 (SHIM) evalúa la presencia y la gravedad de la disfunción eréctil.',
        use: 'El paciente puntúa (1–5) 5 preguntas sobre los últimos 6 meses.',
        interpret: '22–25 sin disfunción · 17–21 leve · 12–16 leve-moderada · 8–11 moderada · 5–7 grave.',
        when: 'Cribado y seguimiento de la disfunción eréctil.',
        caveat: 'Requiere actividad sexual reciente para responder. Investiga causas (vascular, hormonal, fármacos, psicógena).',
        ref: 'Rosen RC et al. Int J Impot Res 1999.'
    },
    calvert: {
        what: 'La fórmula de Calvert calcula la dosis de carboplatino según el AUC objetivo y la función renal.',
        use: 'Introduce el AUC objetivo y la TFG/aclaramiento. Dosis (mg) = AUC × (TFG + 25).',
        interpret: 'Devuelve la dosis total en mg para el ciclo.',
        when: 'Dosificación de carboplatino en quimioterapia.',
        caveat: 'La TFG suele limitarse (≈125 mL/min) para evitar sobredosis; sigue el protocolo oncológico y el método de TFG indicado.',
        ref: 'Calvert AH et al. J Clin Oncol 1989.'
    },
    ecog: {
        what: 'El ECOG performance status mide el estado funcional del paciente oncológico.',
        use: 'Selecciona el grado (0 actividad normal … 4 postrado; 5 fallecido).',
        interpret: '0–1 buen estado (suele tolerar quimioterapia) · 2 limitado · 3–4 mal estado funcional.',
        when: 'Decisiones de tratamiento oncológico, pronóstico y elegibilidad para ensayos.',
        caveat: 'Es subjetivo; valóralo junto a comorbilidad y preferencias. Equivale aproximadamente a Karnofsky.',
        ref: 'Oken MM et al. Am J Clin Oncol 1982.'
    },
    karnofsky: {
        what: 'El índice de Karnofsky mide el estado funcional en una escala de 0 a 100.',
        use: 'Selecciona el porcentaje que mejor describe la autonomía del paciente.',
        interpret: '≥70 autonomía · 40–60 requiere asistencia · ≤30 dependencia grave.',
        when: 'Pronóstico y decisiones terapéuticas en oncología y cuidados paliativos.',
        caveat: 'Subjetivo y con variabilidad; se correlaciona con ECOG (90–100 ≈ ECOG 0).',
        ref: 'Karnofsky DA, Burchenal JH. 1949.'
    },
    barthel: {
        what: 'El índice de Barthel mide la independencia en las actividades básicas de la vida diaria.',
        use: 'Selecciona el nivel de autonomía en 10 actividades (comer, asearse, vestirse, continencia, traslados, deambulación, escaleras).',
        interpret: '100 independiente · 60–95 dependencia leve · 40–55 moderada · 20–35 grave · <20 total.',
        when: 'Geriatría, rehabilitación y seguimiento funcional.',
        caveat: 'Mide actividades básicas, no instrumentales (para eso, Lawton). Tiene efecto techo en pacientes poco dependientes.',
        ref: 'Mahoney FI, Barthel DW. Md State Med J 1965.'
    },
    lawton: {
        what: 'La escala de Lawton-Brody mide la independencia en las actividades instrumentales de la vida diaria.',
        use: 'Marca las actividades que el paciente realiza de forma autónoma (teléfono, compras, comida, casa, ropa, transporte, medicación, finanzas).',
        interpret: '8 = autonomía total; a menor puntuación, mayor dependencia instrumental.',
        when: 'Detección precoz del deterioro funcional en personas mayores.',
        caveat: 'Históricamente influida por el sexo en algunas actividades; interprétala según el contexto del paciente.',
        ref: 'Lawton MP, Brody EM. Gerontologist 1969.'
    },
    asa: {
        what: 'La clasificación ASA estima el estado físico preoperatorio del paciente.',
        use: 'Selecciona la clase (I sano … V moribundo; VI muerte cerebral/donante). Se añade "E" en cirugía de emergencia.',
        interpret: 'A mayor clase, mayor riesgo anestésico-quirúrgico.',
        when: 'Valoración preanestésica.',
        caveat: 'No incluye el tipo de cirugía ni la edad de forma directa; es una valoración global y algo subjetiva.',
        ref: 'American Society of Anesthesiologists.'
    },
    mallampati: {
        what: 'La clasificación de Mallampati predice la dificultad de intubación según la visibilidad orofaríngea.',
        use: 'Con el paciente sentado, boca abierta y lengua fuera, selecciona la clase (I–IV).',
        interpret: 'Clases III–IV predicen una vía aérea difícil.',
        when: 'Valoración preanestésica de la vía aérea.',
        caveat: 'Predictor imperfecto; combínalo con apertura bucal, distancia tiromentoniana, movilidad cervical y antecedentes.',
        ref: 'Mallampati SR et al. Can Anaesth Soc J 1985.'
    },
    stopbang: {
        what: 'STOP-BANG es un cribado del riesgo de apnea obstructiva del sueño.',
        use: 'Marca los 8 ítems (ronquido, cansancio, apneas observadas, hipertensión, IMC>35, edad>50, cuello>40 cm, sexo masculino).',
        interpret: '0–2 bajo · 3–4 intermedio · ≥5 alto riesgo de SAOS.',
        when: 'Cribado preoperatorio y en consulta ante sospecha de apnea del sueño.',
        caveat: 'Es de cribado; el diagnóstico requiere poligrafía/polisomnografía. El riesgo alto obliga a precaución con sedantes y opioides.',
        ref: 'Chung F et al. Anesthesiology 2008.'
    },
    apfel: {
        what: 'El score de Apfel estima el riesgo de náuseas y vómitos postoperatorios (NVPO).',
        use: 'Marca los 4 factores (sexo femenino, no fumador, antecedente de NVPO/cinetosis, uso de opioides postoperatorios).',
        interpret: 'Riesgo aproximado: 0 ≈10% · 1 ≈20% · 2 ≈40% · 3 ≈60% · 4 ≈80%.',
        when: 'Planificación de la profilaxis antiemética perioperatoria.',
        caveat: 'A mayor riesgo, más medidas profilácticas (estrategia multimodal). No incluye el tipo de cirugía/anestesia.',
        ref: 'Apfel CC et al. Anesthesiology 1999.'
    },
    anestLocal: {
        what: 'Calcula la dosis máxima de anestésico local según el peso, para prevenir toxicidad sistémica.',
        use: 'Introduce peso y fármaco. Lidocaína 4.5 mg/kg (7 con epinefrina); bupivacaína 2 mg/kg (3 con epinefrina).',
        interpret: 'Devuelve la dosis máxima total en mg que no debe superarse.',
        when: 'Anestesia local/regional e infiltración.',
        caveat: 'La toxicidad depende de la vía y la zona (la absorción varía). Ten preparada emulsión lipídica al 20% ante toxicidad sistémica (LAST).',
        ref: 'Guías de anestesia regional (ASRA).'
    },
    alvarado: {
        what: 'El score de Alvarado estima la probabilidad de apendicitis aguda.',
        use: 'Marca síntomas, signos y laboratorio (dolor migratorio, anorexia, náusea, dolor en FID, rebote, fiebre, leucocitosis, neutrofilia).',
        interpret: '≤4 improbable · 5–6 posible (observación/imagen) · 7–10 probable (valoración quirúrgica).',
        when: 'Dolor en fosa ilíaca derecha con sospecha de apendicitis.',
        caveat: 'Menos fiable en mujeres (diferencial ginecológico) y niños. La imagen (eco/TC) aclara los casos intermedios.',
        ref: 'Alvarado A. Ann Emerg Med 1986.'
    },
    caprini: {
        what: 'El score de Caprini estima el riesgo de tromboembolia venosa en pacientes quirúrgicos.',
        use: 'Marca los factores presentes; cada uno aporta 1, 2, 3 o 5 puntos según su peso.',
        interpret: '0 muy bajo · 1–2 bajo · 3–4 moderado · ≥5 alto riesgo (profilaxis farmacológica).',
        when: 'Evaluación preoperatoria del riesgo trombótico para indicar profilaxis.',
        caveat: 'Equilibra con el riesgo de sangrado. Para pacientes médicos no quirúrgicos usa Padua.',
        ref: 'Caprini JA. Dis Mon 2005.'
    },
    quemados: {
        what: 'Estima la superficie corporal quemada (SCQ) con la regla de los nueves del adulto.',
        use: 'Marca las regiones afectadas; cada una aporta su porcentaje y se suma el total.',
        interpret: '≥15–20% de SCQ (quemaduras de 2.º–3.er grado) indica reposición de líquidos y, a menudo, ingreso o derivación a unidad de quemados.',
        when: 'Valoración inicial del paciente quemado.',
        caveat: 'La regla de los nueves difiere en niños (cabeza proporcionalmente mayor). Cuenta solo el espesor parcial/total. Para líquidos usa Parkland.',
        ref: 'Wallace AB (regla de los nueves) 1951.'
    },
    abcde: {
        what: 'La regla ABCDE ayuda a identificar lesiones cutáneas sospechosas de melanoma.',
        use: 'Valora Asimetría, Bordes irregulares, Color heterogéneo, Diámetro >6 mm y Evolución/cambio reciente. Marca los presentes.',
        interpret: '≥2 criterios: lesión sospechosa; derivar a dermatología/dermatoscopia.',
        when: 'Cribado de lesiones pigmentadas.',
        caveat: 'No detecta melanomas amelanóticos ni nodulares pequeños (la "E" de evolución es clave). Ante la duda, deriva.',
        ref: 'Friedman RJ et al. CA Cancer J Clin 1985.'
    },
    centor: {
        what: 'Los criterios de Centor/McIsaac estiman la probabilidad de faringitis por estreptococo del grupo A.',
        use: 'Selecciona la edad y marca fiebre, ausencia de tos, adenopatías cervicales y exudado amigdalino.',
        interpret: '≤1 bajo riesgo (sin pruebas/antibiótico) · 2–3 test rápido de estreptococo · ≥4 valorar tratamiento.',
        when: 'Faringoamigdalitis aguda, para decidir prueba y antibiótico.',
        caveat: 'La modificación de McIsaac ajusta por edad. No indicar antibiótico de forma automática (evitar sobreuso).',
        ref: 'Centor RM 1981; McIsaac WJ. CMAJ 1998.'
    },
    epworth: {
        what: 'La escala de Epworth mide la somnolencia diurna excesiva.',
        use: 'El paciente puntúa (0–3) la probabilidad de adormecerse en 8 situaciones cotidianas.',
        interpret: '<10 normal · 10–12 leve · 13–17 moderada · >17 grave.',
        when: 'Cribado de trastornos del sueño (apnea, narcolepsia) y de somnolencia.',
        caveat: 'Es subjetiva y autoinformada. Una puntuación alta justifica estudio del sueño; combínala con STOP-BANG.',
        ref: 'Johns MW. Sleep 1991.'
    },
    satTransf: {
        what: 'La saturación de transferrina estima la proporción de hierro circulante disponible.',
        use: 'Introduce el hierro sérico y la capacidad total de fijación del hierro (TIBC). Saturación = (hierro / TIBC) × 100.',
        interpret: 'Normal ~20–45%. <20% sugiere ferropenia; >45% sugiere sobrecarga de hierro.',
        when: 'Estudio de anemia, sospecha de déficit de hierro o de sobrecarga (hemocromatosis).',
        caveat: 'El hierro sérico varía durante el día y con la inflamación; interprétala junto a la ferritina (que sube como reactante de fase aguda).',
        ref: 'Guías de estudio del metabolismo del hierro.'
    },
    mmse: {
        what: 'El Mini-Mental (MMSE) es una prueba breve de cribado del deterioro cognitivo.',
        use: 'Aplica el test (orientación, memoria, atención y cálculo, lenguaje) e introduce la puntuación total obtenida (0–30).',
        interpret: '≥27 normal · 24–26 deterioro leve · 18–23 moderado · <18 grave.',
        when: 'Cribado de demencia y seguimiento del deterioro cognitivo.',
        caveat: 'Ajusta por escolaridad y edad (falsos positivos con baja escolaridad). No identifica el tipo de demencia y es poco sensible al deterioro leve o frontal.',
        ref: 'Folstein MF et al. J Psychiatr Res 1975.'
    },
};
