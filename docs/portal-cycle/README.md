# Mi Ciclo — módulo de control menstrual del Portal del Paciente

Herramienta del nuevo hub **Mi Salud**. Predice periodo, ventana fértil y
ovulación; permite registro diario (flujo, síntomas, ánimo, dolor, temperatura,
notas); muestra tendencias y genera un **resumen para la consulta** con el
ginecólogo. UI estilo app (rueda del ciclo + calendario), marca HGLC navy/verde.

## Estado

| Capa | Ubicación | Estado |
|------|-----------|--------|
| Frontend hub | `portal/salud.php` + `assets/css/portal-salud.css` | ✅ local, **falta subir a cPanel** |
| Frontend app | `portal/ciclo.php` + `assets/css/portal-ciclo.css` + `assets/js/portal-ciclo.js` | ✅ local, **falta subir a cPanel** |
| Navegación | `portal/_layout.php` (ítem "Mi Salud" + soporte CSS/JS por página) | ✅ local |
| Backend API | `CycleTrait.php` en `api/v1/controllers/` + rutas en `api/index.php` | ✅ **LIVE en JENOFONTE** (E2E 21/21) |
| Tablas | `portal_cycle_settings/periods/logs` en `medical_call_center` | ✅ **LIVE** |
| Recordatorios push | `PushNotifier::cycleReminders` + `cron_push_daily.php` + tabla `portal_cycle_reminders_sent` | ✅ **LIVE** (cron 09:00 RD) |

El backend está LIVE y probado. **Falta subir el frontend a cPanel** (flujo git
del usuario). Apenas el front esté arriba, la app guarda y sincroniza sola; sin
backend corre en modo vista previa (banner, datos en memoria).

**Recordatorios push:** el cron diario calcula, para cada paciente con ciclo
configurado + suscripción push, si su periodo llega en 2 días / hoy (y ventana
fértil / ovulación si el objetivo es buscar embarazo) y envía la notificación.
Anti-duplicado por `portal_cycle_reminders_sent`. Toggle por paciente en la app
(guardado en `portal_cycle_settings.reminders`). Master switch:
`settings.patient_push_cycle_reminder`. No envía nada hasta que existan pacientes
reales con ciclo + suscripción (sin riesgo de spam).

## Arquitectura

- El frontend NO conoce la API interna. Llama a `/api/portal-proxy.php` (mismo
  origen) con `{method, path, query|body}` + header `X-CSRF-Token`. El proxy ya
  **permite el prefijo `/portal/me`**, así que `/portal/me/cycle/*` pasa sin
  tocar el allowlist, y queda **auditado como PHI** automáticamente.
- Las predicciones se calculan en el **cliente** (UI instantánea). El backend
  solo persiste: periodos, registros y preferencias.
- Persistencia **solo en `medical_call_center`**. SGC/SIGMA permanece intacto.

## Despliegue del backend (con tu visto bueno)

1. **Tablas** — aplicar `schema.sql` en `medical_call_center`:
   ```
   mysql medical_call_center < schema.sql
   ```
2. **Endpoints** — integrar `endpoints.reference.php` al router de la API
   interna, dentro del grupo que ya valida el JWT del paciente y expone
   `$patientId`. Cablear:

   | Método | Ruta | Función |
   |--------|------|---------|
   | GET    | `/portal/me/cycle` | `cycle_get($db, $patientId)` |
   | PUT    | `/portal/me/cycle/settings` | `cycle_put_settings($db, $patientId, $body)` |
   | POST   | `/portal/me/cycle/period` | `cycle_post_period($db, $patientId, $body)` |
   | DELETE | `/portal/me/cycle/period/{id}` | `cycle_delete_period($db, $patientId, (int)$id)` |
   | PUT    | `/portal/me/cycle/log` | `cycle_put_log($db, $patientId, $body)` |

   Todas devuelven `['success'=>true,'data'=>...]` (lo que el portal espera).
3. **Sin notificaciones** a pacientes en esta fase (respeta la política de no
   enviar SMS/correo a pacientes reales). Los recordatorios push quedan para una
   fase posterior (columna `reminders` ya reservada).

## Contrato de la API

### `GET /portal/me/cycle`
```json
{ "success": true, "data": {
  "settings": { "avg_cycle_length": 28, "avg_period_length": 5, "goal": "track", "onboarded": true },
  "periods": [ { "id": 12, "start_date": "2026-06-02", "end_date": null } ],
  "logs": { "2026-06-20": { "flow": "medium", "symptoms": ["cramps"], "moods": ["tired"], "intimacy": "", "pain": 2, "temp": 36.7, "notes": "" } }
} }
```
### `PUT /portal/me/cycle/settings`
`{ "avg_cycle_length": 29, "avg_period_length": 5, "goal": "conceive", "onboarded": true }`
### `POST /portal/me/cycle/period`
`{ "start_date": "2026-06-27", "end_date": null }` → `{ "data": { "id": 13, ... } }`
### `DELETE /portal/me/cycle/period/{id}`
### `PUT /portal/me/cycle/log`
`{ "date": "2026-06-27", "flow": "light", "symptoms": ["cramps"], "moods": ["calm"], "intimacy": "none", "pain": 1, "temp": 36.6, "notes": "" }`
Borrar un día: `{ "date": "2026-06-27", "clear": true }`

## Seguridad y privacidad

- Toda consulta acotada por `patient_id` del JWT — el paciente solo ve lo suyo.
- Dato ultra-sensible: **no** exponer estos endpoints fuera del prefijo `/portal/me`.
- PWA: **no** cachear respuestas de `/portal/me/cycle/*` en el Service Worker
  (mantener la política de cero PHI en caché).
- Visibilidad en el portal: el hub "Mi Salud" es visible para todos; "Mi Ciclo"
  se **recomienda** a pacientes con sexo femenino (campo `gender` del expediente).

## Pendiente / siguientes herramientas del hub

Embarazo semana a semana · Recordatorios de prevención (tamizajes por edad/sexo)
· Diario de síntomas · Mis signos vitales · Mis medicamentos. El hub ya está
preparado para sumarlas como tarjetas.
