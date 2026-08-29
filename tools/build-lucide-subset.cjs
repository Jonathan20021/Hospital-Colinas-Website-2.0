/**
 * Genera assets/js/lucide-subset.js con SOLO los iconos que el sitio usa.
 *
 *   node tools/build-lucide-subset.cjs
 *
 * POR QUE: el bundle completo son 400 KB (109 KB con gzip) con 1982 iconos, de
 * los que el sitio usa poco mas de 200. En produccion era el mayor elemento de
 * la cadena critica: 1243 ms.
 *
 * COMO: lee los nombres reales de los `data-lucide` del repo, saca esos iconos
 * del bundle original (que se conserva como fuente) y escribe un archivo con la
 * misma API publica: window.lucide.createIcons().
 *
 * El SVG que produce es IDENTICO al del bundle: mismos atributos, mismo orden y
 * las mismas clases `lucide lucide-<nombre>` delante de las del <i>.
 */

const fs = require('fs');
const path = require('path');

const raiz = path.join(__dirname, '..');
const bundle = path.join(raiz, 'assets/js/lucide.min.js');
const salida = path.join(raiz, 'assets/js/lucide-subset.js');

/* ---------- 1. Nombres usados en el repo ---------- */
const patrones = [/data-lucide=\\?["']([a-z][a-z0-9-]*)/g, /data-lucide="([a-z][a-z0-9-]*)"/g];
const exts = ['.php', '.js', '.html'];
const saltar = new Set(['node_modules', '.git', '.claude', 'vendor']);

const usados = new Set();
(function recorrer(dir) {
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
        if (saltar.has(e.name)) continue;
        const p = path.join(dir, e.name);
        if (e.isDirectory()) { recorrer(p); continue; }
        if (!exts.includes(path.extname(e.name))) continue;
        if (e.name === 'lucide.min.js' || e.name === 'lucide-subset.js') continue;
        const txt = fs.readFileSync(p, 'utf8');
        for (const re of patrones) {
            re.lastIndex = 0;
            let m;
            while ((m = re.exec(txt)) !== null) usados.add(m[1]);
        }
    }
})(raiz);

/* Iconos que llegan desde DATOS (data.php, content.php, categorias de la BD) y
   por eso no salen en el escaneo estatico. Ver el propio archivo. */
const extra = path.join(__dirname, 'lucide-icons-extra.txt');
if (fs.existsSync(extra)) {
    fs.readFileSync(extra, 'utf8').split('\n')
        .map(l => l.trim())
        .filter(l => l && !l.startsWith('#'))
        .forEach(l => usados.add(l));
}

/* ---------- 2. Sacar los iconos del bundle ---------- */
const lucide = require(bundle);
const pascal = (kebab) => kebab.split('-').map(s => s.charAt(0).toUpperCase() + s.slice(1)).join('');

const iconos = {};
const faltan = [];
for (const nombre of [...usados].sort()) {
    const clave = pascal(nombre);
    if (!lucide[clave]) { faltan.push(nombre); continue; }
    iconos[nombre] = lucide[clave];
}

console.log(`Usados en el repo: ${usados.size}`);
console.log(`Encontrados:       ${Object.keys(iconos).length}`);
if (faltan.length) console.log(`NO EXISTEN en lucide: ${faltan.join(', ')}`);

/* ---------- 3. Escribir el subconjunto ---------- */
const cabecera = `/*!
 * Subconjunto de lucide generado por tools/build-lucide-subset.cjs — NO editar a mano.
 * ${Object.keys(iconos).length} iconos de los 1982 del paquete completo.
 * El bundle original se conserva en assets/js/lucide.min.js como fuente.
 * lucide — ISC License.
 */
`;

const cuerpo = `(function (raiz) {
    'use strict';

    // Ruta del bundle completo, derivada de la de este mismo archivo, para el
    // respaldo de mas abajo.
    var YO = document.currentScript && document.currentScript.src;
    var COMPLETO = YO ? YO.replace(/lucide-subset\\.js.*$/, 'lucide.min.js') : null;
    var respaldoPedido = false;

    var BASE = {
        xmlns: 'http://www.w3.org/2000/svg',
        width: 24,
        height: 24,
        viewBox: '0 0 24 24',
        fill: 'none',
        stroke: 'currentColor',
        'stroke-width': 2,
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round'
    };

    var ICONOS = ${JSON.stringify(iconos)};

    function crear(spec) {
        var etiqueta = spec[0], attrs = spec[1] || {}, hijos = spec[2];
        var nodo = document.createElementNS(BASE.xmlns, etiqueta);
        Object.keys(attrs).forEach(function (k) { nodo.setAttribute(k, String(attrs[k])); });
        if (hijos && hijos.length) hijos.forEach(function (h) { nodo.appendChild(crear(h)); });
        return nodo;
    }

    function reemplazar(el, nameAttr, attrs) {
        var nombre = el.getAttribute(nameAttr);
        var def = ICONOS[nombre];
        if (!def) return false;

        var svg = document.createElementNS(BASE.xmlns, 'svg');
        Object.keys(BASE).forEach(function (k) { svg.setAttribute(k, String(BASE[k])); });
        Object.keys(attrs || {}).forEach(function (k) { svg.setAttribute(k, String(attrs[k])); });

        // Mismo orden que el bundle original: primero el atributo del nombre,
        // luego la clase (lucide lucide-<nombre> + las del <i>), luego el resto.
        svg.setAttribute(nameAttr, nombre);
        var clases = ['lucide', 'lucide-' + nombre];
        var propias = (el.getAttribute('class') || '').split(/\\s+/).filter(Boolean);
        svg.setAttribute('class', clases.concat(propias).join(' '));

        Array.prototype.slice.call(el.attributes).forEach(function (a) {
            if (a.name === nameAttr || a.name === 'class') return;
            svg.setAttribute(a.name, a.value);
        });

        def.forEach(function (spec) { svg.appendChild(crear(spec)); });
        if (el.parentNode) el.parentNode.replaceChild(svg, el);
        return true;
    }

    function createIcons(opciones) {
        opciones = opciones || {};
        var nameAttr = opciones.nameAttr || 'data-lucide';
        var root = opciones.root || document;
        var attrs = opciones.attrs || {};
        var faltaron = 0;
        Array.prototype.slice.call(root.querySelectorAll('[' + nameAttr + ']'))
            .forEach(function (el) {
                if (el.tagName.toLowerCase() === 'svg') return; // ya convertido
                if (!reemplazar(el, nameAttr, attrs)) faltaron++;
            });

        // Respaldo: si queda algun icono sin convertir es que no esta en este
        // subconjunto (tipico de iconos que llegan desde la BD). Se carga el
        // bundle completo UNA vez y se reintenta, para que nunca falte un icono
        // por haber recortado. Si pasa, anadirlo a tools/lucide-icons-extra.txt
        // y regenerar, que es lo que evita bajarse los 400 KB.
        if (COMPLETO && !respaldoPedido && faltaron > 0) {
            respaldoPedido = true;
            var s = document.createElement('script');
            s.src = COMPLETO;
            s.onload = function () {
                if (raiz.lucide && raiz.lucide.createIcons !== createIcons) {
                    raiz.lucide.createIcons(opciones);
                }
            };
            document.head.appendChild(s);
        }
    }

    raiz.lucide = { icons: ICONOS, createIcons: createIcons, createElement: crear };
})(typeof globalThis !== 'undefined' ? globalThis : window);
`;

fs.writeFileSync(salida, cabecera + cuerpo);
const kb = (f) => (fs.statSync(f).size / 1024).toFixed(0);
console.log(`\nassets/js/lucide.min.js    ${kb(bundle)} KB  (fuente, se conserva)`);
console.log(`assets/js/lucide-subset.js ${kb(salida)} KB  <- el que se sirve`);
