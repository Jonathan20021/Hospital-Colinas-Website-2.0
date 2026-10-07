/*
 * Cuerpo de función para WKWebView.callAsyncJavaScript (admite await y return).
 * Argumentos: path, body, ms.
 *
 * POST al proxy del portal (/api/portal-proxy.php) desde la propia página, con
 * su sesión y su token CSRF, igual que los fetch del portal. La app nunca toca
 * la cookie de sesión ni conoce el JWT.
 *
 * Devuelve el código HTTP; 0 si no hay red, no es una página del portal o se
 * agotó el tiempo.
 */
const csrf = document.querySelector('meta[name="csrf-token"]');
const api = document.querySelector('meta[name="portal-api-url"]');
if (!csrf || !api) {
  return 0;
}
const opciones = {
  method: 'POST',
  credentials: 'same-origin',
  headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf.content },
  body: JSON.stringify({ method: 'POST', path: path, body: body })
};
if (typeof AbortSignal !== 'undefined' && AbortSignal.timeout) {
  opciones.signal = AbortSignal.timeout(ms);
}
try {
  const r = await fetch(api.content, opciones);
  return r.status;
} catch (e) {
  return 0;
}
