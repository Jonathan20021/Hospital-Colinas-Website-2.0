# Editor de documentos clínicos — despliegue (backend en JENOFONTE)

Módulo "Word en la web" del Portal Médico: el médico redacta/pega un documento
para un paciente, se guarda en su expediente (`medical_call_center`) y se exporta
a PDF con membrete doble (HGLC + logo del médico) vía Dompdf.

> ✅ **BACKEND DESPLEGADO en JENOFONTE (2026-07-01).** Backups
> `*.bak.docs.20260701_160658` de `index.php` y `DoctorPortalController.php`.
> Trait en `api/v1/controllers/DoctorDocumentsTrait.php`, PDF en
> `api/helpers/DoctorDocumentPdf.php`, 12 rutas añadidas al router (líneas
> 246–257), tablas creadas + `doctors.letterhead_logo`. Smoke test: las rutas
> responden 401 sin token (clase compone, ruteo OK). PDF usa sufijo `.pdf`
> (`/portal-doctor/me/documents/{id}.pdf`).
>
> Rollback: `cp *.bak.docs.20260701_160658` sobre los originales.
> Datos SOLO en `medical_call_center`; **SGC es de solo lectura**.
> **Frontend**: commiteado + push a GitHub; falta el clic "Deploy HEAD Commit" en
> cPanel Git Version Control.

## Archivos

| Local (`docs/portal-doctor-documents/`) | Destino en JENOFONTE (API interna) |
|---|---|
| `schema.sql` | ejecutar en `medical_call_center` |
| `DoctorDocumentsTrait.php` | `api/traits/` (o donde vivan los traits del controlador del médico) |
| `DoctorDocumentPdf.php` | `api/helpers/` (junto a `SymptomsPdf.php`) |

## Pasos

### 1) Base de datos
Ejecuta `schema.sql` en `medical_call_center`. Crea `doctor_documents`,
`doctor_document_templates` y añade `doctors.letterhead_logo`.
(MariaDB 10.4 soporta `ADD COLUMN IF NOT EXISTS`.)

### 2) Controlador del portal del médico
- Copia `DoctorDocumentsTrait.php` y agrégalo con `use DoctorDocumentsTrait;` en
  el controlador del portal del doctor (el mismo que hoy sirve `me/notes`,
  `me/rx-templates`, `me/signature`, etc.).
- **Verifica 2 cosas contra los traits del médico que ya existen:**
  1. **ID del médico.** El helper `docDoctorId()` usa
     `$this->doctor['doctor_id'] ?? $this->doctor['id']`. Ajústalo a como el
     controlador expone el id del médico autenticado (mira cómo lo hace, p. ej.,
     el trait de `notes` o `rx-templates`).
  2. **Ámbito paciente↔médico.** `docPatientBelongsToDoctor()` valida por la
     tabla `appointments (doctor_id, patient_id)`. Si el portal ya tiene un
     método de scoping de pacientes, reúsalo aquí para ser consistente.

### 3) Rutas (index.php, `$doctorRoutes`)
Registra (mismo estilo que las demás rutas `/portal-doctor/me/*`):

```
GET    /portal-doctor/me/documents                 -> docDocumentsIndex
POST   /portal-doctor/me/documents                 -> docDocumentCreate
GET    /portal-doctor/me/documents/{id}            -> docDocumentGet
PUT    /portal-doctor/me/documents/{id}            -> docDocumentUpdate
DELETE /portal-doctor/me/documents/{id}            -> docDocumentDelete
GET    /portal-doctor/me/documents/{id}/pdf        -> docDocumentPdf
GET    /portal-doctor/me/document-templates        -> docTemplatesIndex
POST   /portal-doctor/me/document-templates        -> docTemplateCreate
DELETE /portal-doctor/me/document-templates/{id}   -> docTemplateDelete
GET    /portal-doctor/me/letterhead                -> docLetterheadGet
POST   /portal-doctor/me/letterhead                -> docLetterheadSet
DELETE /portal-doctor/me/letterhead                -> docLetterheadDelete
```

El proxy público (`api/doctor-proxy.php`) ya permite todo `/portal-doctor/me`,
así que **no hay que tocar el proxy**. El PDF binario lo sirve el proxy dedicado
`portal-medico/documento-editor.php` (usa `portal_api_call_binary`).

### 4) Exequátur / colegiatura y logo institucional (opcional pero recomendado)
- `DoctorDocumentPdf::doctorInfo()` intenta leer exequátur/colegiatura de varias
  columnas posibles (`exequatur`, `colegiatura`/`cmd`, …). Ajusta los nombres a
  tu esquema real de `doctors` si no coinciden. Si no hay dato, simplemente no
  se imprime esa línea.
- Logo institucional HGLC en el PDF (para que no salga solo el wordmark de
  texto): define en la config del API `define('HGLC_LETTERHEAD_LOGO', '/ruta/al/logo.png');`
  **o** guarda un data-URI en `settings.clinic_logo_data`. Si no, usa el wordmark
  navy/verde (siempre funciona).

## Seguridad (ya contemplado en el código)
- **HTML saneado con lista blanca** (`sanitizeHtml`) al guardar; el mismo HTML se
  reusa en el PDF y en el navegador. Se bloquea `script/style/iframe/...`, `on*`,
  `href` no http/mailto/tel, `src` que no sea `data:image/…`, y `style:` fuera de
  una lista corta de propiedades.
- **Anti-IDOR**: todo acotado por `doctor_id`; el paciente debe pertenecer al
  médico; documentos y plantillas solo del propio médico.
- **Auditoría PHI**: `logAudit(...)` en crear/editar/PDF (el proxy ya registra el
  acceso a `/portal-doctor/me`).
- Imágenes embebidas limitadas a ~2 MB; borrado lógico (`status='deleted'`).

## Prueba end-to-end (tras desplegar)
1. Sube tu logo en *Mi cuenta → Mi membrete*.
2. Abre una consulta → **Redactar documento** → escribe/pega, aplica una plantilla.
3. **Guardar** → verifica que aparece en la ficha del paciente (*Documentos*).
4. **PDF** → revisa el membrete doble, la firma y los saltos de página.
5. Reabre el documento guardado y confirma que carga el contenido saneado.
