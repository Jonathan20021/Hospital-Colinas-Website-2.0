/**
 * Herramientas clínicas — calculadoras por especialidad + certificados.
 *
 * - Catálogo DECLARATIVO (CALC): cada calculadora se define como datos (campos +
 *   fórmula + interpretación) y etiquetada con su(s) especialidad(es). Se renderiza
 *   dinámicamente y se FILTRA por la especialidad del médico que inició sesión
 *   (window.DM_TOOLS.specialty), con opción "ver todas".
 * - Pre-llenado: buscador de pacientes (endpoints existentes) rellena edad/sexo/peso/talla.
 * - Certificados (pestaña): generales para todos.
 * - Todas las calculadoras son APOYO a la decisión; no sustituyen el criterio médico.
 */
(function () {
    'use strict';
    let api = window.doctorApi || null;
    const $ = (s, r) => (r || document).querySelector(s);
    const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const norm = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

    // ── Familias de especialidad ────────────────────────────────────────────────
    const FAMILIES = {
        general:   { label: 'Generales',         kw: [] },
        cardio:    { label: 'Cardiología',       kw: ['cardio'] },
        neumo:     { label: 'Neumología',        kw: ['neumo', 'pulmon'] },
        nefro:     { label: 'Nefrología',        kw: ['nefro'] },
        interna:   { label: 'Medicina interna',  kw: ['interna', 'general medicine', 'intensiv', 'infectolog'] },
        gastro:    { label: 'Gastroenterología', kw: ['gastro', 'endoscop', 'digestiv', 'hepat'] },
        endo:      { label: 'Endocrinología',    kw: ['endocrin'] },
        nutri:     { label: 'Nutrición',         kw: ['nutric'] },
        gineco:    { label: 'Gineco-obstetricia',kw: ['gineco', 'obstet', 'mastolog', 'perinat'] },
        pedia:     { label: 'Pediatría',         kw: ['pediatr', 'neonat'] },
        neuro:     { label: 'Neurología',        kw: ['neuro'] },
        hemato:    { label: 'Hematología',       kw: ['hematolog'] },
        reuma:     { label: 'Reumatología',      kw: ['reumat'] },
        psiq:      { label: 'Psiquiatría',       kw: ['psiquiatr', 'salud mental'] },
        uro:       { label: 'Urología',          kw: ['urolog'] },
        onco:      { label: 'Oncología',         kw: ['oncolog'] },
        geriatria: { label: 'Geriatría',         kw: ['geriatr'] },
        trauma:    { label: 'Traumatología',     kw: ['traumat', 'ortoped'] },
    };
    function doctorFamilies(specialty) {
        const s = norm(specialty);
        const fams = ['general'];
        Object.keys(FAMILIES).forEach((key) => {
            if (key === 'general') return;
            if (FAMILIES[key].kw.some((k) => s.indexOf(norm(k)) !== -1)) fams.push(key);
        });
        return fams;
    }

    // ── Campos reutilizables ─────────────────────────────────────────────────────
    const F = {
        weight: { k: 'weight', label: 'Peso (kg)', type: 'num', fill: 'weight', step: '0.1', min: 0, max: 400 },
        height: { k: 'height', label: 'Talla (cm)', type: 'num', fill: 'height', step: '0.1', min: 0, max: 260 },
        age:    { k: 'age', label: 'Edad (años)', type: 'num', fill: 'age', min: 0, max: 120 },
        sex:    { k: 'sex', label: 'Sexo', type: 'sel', fill: 'sex', opts: [['', '—'], ['M', 'Masculino'], ['F', 'Femenino']] },
        cr:     { k: 'cr', label: 'Creatinina (mg/dL)', type: 'num', step: '0.01', min: 0.1, max: 25 },
    };
    const sel03 = (k, label) => ({ k: k, label: label, type: 'sel', sum: true, opts: [['0', '0'], ['1', '1'], ['2', '2'], ['3', '3']] });

    // ── Catálogo de calculadoras ────────────────────────────────────────────────
    const CALC = [
        // ===== GENERALES =====
        { id: 'imc', spec: ['general'], name: 'Índice de masa corporal', tag: 'Antropometría · OMS', icon: 'scale',
          fields: [F.weight, F.height], note: 'Clasificación OMS para adultos.',
          calc: (v) => { const w = v.n('weight'), h = v.n('height'); if (!w || !h) return null; const b = w / ((h / 100) ** 2); let t, l; if (b < 18.5) { t = 'Bajo peso'; l = 'warn'; } else if (b < 25) { t = 'Peso normal'; l = 'ok'; } else if (b < 30) { t = 'Sobrepeso'; l = 'warn'; } else if (b < 35) { t = 'Obesidad grado I'; l = 'danger'; } else if (b < 40) { t = 'Obesidad grado II'; l = 'danger'; } else { t = 'Obesidad grado III'; l = 'danger'; } return { val: b.toFixed(1), unit: 'kg/m²', tag: t, level: l }; } },
        { id: 'bsa', spec: ['general', 'onco'], name: 'Superficie corporal', tag: 'Antropometría · Mosteller', icon: 'ruler',
          fields: [F.weight, F.height], note: 'Útil para dosificación (quimioterapia, etc.).',
          calc: (v) => { const w = v.n('weight'), h = v.n('height'); if (!w || !h) return null; return { val: Math.sqrt(h * w / 3600).toFixed(2), unit: 'm²', tag: 'Fórmula de Mosteller', level: 'ok' }; } },
        { id: 'pesoideal', spec: ['general'], name: 'Peso ideal', tag: 'Antropometría · Devine', icon: 'dumbbell',
          fields: [F.height, F.sex], note: 'Fórmula de Devine (peso ideal corporal).',
          calc: (v) => { const h = v.n('height'), sx = v.s('sex'); if (!h || !sx) return null; const inch = h / 2.54; const base = sx === 'F' ? 45.5 : 50; const pi = base + 2.3 * Math.max(0, inch - 60); return { val: pi.toFixed(1), unit: 'kg', tag: 'Peso ideal estimado', level: 'ok' }; } },

        // ===== CARDIOLOGÍA =====
        { id: 'chads', spec: ['cardio', 'interna'], name: 'CHA₂DS₂-VASc', tag: 'Cardiología · riesgo de ACV en FA', icon: 'heart-pulse', wide: true,
          fields: [F.age, F.sex],
          checks: [['Insuficiencia cardíaca / disfunción del VI', 1], ['Hipertensión arterial', 1], ['Diabetes mellitus', 1], ['ACV / AIT / tromboembolia previa', 2], ['Enfermedad vascular (IAM, EAP, placa aórtica)', 1]],
          note: 'Edad ≥75 = 2; 65-74 = 1; sexo femenino = 1.',
          calc: (v) => { const age = v.n('age'), sx = v.s('sex'); let sc = v.checks; if (age >= 75) sc += 2; else if (age >= 65) sc += 1; if (sx === 'F') sc += 1; const eff = sx === 'F' ? sc - 1 : sc; let t, l; if (eff <= 0) { t = 'Riesgo bajo — antiagregación/observación'; l = 'ok'; } else if (eff === 1) { t = 'Riesgo intermedio — considerar anticoagular'; l = 'warn'; } else { t = 'Riesgo alto — anticoagulación recomendada'; l = 'danger'; } return { val: sc, unit: '/ 9 pts', tag: t, level: l }; } },
        { id: 'hasbled', spec: ['cardio', 'interna'], name: 'HAS-BLED', tag: 'Cardiología · riesgo de sangrado', icon: 'droplet', wide: true,
          checks: [['Hipertensión (TAS >160)', 1], ['Función renal alterada', 1], ['Función hepática alterada', 1], ['ACV previo', 1], ['Sangrado previo o predisposición', 1], ['INR lábil', 1], ['Edad >65 años', 1], ['Fármacos (AINE/antiagregantes)', 1], ['Alcohol (≥8 U/semana)', 1]],
          note: '≥3 puntos: alto riesgo de sangrado; vigilar.',
          calc: (v) => { const sc = v.checks; let t, l; if (sc <= 2) { t = 'Riesgo bajo-moderado'; l = sc === 0 ? 'ok' : 'warn'; } else { t = 'Alto riesgo de sangrado — precaución'; l = 'danger'; } return { val: sc, unit: '/ 9 pts', tag: t, level: l }; } },
        { id: 'qtc', spec: ['cardio'], name: 'QTc (Bazett)', tag: 'Cardiología · intervalo QT corregido', icon: 'activity',
          fields: [{ k: 'qt', label: 'QT (ms)', type: 'num', min: 200, max: 700 }, { k: 'fc', label: 'Frecuencia cardíaca (lpm)', type: 'num', min: 20, max: 250 }, F.sex],
          note: 'Bazett: QTc = QT / √(RR). Prolongado: H >450, M >470 ms.',
          calc: (v) => { const qt = v.n('qt'), fc = v.n('fc'), sx = v.s('sex'); if (!qt || !fc) return null; const rr = 60 / fc; const qtc = qt / Math.sqrt(rr); const lim = sx === 'F' ? 470 : 450; let l = qtc > 500 ? 'danger' : qtc > lim ? 'warn' : 'ok'; const t = qtc > lim ? 'QTc prolongado' : 'QTc normal'; return { val: Math.round(qtc), unit: 'ms', tag: t, level: l }; } },

        // ===== NEUMOLOGÍA =====
        { id: 'curb', spec: ['neumo', 'interna', 'infectolog'], name: 'CURB-65', tag: 'Neumología · severidad de neumonía', icon: 'wind',
          fields: [F.age],
          checks: [['Confusión (desorientación nueva)', 1], ['Urea >7 mmol/L (BUN >19 mg/dL)', 1], ['Frecuencia respiratoria ≥30/min', 1], ['TA sistólica <90 o diastólica ≤60', 1]],
          note: '0-1: ambulatorio · 2: observación · 3-5: ingreso/UCI.',
          calc: (v) => { let sc = v.checks; if (v.n('age') >= 65) sc += 1; let t, l; if (sc <= 1) { t = 'Bajo riesgo — manejo ambulatorio'; l = 'ok'; } else if (sc === 2) { t = 'Intermedio — observación/ingreso'; l = 'warn'; } else { t = 'Alto riesgo — ingreso, valorar UCI'; l = 'danger'; } return { val: sc, unit: '/ 5 pts', tag: t, level: l }; } },
        { id: 'wellstep', spec: ['neumo', 'interna'], name: 'Wells — TEP', tag: 'Neumología · probabilidad de embolia', icon: 'activity', wide: true,
          checks: [['Signos clínicos de TVP', 3], ['TEP tan o más probable que dx alterno', 3], ['Frecuencia cardíaca >100 lpm', 1.5], ['Inmovilización ≥3 días o cirugía <4 sem', 1.5], ['TVP/TEP previo', 1.5], ['Hemoptisis', 1], ['Cáncer activo', 1]],
          note: '≤4: TEP improbable (dímero-D). >4: probable (angio-TC).',
          calc: (v) => { const sc = v.checks; let t, l; if (sc > 4) { t = 'TEP probable — angio-TC'; l = 'danger'; } else { t = 'TEP improbable — dímero-D'; l = 'ok'; } return { val: sc, unit: 'pts', tag: t, level: l }; } },

        // ===== NEFROLOGÍA =====
        { id: 'cg', spec: ['nefro', 'general'], name: 'Aclaramiento de creatinina', tag: 'Nefrología · Cockcroft-Gault', icon: 'droplets',
          fields: [F.age, F.sex, F.weight, F.cr], note: 'Usa peso real; valida en obesidad/edema.',
          calc: (v) => { const age = v.n('age'), w = v.n('weight'), cr = v.n('cr'), sx = v.s('sex'); if (!age || !w || !cr || !sx) return null; let cl = ((140 - age) * w) / (72 * cr); if (sx === 'F') cl *= 0.85; cl = Math.max(0, cl); let t, l; if (cl >= 90) { t = 'Normal (G1)'; l = 'ok'; } else if (cl >= 60) { t = 'Descenso leve (G2)'; l = 'ok'; } else if (cl >= 45) { t = 'Leve-moderado (G3a)'; l = 'warn'; } else if (cl >= 30) { t = 'Moderado-severo (G3b)'; l = 'warn'; } else if (cl >= 15) { t = 'Severo (G4)'; l = 'danger'; } else { t = 'Falla renal (G5)'; l = 'danger'; } return { val: Math.round(cl), unit: 'mL/min', tag: t, level: l }; } },
        { id: 'ckdepi', spec: ['nefro'], name: 'TFG (CKD-EPI 2021)', tag: 'Nefrología · filtrado glomerular', icon: 'filter',
          fields: [F.age, F.sex, F.cr], note: 'CKD-EPI 2021 (sin coeficiente de raza).',
          calc: (v) => { const age = v.n('age'), cr = v.n('cr'), sx = v.s('sex'); if (!age || !cr || !sx) return null; const fem = sx === 'F'; const k = fem ? 0.7 : 0.9; const a = fem ? -0.241 : -0.302; const min = Math.min(cr / k, 1), max = Math.max(cr / k, 1); let e = 142 * Math.pow(min, a) * Math.pow(max, -1.200) * Math.pow(0.9938, age); if (fem) e *= 1.012; let t, l; if (e >= 90) { t = 'G1 (normal)'; l = 'ok'; } else if (e >= 60) { t = 'G2 (leve)'; l = 'ok'; } else if (e >= 45) { t = 'G3a'; l = 'warn'; } else if (e >= 30) { t = 'G3b'; l = 'warn'; } else if (e >= 15) { t = 'G4 (severo)'; l = 'danger'; } else { t = 'G5 (falla renal)'; l = 'danger'; } return { val: Math.round(e), unit: 'mL/min/1.73m²', tag: t, level: l }; } },
        { id: 'fena', spec: ['nefro', 'interna'], name: 'Fracción excreción de Na (FeNa)', tag: 'Nefrología · injuria renal', icon: 'percent',
          fields: [{ k: 'nao', label: 'Na orina (mEq/L)', type: 'num', min: 1 }, { k: 'nap', label: 'Na plasma (mEq/L)', type: 'num', min: 100 }, { k: 'cro', label: 'Creatinina orina (mg/dL)', type: 'num', min: 1 }, { k: 'crp', label: 'Creatinina plasma (mg/dL)', type: 'num', min: 0.1 }],
          note: '<1%: prerrenal · >2%: renal (NTA).',
          calc: (v) => { const nao = v.n('nao'), nap = v.n('nap'), cro = v.n('cro'), crp = v.n('crp'); if (!nao || !nap || !cro || !crp) return null; const fe = (nao * crp) / (nap * cro) * 100; let t, l; if (fe < 1) { t = 'Prerrenal'; l = 'warn'; } else if (fe > 2) { t = 'Renal (NTA)'; l = 'danger'; } else { t = 'Indeterminado'; l = 'warn'; } return { val: fe.toFixed(2), unit: '%', tag: t, level: l }; } },
        { id: 'aniongap', spec: ['nefro', 'interna'], name: 'Anión gap', tag: 'Nefrología · equilibrio ácido-base', icon: 'sigma',
          fields: [{ k: 'na', label: 'Na (mEq/L)', type: 'num', min: 100, max: 180 }, { k: 'cl', label: 'Cl (mEq/L)', type: 'num', min: 70, max: 130 }, { k: 'hco3', label: 'HCO₃ (mEq/L)', type: 'num', min: 2, max: 50 }, { k: 'alb', label: 'Albúmina (g/dL, opc.)', type: 'num', min: 0, max: 7, step: '0.1' }],
          note: 'Normal 8-12. Corrige +2.5 por cada 1 g/dL de albúmina <4.',
          calc: (v) => { const na = v.n('na'), cl = v.n('cl'), hco3 = v.n('hco3'), alb = v.n('alb'); if (!na || !cl || !hco3) return null; let ag = na - (cl + hco3); if (alb) ag += 2.5 * (4 - alb); let l = ag > 12 ? 'danger' : ag < 8 ? 'warn' : 'ok'; const t = ag > 12 ? 'Anión gap elevado' : ag < 8 ? 'Anión gap bajo' : 'Normal'; return { val: ag.toFixed(1), unit: 'mEq/L', tag: t, level: l }; } },

        // ===== MEDICINA INTERNA =====
        { id: 'wells', spec: ['interna', 'trauma', 'neumo'], name: 'Wells — TVP', tag: 'Medicina interna · trombosis venosa', icon: 'activity', wide: true,
          checks: [['Cáncer activo (≤6 meses o paliativo)', 1], ['Parálisis/paresia o inmovilización de MII', 1], ['Encamado >3 días o cirugía mayor <12 sem', 1], ['Dolor en trayecto venoso profundo', 1], ['Edema de toda la pierna', 1], ['Edema de pantorrilla >3 cm', 1], ['Edema con fóvea (pierna sintomática)', 1], ['Venas superficiales colaterales', 1], ['TVP previa documentada', 1], ['Dx alternativo tan o más probable', -2]],
          note: '≥2: TVP probable. <2: improbable (dímero-D).',
          calc: (v) => { const sc = v.checks; const t = sc >= 2 ? 'TVP probable — eco-Doppler' : 'TVP improbable — dímero-D'; return { val: sc, unit: 'pts', tag: t, level: sc >= 2 ? 'danger' : 'ok' }; } },
        { id: 'qsofa', spec: ['interna', 'infectolog'], name: 'qSOFA', tag: 'Medicina interna · riesgo en sepsis', icon: 'siren',
          checks: [['Frecuencia respiratoria ≥22/min', 1], ['Alteración del estado mental (Glasgow <15)', 1], ['TA sistólica ≤100 mmHg', 1]],
          note: '≥2: alto riesgo; valorar sepsis y nivel de cuidado.',
          calc: (v) => { const sc = v.checks; const t = sc >= 2 ? 'Alto riesgo — valorar sepsis' : 'Bajo riesgo'; return { val: sc, unit: '/ 3 pts', tag: t, level: sc >= 2 ? 'danger' : 'ok' }; } },
        { id: 'glasgow', spec: ['interna', 'neuro'], name: 'Escala de Glasgow (GCS)', tag: 'Neurología · nivel de conciencia', icon: 'brain',
          fields: [
            { k: 'o', label: 'Apertura ocular', type: 'sel', sum: true, opts: [['4', '4 — espontánea'], ['3', '3 — a la voz'], ['2', '2 — al dolor'], ['1', '1 — ninguna']] },
            { k: 've', label: 'Respuesta verbal', type: 'sel', sum: true, opts: [['5', '5 — orientado'], ['4', '4 — confuso'], ['3', '3 — palabras'], ['2', '2 — sonidos'], ['1', '1 — ninguna']] },
            { k: 'm', label: 'Respuesta motora', type: 'sel', sum: true, opts: [['6', '6 — obedece'], ['5', '5 — localiza'], ['4', '4 — retira'], ['3', '3 — flexión'], ['2', '2 — extensión'], ['1', '1 — ninguna']] },
          ], note: '13-15 leve · 9-12 moderado · ≤8 grave (valorar vía aérea).',
          calc: (v) => { if (!v.s('o') || !v.s('ve') || !v.s('m')) return null; const sc = v.sum; let t, l; if (sc >= 13) { t = 'TCE leve'; l = 'ok'; } else if (sc >= 9) { t = 'TCE moderado'; l = 'warn'; } else { t = 'TCE grave — proteger vía aérea'; l = 'danger'; } return { val: sc, unit: '/ 15', tag: t, level: l }; } },

        // ===== GASTROENTEROLOGÍA =====
        { id: 'childpugh', spec: ['gastro'], name: 'Child-Pugh', tag: 'Gastro · función hepática', icon: 'stethoscope', wide: true,
          fields: [
            { k: 'bili', label: 'Bilirrubina', type: 'sel', sum: true, opts: [['1', '<2 mg/dL'], ['2', '2-3 mg/dL'], ['3', '>3 mg/dL']] },
            { k: 'alb', label: 'Albúmina', type: 'sel', sum: true, opts: [['1', '>3.5 g/dL'], ['2', '2.8-3.5 g/dL'], ['3', '<2.8 g/dL']] },
            { k: 'inr', label: 'INR', type: 'sel', sum: true, opts: [['1', '<1.7'], ['2', '1.7-2.3'], ['3', '>2.3']] },
            { k: 'asc', label: 'Ascitis', type: 'sel', sum: true, opts: [['1', 'ausente'], ['2', 'leve'], ['3', 'moderada']] },
            { k: 'enc', label: 'Encefalopatía', type: 'sel', sum: true, opts: [['1', 'ausente'], ['2', 'grado I-II'], ['3', 'grado III-IV']] },
          ], note: 'A: 5-6 · B: 7-9 · C: 10-15.',
          calc: (v) => { if (!v.s('bili') || !v.s('alb') || !v.s('inr') || !v.s('asc') || !v.s('enc')) return null; const sc = v.sum; let t, l; if (sc <= 6) { t = 'Clase A (compensada)'; l = 'ok'; } else if (sc <= 9) { t = 'Clase B'; l = 'warn'; } else { t = 'Clase C (descompensada)'; l = 'danger'; } return { val: sc, unit: 'pts', tag: t, level: l }; } },
        { id: 'meld', spec: ['gastro'], name: 'MELD', tag: 'Gastro · pronóstico hepático', icon: 'gauge',
          fields: [{ k: 'bili', label: 'Bilirrubina (mg/dL)', type: 'num', min: 0.1, step: '0.1' }, { k: 'inr', label: 'INR', type: 'num', min: 0.5, step: '0.1' }, { k: 'cr', label: 'Creatinina (mg/dL)', type: 'num', min: 0.1, step: '0.1' }],
          note: 'MELD (límites a 1.0). Mayor = mayor mortalidad a 90 días.',
          calc: (v) => { let bili = v.n('bili'), inr = v.n('inr'), cr = v.n('cr'); if (!bili || !inr || !cr) return null; bili = Math.max(1, bili); inr = Math.max(1, inr); cr = Math.min(4, Math.max(1, cr)); let m = 3.78 * Math.log(bili) + 11.2 * Math.log(inr) + 9.57 * Math.log(cr) + 6.43; m = Math.round(m); let l = m >= 30 ? 'danger' : m >= 15 ? 'warn' : 'ok'; return { val: m, unit: 'pts', tag: m >= 15 ? 'Considerar referir a trasplante' : 'Riesgo bajo', level: l }; } },

        // ===== ENDOCRINOLOGÍA / NUTRICIÓN =====
        { id: 'hba1c', spec: ['endo', 'interna'], name: 'HbA1c → glucosa media', tag: 'Endocrino · control glucémico', icon: 'candy',
          fields: [{ k: 'a1c', label: 'HbA1c (%)', type: 'num', min: 3, max: 20, step: '0.1' }], note: 'Glucosa media estimada (ADAG): 28.7·A1c − 46.7.',
          calc: (v) => { const a = v.n('a1c'); if (!a) return null; const g = 28.7 * a - 46.7; let l = a < 7 ? 'ok' : a < 8 ? 'warn' : 'danger'; return { val: Math.round(g), unit: 'mg/dL', tag: a < 7 ? 'Meta habitual alcanzada' : 'Por encima de meta', level: l }; } },
        { id: 'corrna', spec: ['endo', 'nefro', 'interna'], name: 'Na corregido por glucosa', tag: 'Endocrino · hiponatremia', icon: 'flask-conical',
          fields: [{ k: 'na', label: 'Na medido (mEq/L)', type: 'num', min: 100, max: 180 }, { k: 'glu', label: 'Glucosa (mg/dL)', type: 'num', min: 50 }], note: 'Corrige +1.6 mEq/L por cada 100 mg/dL de glucosa >100.',
          calc: (v) => { const na = v.n('na'), glu = v.n('glu'); if (!na || !glu) return null; const c = na + 1.6 * ((glu - 100) / 100); return { val: c.toFixed(1), unit: 'mEq/L', tag: 'Na corregido', level: c < 135 ? 'warn' : 'ok' }; } },
        { id: 'harris', spec: ['nutri', 'endo'], name: 'Requerimiento calórico', tag: 'Nutrición · Harris-Benedict', icon: 'utensils',
          fields: [F.weight, F.height, F.age, F.sex, { k: 'act', label: 'Actividad', type: 'sel', opts: [['1.2', 'Sedentario'], ['1.375', 'Ligera'], ['1.55', 'Moderada'], ['1.725', 'Intensa']] }],
          note: 'TMB (Harris-Benedict revisada) × factor de actividad.',
          calc: (v) => { const w = v.n('weight'), h = v.n('height'), age = v.n('age'), sx = v.s('sex'); if (!w || !h || !age || !sx) return null; const tmb = sx === 'F' ? (447.6 + 9.25 * w + 3.1 * h - 4.33 * age) : (88.36 + 13.4 * w + 4.8 * h - 5.7 * age); const act = parseFloat(v.s('act')) || 1.2; return { val: Math.round(tmb * act), unit: 'kcal/día', tag: 'Gasto energético estimado', level: 'ok' }; } },

        // ===== GINECO-OBSTETRICIA =====
        { id: 'ga', spec: ['gineco'], name: 'Gestación: FPP y edad', tag: 'Obstetricia · Naegele', icon: 'baby',
          fields: [{ k: 'lmp', label: 'Fecha de última menstruación (FUM)', type: 'date' }], note: 'Asume ciclos regulares de 28 días.',
          calc: (v) => { const lmp = v.s('lmp'); if (!lmp) return null; const d = new Date(lmp + 'T00:00:00'); if (isNaN(d.getTime())) return null; const fpp = new Date(d); fpp.setDate(fpp.getDate() + 7); fpp.setMonth(fpp.getMonth() - 3); fpp.setFullYear(fpp.getFullYear() + 1); const M = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic']; const fppS = 'FPP: ' + fpp.getDate() + ' ' + M[fpp.getMonth()] + ' ' + fpp.getFullYear(); const today = new Date(); today.setHours(0, 0, 0, 0); const days = Math.floor((today - d) / 86400000); if (days < 0) return { val: '—', unit: '', tag: 'FUM futura · ' + fppS, level: 'muted' }; const wk = Math.floor(days / 7), dd = days % 7; let l = wk > 42 ? 'danger' : wk >= 41 ? 'warn' : 'ok'; return { val: wk + 's ' + dd + 'd', unit: '', tag: fppS, level: l }; } },
        { id: 'bishop', spec: ['gineco'], name: 'Índice de Bishop', tag: 'Obstetricia · inducción del parto', icon: 'clipboard-list', wide: true,
          fields: [
            { k: 'dil', label: 'Dilatación', type: 'sel', sum: true, opts: [['0', '0 cm'], ['1', '1-2 cm'], ['2', '3-4 cm'], ['3', '≥5 cm']] },
            { k: 'bor', label: 'Borramiento', type: 'sel', sum: true, opts: [['0', '0-30%'], ['1', '40-50%'], ['2', '60-70%'], ['3', '≥80%']] },
            { k: 'est', label: 'Estación', type: 'sel', sum: true, opts: [['0', '-3'], ['1', '-2'], ['2', '-1/0'], ['3', '+1/+2']] },
            { k: 'con', label: 'Consistencia', type: 'sel', sum: true, opts: [['0', 'firme'], ['1', 'media'], ['2', 'blanda']] },
            { k: 'pos', label: 'Posición', type: 'sel', sum: true, opts: [['0', 'posterior'], ['1', 'media'], ['2', 'anterior']] },
          ], note: '≥8: cuello favorable. ≤6: desfavorable (madurar).',
          calc: (v) => { if (!v.s('dil') || !v.s('bor') || !v.s('est') || !v.s('con') || !v.s('pos')) return null; const sc = v.sum; let t, l; if (sc >= 8) { t = 'Cuello favorable'; l = 'ok'; } else if (sc >= 7) { t = 'Intermedio'; l = 'warn'; } else { t = 'Desfavorable — considerar maduración'; l = 'warn'; } return { val: sc, unit: '/ 13', tag: t, level: l }; } },

        // ===== PEDIATRÍA =====
        { id: 'dosispeso', spec: ['pedia'], name: 'Dosis por peso', tag: 'Pediatría · dosificación', icon: 'pill',
          fields: [F.weight, { k: 'mgkg', label: 'Dosis (mg/kg)', type: 'num', min: 0, step: '0.1' }, { k: 'freq', label: 'Tomas/día', type: 'num', min: 1, max: 6 }],
          note: 'Verifica la dosis máxima del fármaco.',
          calc: (v) => { const w = v.n('weight'), d = v.n('mgkg'); if (!w || !d) return null; const total = w * d; const freq = v.n('freq'); const porToma = freq ? (total / freq) : null; return { val: total.toFixed(1), unit: 'mg/día', tag: porToma ? ('≈ ' + porToma.toFixed(1) + ' mg por toma') : 'Dosis diaria total', level: 'ok' }; } },
        { id: 'holliday', spec: ['pedia'], name: 'Líquidos de mantenimiento', tag: 'Pediatría · Holliday-Segar', icon: 'droplets',
          fields: [F.weight], note: '100/50/20 mL/kg/día (10+10+resto kg).',
          calc: (v) => { const w = v.n('weight'); if (!w) return null; let ml = 0; ml += Math.min(w, 10) * 100; if (w > 10) ml += Math.min(w - 10, 10) * 50; if (w > 20) ml += (w - 20) * 20; return { val: Math.round(ml), unit: 'mL/día', tag: '≈ ' + Math.round(ml / 24) + ' mL/h', level: 'ok' }; } },
        { id: 'apgar', spec: ['pedia'], name: 'Test de APGAR', tag: 'Neonatología · vitalidad', icon: 'baby', wide: true,
          fields: [
            { k: 'a', label: 'Apariencia (color)', type: 'sel', sum: true, opts: [['2', '2 — sonrosado'], ['1', '1 — acrocianosis'], ['0', '0 — pálido/cianótico']] },
            { k: 'p', label: 'Pulso (FC)', type: 'sel', sum: true, opts: [['2', '2 — >100'], ['1', '1 — <100'], ['0', '0 — ausente']] },
            { k: 'g', label: 'Gesto (reflejos)', type: 'sel', sum: true, opts: [['2', '2 — llanto'], ['1', '1 — mueca'], ['0', '0 — ninguno']] },
            { k: 'ac', label: 'Actividad (tono)', type: 'sel', sum: true, opts: [['2', '2 — activo'], ['1', '1 — flexión'], ['0', '0 — flácido']] },
            { k: 'r', label: 'Respiración', type: 'sel', sum: true, opts: [['2', '2 — llanto vigoroso'], ['1', '1 — irregular'], ['0', '0 — ausente']] },
          ], note: '7-10 normal · 4-6 depresión moderada · 0-3 grave.',
          calc: (v) => { if (!v.s('a') || !v.s('p') || !v.s('g') || !v.s('ac') || !v.s('r')) return null; const sc = v.sum; let t, l; if (sc >= 7) { t = 'Normal'; l = 'ok'; } else if (sc >= 4) { t = 'Depresión moderada'; l = 'warn'; } else { t = 'Depresión grave — reanimación'; l = 'danger'; } return { val: sc, unit: '/ 10', tag: t, level: l }; } },

        // ===== NEUROLOGÍA =====
        { id: 'abcd2', spec: ['neuro', 'interna'], name: 'ABCD²', tag: 'Neurología · riesgo de ACV tras AIT', icon: 'brain',
          fields: [
            { k: 'edad', label: 'Edad ≥60', type: 'sel', sum: true, opts: [['0', 'No'], ['1', 'Sí']] },
            { k: 'ta', label: 'TA ≥140/90', type: 'sel', sum: true, opts: [['0', 'No'], ['1', 'Sí']] },
            { k: 'cl', label: 'Clínica', type: 'sel', sum: true, opts: [['2', 'Debilidad unilateral'], ['1', 'Trastorno del habla sin debilidad'], ['0', 'Otro']] },
            { k: 'dur', label: 'Duración', type: 'sel', sum: true, opts: [['2', '≥60 min'], ['1', '10-59 min'], ['0', '<10 min']] },
            { k: 'dm', label: 'Diabetes', type: 'sel', sum: true, opts: [['0', 'No'], ['1', 'Sí']] },
          ], note: '0-3 bajo · 4-5 moderado · 6-7 alto riesgo a 2 días.',
          calc: (v) => { if (!v.s('cl') || !v.s('dur')) return null; const sc = v.sum; let t, l; if (sc <= 3) { t = 'Riesgo bajo'; l = 'ok'; } else if (sc <= 5) { t = 'Riesgo moderado'; l = 'warn'; } else { t = 'Riesgo alto — ingreso'; l = 'danger'; } return { val: sc, unit: '/ 7', tag: t, level: l }; } },

        // ===== HEMATOLOGÍA =====
        { id: 'anc', spec: ['hemato', 'onco'], name: 'Neutrófilos absolutos (ANC)', tag: 'Hematología · neutropenia', icon: 'shield',
          fields: [{ k: 'wbc', label: 'Leucocitos (×10³/µL)', type: 'num', min: 0, step: '0.1' }, { k: 'seg', label: 'Segmentados (%)', type: 'num', min: 0, max: 100 }, { k: 'band', label: 'Bandas (%)', type: 'num', min: 0, max: 100 }],
          note: '<500: neutropenia grave (riesgo de infección).',
          calc: (v) => { const wbc = v.n('wbc'), seg = v.n('seg'), band = v.n('band') || 0; if (!wbc || isNaN(seg)) return null; const anc = wbc * 1000 * (seg + band) / 100; let t, l; if (anc >= 1500) { t = 'Normal'; l = 'ok'; } else if (anc >= 1000) { t = 'Neutropenia leve'; l = 'warn'; } else if (anc >= 500) { t = 'Neutropenia moderada'; l = 'warn'; } else { t = 'Neutropenia grave'; l = 'danger'; } return { val: Math.round(anc), unit: '/µL', tag: t, level: l }; } },

        // ===== REUMATOLOGÍA =====
        { id: 'das28', spec: ['reuma'], name: 'DAS28-VSG', tag: 'Reumatología · actividad de AR', icon: 'bone',
          fields: [{ k: 'tjc', label: 'Articulaciones dolorosas (0-28)', type: 'num', min: 0, max: 28 }, { k: 'sjc', label: 'Articulaciones inflamadas (0-28)', type: 'num', min: 0, max: 28 }, { k: 'vsg', label: 'VSG (mm/h)', type: 'num', min: 1, max: 150 }, { k: 'gh', label: 'EVA global paciente (0-100)', type: 'num', min: 0, max: 100 }],
          note: '<2.6 remisión · ≤3.2 baja · ≤5.1 moderada · >5.1 alta.',
          calc: (v) => { const tjc = v.n('tjc'), sjc = v.n('sjc'), vsg = v.n('vsg'), gh = v.n('gh'); if (isNaN(tjc) || isNaN(sjc) || !vsg || isNaN(gh)) return null; const d = 0.56 * Math.sqrt(tjc) + 0.28 * Math.sqrt(sjc) + 0.70 * Math.log(vsg) + 0.014 * gh; let t, l; if (d < 2.6) { t = 'Remisión'; l = 'ok'; } else if (d <= 3.2) { t = 'Actividad baja'; l = 'ok'; } else if (d <= 5.1) { t = 'Actividad moderada'; l = 'warn'; } else { t = 'Actividad alta'; l = 'danger'; } return { val: d.toFixed(2), unit: '', tag: t, level: l }; } },

        // ===== PSIQUIATRÍA =====
        { id: 'phq9', spec: ['psiq'], name: 'PHQ-9 (depresión)', tag: 'Psiquiatría · tamizaje de depresión', icon: 'brain', wide: true,
          fields: ['Poco interés o placer', 'Decaído/deprimido', 'Sueño alterado', 'Cansancio/poca energía', 'Apetito alterado', 'Se siente mal consigo mismo', 'Dificultad para concentrarse', 'Lentitud o inquietud', 'Pensamientos de muerte'].map((t, i) => sel03('q' + i, t)),
          note: '0-4 mínima · 5-9 leve · 10-14 moderada · 15-19 mod-grave · 20-27 grave.',
          calc: (v) => { if (!v.anyFilled) return null; const sc = v.sum; let t, l; if (sc <= 4) { t = 'Mínima'; l = 'ok'; } else if (sc <= 9) { t = 'Leve'; l = 'ok'; } else if (sc <= 14) { t = 'Moderada'; l = 'warn'; } else if (sc <= 19) { t = 'Moderada-grave'; l = 'danger'; } else { t = 'Grave'; l = 'danger'; } return { val: sc, unit: '/ 27', tag: t + (sc >= 10 ? ' — valorar tratamiento' : ''), level: l }; } },
        { id: 'gad7', spec: ['psiq'], name: 'GAD-7 (ansiedad)', tag: 'Psiquiatría · tamizaje de ansiedad', icon: 'brain', wide: true,
          fields: ['Nervioso/ansioso', 'No poder parar de preocuparse', 'Preocuparse por muchas cosas', 'Dificultad para relajarse', 'Inquietud', 'Irritabilidad', 'Miedo a que algo terrible pase'].map((t, i) => sel03('q' + i, t)),
          note: '0-4 mínima · 5-9 leve · 10-14 moderada · 15-21 grave.',
          calc: (v) => { if (!v.anyFilled) return null; const sc = v.sum; let t, l; if (sc <= 4) { t = 'Mínima'; l = 'ok'; } else if (sc <= 9) { t = 'Leve'; l = 'ok'; } else if (sc <= 14) { t = 'Moderada'; l = 'warn'; } else { t = 'Grave'; l = 'danger'; } return { val: sc, unit: '/ 21', tag: t, level: l }; } },

        // ===== UROLOGÍA =====
        { id: 'ipss', spec: ['uro'], name: 'IPSS (próstata)', tag: 'Urología · síntomas prostáticos', icon: 'clipboard-list', wide: true,
          fields: ['Vaciado incompleto', 'Frecuencia', 'Intermitencia', 'Urgencia', 'Chorro débil', 'Esfuerzo al iniciar', 'Nicturia'].map((t, i) => ({ k: 'q' + i, label: t, type: 'sel', sum: true, opts: [['0', '0'], ['1', '1'], ['2', '2'], ['3', '3'], ['4', '4'], ['5', '5']] })),
          note: '0-7 leve · 8-19 moderado · 20-35 grave.',
          calc: (v) => { if (!v.anyFilled) return null; const sc = v.sum; let t, l; if (sc <= 7) { t = 'Sintomatología leve'; l = 'ok'; } else if (sc <= 19) { t = 'Moderada'; l = 'warn'; } else { t = 'Grave'; l = 'danger'; } return { val: sc, unit: '/ 35', tag: t, level: l }; } },

        // ===== ONCOLOGÍA =====
        { id: 'calvert', spec: ['onco'], name: 'Carboplatino (Calvert)', tag: 'Oncología · dosis por AUC', icon: 'pill',
          fields: [{ k: 'auc', label: 'AUC objetivo', type: 'num', min: 1, max: 8, step: '0.5' }, { k: 'tfg', label: 'TFG / aclaramiento (mL/min)', type: 'num', min: 5, max: 200 }],
          note: 'Dosis (mg) = AUC × (TFG + 25). Limita TFG a 125 mL/min.',
          calc: (v) => { const auc = v.n('auc'), tfg = v.n('tfg'); if (!auc || !tfg) return null; const dose = auc * (Math.min(tfg, 125) + 25); return { val: Math.round(dose), unit: 'mg', tag: 'Dosis total de carboplatino', level: 'ok' }; } },

        // ===== GERIATRÍA =====
        { id: 'barthel', spec: ['geriatria', 'interna'], name: 'Índice de Barthel', tag: 'Geriatría · independencia funcional', icon: 'accessibility', wide: true,
          fields: [
            { k: 'com', label: 'Comer', type: 'sel', sum: true, opts: [['10', 'Independiente'], ['5', 'Ayuda'], ['0', 'Dependiente']] },
            { k: 'bano', label: 'Bañarse', type: 'sel', sum: true, opts: [['5', 'Independiente'], ['0', 'Dependiente']] },
            { k: 'vest', label: 'Vestirse', type: 'sel', sum: true, opts: [['10', 'Independiente'], ['5', 'Ayuda'], ['0', 'Dependiente']] },
            { k: 'aseo', label: 'Aseo personal', type: 'sel', sum: true, opts: [['5', 'Independiente'], ['0', 'Dependiente']] },
            { k: 'dep', label: 'Deposición', type: 'sel', sum: true, opts: [['10', 'Continente'], ['5', 'Ocasional'], ['0', 'Incontinente']] },
            { k: 'mic', label: 'Micción', type: 'sel', sum: true, opts: [['10', 'Continente'], ['5', 'Ocasional'], ['0', 'Incontinente']] },
            { k: 'wc', label: 'Usar WC', type: 'sel', sum: true, opts: [['10', 'Independiente'], ['5', 'Ayuda'], ['0', 'Dependiente']] },
            { k: 'trans', label: 'Traslado cama/silla', type: 'sel', sum: true, opts: [['15', 'Independiente'], ['10', 'Mínima ayuda'], ['5', 'Gran ayuda'], ['0', 'Dependiente']] },
            { k: 'deamb', label: 'Deambulación', type: 'sel', sum: true, opts: [['15', 'Independiente'], ['10', 'Ayuda'], ['5', 'Silla de ruedas'], ['0', 'Inmóvil']] },
            { k: 'esc', label: 'Escaleras', type: 'sel', sum: true, opts: [['10', 'Independiente'], ['5', 'Ayuda'], ['0', 'Dependiente']] },
          ], note: '100 independiente · 60-95 leve · 40-55 moderada · 20-35 grave · <20 total.',
          calc: (v) => { if (!v.anyFilled) return null; const sc = v.sum; let t, l; if (sc >= 100) { t = 'Independiente'; l = 'ok'; } else if (sc >= 60) { t = 'Dependencia leve'; l = 'ok'; } else if (sc >= 40) { t = 'Dependencia moderada'; l = 'warn'; } else if (sc >= 20) { t = 'Dependencia grave'; l = 'danger'; } else { t = 'Dependencia total'; l = 'danger'; } return { val: sc, unit: '/ 100', tag: t, level: l }; } },
    ];

    // ── Render de una tarjeta ────────────────────────────────────────────────────
    function fieldHtml(f) {
        const fill = f.fill ? ' data-fill="' + f.fill + '"' : '';
        const sum = f.sum ? ' data-sum="1"' : '';
        if (f.type === 'sel') {
            const opts = f.opts.map((o) => '<option value="' + o[0] + '">' + esc(o[1]) + '</option>').join('');
            return '<label class="tool-f">' + esc(f.label) + '<select class="doctor-input" data-k="' + f.k + '"' + fill + sum + '>' + opts + '</select></label>';
        }
        if (f.type === 'date') return '<label class="tool-f">' + esc(f.label) + '<input type="date" class="doctor-input" data-k="' + f.k + '"' + fill + '></label>';
        const attrs = (f.step ? ' step="' + f.step + '"' : '') + (f.min != null ? ' min="' + f.min + '"' : '') + (f.max != null ? ' max="' + f.max + '"' : '');
        return '<label class="tool-f">' + esc(f.label) + '<input type="number" class="doctor-input" data-k="' + f.k + '"' + fill + sum + attrs + ' inputmode="decimal"></label>';
    }
    function cardHtml(def) {
        let h = '<article class="tool-card' + (def.wide ? ' tool-card-wide' : '') + '" data-tool="' + def.id + '">';
        h += '<header class="tool-card-h"><div class="tool-ic"><i data-lucide="' + (def.icon || 'calculator') + '"></i></div>';
        h += '<div class="tool-card-t"><h3>' + esc(def.name) + '</h3><span class="tool-tag">' + esc(def.tag) + '</span></div></header>';
        if (def.fields && def.fields.length) {
            const cols = def.fields.length >= 2 ? ' tool-cols-2' : '';
            h += '<div class="tool-fields' + cols + '">' + def.fields.map(fieldHtml).join('') + '</div>';
        }
        if (def.checks && def.checks.length) {
            h += '<div class="tool-checks">' + def.checks.map((c) => '<label class="tool-check"><input type="checkbox" data-pts="' + c[1] + '"><span>' + esc(c[0]) + '</span><b>' + (c[1] < 0 ? '−' + Math.abs(c[1]) : '+' + c[1]) + '</b></label>').join('') + '</div>';
        }
        h += '<div class="tool-out" data-out><div class="tool-out-row"><span class="tool-out-val">—</span><span class="tool-out-unit"></span></div><div class="tool-out-tag">Completa los campos</div></div>';
        if (def.note) h += '<p class="tool-note"><i data-lucide="info"></i> ' + esc(def.note) + '</p>';
        h += '</article>';
        return h;
    }

    // ── Cálculo / salida ──────────────────────────────────────────────────────────
    function setOut(card, r) {
        const out = card.querySelector('[data-out]');
        const vEl = out.querySelector('.tool-out-val'), uEl = out.querySelector('.tool-out-unit'), tEl = out.querySelector('.tool-out-tag');
        out.classList.remove('tool-lvl-ok', 'tool-lvl-warn', 'tool-lvl-danger', 'tool-lvl-muted');
        if (!r) { vEl.textContent = '—'; uEl.textContent = ''; tEl.textContent = 'Completa los campos'; out.classList.add('tool-lvl-muted'); return; }
        vEl.textContent = r.val; uEl.textContent = r.unit || ''; tEl.textContent = r.tag || '';
        out.classList.add('tool-lvl-' + (r.level || 'muted'));
    }
    function recalc(card) {
        const def = CALC.find((d) => d.id === card.dataset.tool); if (!def) return;
        const get = (k) => { const el = card.querySelector('[data-k="' + k + '"]'); return el ? el.value : ''; };
        let anyFilled = false;
        card.querySelectorAll('[data-k]').forEach((el) => { if (el.value !== '' && el.value != null) anyFilled = true; });
        let sum = 0; card.querySelectorAll('[data-sum]').forEach((el) => { const n = parseFloat(el.value); if (!isNaN(n)) sum += n; });
        let checks = 0; card.querySelectorAll('.tool-check input:checked').forEach((c) => { checks += parseFloat(c.dataset.pts) || 0; });
        const v = { n: (k) => { const x = parseFloat(get(k)); return isNaN(x) ? NaN : x; }, s: (k) => get(k) || '', sum: sum, checks: checks, anyFilled: anyFilled };
        try { setOut(card, def.calc(v)); } catch (e) { setOut(card, null); }
    }
    function recalcAll() { $$('#tool-grid .tool-card').forEach(recalc); }

    // ── Filtrado por especialidad ─────────────────────────────────────────────────
    let myFamilies = ['general'], showAll = false, activeFam = 'all';
    function visibleDefs() {
        if (showAll) return CALC;
        return CALC.filter((d) => d.spec.some((s) => myFamilies.indexOf(s) !== -1));
    }
    function renderGrid() {
        const grid = $('#tool-grid'); if (!grid) return;
        let defs = visibleDefs();
        if (activeFam !== 'all') defs = defs.filter((d) => d.spec.indexOf(activeFam) !== -1);
        grid.innerHTML = defs.map(cardHtml).join('');
        $('#tool-empty').hidden = defs.length > 0;
        recalcAll();
        if (window.lucide) lucide.createIcons();
    }
    function renderFilters() {
        const bar = $('#tool-filters'); if (!bar) return;
        const fams = showAll ? Object.keys(FAMILIES) : myFamilies;
        // solo familias que tienen al menos una calc visible
        const present = fams.filter((f) => CALC.some((d) => d.spec.indexOf(f) !== -1));
        let h = '<button type="button" class="tool-chip on" data-fam="all">Todas</button>';
        h += present.map((f) => '<button type="button" class="tool-chip" data-fam="' + f + '">' + esc(FAMILIES[f].label) + '</button>').join('');
        bar.innerHTML = h;
        activeFam = 'all';
        bar.querySelectorAll('.tool-chip').forEach((ch) => ch.addEventListener('click', () => {
            bar.querySelectorAll('.tool-chip').forEach((x) => x.classList.remove('on'));
            ch.classList.add('on'); activeFam = ch.dataset.fam; renderGrid();
        }));
    }

    // ── Pre-llenado de paciente ────────────────────────────────────────────────────
    function setFill(key, value) { if (value == null || value === '') return; $$('[data-fill="' + key + '"]').forEach((el) => { el.value = value; }); }
    function normSex(g) { if (!g) return ''; const s = String(g).trim().toLowerCase(); if (s[0] === 'm') return 'M'; if (s[0] === 'f') return 'F'; return ''; }
    function ageFromDob(dob) { if (!dob) return null; const d = new Date(dob); if (isNaN(d.getTime())) return null; const t = new Date(); let a = t.getFullYear() - d.getFullYear(); const mm = t.getMonth() - d.getMonth(); if (mm < 0 || (mm === 0 && t.getDate() < d.getDate())) a--; return (a >= 0 && a < 130) ? a : null; }
    async function selectPatient(item) {
        api = window.doctorApi || api;
        $('#tool-patient-name').textContent = item.name || '—';
        const age = ageFromDob(item.dob), sx = normSex(item.gender);
        $('#tool-patient-meta').textContent = [item.cedula || '', age != null ? (age + ' años') : '', sx].filter(Boolean).join(' · ');
        $('#tool-patient-chip').hidden = false; $('#tool-patient-results').hidden = true; $('#tool-patient-q').value = '';
        if (age != null) setFill('age', age); if (sx) setFill('sex', sx);
        if (api) { try { const r = await api('GET', '/portal-doctor/me/patients/' + item.id); if (r && r.ok && r.data) { const vit = (r.data.vitals && r.data.vitals[0]) || null; if (vit) { if (vit.weight_kg) setFill('weight', vit.weight_kg); if (vit.height_cm) setFill('height', vit.height_cm); } } } catch (e) {} }
        recalcAll(); if (window.lucide) lucide.createIcons();
    }
    let timer = null;
    async function doSearch(q) {
        api = window.doctorApi || api;
        const box = $('#tool-patient-results');
        if (!api || q.trim().length < 2) { box.hidden = true; return; }
        try {
            const r = await api('GET', '/portal-doctor/me/patients', { q: q.trim(), per_page: 8 });
            const items = (r && r.ok && r.data && r.data.items) || [];
            if (!items.length) { box.innerHTML = '<div class="tool-pb-empty">Sin coincidencias</div>'; box.hidden = false; return; }
            box.innerHTML = items.map((it, i) => '<button type="button" class="tool-pb-item" data-i="' + i + '"><strong>' + esc(it.name) + '</strong><span>' + esc(it.cedula) + '</span></button>').join('');
            box._items = items; box.hidden = false;
        } catch (e) { box.hidden = true; }
    }

    // ── Boot ────────────────────────────────────────────────────────────────────
    function initTools() {
        if (!$('#tool-grid')) return;
        const spec = (window.DM_TOOLS && window.DM_TOOLS.specialty) || '';
        myFamilies = doctorFamilies(spec);
        // si el médico no matchea ninguna familia específica, mostrar todo
        if (myFamilies.length <= 1) showAll = true;
        const bar = $('#tool-specbar');
        if (bar) {
            bar.hidden = false;
            $('#tool-spec-name').textContent = spec ? spec : 'tu especialidad';
            const all = $('#tool-spec-all');
            all.checked = showAll;
            all.addEventListener('change', () => { showAll = all.checked; renderFilters(); renderGrid(); });
        }
        renderFilters();
        renderGrid();

        const grid = $('#tool-grid');
        grid.addEventListener('input', (e) => { const c = e.target.closest('.tool-card'); if (c) recalc(c); });
        grid.addEventListener('change', (e) => { const c = e.target.closest('.tool-card'); if (c) recalc(c); });

        const q = $('#tool-patient-q');
        if (q) {
            q.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => doSearch(q.value), 280); });
            q.addEventListener('focus', () => { if (q.value.trim().length >= 2) doSearch(q.value); });
        }
        const box = $('#tool-patient-results');
        if (box) box.addEventListener('click', (e) => { const btn = e.target.closest('.tool-pb-item'); if (!btn) return; const it = (box._items || [])[parseInt(btn.dataset.i, 10)]; if (it) selectPatient(it); });
        const clr = $('#tool-patient-clear'); if (clr) clr.addEventListener('click', () => { $('#tool-patient-chip').hidden = true; });
        document.addEventListener('click', (e) => { if (!e.target.closest('.tool-pb-search')) { const b = $('#tool-patient-results'); if (b) b.hidden = true; } });
    }

    // ── TABS (Calculadoras | Certificados) ─────────────────────────────────────────
    let certLoaded = false;
    function initTabs() {
        const tabs = $$('.tool-tab'); if (!tabs.length) return;
        tabs.forEach((t) => t.addEventListener('click', () => {
            tabs.forEach((x) => x.classList.remove('on')); t.classList.add('on');
            const which = t.dataset.tab;
            if ($('#tab-calc')) $('#tab-calc').hidden = which !== 'calc';
            if ($('#tab-cert')) $('#tab-cert').hidden = which !== 'cert';
            if (which === 'cert' && !certLoaded) { certLoaded = true; loadCerts(); }
            if (window.lucide) lucide.createIcons();
        }));
    }

    // ── CERTIFICADOS ────────────────────────────────────────────────────────────
    const CERT_FIELDS = {
        reposo: '<div class="tool-fields tool-cols-2">'
            + '<label class="tool-f">Días de reposo<input type="number" class="doctor-input" data-ck="dias" min="1" max="120" inputmode="numeric"></label>'
            + '<label class="tool-f">Diagnóstico (opcional)<input type="text" class="doctor-input" data-ck="diagnostico"></label>'
            + '<label class="tool-f">Desde<input type="date" class="doctor-input" data-ck="desde"></label>'
            + '<label class="tool-f">Hasta<input type="date" class="doctor-input" data-ck="hasta"></label></div>',
        asistencia: '<div class="tool-fields tool-cols-2">'
            + '<label class="tool-f">Fecha<input type="date" class="doctor-input" data-ck="fecha"></label>'
            + '<label class="tool-f">Motivo (opcional)<input type="text" class="doctor-input" data-ck="motivo"></label>'
            + '<label class="tool-f">Hora desde<input type="time" class="doctor-input" data-ck="hora_desde"></label>'
            + '<label class="tool-f">Hora hasta<input type="time" class="doctor-input" data-ck="hora_hasta"></label></div>',
        aptitud: '<div class="tool-fields tool-cols-2">'
            + '<label class="tool-f">Propósito<input type="text" class="doctor-input" data-ck="proposito" placeholder="laboral, escolar, deportivo…"></label>'
            + '<label class="tool-f">Resultado<select class="doctor-input" data-ck="apto"><option value="1">Apto</option><option value="0">No apto</option></select></label></div>',
    };
    let certType = 'reposo', certPatient = null, certTimer = null;
    function renderCertFields() { const box = $('#cert-fields'); if (box) box.innerHTML = CERT_FIELDS[certType] || ''; if (window.lucide) lucide.createIcons(); }
    function collectCertData() { const data = {}; $$('#cert-fields [data-ck]').forEach((el) => { if (el.value !== '') data[el.dataset.ck] = el.value; }); return data; }
    function certStatus(msg, type) { const el = $('#cert-status'); if (!el) return; el.textContent = msg || ''; el.className = 'doctor-save-status' + (type === 'saved' ? ' doctor-save-saved' : type === 'error' ? ' doctor-save-error' : ''); }
    async function certSearch(q) {
        api = window.doctorApi || api; const box = $('#cert-patient-results');
        if (!api || q.trim().length < 2) { box.hidden = true; return; }
        try {
            const r = await api('GET', '/portal-doctor/me/patients', { q: q.trim(), per_page: 8 });
            const items = (r && r.ok && r.data && r.data.items) || [];
            if (!items.length) { box.innerHTML = '<div class="tool-pb-empty">Sin coincidencias</div>'; box.hidden = false; return; }
            box.innerHTML = items.map((it, i) => '<button type="button" class="tool-pb-item" data-i="' + i + '"><strong>' + esc(it.name) + '</strong><span>' + esc(it.cedula) + '</span></button>').join('');
            box._items = items; box.hidden = false;
        } catch (e) { box.hidden = true; }
    }
    function certSelect(item) {
        certPatient = item;
        $('#cert-patient-name').textContent = item.name || '—';
        $('#cert-patient-meta').textContent = [item.cedula || '', normSex(item.gender)].filter(Boolean).join(' · ');
        $('#cert-patient-chip').hidden = false; $('#cert-patient-results').hidden = true; $('#cert-patient-q').value = '';
        if (window.lucide) lucide.createIcons();
    }
    function resetCertForm() {
        certPatient = null; $('#cert-patient-chip').hidden = true;
        const ext = $('#cert-ext'); if (ext) ext.checked = false; $('#cert-ext-fields').hidden = true;
        $('#cert-ext-name').value = ''; $('#cert-ext-ced').value = ''; $('#cert-obs').value = ''; renderCertFields();
    }
    async function emitCert() {
        api = window.doctorApi || api; const btn = $('#cert-emit'); const ext = $('#cert-ext') && $('#cert-ext').checked;
        const body = { type: certType, data: collectCertData(), body_text: $('#cert-obs').value || '' };
        if (ext) { const nm = $('#cert-ext-name').value.trim(), cd = $('#cert-ext-ced').value.trim(); if (!nm) { certStatus('Escribe el nombre del paciente externo.', 'error'); return; } body.patient_name = nm; body.patient_cedula = cd; }
        else { if (!certPatient) { certStatus('Selecciona un paciente o marca "paciente externo".', 'error'); return; } body.patient_id = certPatient.id; }
        btn.disabled = true; certStatus('Emitiendo…', '');
        try {
            const r = await api('POST', '/portal-doctor/me/certificates', body);
            if (r && r.ok && r.data) { certStatus('✓ Certificado ' + (r.data.folio || '') + ' emitido.', 'saved'); window.open('certificado-pdf.php?id=' + r.data.id, '_blank'); resetCertForm(); loadCerts(); }
            else { certStatus((r && r.message) || 'No se pudo emitir el certificado.', 'error'); }
        } catch (e) { certStatus('Error de conexión.', 'error'); }
        btn.disabled = false;
    }
    async function loadCerts() {
        api = window.doctorApi || api; const box = $('#cert-list'); if (!box) return;
        try { const r = await api('GET', '/portal-doctor/me/certificates'); renderCertList((r && r.ok && Array.isArray(r.data)) ? r.data : []); }
        catch (e) { box.innerHTML = '<div class="doctor-empty" style="padding:30px 16px"><p>No se pudo cargar.</p></div>'; }
    }
    function renderCertList(items) {
        const box = $('#cert-list'); if (!box) return;
        if (!items.length) { box.innerHTML = '<div class="doctor-empty" style="padding:30px 16px"><p class="doctor-empty-title">Sin certificados aún</p><p>Los que emitas aparecerán aquí.</p></div>'; return; }
        const TL = { reposo: 'Reposo', asistencia: 'Asistencia', aptitud: 'Aptitud' };
        box.innerHTML = items.map((c) => { const d = c.issued_at ? new Date(String(c.issued_at).replace(' ', 'T')) : null; const fecha = (d && !isNaN(d)) ? (d.getDate() + '/' + (d.getMonth() + 1) + '/' + d.getFullYear()) : ''; return '<div class="cert-item"><div class="cert-item-info"><strong>' + esc(c.patient_name) + '</strong><span>' + (TL[c.type] || 'Certificado') + ' · ' + esc(c.folio || '') + ' · ' + fecha + (c.revoked_at ? ' · <em>anulado</em>' : '') + '</span></div><a class="cert-item-pdf" href="certificado-pdf.php?id=' + c.id + '" target="_blank" rel="noopener" title="Abrir PDF"><i data-lucide="file-text"></i></a></div>'; }).join('');
        if (window.lucide) lucide.createIcons();
    }
    function initCerts() {
        if (!$('#tab-cert')) return;
        renderCertFields();
        $$('.cert-type').forEach((b) => b.addEventListener('click', () => { $$('.cert-type').forEach((x) => x.classList.remove('on')); b.classList.add('on'); certType = b.dataset.type; renderCertFields(); }));
        const ext = $('#cert-ext'); if (ext) ext.addEventListener('change', () => { $('#cert-ext-fields').hidden = !ext.checked; if (ext.checked) { certPatient = null; $('#cert-patient-chip').hidden = true; } });
        const q = $('#cert-patient-q');
        if (q) { q.addEventListener('input', () => { clearTimeout(certTimer); certTimer = setTimeout(() => certSearch(q.value), 280); }); q.addEventListener('focus', () => { if (q.value.trim().length >= 2) certSearch(q.value); }); }
        const box = $('#cert-patient-results');
        if (box) box.addEventListener('click', (e) => { const btn = e.target.closest('.tool-pb-item'); if (!btn) return; const it = (box._items || [])[parseInt(btn.dataset.i, 10)]; if (it) certSelect(it); });
        const clr = $('#cert-patient-clear'); if (clr) clr.addEventListener('click', () => { certPatient = null; $('#cert-patient-chip').hidden = true; });
        const emit = $('#cert-emit'); if (emit) emit.addEventListener('click', emitCert);
        document.addEventListener('click', (e) => { if (!e.target.closest('#cert-patient-q') && !e.target.closest('#cert-patient-results')) { const b = $('#cert-patient-results'); if (b) b.hidden = true; } });
    }

    document.addEventListener('DOMContentLoaded', () => { api = window.doctorApi || api; initTabs(); initTools(); initCerts(); if (window.lucide) lucide.createIcons(); });
})();
