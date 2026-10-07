#!/usr/bin/env bash
# Pruebas del traspaso de avisos de iOS (ApnsSender + ApnsTrait).
#
#   bash docs/ios-push/pruebas/correr.sh
#
# Necesita php (con curl HTTP/2, openssl y pdo_sqlite), node y openssl. No
# toca Apple: levanta dos APNs simulados (producción y sandbox) en
# 127.0.0.1:18443/18444 con una llave y un certificado generados al vuelo.
#
# La prueba del trait necesita además una base MariaDB/MySQL DESECHABLE:
#   PRUEBA_MYSQL_DSN='mysql:host=127.0.0.1;port=3306;dbname=prueba;charset=utf8mb4' \
#   PRUEBA_MYSQL_USUARIO=root PRUEBA_MYSQL_CLAVE= bash docs/ios-push/pruebas/correr.sh
# (borra y crea las tablas settings y portal_push_apns). Sin esa variable se omite.
set -euo pipefail

aqui="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
docs="$(dirname "$aqui")"
trabajo="$(mktemp -d)"
pids=()
limpiar() {
  for pid in "${pids[@]}"; do kill "$pid" 2>/dev/null || true; done
  rm -rf "$trabajo"
}
trap limpiar EXIT

# Llave de proveedor como la .p8 de Apple (PKCS#8, P-256), su pública y un
# certificado TLS para los simulados.
openssl genpkey -algorithm EC -pkeyopt ec_paramgen_curve:P-256 -out "$trabajo/AuthKey_TEST123456.p8" 2>/dev/null
openssl pkey -in "$trabajo/AuthKey_TEST123456.p8" -pubout -out "$trabajo/apns-pub.pem" 2>/dev/null
openssl req -x509 -newkey ec -pkeyopt ec_paramgen_curve:P-256 -nodes \
  -keyout "$trabajo/tls-key.pem" -out "$trabajo/tls-cert.pem" -days 1 \
  -subj "/CN=localhost" -addext "subjectAltName=DNS:localhost,IP:127.0.0.1" 2>/dev/null
mkdir -p "$trabajo/cache"

node "$aqui/mock-apns.js" 18443 production "$trabajo/registro.jsonl" "$trabajo" > "$trabajo/prod.log" 2>&1 &
pids+=($!)
node "$aqui/mock-apns.js" 18444 sandbox "$trabajo/registro.jsonl" "$trabajo" > "$trabajo/sand.log" 2>&1 &
pids+=($!)
for _ in $(seq 1 50); do
  grep -q 18443 "$trabajo/prod.log" 2>/dev/null && grep -q 18444 "$trabajo/sand.log" 2>/dev/null && break
  sleep 0.1
done

echo "== Emisor (ApnsSender) =="
php "$aqui/prueba-emisor.php" "$docs" "$trabajo"

if [[ -n "${PRUEBA_MYSQL_DSN:-}" ]]; then
  echo "== Endpoints (ApnsTrait) y schema.sql en MariaDB/MySQL =="
  rm -f "$trabajo"/cache/*
  php "$aqui/prueba-trait.php" "$docs" "$trabajo"
else
  echo "== Endpoints (ApnsTrait): omitida, falta PRUEBA_MYSQL_DSN =="
fi

echo "Todo en orden."
