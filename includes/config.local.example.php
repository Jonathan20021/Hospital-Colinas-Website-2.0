<?php

// Copy this file to includes/config.local.php on each environment
// and adjust the credentials. config.local.php is gitignored so it
// will never be pushed to GitHub.

putenv('DB_HOST=localhost');
putenv('DB_PORT=3306');
putenv('DB_NAME=your_database_name');
putenv('DB_USER=your_database_user');
putenv('DB_PASS=your_database_password');

// ── Portal de Pacientes ────────────────────────────────────────────────────
// Apunta a la API interna del hospital (VIP de Fortinet).
// Para desarrollo local con XAMPP: 'http://localhost/api/v1' (sin la VIP).
define('PORTAL_API_BASE', 'https://186.149.243.228:20443/api/v1');

// La VIP usa certificado autofirmado, por eso desactivamos la verificación TLS
// en las llamadas server-to-server. NO afecta a los pacientes: ellos hablan
// solo con tu dominio público (TLS válido). Pon true cuando la VIP tenga
// certificado de CA pública.
define('PORTAL_API_VERIFY_TLS', false);

// API Key del staff del hospital, para que el admin del cPanel pueda subir
// fotos de médicos y editar campos del directorio sin tener que loguear.
// Generarla desde el admin interno del hospital → API Keys → crear nueva,
// con permisos de admin. Solo necesaria si quieres gestionar médicos desde
// admin/medicos.php del cPanel.
// define('HOSPITAL_API_KEY', 're_xxxxxxxxxxxxxxx');

// ── Chat IA (Colinas IA) ───────────────────────────────────────────────────
// Secreto compartido server-to-server para que el chat IA pueda agendar citas
// guest sin tener que resolver hCaptcha (un LLM no puede resolver un widget de
// captcha). El valor debe coincidir con el setting `ai_chat_secret` en la
// tabla `settings` de la API interna del hospital. Si está vacío o no definido,
// el chat IA NO podrá completar agendamientos — el resto del bot sigue
// funcionando (info, recomendar médicos, etc.).
// define('PORTAL_AI_CHAT_SECRET', 'paste-the-64-hex-secret-from-the-hospital-settings-table-here');

// ── Asistente de IA del visor de imágenes ───────────────────────────────────
// API key de OpenAI para el asistente que apoya al médico leyendo estudios
// (radiografías, TC, etc.) en /portal-medico/visor-imagen. La key vive SOLO en
// el servidor: el navegador nunca la ve; el llamado a OpenAI lo hace
// api/ai-imaging.php server-side. Si está vacía o no definida, el botón "IA"
// del visor responde "aún no está configurado" y nada más cambia.
// Privacidad: solo se envían los píxeles de la imagen + contexto NO identificante
// (modalidad, región, sexo, edad). NUNCA nombre, cédula ni fecha de nacimiento.
// define('OPENAI_API_KEY', 'sk-...');
//
// Modelo de visión (opcional). Por defecto 'gpt-4.1-mini' (preciso y económico).
// Para más precisión analítica: 'o4-mini' (razona; cuesta algo más).
// define('OPENAI_IMAGING_MODEL', 'gpt-4.1-mini');
