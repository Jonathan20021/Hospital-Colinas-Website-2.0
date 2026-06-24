# Despliegue — Autorización de estudios (Imágenes y Laboratorio)

Feature completa para captar solicitudes de estudios (paciente externo o del
portal) → el agente de seguros autoriza con la ARS → el paciente ve su copago en
el portal. **Nada de esto se ha desplegado todavía.** Esta guía es el checklist.

Decisiones fijadas con gerencia (Fase 1):
- Autorizar **primero**, visita después (sin selector de fecha/slot).
- El **agente teclea** el copago/restante (sin tarifario automático).
- Notificación al paciente **solo por el portal** (sin mensajes salientes auto).
- Paciente externo: **crea cuenta ligera** y queda dentro del portal.

---

## A) Backend en JENOFONTE (hacer PRIMERO)

1. **Base de datos** — ejecutar el esquema contra `medical_call_center`
   (NUNCA SGC/SIGMA, que es solo lectura):
   ```bash
   mysql -u <user> -p medical_call_center < 01-schema.sql
   ```

2. **Config** (en el config del API interno, NO en el repo):
   ```php
   define('STUDY_DOCS_DIR', '/var/secure/study_docs');     // fuera del webroot
   define('STUDY_DOCS_KEY', /* 32 bytes binarios desde un secreto */);
   ```
   `mkdir -p /var/secure/study_docs && chmod 700` con el dueño del proceso PHP.

3. **Endpoints** — implementar según `02-api-contrato.md`, usando
   `03-endpoints-referencia.php` como referencia (adaptar a los traits/router del
   API). Endpoints del paciente:
   - `POST /portal/guest/study-requests`
   - `POST /portal/me/study-requests`
   - `GET  /portal/me/study-requests`
   - `GET  /portal/me/study-requests/{id}`
   - `POST /portal/me/study-requests/{id}/documents`
   > Reutilizar la lógica de creación de cuenta ligera del agendamiento de
   > invitado (`find_or_create_light_patient`): misma cuenta, contraseña = cédula.

4. **Panel del agente** (app interna de staff) — endpoints `/staff/study-requests*`
   del contrato: cola con filtros, ficha con documentos (descifrar+stream),
   cambiar estado, y **registrar la cotización (copago/restante)**.
   ⚠️ **PENDIENTE DE CONFIRMAR:** ¿dónde vive hoy el resto de herramientas del
   call center en JENOFONTE? El panel debe montarse ahí, no como app suelta.

5. Programar la **purga** de `study_auth_documents` según política de retención.

---

## B) Sitio público (cPanel) — subir archivos

**Nuevos:**
- `solicitar-estudios.php`            (página pública / invitado)
- `portal/solicitar-estudios.php`     (intake autenticado)
- `portal/mis-solicitudes.php`        (estado + copago)
- `api/guest-study-request.php`       (proxy invitado + establece sesión)
- `includes/study_request_form.php`   (formulario compartido)
- `assets/js/solicitar-estudios.js`
- `assets/css/estudios.css`

**Modificados:**
- `.htaccess`                  (regla de URL limpia `/solicitar-estudios`)
- `index.php`                  (CTA en la sección de seguros del home)
- `includes/public-layout.php` (acceso en el menú móvil)
- `portal/_layout.php`         (2 enlaces en el menú del portal + diálogo "Más")
- `portal/dashboard.php`       (2 accesos rápidos)

**NO subir** la carpeta `deploy/` (son artefactos/documentación del backend).

> El proxy `api/portal-proxy.php` **no requiere cambios**: ya permite `/portal/me/*`,
> que cubre `/portal/me/study-requests*` y la subida de documentos.

---

## C) Prueba de humo (tras desplegar)

1. **Invitado nuevo:** abrir `/solicitar-estudios` sin sesión → elegir tipo →
   marcar estudios → seguro → datos (cédula nueva) → subir orden + carnet →
   enviar. Debe crear cuenta, loguear, subir documentos y mostrar el código
   `EST-AAAA-######`.
2. **Invitado con cédula ya registrada:** debe crear la solicitud y pedir iniciar
   sesión para subir documentos.
3. **Paciente del portal:** entrar → "Solicitar estudios" → datos precargados →
   enviar con documentos → aparece en "Mis solicitudes".
4. **Agente:** ve la solicitud, abre los documentos, registra autorización +
   restante. El paciente lo ve en "Mis solicitudes" como **copago listo**.
5. Verificar en móvil (formulario responsivo) y que el JWT nunca llega al navegador.

## Rollback
Todo es aditivo. Para desactivar: quitar el CTA del home, los enlaces del menú y
la regla del `.htaccess`. Las tablas nuevas quedan aisladas en `medical_call_center`.
