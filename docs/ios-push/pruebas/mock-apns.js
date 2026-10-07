// APNs simulado (HTTP/2 + TLS). Verifica el JWT ES256 con la llave pública
// usando crypto de Node (implementación independiente de la de PHP) y responde
// según el token: a… → 200, b… → 410 Unregistered, c… → 400 BadDeviceToken.
// Uso: node mock-apns.js <puerto> <entorno> <archivo-registro> <carpeta-de-llaves>
const http2 = require('http2');
const fs = require('fs');
const crypto = require('crypto');
const path = require('path');

const [puerto, entorno, registro] = process.argv.slice(2);
const dir = process.argv[5] || __dirname;
const pub = crypto.createPublicKey(fs.readFileSync(path.join(dir, 'apns-pub.pem')));

function b64urlDecode(s) {
  return Buffer.from(s.replace(/-/g, '+').replace(/_/g, '/'), 'base64');
}

function verificarJwt(jwt) {
  const partes = jwt.split('.');
  if (partes.length !== 3) return { ok: false, motivo: 'jwt sin 3 partes' };
  const cab = JSON.parse(b64urlDecode(partes[0]));
  const claims = JSON.parse(b64urlDecode(partes[1]));
  const firma = b64urlDecode(partes[2]);
  const ok = crypto.verify('sha256', Buffer.from(partes[0] + '.' + partes[1]), { key: pub, dsaEncoding: 'ieee-p1363' }, firma);
  return { ok, cab, claims, largoFirma: firma.length };
}

const servidor = http2.createSecureServer({
  key: fs.readFileSync(path.join(dir, 'tls-key.pem')),
  cert: fs.readFileSync(path.join(dir, 'tls-cert.pem')),
});

servidor.on('stream', (stream, h) => {
  let cuerpo = '';
  stream.on('data', (c) => { cuerpo += c; });
  stream.on('end', () => {
    const token = (h[':path'] || '').replace('/3/device/', '');
    const jwt = String(h['authorization'] || '').replace(/^bearer /, '');
    const v = verificarJwt(jwt);
    let payload = null;
    try { payload = JSON.parse(cuerpo); } catch (e) {}
    const entrada = {
      entorno, metodo: h[':method'], token: token.slice(0, 8) + '…', topic: h['apns-topic'],
      pushType: h['apns-push-type'], prioridad: h['apns-priority'], caduca: h['apns-expiration'], colapsar: h['apns-collapse-id'], jwtValido: v.ok,
      alg: v.cab && v.cab.alg, kid: v.cab && v.cab.kid, iss: v.claims && v.claims.iss,
      largoFirma: v.largoFirma, cuerpo: payload,
    };
    fs.appendFileSync(registro, JSON.stringify(entrada) + '\n');

    if (!v.ok) {
      stream.respond({ ':status': 403, 'content-type': 'application/json' });
      return stream.end(JSON.stringify({ reason: 'InvalidProviderToken' }));
    }
    if (token.startsWith('a')) {
      stream.respond({ ':status': 200, 'apns-id': crypto.randomUUID() });
      return stream.end();
    }
    if (token.startsWith('b')) {
      stream.respond({ ':status': 410, 'content-type': 'application/json' });
      return stream.end(JSON.stringify({ reason: 'Unregistered', timestamp: Date.now() }));
    }
    stream.respond({ ':status': 400, 'content-type': 'application/json' });
    stream.end(JSON.stringify({ reason: 'BadDeviceToken' }));
  });
});

servidor.listen(Number(puerto), '127.0.0.1', () => console.log(`APNs simulado (${entorno}) en ${puerto}`));
