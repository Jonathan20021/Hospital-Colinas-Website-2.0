# API — Autorización de estudios (contrato)

Todos los endpoints viven en **JENOFONTE** bajo `/api/v1`. El sitio público nunca
habla directo con la VIP: el navegador llama al proxy del portal
(`api/portal-proxy.php`) que reenvía con el JWT del paciente en sesión. El intake
de invitado usa `api/guest-study-request.php`.

Respuesta estándar (igual que el resto del API del portal):
```json
{ "success": true, "data": { ... }, "message": null, "errors": null }
```

---

## 1. Invitado (paciente externo / no registrado)

### `POST /portal/guest/study-requests`
Crea la solicitud. Si la cédula **no** tiene cuenta de portal, crea una **cuenta
ligera** (igual que el agendamiento de invitado: usuario = cédula/correo,
contraseña inicial = cédula sin guiones) y devuelve un token para que el paciente
quede logueado y pueda subir documentos y dar seguimiento. Si la cédula **ya**
tiene cuenta, NO autologuea: devuelve `has_account: true` y el paciente debe
iniciar sesión para subir documentos.

Request:
```json
{
  "full_name": "Juan Pérez",
  "cedula": "031-0000000-0",
  "phone": "(809) 000-0000",
  "email": "juan@correo.com",            // opcional
  "study_type": "imaging",                // imaging | lab | both
  "items": [
    { "category": "imaging", "name": "Radiografía y Panorámica" }
  ],
  "insurer": "ARS Humano",                // opcional pero recomendado
  "insurer_member_id": "1234567",         // opcional
  "insurer_plan": "Plan Complementario",  // opcional
  "referring_center": "HOMS",             // opcional
  "referring_doctor": "Dra. Familia",     // opcional
  "urgency": "normal",                    // normal | urgent
  "preferred_date": "2026-07-01",         // opcional
  "notes": "Vengo referido del HOMS",     // opcional
  "consent_contact": true,
  "captcha_token": "..."                  // si hcaptcha está activo
}
```

Response (cédula nueva → autologin):
```json
{ "success": true, "data": {
  "request_id": 123, "public_code": "EST-2026-000123",
  "status": "received",
  "token": "<jwt>", "expires_in": 3600,
  "patient": { "id": 9, "name": "Juan Pérez", "email": null },
  "email_verified": false,
  "account_created": true, "has_account": false
}}
```

Response (cédula ya registrada → sin autologin):
```json
{ "success": true, "data": {
  "request_id": 123, "public_code": "EST-2026-000123", "status": "received",
  "account_created": false, "has_account": true
}}
```

El servidor valida todo (campos, captcha, rate-limit por IP/cédula, duplicados).

---

## 2. Paciente autenticado (`Authorization: Bearer <jwt>`)

### `POST /portal/me/study-requests`
Crea la solicitud asociada al paciente en sesión (toma nombre/cédula/seguro del
expediente; el body solo trae lo específico del pedido).

Request:
```json
{
  "study_type": "both",
  "items": [
    { "category": "imaging", "name": "Sonografía" },
    { "category": "lab", "name": "Laboratorio Clínico y Banco de Sangre" }
  ],
  "insurer": "ARS Humano",
  "insurer_member_id": "1234567",
  "insurer_plan": null,
  "referring_center": "HOMS",
  "referring_doctor": "Dra. Familia",
  "urgency": "normal",
  "preferred_date": null,
  "notes": null,
  "consent_contact": true
}
```
Response: `{ "success": true, "data": { "request_id": 124, "public_code": "EST-2026-000124", "status": "received" } }`

### `GET /portal/me/study-requests`
Lista las solicitudes del paciente (más recientes primero).
```json
{ "success": true, "data": { "requests": [
  {
    "id": 124, "public_code": "EST-2026-000124",
    "study_type": "both", "status": "quoted",
    "items": [ { "category": "imaging", "name": "Sonografía" } ],
    "insurer": "ARS Humano",
    "created_at": "2026-06-23 10:00:00",
    "documents_count": 3,
    "quote": {
      "authorization_number": "AUT-99887",
      "currency": "DOP",
      "total_amount": 3500.00, "covered_amount": 2800.00,
      "copay_amount": null, "patient_balance": 700.00,
      "valid_until": "2026-07-23",
      "agent_note": "Trae tu orden impresa. Ayuno de 8h para la sonografía abdominal."
    }
  }
] } }
```

### `GET /portal/me/study-requests/{id}`
Detalle de una solicitud (incluye metadata de documentos, sin el binario) + quote
+ línea de tiempo (`events` visibles para el paciente).

### `POST /portal/me/study-requests/{id}/documents`
Sube un documento. El archivo viaja en **base64** dentro del JSON (mismo patrón
que los adjuntos del chat) — así reusa el proxy JSON existente, sin multipart.
Solo el dueño de la solicitud puede adjuntar (anti-IDOR).
```json
{ "doc_type": "order", "filename": "orden.jpg", "mime": "image/jpeg", "data": "<base64>" }
```
Validar: `mime ∈ {image/jpeg,image/png,image/webp,image/gif,application/pdf}`,
tamaño ≤ 5 MB. Guardar el binario **cifrado** (AES-256-GCM) fuera del webroot.
Response: `{ "success": true, "data": { "document_id": 55 } }`

### (Opcional, no requerido en Fase 1 para el paciente)
`GET /portal/me/study-requests/{id}/documents/{docId}` → stream del binario
descifrado, validando pertenencia. El paciente normalmente no necesita
re-descargar; el AGENTE sí los ve desde el panel interno.

---

## 3. Panel del agente (app interna en JENOFONTE — NO en el sitio público)

Protegido por el login de staff existente (los mismos que usan el call center).

- `GET  /staff/study-requests?status=&q=&assigned=` — cola con filtros.
- `GET  /staff/study-requests/{id}` — ficha completa + documentos (stream descifrado).
- `POST /staff/study-requests/{id}/assign` — autoasignarse / asignar agente.
- `POST /staff/study-requests/{id}/status` — `{ status, note }` cambia estado + evento.
- `POST /staff/study-requests/{id}/quote` — `{ authorization_number, total_amount,
  covered_amount, copay_amount, patient_balance, valid_until, agent_note }`
  registra la cotización; cambia estado a `quoted` y crea evento. **Aquí el agente
  teclea el restante a pagar.**

> Notificación al paciente: en Fase 1 es **solo-portal** (el paciente entra y ve el
> estado/copago). No se envían mensajes salientes automáticos. El agente puede
> llamar/escribir manualmente. WhatsApp/correo automático = fase posterior, con
> consentimiento (`consent_contact`).
