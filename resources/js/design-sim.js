/**
 * Simulasi Desain knalpot — editor 3D parametrik.
 *
 * Model dibangun dari ukuran (mm): leheran = pipa mengikuti kurva yang
 * panjangnya dipaskan ke "Panjang leheran", silincer = loft penampang
 * (bulat/oval/hexagon/trapesium GP/megaphone) + corong + tutup + ujung,
 * lengkap dengan detail produksi (las, flange berbaut, sensor O2, cincin
 * tutup berpaku keling, plat logo, pengait per).
 *
 * Mode: Realistis (PBR, stainless sikat, studio, ambient occlusion),
 * CAD (garis tepi, grid, label ukuran), Di motor (knalpot ditempel di foto
 * motor tampak samping, bisa digeser/diperbesar/diputar lalu diunduh).
 * Estimasi tenaga dihitung di server (App\Support\ExhaustSimulator).
 */
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { CSS2DRenderer, CSS2DObject } from 'three/examples/jsm/renderers/CSS2DRenderer.js';
import { STLExporter } from 'three/examples/jsm/exporters/STLExporter.js';
import { EffectComposer } from 'three/examples/jsm/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/examples/jsm/postprocessing/RenderPass.js';
import { GTAOPass } from 'three/examples/jsm/postprocessing/GTAOPass.js';
import { OutputPass } from 'three/examples/jsm/postprocessing/OutputPass.js';
import { DecalGeometry } from 'three/examples/jsm/geometries/DecalGeometry.js';

const MM = 0.01; // 1 satuan scene = 100 mm
const SEGMENTS = 72; // titik per penampang silincer

/* ------------------------------------------------------------ penampang */
// Jarak dari sumbu ke tepi penampang pada sudut t (radian), untuk tiap bentuk.
function radiusAt(shape, w, h, t) {
    const c = Math.cos(t), s = Math.sin(t);
    switch (shape) {
        case 'round':
        case 'megaphone':
            return w / 2;
        case 'oval':
            return 1 / Math.sqrt((c / (w / 2)) ** 2 + (s / (h / 2)) ** 2);
        case 'hexagon': {
            const apothem = (w / 2) * Math.cos(Math.PI / 6);
            const sector = ((t % (Math.PI / 3)) + Math.PI / 3) % (Math.PI / 3);
            // Sudut hexagon sedikit membulat seperti hasil tekuk plat.
            return 0.9 * (apothem / Math.cos(sector - Math.PI / 6)) + 0.1 * (w / 2);
        }
        default: { // trapesium GP: atas lebar, bawah 72%, sudut membulat
            const top = w / 2, bottom = (w * 0.72) / 2, half = h / 2;
            const poly = [[top, half], [-top, half], [-bottom, -half], [bottom, -half]];
            let best = Infinity;
            for (let i = 0; i < 4; i++) {
                const [x1, y1] = poly[i], [x2, y2] = poly[(i + 1) % 4];
                const ex = x2 - x1, ey = y2 - y1;
                // Sinar d·(c, s) memotong sisi P1 + u·E: d = (P1×E)/den, u = (P1×dir)/den.
                const den = c * ey - s * ex;
                if (Math.abs(den) < 1e-9) continue;
                const d = (x1 * ey - y1 * ex) / den;
                const u = (x1 * s - y1 * c) / den;
                if (d > 0 && u >= -0.001 && u <= 1.001) best = Math.min(best, d);
            }
            const ellipse = 1 / Math.sqrt((c / top) ** 2 + (s / half) ** 2);
            return 0.82 * best + 0.18 * ellipse;
        }
    }
}

const sectionRadius = (sec, t) => (sec.r ? sec.r(t) : radiusAt(sec.shape, sec.w, sec.h, t));

/**
 * Loft: rangkaian penampang sepanjang sumbu +X.
 * sections = [{ x, shape, w, h } | { x, r(t) }] — bentuk antar penampang bebas.
 */
function loft(sections, { capStart = false, capEnd = false } = {}) {
    const positions = [], uvs = [], indices = [];
    const length = sections[sections.length - 1].x - sections[0].x || 1;
    sections.forEach((sec, i) => {
        for (let j = 0; j <= SEGMENTS; j++) {
            const t = (j / SEGMENTS) * Math.PI * 2;
            const r = sectionRadius(sec, t);
            positions.push(sec.x * MM, Math.sin(t) * r * MM, Math.cos(t) * r * MM);
            uvs.push((sec.x - sections[0].x) / length, j / SEGMENTS);
        }
        if (i > 0) {
            const a = (i - 1) * (SEGMENTS + 1), b = i * (SEGMENTS + 1);
            for (let j = 0; j < SEGMENTS; j++) indices.push(a + j, b + j, a + j + 1, a + j + 1, b + j, b + j + 1);
        }
    });
    const cap = (index, flip) => {
        const center = positions.length / 3;
        positions.push(sections[index].x * MM, 0, 0);
        uvs.push(0.5, 0.5);
        const ring = index * (SEGMENTS + 1);
        for (let j = 0; j < SEGMENTS; j++) indices.push(...(flip ? [center, ring + j + 1, ring + j] : [center, ring + j, ring + j + 1]));
    };
    if (capStart) cap(0, true);
    if (capEnd) cap(sections.length - 1, false);
    const geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    geo.setAttribute('uv', new THREE.Float32BufferAttribute(uvs, 2));
    geo.setIndex(indices);
    geo.computeVertexNormals();
    return geo;
}

// Interpolasi penampang A → B (corong).
const morph = (shapeA, wa, ha, shapeB, wb, hb, k) => t => (1 - k) * radiusAt(shapeA, wa, ha, t) + k * radiusAt(shapeB, wb, hb, t);

/* ------------------------------------------------------------- tekstur */
function canvasTexture(width, height, paint, { repeat = [1, 1], color = true } = {}) {
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    paint(canvas.getContext('2d'), width, height);
    const texture = new THREE.CanvasTexture(canvas);
    if (color) texture.colorSpace = THREE.SRGBColorSpace;
    texture.wrapS = texture.wrapT = THREE.RepeatWrapping;
    texture.repeat.set(...repeat);
    texture.anisotropy = 8;
    return texture;
}

// Garis sikat (brushed) memanjang: dipakai sebagai roughness & bump logam.
const brushedTexture = (repeat = [3, 1]) => canvasTexture(1024, 128, (g, w, h) => {
    g.fillStyle = '#8a8a8a';
    g.fillRect(0, 0, w, h);
    for (let i = 0; i < 2600; i++) {
        const y = Math.random() * h, len = 80 + Math.random() * 500, x = Math.random() * w;
        const v = 100 + Math.random() * 90;
        g.strokeStyle = `rgba(${v},${v},${v},${0.18 + Math.random() * 0.3})`;
        g.lineWidth = Math.random() < 0.85 ? 0.6 : 1.4;
        g.beginPath();
        g.moveTo(x, y);
        g.lineTo(x + len, y + (Math.random() - 0.5) * 0.6);
        g.stroke();
    }
}, { repeat, color: false });

// Warna bakar titanium: emas → ungu → biru → baja, sepanjang pipa (u).
const burntTexture = () => canvasTexture(1024, 8, (g, w, h) => {
    const grad = g.createLinearGradient(0, 0, w, 0);
    [[0, '#e0c070'], [0.1, '#c88a3c'], [0.2, '#9a4f86'], [0.32, '#5a3fb6'], [0.44, '#2f63d0'], [0.56, '#4fa4dc'], [0.7, '#a8b4c4'], [1, '#cfd4db']]
        .forEach(([at, color]) => grad.addColorStop(at, color));
    g.fillStyle = grad;
    g.fillRect(0, 0, w, h);
});

// Anyaman karbon 2×2 twill dengan kilau serat.
const carbonTexture = () => canvasTexture(256, 256, (g, w) => {
    const cell = w / 8;
    for (let x = 0; x < 8; x++) {
        for (let y = 0; y < 8; y++) {
            const along = (x + y) % 4 < 2;
            const grad = along ? g.createLinearGradient(x * cell, 0, (x + 1) * cell, 0) : g.createLinearGradient(0, y * cell, 0, (y + 1) * cell);
            grad.addColorStop(0, '#050506');
            grad.addColorStop(0.45, along ? '#2a2c31' : '#1a1b1f');
            grad.addColorStop(0.55, along ? '#24262b' : '#17181b');
            grad.addColorStop(1, '#050506');
            g.fillStyle = grad;
            g.fillRect(x * cell, y * cell, cell, cell);
        }
    }
}, { repeat: [26, 9] });

// Stiker logo bergaya decal knalpot racing (latar transparan).
const logoTexture = () => {
    const texture = canvasTexture(1024, 300, (g, w, h) => {
        const r = 46;
        g.beginPath();
        g.roundRect(12, 12, w - 24, h - 24, r);
        g.fillStyle = '#111216';
        g.fill();
        g.lineWidth = 8;
        g.strokeStyle = '#f2f2f2';
        g.stroke();
        g.save(); // garis miring merah–oranye di kiri
        g.clip();
        [['#d61f26', 40], ['#f2621f', 110], ['#ffb400', 170]].forEach(([color, x]) => {
            g.fillStyle = color;
            g.beginPath();
            g.moveTo(x, 12); g.lineTo(x + 46, 12); g.lineTo(x - 40, h - 12); g.lineTo(x - 86, h - 12);
            g.fill();
        });
        g.restore();
        const text = g.createLinearGradient(0, 70, 0, 200);
        text.addColorStop(0, '#ffe066');
        text.addColorStop(1, '#ff8a00');
        g.fillStyle = text;
        g.font = 'italic 900 128px "Racing Sans One", "Arial Black", sans-serif';
        g.textBaseline = 'middle';
        g.fillText('IHC', 240, h * 0.47);
        g.fillStyle = '#ffffff';
        g.fillText('RACING', 480, h * 0.47);
        g.font = '600 30px "Poppins", Arial, sans-serif';
        g.fillStyle = '#c7cbd3';
        g.fillText('HYPERSONIC SPEED TECH', 256, h * 0.82);
    });
    texture.wrapS = texture.wrapT = THREE.ClampToEdgeWrapping;
    return texture;
};

// Lingkungan studio foto produk: ruangan terang + softbox panjang → kilau garis di logam.
function studioEnvironment(renderer) {
    const env = new THREE.Scene();
    env.add(new THREE.Mesh(new THREE.BoxGeometry(30, 16, 30), new THREE.MeshBasicMaterial({ color: 0x74777d, side: THREE.BackSide })));
    const panel = (w, h, intensity, position, lookAt) => {
        const m = new THREE.Mesh(new THREE.PlaneGeometry(w, h), new THREE.MeshBasicMaterial({ color: new THREE.Color().setScalar(intensity), side: THREE.DoubleSide }));
        m.position.set(...position);
        m.lookAt(...lookAt);
        env.add(m);
    };
    panel(16, 3, 6, [0, 7.5, 0], [0, 0, 0]);           // softbox atas memanjang
    panel(3, 10, 4.5, [-9, 2, 6], [0, 0, 0]);          // strip kiri depan
    panel(3, 10, 3.5, [9, 2, -6], [0, 0, 0]);          // strip kanan belakang
    panel(12, 4, 1.6, [0, 1, 12], [0, 0, 0]);          // isi depan
    panel(30, 6, 2.2, [0, -7.5, 0], [0, 0, 0]);        // pantulan lantai putih
    const pmrem = new THREE.PMREMGenerator(renderer);
    const texture = pmrem.fromScene(env, 0.025).texture;
    pmrem.dispose();
    return texture;
}

// Bayangan elips lembut (kanvas persegi, diregangkan lewat skala plane) — tepi selalu pudar.
const shadowTexture = () => canvasTexture(256, 256, (g, w, h) => {
    const grad = g.createRadialGradient(w / 2, h / 2, 1, w / 2, h / 2, w / 2);
    grad.addColorStop(0, 'rgba(0,0,0,0.42)');
    grad.addColorStop(0.45, 'rgba(0,0,0,0.2)');
    grad.addColorStop(0.8, 'rgba(0,0,0,0.05)');
    grad.addColorStop(1, 'rgba(0,0,0,0)');
    g.fillStyle = grad;
    g.fillRect(0, 0, w, h);
});

/* ------------------------------------------------------------ material */
// Matcap gaya ilustrasi (vektor): pita terang–gelap tegas + kilau putih memanjang.
function matcap(stops, streak = 'rgba(255,255,255,0.95)') {
    return canvasTexture(256, 256, (g, w) => {
        g.save();
        g.beginPath();
        g.arc(w / 2, w / 2, w / 2, 0, Math.PI * 2);
        g.clip();
        const grad = g.createLinearGradient(0, 0, 0.35 * w, w);
        stops.forEach(([at, color]) => grad.addColorStop(at, color));
        g.fillStyle = grad;
        g.fillRect(0, 0, w, w);
        g.fillStyle = streak; // kilau memanjang
        g.beginPath();
        g.ellipse(w * 0.36, w * 0.3, w * 0.07, w * 0.26, -0.5, 0, Math.PI * 2);
        g.fill();
        g.restore();
    });
}

function makeMaterials() {
    const brushed = brushedTexture([6, 1]);
    const brushedFine = brushedTexture([2, 1]);
    const burnt = burntTexture();
    const logo = logoTexture();
    const metal = extra => new THREE.MeshPhysicalMaterial({ metalness: 1, roughnessMap: brushed, bumpMap: brushed, bumpScale: 0.35, anisotropy: 0.75, ...extra });
    const real = {
        stainless: metal({ color: 0xd9dde3, roughness: 0.32, clearcoat: 0.15 }),
        burnt: metal({ color: 0xffffff, map: burnt, roughness: 0.3, clearcoat: 0.35 }),
        black: new THREE.MeshPhysicalMaterial({ color: 0x16171b, metalness: 0.35, roughness: 0.62, clearcoat: 0.25, clearcoatRoughness: 0.5 }),
        carbon: new THREE.MeshPhysicalMaterial({ color: 0xffffff, map: carbonTexture(), metalness: 0.15, roughness: 0.38, clearcoat: 1, clearcoatRoughness: 0.06 }),
        polished: new THREE.MeshPhysicalMaterial({ color: 0xe3e6ea, metalness: 1, roughness: 0.16, clearcoat: 0.5 }),
        billet: metal({ color: 0x9aa0a8, roughness: 0.4, roughnessMap: brushedFine, bumpMap: brushedFine, envMapIntensity: 0.75 }),
        titanium: metal({ color: 0xffffff, map: burnt, roughness: 0.26 }),
        titaniumSolid: metal({ color: 0xa9b0bd, roughness: 0.24, roughnessMap: brushedFine, bumpMap: brushedFine }),
        weld: metal({ color: 0xb8a58c, map: burnt, roughness: 0.55, bumpScale: 1.2 }),
        bolt: new THREE.MeshPhysicalMaterial({ color: 0x3b3e45, metalness: 0.9, roughness: 0.38 }),
        satin: metal({ color: 0xdadde1, roughness: 0.33, anisotropy: 0.9, bumpScale: 0.18, envMapIntensity: 1.25 }),
        logo: new THREE.MeshPhysicalMaterial({ map: logo, transparent: true, metalness: 0, roughness: 0.28, clearcoat: 1, polygonOffset: true, polygonOffsetFactor: -4, depthWrite: false }),
        inner: new THREE.MeshStandardMaterial({ color: 0x0c0d10, metalness: 0.4, roughness: 0.9, side: THREE.DoubleSide }),
        slot: new THREE.MeshStandardMaterial({ color: 0x45484e, roughness: 0.8, side: THREE.DoubleSide, polygonOffset: true, polygonOffsetFactor: -2 }),
    };

    // Ilustrasi teknik (mode CAD): matcap tanpa lampu + garis luar tebal.
    const chrome = matcap([[0, '#ffffff'], [0.16, '#eef1f4'], [0.3, '#a7aeb7'], [0.42, '#454b54'], [0.52, '#d5dae0'], [0.66, '#ffffff'], [0.82, '#8f96a0'], [1, '#3f444c']]);
    const alu = matcap([[0, '#ffffff'], [0.25, '#dfe3e8'], [0.45, '#8c939c'], [0.6, '#eceff2'], [1, '#6b727c']]);
    const blue = matcap([[0, '#d9e6ff'], [0.14, '#6f8cff'], [0.3, '#2b3bd6'], [0.45, '#141a7a'], [0.56, '#3c5cf0'], [0.7, '#9cc0ff'], [0.85, '#2430a8'], [1, '#0d1150']]);
    const dark = matcap([[0, '#8d929c'], [0.2, '#4b4f58'], [0.45, '#1b1d22'], [0.6, '#3a3e46'], [1, '#101114']], 'rgba(255,255,255,0.55)');
    const carbonLight = matcap([[0, '#ffffff'], [0.3, '#c9cdd3'], [0.5, '#7d838c'], [0.65, '#d7dbe0'], [1, '#5c626b']], 'rgba(255,255,255,0.6)');
    const carbonIll = canvasTexture(256, 256, (g, w) => {
        const cell = w / 8;
        for (let x = 0; x < 8; x++) for (let y = 0; y < 8; y++) {
            g.fillStyle = (x + y) % 4 < 2 ? '#4a4e57' : '#33363d';
            g.fillRect(x * cell, y * cell, cell, cell);
        }
    }, { repeat: [20, 7] });
    const cap = map => new THREE.MeshMatcapMaterial({ matcap: map });
    const chromeMat = cap(chrome), darkMat = cap(dark), blueMat = cap(blue), aluMat = cap(alu);
    const ill = {
        stainless: chromeMat,
        polished: chromeMat,
        weld: cap(alu),
        billet: aluMat,
        burnt: blueMat,
        titanium: blueMat,
        titaniumSolid: aluMat,
        black: darkMat,
        bolt: darkMat,
        carbon: new THREE.MeshMatcapMaterial({ matcap: carbonLight, map: carbonIll }),
        satin: aluMat,
        logo: new THREE.MeshBasicMaterial({ map: logo, transparent: true, polygonOffset: true, polygonOffsetFactor: -4, depthWrite: false }),
        inner: new THREE.MeshBasicMaterial({ color: 0x0b0c0e, side: THREE.DoubleSide }),
        slot: new THREE.MeshBasicMaterial({ color: 0x0b0c0e, side: THREE.DoubleSide, polygonOffset: true, polygonOffsetFactor: -2 }),
    };

    return {
        real,
        ill,
        // Garis luar ilustrasi (inverted hull): sisi belakang digeser keluar mengikuti normal.
        outline: new THREE.ShaderMaterial({
            uniforms: { thickness: { value: 1.25 * MM } },
            vertexShader: 'uniform float thickness; void main() { gl_Position = projectionMatrix * modelViewMatrix * vec4(position + normalize(normal) * thickness, 1.0); }',
            fragmentShader: 'void main() { gl_FragColor = vec4(0.07, 0.08, 0.1, 1.0); }',
            side: THREE.BackSide,
        }),
        crease: new THREE.LineBasicMaterial({ color: 0x15171c }),
        shadow: new THREE.MeshBasicMaterial({ map: shadowTexture(), transparent: true, depthWrite: false }),
        dim: new THREE.LineBasicMaterial({ color: 0xe8641f }),
    };
}

/* --------------------------------------------------------------- model */
// Kurva leheran yang panjangnya dipaskan ke ukuran (mm).
function headerCurve(p, dir) {
    const drop = 170, side = 70;
    const rise = { racing: 0, low: -25, upswept: 60 }[p.header.bend] ?? 0;
    const build = run => {
        const end = new THREE.Vector3(run, -drop * 1.02 + rise, side);
        return new THREE.CatmullRomCurve3([
            new THREE.Vector3(0, 0, 0),
            new THREE.Vector3(0, -drop * 0.45, 0),
            new THREE.Vector3(22, -drop * 0.88, side * 0.35),
            new THREE.Vector3(Math.min(run * 0.35, 120), -drop * 1.06 + rise * 0.2, side * 0.85),
            end,
            end.clone().add(dir.clone().multiplyScalar(40)),
        ].map(v => v.multiplyScalar(MM)), false, 'centripetal', 0.5);
    };
    let run = Math.max(p.header.length - 200, 60);
    let curve = build(run);
    for (let i = 0; i < 12; i++) { // sesuaikan panjang lurus belakang
        const diff = p.header.length * MM - curve.getLength();
        if (Math.abs(diff) < 0.002) break;
        run = Math.max(40, run + diff / MM);
        curve = build(run);
    }
    return curve;
}

function orientTo(object, point, tangent) {
    object.position.copy(point);
    object.quaternion.setFromUnitVectors(new THREE.Vector3(0, 0, 1), tangent.clone().normalize());
}

/**
 * @param {boolean} cad  true = ilustrasi teknik (matcap + garis luar), false = realistis
 */
function buildModel(p, mats, cad) {
    const group = new THREE.Group();
    const set = cad ? mats.ill : mats.real;
    const M = key => set[key] || set.stainless;
    // Tiap benda: bayangan (realistis) atau garis luar + garis lipatan (ilustrasi).
    const mesh = (geo, mat, parent = group, { outline = true, crease = true } = {}) => {
        const m = new THREE.Mesh(geo, mat);
        m.castShadow = m.receiveShadow = !cad;
        if (cad && outline) m.add(new THREE.Mesh(geo, mats.outline));
        if (cad && crease) m.add(new THREE.LineSegments(new THREE.EdgesGeometry(geo, 38), mats.crease));
        parent.add(m);
        return m;
    };

    const s = p.silencer;
    const angle = THREE.MathUtils.degToRad(s.angle);
    const dir = new THREE.Vector3(Math.cos(angle), Math.sin(angle), 0).normalize();

    /* ---- leheran */
    const outer = p.header.diameter + p.header.wall * 2;
    const R = (outer / 2) * MM;
    const curve = headerCurve(p, dir);
    mesh(new THREE.TubeGeometry(curve, 260, R, 36, false), M(p.header.finish === 'carbon' ? 'stainless' : p.header.finish), group, { crease: false });

    // Flange 2 baut di lubang buang mesin.
    const fw = outer * 1.45, fl = outer * 3.1;
    const fShape = new THREE.Shape();
    fShape.absarc(-fl / 2 + fw / 2, 0, fw / 2, Math.PI / 2, Math.PI * 1.5, false);
    fShape.absarc(fl / 2 - fw / 2, 0, fw / 2, -Math.PI / 2, Math.PI / 2, false);
    [-1, 1].forEach(side => {
        const hole = new THREE.Path();
        hole.absarc(side * (fl / 2 - fw / 2), 0, 4.2, 0, Math.PI * 2, true);
        fShape.holes.push(hole);
    });
    const portHole = new THREE.Path();
    portHole.absarc(0, 0, p.header.diameter / 2, 0, Math.PI * 2, true);
    fShape.holes.push(portHole);
    const flangeGeo = new THREE.ExtrudeGeometry(fShape, { depth: 6, bevelEnabled: true, bevelThickness: 0.8, bevelSize: 0.8, bevelSegments: 2, curveSegments: 32 });
    flangeGeo.scale(MM, MM, MM);
    flangeGeo.rotateX(Math.PI / 2);
    mesh(flangeGeo, M('billet')).position.set(0, 7 * MM, 0);
    [-1, 1].forEach(side => { // baut + ring
        mesh(new THREE.CylinderGeometry(5.6 * MM, 5.6 * MM, 6 * MM, 6), M('bolt')).position.set(side * (fl / 2 - fw / 2) * MM, 10 * MM, 0);
        mesh(new THREE.CylinderGeometry(7 * MM, 7 * MM, 1.4 * MM, 24), M('billet'), group, { outline: false }).position.set(side * (fl / 2 - fw / 2) * MM, 7.6 * MM, 0);
    });
    const rootWeld = mesh(new THREE.TorusGeometry(R * 1.02, R * 0.11, 10, 48), M('weld'), group, { crease: false });
    orientTo(rootWeld, curve.getPoint(0.004), curve.getTangent(0.004));

    // Sensor O2 (dudukan + sensor) untuk motor injeksi.
    const o2t = 0.16, o2p = curve.getPoint(o2t), o2n = curve.getTangent(o2t).cross(new THREE.Vector3(0, 0, 1)).normalize();
    const bung = mesh(new THREE.CylinderGeometry(8 * MM, 9 * MM, 10 * MM, 6), M('billet'));
    bung.position.copy(o2p).add(o2n.clone().multiplyScalar(R + 4 * MM));
    bung.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), o2n);
    const sensor = mesh(new THREE.CylinderGeometry(5.5 * MM, 5.5 * MM, 26 * MM, 24), M('bolt'));
    sensor.position.copy(o2p).add(o2n.clone().multiplyScalar(R + 20 * MM));
    sensor.quaternion.copy(bung.quaternion);

    /* ---- silincer: corong masuk → badan → corong keluar → tutup */
    const shape = s.shape;
    const w = s.width, h = ['round', 'hexagon', 'megaphone'].includes(shape) ? s.width : s.height;
    const body = Math.max(40, s.length - s.inlet_cone - s.outlet_cone);
    const scaleIn = shape === 'megaphone' ? 0.72 : 1;
    const sections = [];
    const steps = 12;
    for (let i = 0; i <= steps; i++) {
        const k = i / steps, ease = 1 - (1 - k) ** 2.2;
        sections.push({ x: k * s.inlet_cone, r: morph('round', outer, outer, shape, w * scaleIn, h * scaleIn, ease) });
    }
    const bodyAt = k => (shape === 'megaphone' ? { r: t => radiusAt('round', w * (0.72 + 0.28 * k), 0, t) } : { shape, w, h });
    for (let i = 1; i <= 8; i++) sections.push({ x: s.inlet_cone + (i / 8) * body, ...bodyAt(i / 8) });
    const outEnd = 0.86;
    for (let i = 1; i <= steps; i++) {
        const k = i / steps;
        sections.push({ x: s.inlet_cone + body + k * s.outlet_cone, r: morph(shape, w, h, shape, w * outEnd, h * outEnd, k * k * (3 - 2 * k)) });
    }
    const silencer = new THREE.Group();
    const sleeve = mesh(loft(sections), M(s.finish), silencer, { crease: false });
    const capStart = s.inlet_cone + body + s.outlet_cone;

    // Benda kecil yang menempel di permukaan silincer, menghadap keluar (sudut t).
    const onSurface = (object, x, r, t, lift = 0) => {
        const n = new THREE.Vector3(0, Math.sin(t), Math.cos(t));
        const tangent = new THREE.Vector3(0, Math.cos(t), -Math.sin(t));
        object.position.set(x * MM, 0, 0).add(n.clone().multiplyScalar((r + lift) * MM));
        object.quaternion.setFromRotationMatrix(new THREE.Matrix4().makeBasis(new THREE.Vector3(1, 0, 0), tangent, n));
        return object;
    };

    // Klem depan & belakang (CNC) dengan lubang oval + paku keling, seperti silincer racing asli.
    const bandMat = M(s.finish === 'burnt' ? 'titaniumSolid' : 'billet');
    const band = (x, sec, len) => {
        const grow = 1.03;
        mesh(loft([
            { x: x - len / 2, r: t => sectionRadius(sec, t) * grow * 0.985 },
            { x: x - len / 2 + 1.5, r: t => sectionRadius(sec, t) * grow },
            { x: x + len / 2 - 1.5, r: t => sectionRadius(sec, t) * grow },
            { x: x + len / 2, r: t => sectionRadius(sec, t) * grow * 0.985 },
        ]), bandMat, silencer);
        for (let i = 0; i < 12; i++) {
            const t = (i / 12) * Math.PI * 2 + Math.PI / 12;
            const r = sectionRadius(sec, t) * grow;
            if (i % 2 === 0) { // lubang oval
                const slot = new THREE.Mesh(new THREE.CircleGeometry(1, 24), M('slot'));
                slot.scale.set(len * 0.16 * MM, Math.min(5, r * 0.16) * MM, 1);   // lingkaran r=1 → elips mm
                silencer.add(onSurface(slot, x, r, t, 0.35));
            } else { // paku keling
                const rivet = new THREE.Mesh(new THREE.SphereGeometry(1.9 * MM, 12, 8), M('billet'));
                rivet.castShadow = !cad;
                silencer.add(onSurface(rivet, x, r, t, 0.2));
            }
        }
    };
    band(s.inlet_cone + 8, { ...bodyAt(0), r: shape === 'megaphone' ? t => radiusAt('round', w * 0.72, 0, t) : undefined }, 16);
    band(s.inlet_cone + body - 8, bodyAt(1), 16);

    // Tutup ujung (end cap) + baut.
    mesh(loft([
        { x: capStart - 2, shape, w: w * outEnd * 1.03, h: h * outEnd * 1.03 },
        { x: capStart + 9, shape, w: w * outEnd * 1.03, h: h * outEnd * 1.03 },
        { x: capStart + 14, shape, w: w * outEnd * 0.92, h: h * outEnd * 0.92 },
    ], { capEnd: true }), M(s.finish === 'carbon' ? 'titaniumSolid' : 'carbon'), silencer);
    for (let i = 0; i < 6; i++) {
        const t = (i / 6) * Math.PI * 2;
        const r = radiusAt(shape, w * outEnd * 0.8, h * outEnd * 0.8, t) * 0.92;
        const bolt = mesh(new THREE.CylinderGeometry(3 * MM, 3 * MM, 2.5 * MM, 6), M('billet'), silencer, { crease: false });
        bolt.rotation.z = Math.PI / 2;
        bolt.position.set((capStart + 14.5) * MM, Math.sin(t) * r * MM, Math.cos(t) * r * MM);
    }

    // Ujung (tip) + bibir tebal + bagian dalam gelap.
    const tipR = (s.tip_diameter / 2) * MM;
    const tipLen = (s.tip === 'cap' ? 18 : 48) * MM;
    const tipMat = M(s.tip === 'cap' ? 'carbon' : 'polished');
    const tipOffsets = s.tip === 'double' ? [-(s.tip_diameter * 0.62), s.tip_diameter * 0.62] : [0];
    tipOffsets.forEach(offset => {
        const geo = new THREE.CylinderGeometry(tipR, tipR, tipLen, 48, 1, true);
        geo.rotateZ(-Math.PI / 2);
        if (s.tip === 'slash') { // potong miring
            const pos = geo.attributes.position;
            for (let i = 0; i < pos.count; i++) if (pos.getX(i) > 0) pos.setX(i, pos.getX(i) + pos.getY(i) * 0.9);
            geo.computeVertexNormals();
        }
        const tip = mesh(geo, tipMat, silencer);
        tip.position.set((capStart + 14) * MM + tipLen / 2, 0, offset * MM);
        if (s.tip !== 'slash') {
            const lip = mesh(new THREE.TorusGeometry(tipR * 0.97, 1.1 * MM, 8, 48), M('polished'), silencer, { crease: false });
            lip.rotation.y = Math.PI / 2;
            lip.position.set((capStart + 14) * MM + tipLen, 0, offset * MM);
        }
        const inner = new THREE.Mesh(new THREE.CylinderGeometry(tipR * 0.9, tipR * 0.9, tipLen * 0.9, 32, 1, true), M('inner'));
        inner.rotation.z = Math.PI / 2;
        inner.position.copy(tip.position);
        silencer.add(inner);
        const back = new THREE.Mesh(new THREE.CircleGeometry(tipR * 0.9, 32), M('inner'));
        back.rotation.y = Math.PI / 2;
        back.position.set((capStart + 15) * MM, 0, offset * MM);
        silencer.add(back);
    });

    // Tab braket berlubang di atas silincer (dudukan ke behel/footstep).
    const tab = new THREE.Shape();
    tab.moveTo(-12, -4);
    tab.lineTo(12, -4);
    tab.lineTo(12, 14);
    tab.absarc(0, 14, 12, 0, Math.PI, false);
    tab.lineTo(-12, -4);
    const tabHole = new THREE.Path();
    tabHole.absarc(0, 14, 4.6, 0, Math.PI * 2, true);
    tab.holes.push(tabHole);
    const tabGeo = new THREE.ExtrudeGeometry(tab, { depth: 3, bevelEnabled: true, bevelThickness: 0.5, bevelSize: 0.5, bevelSegments: 1, curveSegments: 24 });
    tabGeo.translate(0, 0, -1.5);
    tabGeo.scale(MM, MM, MM);
    const tabMesh = mesh(tabGeo, M('billet'), silencer);
    tabMesh.position.set((s.inlet_cone + Math.min(body * 0.3, 70)) * MM, radiusAt(shape, w, h, Math.PI / 2) * MM, 0);

    // Pasang silincer di ujung leheran, menghadap arah sudut naik.
    const start = curve.getPoint(1);
    silencer.position.copy(start);
    silencer.quaternion.setFromUnitVectors(new THREE.Vector3(1, 0, 0), dir);
    group.add(silencer);

    // Stiker logo: diproyeksikan ke permukaan silincer sehingga ikut melengkung.
    if (body > 90) {
        group.updateMatrixWorld(true);
        const stickerW = Math.min(body * 0.5, 140), stickerH = stickerW * 0.29;
        const at = new THREE.Vector3((s.inlet_cone + body * 0.5) * MM, 0, radiusAt(shape, w, h, 0) * MM).applyMatrix4(silencer.matrixWorld);
        const axis = dir.clone(), normal = new THREE.Vector3(0, 0, 1), up = normal.clone().cross(axis);
        const orientation = new THREE.Euler().setFromRotationMatrix(new THREE.Matrix4().makeBasis(axis, up, normal));
        const decal = new THREE.Mesh(new DecalGeometry(sleeve, at, orientation, new THREE.Vector3(stickerW * MM, stickerH * MM, w * 0.6 * MM)), M('logo'));
        decal.renderOrder = 2;
        group.add(decal);
    }

    // Las sambungan + pengait per di sambungan leheran–silincer.
    const joint = mesh(new THREE.TorusGeometry(R * 1.04, R * 0.11, 10, 48), M('weld'), group, { crease: false });
    orientTo(joint, curve.getPoint(0.985), curve.getTangent(0.985));
    const hookA = curve.getPoint(0.95).add(new THREE.Vector3(0, R + 6 * MM, 0));
    const hookB = start.clone().add(dir.clone().multiplyScalar(s.inlet_cone * 0.9 * MM)).add(new THREE.Vector3(0, radiusAt(shape, w, h, Math.PI / 2) * MM * 0.9, 0));
    mesh(new THREE.TubeGeometry(new THREE.CatmullRomCurve3([
        hookA, hookA.clone().lerp(hookB, 0.5).add(new THREE.Vector3(0, 5 * MM, 0)), hookB,
    ]), 24, 1.6 * MM, 8), M('polished'), group, { crease: false });

    return { group, curve, silencer, dims: { w, h, capStart, outer, tipLen } };
}

/* ----------------------------------------------------- label ukuran CAD */
function dimensionLayer(model, p, mats) {
    const layer = new THREE.Group();
    const label = (text, position) => {
        const el = document.createElement('div');
        el.className = 'ds-dim';
        el.textContent = text;
        const obj = new CSS2DObject(el);
        obj.position.copy(position);
        layer.add(obj);
    };
    const line = points => layer.add(new THREE.Line(new THREE.BufferGeometry().setFromPoints(points), mats.dim));

    const { silencer, curve, dims } = model;
    silencer.updateMatrixWorld(true);
    const local = (x, y, z = 0) => new THREE.Vector3(x * MM, y * MM, z * MM).applyMatrix4(silencer.matrixWorld);
    const below = -(dims.h / 2 + 34);
    const a = local(0, below), b = local(dims.capStart + 14, below);
    line([local(0, -dims.h / 2 - 6), a, b, local(dims.capStart + 14, -dims.h / 2 - 6)]);
    label(`Silincer ${Math.round(p.silencer.length)} mm`, a.clone().lerp(b, 0.5));
    label(`${Math.round(dims.w)}×${Math.round(dims.h)} mm · core Ø${p.silencer.core}`, local(p.silencer.inlet_cone + 40, dims.h / 2 + 40));
    label(`Tip Ø${p.silencer.tip_diameter}`, local(dims.capStart + 60, 0, dims.w / 2));
    label(`Leheran ${Math.round(p.header.length)} mm`, curve.getPoint(0.55).add(new THREE.Vector3(0, -0.45, 0)));
    label(`Ø${p.header.diameter} mm (dalam)`, curve.getPoint(0.12).add(new THREE.Vector3(-0.55, 0, 0)));
    return layer;
}

/* ------------------------------------------------- motor 3D (skuter matic) */
// Ukuran skuter matic dalam mm (jarak sumbu roda & diameter ban per motor).
const SCOOTER = {
    mio: { wheelbase: 1240, tire: 470 }, 'mio-m3': { wheelbase: 1260, tire: 480 }, beat: { wheelbase: 1256, tire: 470 },
    'vario-125': { wheelbase: 1280, tire: 500 }, 'vario-160': { wheelbase: 1277, tire: 530 }, 'pcx-160': { wheelbase: 1313, tire: 540 },
    nmax: { wheelbase: 1340, tire: 540 }, aerox: { wheelbase: 1350, tire: 540 }, custom: { wheelbase: 1280, tire: 500 },
};

// Matcap cat mengilap dari satu warna (gaya ilustrasi vektor).
function paintMatcap(hex) {
    const base = new THREE.Color(hex);
    const tone = k => '#' + base.clone().offsetHSL(0, k > 0 ? -0.08 * k : 0, k * 0.28).getHexString();
    return matcap([[0, '#ffffff'], [0.12, tone(0.9)], [0.3, tone(0.25)], [0.5, tone(-0.15)], [0.62, tone(-0.55)], [0.75, tone(0.1)], [0.9, tone(0.5)], [1, tone(-0.75)]]);
}

// Stiker garis bodi (generik, sporty).
const stripeTexture = () => {
    const texture = canvasTexture(1024, 256, (g, w, h) => {
        [['#d61f26', 0], ['#ff8a00', 46], ['#1d2230', 92]].forEach(([color, off]) => {
            g.fillStyle = color;
            g.beginPath();
            g.moveTo(40 + off, h - 30); g.lineTo(w - 80 + off * 0.3, 40 + off * 0.5); g.lineTo(w - 40 + off * 0.3, 60 + off * 0.5); g.lineTo(80 + off, h - 10);
            g.closePath();
            g.fill();
        });
        g.fillStyle = '#ffffff';
        g.font = 'italic 900 90px "Racing Sans One", "Arial Black", sans-serif';
        g.fillText('IHC', w * 0.56, h * 0.62);
    });
    texture.wrapS = texture.wrapT = THREE.ClampToEdgeWrapping;
    return texture;
};

/**
 * Skuter matic generik (mm, depan = −x, tanah y = 0, sisi kanan motor = +z
 * menghadap kamera). Dibangun dari profil samping yang diekstrusi dengan sudut
 * membulat → cocok untuk gaya vektor (matcap + garis luar) maupun realistis.
 */
function buildScooter(motorKey, color, mats, vector) {
    const size = SCOOTER[motorKey] || SCOOTER.custom;
    const R = size.tire / 2, wb = size.wheelbase, rearX = wb / 2, frontX = -wb / 2, axleY = R;
    const k = wb / 1240;                       // skala bodi mengikuti panjang motor
    const group = new THREE.Group();
    const outline = new THREE.ShaderMaterial({
        uniforms: { thickness: { value: 2.2 } },
        vertexShader: mats.outline.vertexShader,
        fragmentShader: mats.outline.fragmentShader,
        side: THREE.BackSide,
    });
    const paint = vector
        ? new THREE.MeshMatcapMaterial({ matcap: paintMatcap(color) })
        : new THREE.MeshPhysicalMaterial({ color, metalness: 0.25, roughness: 0.28, clearcoat: 1, clearcoatRoughness: 0.05 });
    const M = vector ? {
        paint,
        dark: mats.ill.black,
        tire: new THREE.MeshMatcapMaterial({ matcap: matcap([[0, '#6b6f77'], [0.3, '#2a2c31'], [0.6, '#121316'], [1, '#050506']], 'rgba(255,255,255,0.25)') }),
        rim: mats.ill.billet,
        chrome: mats.ill.stainless,
        seat: new THREE.MeshMatcapMaterial({ matcap: matcap([[0, '#5b5f68'], [0.35, '#2b2d33'], [0.7, '#16171a'], [1, '#0b0b0d']], 'rgba(255,255,255,0.35)') }),
        lens: new THREE.MeshMatcapMaterial({ matcap: matcap([[0, '#ffffff'], [0.4, '#cfe3f5'], [0.7, '#8fb2d4'], [1, '#e9f3ff']]) }),
        red: new THREE.MeshMatcapMaterial({ matcap: matcap([[0, '#ffb3b3'], [0.3, '#e0262e'], [0.7, '#8c0f14'], [1, '#ff6b6b']]) }),
        stripe: new THREE.MeshBasicMaterial({ map: stripeTexture(), transparent: true, polygonOffset: true, polygonOffsetFactor: -4, depthWrite: false }),
    } : {
        paint,
        dark: new THREE.MeshPhysicalMaterial({ color: 0x1d1e22, roughness: 0.55, metalness: 0.1, clearcoat: 0.3 }),
        tire: new THREE.MeshPhysicalMaterial({ color: 0x151517, roughness: 0.92 }),
        rim: mats.real.billet,
        chrome: mats.real.polished,
        seat: new THREE.MeshPhysicalMaterial({ color: 0x18191c, roughness: 0.78, sheen: 0.4 }),
        lens: new THREE.MeshPhysicalMaterial({ color: 0xe6eef6, metalness: 0.6, roughness: 0.05, clearcoat: 1 }),
        red: new THREE.MeshPhysicalMaterial({ color: 0xb3121a, roughness: 0.15, clearcoat: 1 }),
        stripe: new THREE.MeshPhysicalMaterial({ map: stripeTexture(), transparent: true, roughness: 0.3, clearcoat: 1, polygonOffset: true, polygonOffsetFactor: -4, depthWrite: false }),
    };
    const add = (geo, mat, { line = true } = {}) => {
        const m = new THREE.Mesh(geo, mat);
        m.castShadow = m.receiveShadow = !vector;
        if (vector && line) {
            m.add(new THREE.Mesh(geo, outline));
            m.add(new THREE.LineSegments(new THREE.EdgesGeometry(geo, 40), mats.crease));
        }
        group.add(m);
        return m;
    };
    // Panel dari profil samping (x, y) yang diekstrusi selebar `width` (sumbu z).
    const panel = (draw, width, mat, z = 0, bevel = 18) => {
        const shape = new THREE.Shape();
        draw(shape);
        const geo = new THREE.ExtrudeGeometry(shape, { depth: Math.max(width - bevel * 2, 4), bevelEnabled: true, bevelThickness: bevel, bevelSize: bevel * 0.8, bevelSegments: 5, curveSegments: 24 });
        geo.translate(0, 0, -(Math.max(width - bevel * 2, 4)) / 2 + z);
        return add(geo, mat);
    };
    const between = (a, b, radius, mat, segments = 18) => {
        const from = new THREE.Vector3(...a), to = new THREE.Vector3(...b);
        const geo = new THREE.CylinderGeometry(radius, radius, from.distanceTo(to), segments);
        const m = add(geo, mat);
        m.position.copy(from).lerp(to, 0.5);
        m.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), to.clone().sub(from).normalize());
        return m;
    };

    /* roda: ban + pelek 5 palang + tromol/cakram */
    const wheel = (x, front) => {
        const tire = add(new THREE.TorusGeometry(R - 42, 42, 20, 64), M.tire, { line: false });
        tire.position.set(x, axleY, 0);
        if (vector) tire.add(new THREE.Mesh(tire.geometry, outline));
        const rim = new THREE.Shape();
        rim.absarc(0, 0, R - 70, 0, Math.PI * 2, false);
        for (let i = 0; i < 5; i++) { // lubang di antara palang
            const a = (i / 5) * Math.PI * 2;
            const hole = new THREE.Path();
            hole.absarc(Math.cos(a) * (R - 125), Math.sin(a) * (R - 125), (R - 70) * 0.28, 0, Math.PI * 2, true);
            rim.holes.push(hole);
        }
        const rimGeo = new THREE.ExtrudeGeometry(rim, { depth: 34, bevelEnabled: true, bevelThickness: 4, bevelSize: 4, bevelSegments: 2, curveSegments: 40 });
        rimGeo.translate(0, 0, -17);
        add(rimGeo, M.rim).position.set(x, axleY, 0);
        add(new THREE.CylinderGeometry(34, 34, 90, 24).rotateX(Math.PI / 2), M.dark).position.set(x, axleY, 0);
        if (front) { // cakram rem
            const disc = add(new THREE.CylinderGeometry(R * 0.42, R * 0.42, 5, 40).rotateX(Math.PI / 2), M.chrome);
            disc.position.set(x, axleY, 46);
        }
    };
    wheel(frontX, true);
    wheel(rearX, false);

    /* garpu depan + sepatbor depan */
    const head = [frontX + 150 * k, 790];
    [-58, 58].forEach(z => between([frontX, axleY, z], [head[0], head[1], z * 0.8], 19, M.chrome));
    const fender = add(new THREE.TorusGeometry(R + 18, 30, 12, 40, Math.PI * 0.95).scale(1, 1, 2.1), M.paint);
    fender.position.set(frontX, axleY, 0);
    fender.rotation.z = Math.PI * 0.1;

    /* tameng depan + dek kaki */
    panel(s => {
        s.moveTo(-330 * k, 300);
        s.lineTo(-350 * k, 520);
        s.quadraticCurveTo(-385 * k, 800, -445 * k, 960);
        s.lineTo(-485 * k, 1015);
        s.quadraticCurveTo(-570 * k, 1030, -610 * k, 950);
        s.quadraticCurveTo(-655 * k, 760, -612 * k, 585);
        s.quadraticCurveTo(-570 * k, 495, -470 * k, 470);
        s.quadraticCurveTo(-390 * k, 450, -375 * k, 330);
        s.closePath();
    }, 360, M.paint);
    panel(s => { s.moveTo(-345 * k, 268); s.lineTo(95 * k, 268); s.lineTo(105 * k, 322); s.lineTo(-340 * k, 322); s.closePath(); }, 300, M.dark, 0, 10);

    /* bodi belakang + jok */
    const rearBody = panel(s => {
        s.moveTo(60 * k, 315);
        s.lineTo(82 * k, 600);
        s.quadraticCurveTo(165 * k, 718, 335 * k, 735);
        s.lineTo(760 * k, 752);
        s.quadraticCurveTo(905 * k, 756, 930 * k, 696);
        s.quadraticCurveTo(912 * k, 636, 826 * k, 606);
        s.lineTo(560 * k, 520 + (R - 235) * 0.6);
        s.quadraticCurveTo(420 * k, 470, 300 * k, 380);
        s.quadraticCurveTo(200 * k, 318, 60 * k, 315);
    }, 300, M.paint);
    panel(s => {
        s.moveTo(95 * k, 712);
        s.quadraticCurveTo(110 * k, 800, 270 * k, 818);
        s.lineTo(730 * k, 838);
        s.quadraticCurveTo(870 * k, 842, 890 * k, 786);
        s.lineTo(866 * k, 752);
        s.lineTo(335 * k, 738);
        s.quadraticCurveTo(160 * k, 724, 95 * k, 712);
    }, 286, M.seat, 0, 26);

    /* mesin + bak CVT (sisi kiri) + shock belakang */
    panel(s => {
        s.moveTo(150 * k, 330);
        s.lineTo(165 * k, 190);
        s.quadraticCurveTo(200 * k, 150, 300 * k, 160);
        s.lineTo(430 * k, 175);
        s.lineTo(430 * k, 360);
        s.quadraticCurveTo(300 * k, 380, 150 * k, 330);
    }, 230, M.dark, 0, 14);
    panel(s => {
        s.moveTo(250 * k, 300);
        s.lineTo(300 * k, 185);
        s.quadraticCurveTo(360 * k, 140, 470 * k, 150);
        s.lineTo(rearX + 40, axleY - 40);
        s.quadraticCurveTo(rearX + 95, axleY + 10, rearX + 30, axleY + 70);
        s.lineTo(560 * k, 335);
        s.quadraticCurveTo(400 * k, 360, 250 * k, 300);
    }, 120, M.dark, -110, 14);
    between([rearX + 40, axleY + 40, -110], [rearX + 120, 640, -105], 20, M.red);
    between([rearX + 40, axleY + 40, -110], [rearX + 120, 640, -105], 12, M.chrome);

    /* kepala lampu + setang + spion */
    panel(s => {
        s.moveTo(-625 * k, 955);
        s.quadraticCurveTo(-635 * k, 1065, -525 * k, 1098);
        s.lineTo(-428 * k, 1088);
        s.quadraticCurveTo(-395 * k, 1045, -428 * k, 1000);
        s.lineTo(-520 * k, 975);
        s.closePath();
    }, 380, M.paint);
    // Bola diregangkan lewat geometri (bukan skala mesh) supaya garis luar tetap tipis.
    const blob = (sx, sy, sz, mat) => add(new THREE.SphereGeometry(1, 32, 16).scale(sx, sy, sz), mat);
    blob(30, 46, 120, M.lens).position.set(-622 * k, 1018, 0);
    const handleY = 1078, handleX = -470 * k;
    between([handleX, handleY, -335], [handleX, handleY, 335], 11, M.chrome);
    [-1, 1].forEach(side => {
        between([handleX, handleY, side * 250], [handleX, handleY, side * 360], 18, M.dark);
        between([handleX + 10, handleY, side * 200], [handleX - 30, 1250, side * 265], 6, M.chrome);
        blob(10, 30, 48, M.dark).position.set(handleX - 34, 1268, side * 278);
    });

    /* sepatbor belakang + lampu belakang */
    const rearFender = add(new THREE.TorusGeometry(R + 12, 14, 8, 28, Math.PI * 0.55).scale(1, 1, 3.2), M.dark);
    rearFender.position.set(rearX, axleY, 0);
    rearFender.rotation.z = Math.PI * 0.02;
    blob(22, 30, 95, M.red).position.set(925 * k, 712, 0);

    // Stiker garis di bodi belakang sisi kanan (menghadap kamera).
    group.updateMatrixWorld(true);
    if (rearBody) {
        const decal = new THREE.Mesh(new DecalGeometry(rearBody, new THREE.Vector3(560 * k, 610, 150), new THREE.Euler(0, 0, -0.18), new THREE.Vector3(520 * k, 130, 120)), M.stripe);
        decal.renderOrder = 2;
        group.add(decal);
    }

    group.scale.setScalar(MM);
    return { group, rearX, axleY, R, tireHalf: 42 };
}

/* --------------------------------------------- analisis foto (di browser) */
function pixelsOf(img, maxDim) {
    const k = Math.min(1, maxDim / Math.max(img.naturalWidth, img.naturalHeight));
    const W = Math.max(8, Math.round(img.naturalWidth * k)), H = Math.max(8, Math.round(img.naturalHeight * k));
    const canvas = Object.assign(document.createElement('canvas'), { width: W, height: H });
    const g = canvas.getContext('2d', { willReadFrequently: true });
    g.drawImage(img, 0, 0, W, H);
    return { data: g.getImageData(0, 0, W, H).data, W, H };
}

/**
 * Cari dua roda motor di foto samping (transformasi Hough lingkaran).
 * Hasil dinormalisasi: x & r terhadap lebar foto, y terhadap tinggi foto.
 * Roda belakang = yang di sebelahnya ada massa gelap mesin/CVT (motor matic).
 */
function detectWheels(img) {
    const { data, W, H } = pixelsOf(img, 320);
    const N = W * H;
    const gray = new Float32Array(N);
    for (let i = 0; i < N; i++) gray[i] = 0.299 * data[i * 4] + 0.587 * data[i * 4 + 1] + 0.114 * data[i * 4 + 2];
    const blur = new Float32Array(N);
    for (let y = 1; y < H - 1; y++) for (let x = 1; x < W - 1; x++) {
        let sum = 0;
        for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) sum += gray[(y + dy) * W + x + dx];
        blur[y * W + x] = sum / 9;
    }
    const gx = new Float32Array(N), gy = new Float32Array(N), mag = new Float32Array(N);
    for (let y = 1; y < H - 1; y++) for (let x = 1; x < W - 1; x++) {
        const i = y * W + x, b = (dx, dy) => blur[i + dy * W + dx];
        gx[i] = b(1, -1) + 2 * b(1, 0) + b(1, 1) - b(-1, -1) - 2 * b(-1, 0) - b(-1, 1);
        gy[i] = b(-1, 1) + 2 * b(0, 1) + b(1, 1) - b(-1, -1) - 2 * b(0, -1) - b(1, -1);
        mag[i] = Math.hypot(gx[i], gy[i]);
    }
    const threshold = Math.max(24, Float32Array.from(mag).sort()[Math.floor(N * 0.85)]);
    // Roda di foto 3/4 tampak lonjong: dicari juga elips (lebar 75% dari tinggi).
    const rMin = W * 0.075, rMax = W * 0.24, nR = 14, aspects = [1, 0.75];
    const radii = Array.from({ length: nR }, (_, k) => rMin + ((rMax - rMin) * k) / (nR - 1));
    const acc = new Float32Array(aspects.length * nR * N);
    for (let y = 1; y < H - 1; y++) for (let x = 1; x < W - 1; x++) {
        const i = y * W + x;
        if (mag[i] < threshold) continue;
        const ux = gx[i] / mag[i], uy = gy[i] / mag[i];
        aspects.forEach((e, a) => radii.forEach((r, k) => {
            for (const sign of [-1, 1]) {
                const cx = Math.round(x + sign * ux * r * e), cy = Math.round(y + sign * uy * r);
                if (cx >= 0 && cy >= 0 && cx < W && cy < H) acc[(a * nR + k) * N + cy * W + cx] += 1;
            }
        }));
    }
    const candidates = [];
    aspects.forEach((e, a) => radii.forEach((r, k) => {
        const base = (a * nR + k) * N, circumference = Math.PI * r * (1 + e);
        // Roda menapak tanah: pusatnya di setengah bawah foto.
        for (let y = Math.round(H * 0.4); y < H - 2; y++) for (let x = 2; x < W - 2; x++) {
            let votes = 0;
            for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) votes += acc[base + (y + dy) * W + x + dx];
            const score = votes / circumference;
            if (score > 0.3) candidates.push({ x, y, r, e, score });
        }
    }));
    candidates.sort((a, b) => b.score - a.score);
    const peaks = [];
    for (const c of candidates) {
        if (peaks.length >= 40) break;
        if (peaks.every(p => Math.hypot(p.x - c.x, p.y - c.y) > Math.max(p.r, c.r) * 0.6)) peaks.push(c);
    }
    let best = null;
    for (let i = 0; i < peaks.length; i++) for (let j = i + 1; j < peaks.length; j++) {
        const a = peaks[i], b = peaks[j], r = (a.r + b.r) / 2, dx = Math.abs(a.x - b.x);
        if (a.r / b.r < 0.78 || a.r / b.r > 1.28) continue;                    // ukuran roda mirip
        if (Math.abs(a.y + a.r - (b.y + b.r)) > r * 0.4) continue;            // sama-sama menapak tanah
        if (dx < r * 3.2 || dx > r * 7.5) continue;                             // jarak sumbu roda wajar
        const score = a.score + b.score;
        if (!best || score > best.score) best = { a, b, score };
    }
    // Tidak yakin = jangan memaksa posisi (lebih baik diatur manual).
    if (!best || best.score < 0.95) return null;

    // Massa gelap (mesin/CVT) di sisi dalam roda belakang.
    const darkness = (wheel, other) => {
        const d = Math.sign(other.x - wheel.x);
        let sum = 0, n = 0;
        for (let y = Math.round(wheel.y - wheel.r * 0.9); y <= wheel.y + wheel.r * 0.2; y++) {
            for (let t = 1.1; t <= 2.3; t += 0.05) {
                const x = Math.round(wheel.x + d * t * wheel.r);
                if (x < 0 || y < 0 || x >= W || y >= H) continue;
                sum += 255 - gray[y * W + x];
                n++;
            }
        }
        return n ? sum / n : 0;
    };
    const [rear, front] = darkness(best.a, best.b) >= darkness(best.b, best.a) ? [best.a, best.b] : [best.b, best.a];
    const norm = w => ({ x: w.x / W, y: w.y / H, r: w.r / W });
    return { rear: norm(rear), front: norm(front), facingRight: rear.x < front.x };
}

/**
 * Baca foto knalpot contoh: pisahkan dari latar, cari sumbu memanjang,
 * lalu ukur proporsi silincer, bentuk (lurus/megaphone) dan warna/finishing.
 */
function analyzeReference(img) {
    const { data, W, H } = pixelsOf(img, 256);
    const N = W * H;
    const mask = new Uint8Array(N);
    let transparent = 0;
    for (let i = 0; i < N; i++) if (data[i * 4 + 3] < 16) transparent++;
    let groups = null;
    const isTransparent = transparent > N * 0.05;
    if (isTransparent) {
        for (let i = 0; i < N; i++) mask[i] = data[i * 4 + 3] > 128 ? 1 : 0;
    } else {
        // Warna latar dari tepi foto (2 kelompok: latar polos atau pola kotak-kotak transparan).
        const border = [];
        for (let x = 0; x < W; x++) border.push(x, (H - 1) * W + x);
        for (let y = 0; y < H; y++) border.push(y * W, y * W + W - 1);
        const lum = i => data[i * 4] + data[i * 4 + 1] + data[i * 4 + 2];
        const median = border.map(lum).sort((a, b) => a - b)[border.length >> 1];
        const mean = list => {
            const m = [0, 0, 0];
            list.forEach(i => { m[0] += data[i * 4]; m[1] += data[i * 4 + 1]; m[2] += data[i * 4 + 2]; });
            return m.map(v => v / Math.max(list.length, 1));
        };
        groups = [mean(border.filter(i => lum(i) <= median)), mean(border.filter(i => lum(i) > median))];
        for (let i = 0; i < N; i++) {
            const far = groups.every(c => Math.hypot(data[i * 4] - c[0], data[i * 4 + 1] - c[1], data[i * 4 + 2] - c[2]) > 42);
            mask[i] = far ? 1 : 0;
        }
    }
    // Ambil bagian terbesar yang menyambung (buang noda kecil).
    const label = new Int32Array(N).fill(-1);
    let bestId = -1, bestSize = 0;
    for (let start = 0; start < N; start++) {
        if (!mask[start] || label[start] !== -1) continue;
        const stack = [start];
        label[start] = start;
        let size = 0;
        while (stack.length) {
            const i = stack.pop();
            size++;
            const x = i % W, y = (i / W) | 0;
            for (const [nx, ny] of [[x + 1, y], [x - 1, y], [x, y + 1], [x, y - 1]]) {
                const j = ny * W + nx;
                if (nx >= 0 && ny >= 0 && nx < W && ny < H && mask[j] && label[j] === -1) { label[j] = start; stack.push(j); }
            }
        }
        if (size > bestSize) { bestSize = size; bestId = start; }
    }
    if (bestSize < N * 0.02) return null;

    const pts = [];
    for (let i = 0; i < N; i++) if (label[i] === bestId) pts.push(i);
    let mx = 0, my = 0;
    pts.forEach(i => { mx += i % W; my += (i / W) | 0; });
    mx /= pts.length; my /= pts.length;
    let sxx = 0, syy = 0, sxy = 0;
    pts.forEach(i => { const dx = (i % W) - mx, dy = ((i / W) | 0) - my; sxx += dx * dx; syy += dy * dy; sxy += dx * dy; });
    const theta = 0.5 * Math.atan2(2 * sxy, sxx - syy), c = Math.cos(theta), s = Math.sin(theta);
    const uv = pts.map(i => { const dx = (i % W) - mx, dy = ((i / W) | 0) - my; return [dx * c + dy * s, -dx * s + dy * c, i]; });
    const umin = Math.min(...uv.map(p => p[0])), umax = Math.max(...uv.map(p => p[0])), L = umax - umin || 1;
    const bins = 60, lo = new Array(bins).fill(Infinity), hi = new Array(bins).fill(-Infinity);
    const binOf = u => Math.min(bins - 1, Math.floor(((u - umin) / L) * bins));
    uv.forEach(([u, v]) => { const b = binOf(u); lo[b] = Math.min(lo[b], v); hi[b] = Math.max(hi[b], v); });
    const thick = lo.map((l, b) => (hi[b] > l ? hi[b] - l : 0));
    const tmax = Math.max(...thick);
    let runA = 0, runB = -1, a = -1;
    thick.forEach((t, b) => {
        if (t >= tmax * 0.55) { if (a < 0) a = b; if (b - a > runB - runA) { runA = a; runB = b; } } else a = -1;
    });
    const runLen = runB - runA + 1;
    const silencerLength = (runLen / bins) * L;
    const avg = (from, to) => { const part = thick.slice(from, to + 1).filter(Boolean); return part.reduce((x, y) => x + y, 0) / Math.max(part.length, 1); };
    const edge = Math.max(1, Math.round(runLen * 0.08)), quarter = Math.max(1, Math.round(runLen * 0.25));
    const tA = avg(runA + edge, runA + edge + quarter), tB = avg(runB - edge - quarter, runB - edge);
    const taper = Math.max(tA, tB) / Math.max(Math.min(tA, tB), 1);

    // Warna & tekstur di badan silincer. Piksel berwarna terang (stiker) dihitung terpisah
    // supaya tidak mengacaukan bacaan logam; biru-ungu dominan = titanium bakar.
    const hsv = (R, G, B) => {
        const max = Math.max(R, G, B), min = Math.min(R, G, B);
        let hue = 0;
        if (max !== min) {
            hue = max === R ? ((G - B) / (max - min)) % 6 : max === G ? (B - R) / (max - min) + 2 : (R - G) / (max - min) + 4;
            hue = (hue * 60 + 360) % 360;
        }
        return { hue, sat: max ? (max - min) / max : 0 };
    };
    let lumSum = 0, l2 = 0, n = 0, blue = 0, total = 0;
    uv.forEach(([u, , i]) => {
        const bin = binOf(u);
        if (bin < runA + edge || bin > runB - edge) return;
        const R = data[i * 4], G = data[i * 4 + 1], B = data[i * 4 + 2];
        const { hue, sat } = hsv(R, G, B);
        total++;
        if (sat > 0.25) { if (hue > 195 && hue < 300) blue++; return; }   // stiker / warna bakar
        const lum = (0.299 * R + 0.587 * G + 0.114 * B) / 255;
        lumSum += lum; l2 += lum * lum; n++;
    });
    const lum = n ? lumSum / n : 0.5, sd = n ? Math.sqrt(Math.max(l2 / n - lum * lum, 0)) : 0;
    const finish = blue > total * 0.3 ? 'burnt'
        : lum < 0.36 ? (sd > 0.06 ? 'carbon' : 'black')
            : sd > 0.22 ? 'stainless' : 'satin';

    // Diameter = nilai tengah tebal badan (bukan maksimum, yang ikut terbesar karena sudut foto).
    const body = thick.slice(runA + edge, runB - edge + 1).filter(Boolean).sort((x, y) => x - y);
    const typical = body.length ? body[body.length >> 1] : tmax;
    const ratio = Math.min(0.45, Math.max(0.18, (typical / Math.max(silencerLength, 1)) * 0.85));
    return {
        ratio, megaphone: taper > 1.45, finish, silencerShare: runLen / bins,
        // Data untuk memotong knalpot dari foto (makeCutout).
        geom: { W, H, label, bestId, groups, isTransparent, theta, runA, runB, bins },
    };
}

/**
 * Potong knalpot dari foto referensi (resolusi penuh, tepi halus), luruskan
 * sumbu memanjangnya jadi horizontal dengan silincer di kanan — sama dengan
 * arah model 3D — lalu potong rapat. Hasilnya dipakai sebagai desain aktif.
 */
function makeCutout(img, found) {
    const g0 = found.geom;
    const k = Math.min(1, 1400 / Math.max(img.naturalWidth, img.naturalHeight));
    const W = Math.round(img.naturalWidth * k), H = Math.round(img.naturalHeight * k);
    const source = Object.assign(document.createElement('canvas'), { width: W, height: H });
    const sctx = source.getContext('2d', { willReadFrequently: true });
    sctx.drawImage(img, 0, 0, W, H);
    const px = sctx.getImageData(0, 0, W, H);
    const d = px.data;
    const sx = g0.W / W, sy = g0.H / H;
    const inPart = (x, y) => { // termasuk bagian knalpot terbesar (toleransi 1 piksel di peta kecil)
        const cx = Math.floor(x * sx), cy = Math.floor(y * sy);
        for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) {
            const nx = cx + dx, ny = cy + dy;
            if (nx >= 0 && ny >= 0 && nx < g0.W && ny < g0.H && g0.label[ny * g0.W + nx] === g0.bestId) return true;
        }
        return false;
    };
    for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) {
        const i = (y * W + x) * 4;
        if (!inPart(x, y)) { d[i + 3] = 0; continue; }
        if (!g0.isTransparent) {
            const dist = Math.min(...g0.groups.map(c => Math.hypot(d[i] - c[0], d[i + 1] - c[1], d[i + 2] - c[2])));
            d[i + 3] = Math.round(Math.min(1, Math.max(0, (dist - 26) / 26)) * 255);   // tepi lembut
        }
    }
    sctx.putImageData(px, 0, 0);

    // Luruskan: sumbu (cos θ, sin θ) → horizontal; silincer di kiri → dibalik.
    const flip = (g0.runA + g0.runB) / 2 / g0.bins < 0.5;
    const c = Math.abs(Math.cos(g0.theta)), s = Math.abs(Math.sin(g0.theta));
    const RW = Math.ceil(W * c + H * s), RH = Math.ceil(W * s + H * c);
    const rotated = Object.assign(document.createElement('canvas'), { width: RW, height: RH });
    const rctx = rotated.getContext('2d', { willReadFrequently: true });
    rctx.translate(RW / 2, RH / 2);
    rctx.rotate(-g0.theta);   // tidak dicerminkan: tulisan/logo tetap terbaca
    rctx.drawImage(source, -W / 2, -H / 2);

    // Potong rapat ke batas knalpot.
    const a = rctx.getImageData(0, 0, RW, RH).data;
    let x0 = RW, y0 = RH, x1 = 0, y1 = 0;
    for (let y = 0; y < RH; y++) for (let x = 0; x < RW; x++) {
        if (a[(y * RW + x) * 4 + 3] > 24) { x0 = Math.min(x0, x); y0 = Math.min(y0, y); x1 = Math.max(x1, x); y1 = Math.max(y1, y); }
    }
    const CW = Math.max(1, x1 - x0 + 1), CH = Math.max(1, y1 - y0 + 1);
    const canvas = Object.assign(document.createElement('canvas'), { width: CW, height: CH });
    canvas.getContext('2d').drawImage(rotated, x0, y0, CW, CH, 0, 0, CW, CH);

    // Titik jangkar: 60% sepanjang silincer, di tengah tebal pada kolom itu.
    const fu = (g0.runA + 0.6 * (g0.runB - g0.runA) + 0.5) / g0.bins;
    const col = Math.min(CW - 1, Math.max(0, Math.round(fu * CW)));
    const ca = canvas.getContext('2d', { willReadFrequently: true }).getImageData(col, 0, 1, CH).data;
    let ys = 0, n = 0;
    for (let y = 0; y < CH; y++) if (ca[y * 4 + 3] > 128) { ys += y; n++; }
    return { canvas, url: canvas.toDataURL('image/png'), aspect: CH / CW, anchor: { x: fu, y: n ? ys / n / CH : 0.5 }, silencerRight: !flip };
}

/* --------------------------------------------------------------- editor */
function init(root) {
    const viewport = root.querySelector('[data-viewport]');
    const paramsInput = root.querySelector('[data-params-input]');
    const photoEl = root.querySelector('[data-motor-photo]');
    let params = JSON.parse(paramsInput.value);
    let mode = 'real';
    const savedPhotos = JSON.parse(root.querySelector('[data-motor-photos]')?.textContent || '[]');
    let photos = [...savedPhotos];

    const renderer = new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: true, alpha: true });
    renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
    renderer.toneMapping = THREE.NeutralToneMapping;   // putih tetap putih seperti foto produk
    renderer.toneMappingExposure = 1;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    viewport.appendChild(renderer.domElement);
    const labels = new CSS2DRenderer();
    labels.domElement.className = 'ds-labels';
    viewport.appendChild(labels.domElement);

    const scene = new THREE.Scene();
    const pmrem = new THREE.PMREMGenerator(renderer);
    const environment = studioEnvironment(renderer);
    const cadEnvironment = pmrem.fromScene(new RoomEnvironment(), 0.035).texture;
    const camera = new THREE.PerspectiveCamera(30, 1, 0.05, 100);
    const ortho = new THREE.OrthographicCamera(-1, 1, 1, -1, 1, 6000);
    ortho.position.set(0, 0, 3000);
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;

    // Lampu studio: kunci, isi, dan rim untuk kilau tepi logam.
    const keyLight = new THREE.DirectionalLight(0xffffff, 1.6);
    keyLight.position.set(1.5, 8, 2.5);
    keyLight.castShadow = true;
    keyLight.shadow.mapSize.set(2048, 2048);
    keyLight.shadow.radius = 10;
    keyLight.shadow.bias = -0.0004;
    Object.assign(keyLight.shadow.camera, { left: -6, right: 6, top: 6, bottom: -6 });
    const rimLight = new THREE.DirectionalLight(0xc9dcff, 1.4);
    rimLight.position.set(-5, 3, -4);
    const fillLight = new THREE.DirectionalLight(0xffffff, 0.5);
    fillLight.position.set(-2, 1, 6);
    const hemi = new THREE.HemisphereLight(0xffffff, 0x6d7281, 0.35);
    const STUDIO = 0xfbfbfc;
    const ground = new THREE.Mesh(new THREE.PlaneGeometry(60, 60), new THREE.ShadowMaterial({ opacity: 0.13 }));
    ground.rotation.x = -Math.PI / 2;
    ground.receiveShadow = true;
    ground.renderOrder = 2;
    // Lantai putih semi-transparan: pantulan knalpot di bawahnya terlihat samar.
    const floorFade = new THREE.Mesh(new THREE.PlaneGeometry(60, 60), new THREE.MeshBasicMaterial({ color: STUDIO, transparent: true, opacity: 0.93, toneMapped: false, depthWrite: false, side: THREE.DoubleSide }));
    floorFade.rotation.x = -Math.PI / 2;
    floorFade.renderOrder = 1;
    scene.add(floorFade);
    let reflection = null, floorLevel = 0;
    const grid = new THREE.GridHelper(20, 40, 0x9fb0c8, 0xd5dde8);
    scene.add(keyLight, rimLight, fillLight, hemi, ground, grid);

    // Ambient occlusion (bayangan di celah & sambungan) — layar lebar saja demi performa HP.
    const useAO = !matchMedia('(max-width: 767px)').matches;
    let composer = null, aoPass = null;
    if (useAO) {
        composer = new EffectComposer(renderer);
        composer.addPass(new RenderPass(scene, camera));
        aoPass = new GTAOPass(scene, camera, 1, 1);
        aoPass.updateGtaoMaterial({ radius: 0.35, distanceExponent: 1.4, thickness: 1, scale: 1 });
        aoPass.blendIntensity = 0.85;
        composer.addPass(aoPass);
        composer.addPass(new OutputPass());
    }

    const mats = makeMaterials();
    const mockRoot = new THREE.Group();
    scene.add(mockRoot);
    const contactShadow = new THREE.Mesh(new THREE.PlaneGeometry(1, 1), mats.shadow);
    // Bayangan elips lembut di bawah knalpot (gaya ilustrasi CAD).
    const floorShadow = new THREE.Mesh(new THREE.PlaneGeometry(1, 1), mats.shadow);
    floorShadow.rotation.x = -Math.PI / 2;
    scene.add(floorShadow);
    let showDims = false, motorStyle = 'vector', rig = null;
    // Desain dari foto referensi: knalpot asli dari foto (dipotong & diluruskan) menggantikan model bawaan.
    let refDesign = null, refMesh = null;
    const refView = Object.assign(document.createElement('div'), { className: 'ds-ref-view', hidden: true });
    const refImg = Object.assign(document.createElement('img'), { alt: 'Desain dari foto referensi' });
    refView.appendChild(refImg);
    viewport.appendChild(refView);
    function refPlane(width) {
        if (!refDesign.texture) {
            refDesign.texture = new THREE.CanvasTexture(refDesign.canvas);
            refDesign.texture.colorSpace = THREE.SRGBColorSpace;
            refDesign.texture.anisotropy = 8;
        }
        const mesh = new THREE.Mesh(new THREE.PlaneGeometry(width, width * refDesign.aspect),
            new THREE.MeshBasicMaterial({ map: refDesign.texture, transparent: true, side: THREE.DoubleSide, depthWrite: false }));
        if (!refDesign.silencerRight) mesh.scale.x = -1;   // samakan arah: silincer menghadap belakang
        return mesh;
    }
    // Titik jangkar setelah arah disamakan (silincer di kanan).
    const refAnchorX = () => (refDesign.silencerRight ? refDesign.anchor.x : 1 - refDesign.anchor.x);
    let model = null, dims = null, center = new THREE.Vector3(), size = 1, extent = new THREE.Vector3(1, 1, 1);
    let mockBase = { length: 1, height: 1, center: new THREE.Vector3() };

    const studioBackground = new THREE.Color(STUDIO);

    function rebuild() {
        if (model) { model.group.parent?.remove(model.group); model.group.traverse(o => o.geometry?.dispose()); }
        if (reflection) { scene.remove(reflection); reflection = null; }
        if (dims) { scene.remove(dims); dims.traverse(o => { o.geometry?.dispose(); if (o.element) o.element.remove(); }); dims = null; }
        if (rig) { scene.remove(rig); rig.traverse(o => o.geometry?.dispose()); rig = null; }
        if (refMesh) { refMesh.parent?.remove(refMesh); refMesh.geometry.dispose(); refMesh = null; }
        const vectorStyle = mode === 'cad' || (mode === 'motor' && motorStyle === 'vector');
        model = buildModel(params, mats, vectorStyle);
        let framed = model.group;
        if (mode === 'motor') {
            // Knalpot dipasang di sisi kanan skuter: silincer di samping roda belakang.
            const bike = buildScooter(params.motor, params.mockup.body_color, mats, vectorStyle);
            rig = new THREE.Group();
            rig.add(bike.group);
            if (refDesign) { // knalpot dari foto: dipasang sebagai panel di sisi kanan, ukuran nyata
                const width = refDesign.lengthMm * MM;
                refMesh = refPlane(width);
                const target = new THREE.Vector3((bike.rearX - 170 + 0.6 * params.silencer.length) * MM, (bike.axleY + 30) * MM, (bike.tireHalf + 70) * MM);
                refMesh.position.copy(target).sub(new THREE.Vector3((refAnchorX() - 0.5) * width, (0.5 - refDesign.anchor.y) * width * refDesign.aspect, 0));
                rig.add(refMesh);
            } else {
                const start = model.curve.getPoint(1);
                const silencerZ = (bike.tireHalf + 14 + model.dims.w / 2) * MM;
                model.group.position.set((bike.rearX - 170) * MM - start.x, (bike.axleY + 30) * MM - start.y, silencerZ - start.z);
                rig.add(model.group);
            }
            framed = rig;
        }
        const box = new THREE.Box3().setFromObject(framed);
        box.getCenter(center);
        extent = box.getSize(new THREE.Vector3());
        size = extent.length();
        if (mode === 'mockup') {
            // Pusat knalpot di titik asal, tampak samping.
            if (refDesign) {
                refMesh = refPlane(1);
                mockRoot.add(refMesh);
                mockBase = { length: 1, height: refDesign.aspect };
                contactShadow.scale.set(1.05, refDesign.aspect * 0.3, 1);
                contactShadow.position.set(0, -refDesign.aspect * 0.5, -0.01);
            } else {
                model.group.position.sub(center);
                mockRoot.add(model.group);
                mockBase = { length: extent.x, height: extent.y };
                contactShadow.scale.set(extent.x * 1.05, extent.y * 0.28, 1);
                contactShadow.position.set(0, -extent.y / 2 - extent.y * 0.04, -extent.z);
            }
            mockRoot.add(contactShadow);
            placeMockup();
        } else {
            mockRoot.remove(contactShadow);
            scene.add(rig || model.group);
            const floorY = box.min.y - (mode === 'real' ? 0.06 : 0.15);
            ground.position.y = grid.position.y = floorY + 0.002;
            floorFade.position.y = floorLevel = floorY;
            floorShadow.visible = true;
            floorShadow.renderOrder = 3;
            floorShadow.position.set(center.x, floorY + 0.004, center.z);
            if (mode === 'motor') floorShadow.scale.set(extent.x * 1.05, extent.z * 1.6, 1);
            if (mode === 'real') { // bayangan cermin knalpot di bawah lantai
                reflection = model.group.clone(true);
                reflection.scale.y = -1;
                reflection.position.y = 2 * floorY;
                reflection.traverse(o => { o.castShadow = o.receiveShadow = false; });
                scene.add(reflection);
            }
            floorShadow.scale.set(extent.x * 1.15, Math.max(extent.z, extent.x * 0.2) * 1.3, 1);
            if (mode === 'cad' && showDims) { dims = dimensionLayer(model, params, mats); scene.add(dims); }
            controls.target.copy(center);
        }
    }

    function applyMode() {
        const mock = mode === 'mockup', motor = mode === 'motor';
        const cad = mode === 'cad' || (motor && motorStyle === 'vector');
        scene.environment = cad ? cadEnvironment : environment;
        scene.background = mock ? null : (cad ? new THREE.Color(0xffffff) : studioBackground);
        renderer.setClearColor(0x000000, mock ? 0 : 1);
        ground.visible = mode === 'real' || (motor && !cad);
        floorFade.visible = mode === 'real';
        grid.visible = false;
        floorShadow.visible = !mock;
        const refShown = !!refDesign && (mode === 'real' || mode === 'cad');
        refView.hidden = !refShown;
        refView.classList.toggle('is-vector', mode === 'cad');
        renderer.domElement.style.visibility = refShown ? 'hidden' : '';
        labels.domElement.hidden = refShown;
        root.querySelector('[data-source-chip]').hidden = !refDesign;
        root.querySelector('[data-export="stl"]').disabled = !!refDesign;
        root.querySelector('[data-dims-toggle]').hidden = mode !== 'cad' || refShown;
        root.querySelector('[data-motor-style]').hidden = !motor;
        root.querySelector('[data-motor-color]').hidden = !motor;
        root.querySelectorAll('[data-style]').forEach(b => b.classList.toggle('is-active', b.dataset.style === motorStyle));
        hemi.intensity = cad ? 1.4 : 0.25;
        keyLight.intensity = cad ? 0.9 : 1.6;
        rimLight.intensity = cad ? 0 : 0.6;
        controls.enabled = !mock;
        // Realistis = foto produk: kamera tidak boleh turun ke bawah lantai (pantulan jadi knalpot kedua).
        controls.maxPolarAngle = mode === 'real' || motor ? Math.PI / 2 - 0.04 : Math.PI;
        keyLight.castShadow = !mock;   // di foto: bayangan lembut (contactShadow), bukan shadow map
        renderer.toneMappingExposure = mock ? params.mockup.brightness : 1;
        root.querySelectorAll('[data-mode]').forEach(b => b.classList.toggle('is-active', b.dataset.mode === mode));
        root.querySelector('[data-view-group]').hidden = mock || refShown;
        viewport.classList.toggle('is-mockup', mock);
        root.querySelector('[data-mockup-hint]').hidden = !mock || !photos.length;
        root.querySelector('[data-mockup-empty]').hidden = !mock || photos.length > 0;
        photoEl.hidden = !mock || !photos.length;
        if (mock) showPhoto();
        fitViewportToPhoto();
        rebuild();
        if (!mock) setView(currentView);
    }

    let currentView = 'iso';
    function setView(view) {
        currentView = view;
        const vHalf = THREE.MathUtils.degToRad(camera.fov / 2);
        const hHalf = Math.atan(Math.tan(vHalf) * camera.aspect);
        // Muat lebar (panjang knalpot) dan tinggi model, ambil yang butuh jarak terjauh.
        const width = Math.max(extent.x, extent.z), height = Math.max(extent.y, extent.z * 0.6);
        const d = Math.max((width * 0.5) / Math.tan(hHalf), (height * 0.7) / Math.tan(vHalf)) + extent.z * 0.15;
        const offsets = mode === 'motor'
            ? { iso: [-0.62, 0.3, 1], side: [0, 0.06, 1], top: [0, 1, 0.0001], back: [1, 0.2, 0.25] }
            : { iso: [0.8, 0.42, 1], side: [0, 0.02, 1], top: [0, 1, 0.0001], back: [1, 0.15, 0] };
        const [x, y, z] = offsets[view] || offsets.iso;
        camera.position.copy(center).add(new THREE.Vector3(x, y, z).normalize().multiplyScalar(d));
        controls.target.copy(center);
        controls.update();
        root.querySelectorAll('[data-view]').forEach(b => b.classList.toggle('is-active', b.dataset.view === view));
    }

    /* ---------------------------------------------- mode Di motor (foto) */
    // Kotak foto di dalam viewport (object-fit: contain).
    function photoRect(W = viewport.clientWidth, H = viewport.clientHeight) {
        const iw = photoEl.naturalWidth || 16, ih = photoEl.naturalHeight || 9;
        const scale = Math.min(W / iw, H / ih);
        const w = iw * scale, h = ih * scale;
        return { x: (W - w) / 2, y: (H - h) / 2, w, h };
    }

    // Tinggi area 3D mengikuti proporsi foto supaya foto tampil penuh (terutama di HP).
    function fitViewportToPhoto() {
        if (mode !== 'mockup' || !photoEl.naturalWidth) { viewport.style.height = ''; return; }
        const h = viewport.clientWidth * (photoEl.naturalHeight / photoEl.naturalWidth);
        viewport.style.height = Math.round(Math.max(220, Math.min(h, innerHeight * 0.72))) + 'px';
    }

    function placeMockup(W = viewport.clientWidth, H = viewport.clientHeight, rect = photoRect(W, H)) {
        const m = params.mockup;
        ortho.left = -W / 2; ortho.right = W / 2; ortho.top = H / 2; ortho.bottom = -H / 2;
        ortho.updateProjectionMatrix();
        const pxPerUnit = (m.scale * rect.w) / Math.max(mockBase.length, 0.01);
        mockRoot.position.set(rect.x + m.x * rect.w - W / 2, H / 2 - (rect.y + m.y * rect.h), 0);
        mockRoot.rotation.set(0, 0, THREE.MathUtils.degToRad(-m.rotation));
        mockRoot.scale.set(pxPerUnit * (m.flip ? -1 : 1), pxPerUnit, pxPerUnit);
        // Cahaya kunci mengikuti arah balik supaya kilau tetap dari atas-depan.
        keyLight.position.set(m.flip ? -3 : 3, 6, 5);
    }

    function showPhoto() {
        const src = photos[Math.min(params.mockup.photo, photos.length - 1)];
        if (!src) return;
        if (photoEl.getAttribute('src') !== src) {
            photoEl.onload = () => {
                matchBrightness();
                fitViewportToPhoto();
                placeMockup();
                if (autoOnLoad) { autoOnLoad = false; requestAnimationFrame(() => autoPlace()); }
            };
            photoEl.src = src;
        }
        root.querySelectorAll('[data-photo-pick]').forEach(b => b.classList.toggle('is-active', +b.dataset.photoPick === params.mockup.photo));
    }

    // Diameter luar ban standar (mm) → skala knalpot nyata di foto.
    const TIRE_MM = { mio: 470, 'mio-m3': 480, beat: 470, 'vario-125': 500, 'vario-160': 530, 'pcx-160': 540, nmax: 540, aerox: 540, custom: 500 };
    let autoOnLoad = false;
    function placeFromWheel(rear, facingRight) {
        const iw = photoEl.naturalWidth, ih = photoEl.naturalHeight, m = params.mockup;
        const tire = TIRE_MM[params.motor] ?? 500;
        const wheelPx = rear.r * 2 * iw;
        const pxPerMm = wheelPx / tire;
        m.flip = facingRight;                                         // roda belakang di kiri → silincer menghadap kiri
        m.rotation = 0;
        const lengthMm = refDesign ? refDesign.lengthMm : mockBase.length / MM;
        m.scale = THREE.MathUtils.clamp(lengthMm * pxPerMm / iw, 0.05, 3);
        // Titik 60% sepanjang silincer diletakkan sedikit di belakang as roda belakang.
        let anchor;
        if (refDesign) {
            anchor = new THREE.Vector3((refAnchorX() - 0.5) * mockBase.length, (0.5 - refDesign.anchor.y) * mockBase.height, 0);
        } else {
            model.silencer.updateMatrix();
            anchor = new THREE.Vector3(model.dims.capStart * 0.6 * MM, 0, 0).applyMatrix4(model.silencer.matrix).add(model.group.position);
        }
        const ppu = (m.scale * iw) / mockBase.length, sign = m.flip ? -1 : 1, outward = facingRight ? -1 : 1;
        const tx = rear.x * iw + outward * 0.25 * rear.r * iw;
        const ty = rear.y * ih - 0.3 * rear.r * iw;   // silincer kira-kira setinggi as roda
        m.x = (tx - sign * anchor.x * ppu) / iw;
        m.y = (ty + anchor.y * ppu) / ih;
        placeMockup();
        paramsInput.value = JSON.stringify(params);
        syncInputs();
        return { wheelPx, tire };
    }

    function autoPlace() {
        const status = root.querySelector('[data-auto-status]');
        if (mode !== 'mockup' || !model || !photoEl.naturalWidth) return false;
        const found = detectWheels(photoEl);
        if (!found) { startTapping('Roda tidak terdeteksi otomatis di foto ini. '); return false; }
        const { wheelPx, tire } = placeFromWheel(found.rear, found.facingRight);
        status.textContent = 'Roda terdeteksi (Ø' + Math.round(wheelPx) + ' px ≈ ban ' + tire + ' mm), motor menghadap ' + (found.facingRight ? 'kanan' : 'kiri') + '. Kurang pas? Tekan "Pasang dengan 2 ketukan".';
        return true;
    }

    // Cadangan yang selalu tepat: ketuk atas & bawah ban belakang.
    let taps = null;
    const tapLayer = document.createElement('div');
    tapLayer.className = 'ds-tap-layer';
    tapLayer.hidden = true;
    viewport.appendChild(tapLayer);
    function startTapping(prefix = '') {
        if (mode !== 'mockup' || !photoEl.naturalWidth) return;
        taps = [];
        tapLayer.replaceChildren();
        tapLayer.hidden = false;
        const help = Object.assign(document.createElement('p'), { className: 'ds-tap-help', textContent: 'Ketuk bagian ATAS ban belakang' });
        tapLayer.appendChild(help);
        root.querySelector('[data-auto-status]').textContent = prefix + 'Ketuk bagian ATAS ban belakang, lalu bagian BAWAH ban belakang (titik menapak tanah). Ukuran & posisi knalpot dihitung dari ukuran ban ' + (TIRE_MM[params.motor] ?? 500) + ' mm.';
    }
    tapLayer.addEventListener('pointerdown', e => {
        if (!taps) return;
        e.preventDefault();
        const box = viewport.getBoundingClientRect(), rect = photoRect();
        const px = e.clientX - box.left, py = e.clientY - box.top;
        taps.push({ x: (px - rect.x) / rect.w, y: (py - rect.y) / rect.h });
        const dot = document.createElement('span');
        dot.className = 'ds-tap-dot';
        dot.style.left = px + 'px';
        dot.style.top = py + 'px';
        tapLayer.appendChild(dot);
        const help = tapLayer.querySelector('.ds-tap-help');
        if (taps.length === 1) { help.textContent = 'Sekarang ketuk bagian BAWAH ban belakang'; return; }
        const [a, b] = taps;
        taps = null;
        tapLayer.hidden = true;
        const iw = photoEl.naturalWidth, ih = photoEl.naturalHeight;
        const diameterPx = Math.hypot((a.x - b.x) * iw, (a.y - b.y) * ih);
        const rear = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2, r: diameterPx / 2 / iw };
        const facingRight = rear.x < 0.5;                              // roda belakang di kiri foto → motor menghadap kanan
        const { tire } = placeFromWheel(rear, facingRight);
        root.querySelector('[data-auto-status]').textContent = 'Terpasang dari ukuran ban (Ø' + Math.round(diameterPx) + ' px = ' + tire + ' mm). Arah terbalik? Centang "Balik arah". Geser untuk menyempurnakan.';
    });

    // Terang knalpot disamakan dengan terang foto (sekali per foto, bila belum diatur manual).
    let brightnessTouched = Math.abs(params.mockup.brightness - 1) > 0.001;
    function matchBrightness() {
        if (brightnessTouched) return;
        const c = Object.assign(document.createElement('canvas'), { width: 48, height: 48 });
        const g = c.getContext('2d');
        g.drawImage(photoEl, 0, 0, 48, 48);
        const px = g.getImageData(0, 0, 48, 48).data;
        let sum = 0;
        for (let i = 0; i < px.length; i += 4) sum += 0.2126 * px[i] + 0.7152 * px[i + 1] + 0.0722 * px[i + 2];
        const luminance = sum / (px.length / 4) / 255;   // 0 gelap … 1 terang
        params.mockup.brightness = Math.round(THREE.MathUtils.clamp(0.45 + luminance * 1.25, 0.5, 1.5) * 20) / 20;
        renderer.toneMappingExposure = params.mockup.brightness;
        paramsInput.value = JSON.stringify(params);
        syncInputs();
    }

    // Geser = pindah, roda/cubit = ukuran.
    const pointers = new Map();
    let pinchStart = null;
    renderer.domElement.addEventListener('pointerdown', e => {
        if (mode !== 'mockup') return;
        pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        try { renderer.domElement.setPointerCapture(e.pointerId); } catch (err) { /* pointer sintetis */ }
        if (pointers.size === 2) {
            const [a, b] = [...pointers.values()];
            pinchStart = { dist: Math.hypot(a.x - b.x, a.y - b.y), scale: params.mockup.scale };
        }
    });
    renderer.domElement.addEventListener('pointermove', e => {
        if (mode !== 'mockup' || !pointers.has(e.pointerId)) return;
        const prev = pointers.get(e.pointerId);
        pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        const rect = photoRect();
        if (pointers.size === 2 && pinchStart) {
            const [a, b] = [...pointers.values()];
            params.mockup.scale = THREE.MathUtils.clamp(pinchStart.scale * Math.hypot(a.x - b.x, a.y - b.y) / pinchStart.dist, 0.05, 3);
        } else if (pointers.size === 1) {
            params.mockup.x += (e.clientX - prev.x) / rect.w;
            params.mockup.y += (e.clientY - prev.y) / rect.h;
        }
        placeMockup();
        mockupChanged();
    });
    const release = e => { pointers.delete(e.pointerId); if (pointers.size < 2) pinchStart = null; };
    renderer.domElement.addEventListener('pointerup', release);
    renderer.domElement.addEventListener('pointercancel', release);
    renderer.domElement.addEventListener('wheel', e => {
        if (mode !== 'mockup') return;
        e.preventDefault();
        params.mockup.scale = THREE.MathUtils.clamp(params.mockup.scale * Math.exp(-e.deltaY * 0.0012), 0.05, 3);
        placeMockup();
        mockupChanged();
    }, { passive: false });

    let mockupTimer = null;
    function mockupChanged() {
        paramsInput.value = JSON.stringify(params);
        clearTimeout(mockupTimer);
        mockupTimer = setTimeout(() => syncInputs(), 120);
    }

    /* ------------------------------------------------------ render loop */
    function resize() {
        const { clientWidth: w, clientHeight: h } = viewport;
        renderer.setSize(w, h);
        labels.setSize(w, h);
        composer?.setSize(w, h);
        const previous = camera.aspect;
        camera.aspect = w / Math.max(h, 1);
        camera.updateProjectionMatrix();
        if (mode === 'mockup') placeMockup();
        else if (model && Math.abs(previous - camera.aspect) > 0.2) setView(currentView);
    }
    new ResizeObserver(resize).observe(viewport);

    function draw() {
        if (mode === 'mockup') renderer.render(scene, ortho);
        else if (mode === 'real' && composer) composer.render();
        else renderer.render(scene, camera);
        labels.render(scene, mode === 'mockup' ? ortho : camera);
    }
    (function loop() {
        controls.update();
        if (reflection) reflection.visible = camera.position.y > floorLevel;
        draw();
        requestAnimationFrame(loop);
    })();

    resize();
    applyMode();
    setView('iso');
    root.querySelector('[data-loading]')?.remove();

    /* ---------------------------------------------------- isian ukuran */
    const getAt = (obj, path) => path.split('.').reduce((o, k) => o?.[k], obj);
    const setAt = (obj, path, value) => {
        const keys = path.split('.');
        keys.slice(0, -1).reduce((o, k) => (o[k] ??= {}), obj)[keys.at(-1)] = value;
    };

    function syncInputs() {
        root.querySelectorAll('[data-param]').forEach(el => {
            const v = getAt(params, el.dataset.param);
            if (v === undefined || el === document.activeElement) return;
            if (el.type === 'checkbox') el.checked = !!v;
            else if (el.value !== String(v)) {
                el.value = typeof v === 'number' && el.type === 'number' ? +v.toFixed(3) : v;
                el.dispatchEvent(new Event('change', { bubbles: false }));
            }
        });
        root.querySelectorAll('[data-range]').forEach(el => { el.value = getAt(params, el.dataset.range); });
        root.querySelectorAll('[data-choice]').forEach(group => {
            const v = getAt(params, group.dataset.choice);
            group.querySelectorAll('[data-value]').forEach(b => b.classList.toggle('is-active', b.dataset.value === v));
        });
        toggleFields();
    }

    function toggleFields() {
        root.querySelector('[data-custom-motor]').hidden = params.motor !== 'custom';
        const heightField = root.querySelector('[data-range="silencer.height"]')?.closest('[data-slider]');
        if (heightField) heightField.hidden = ['round', 'hexagon', 'megaphone'].includes(params.silencer.shape);
    }

    let pending = null;
    function changed(path = '') {
        paramsInput.value = JSON.stringify(params);
        if (path.startsWith('mockup.')) { // posisi di foto: cukup geser, tanpa bangun ulang/hitung ulang
            renderer.toneMappingExposure = mode === 'mockup' ? params.mockup.brightness : 1;
            if (mode === 'mockup') placeMockup();
            return;
        }
        rebuild();
        clearTimeout(pending);
        pending = setTimeout(simulate, 220);
    }

    root.addEventListener('input', e => {
        const el = e.target;
        if (el.dataset.range) {
            setAt(params, el.dataset.range, parseFloat(el.value));
            const number = root.querySelector(`[data-param="${el.dataset.range}"]`);
            if (number) number.value = el.value;
            changed(el.dataset.range);
            if (el.dataset.range === 'mockup.brightness') brightnessTouched = true;
        } else if (el.dataset.param && el.type === 'color') {
            setAt(params, el.dataset.param, el.value);
            paramsInput.value = JSON.stringify(params);
            if (mode === 'motor') rebuild();
        } else if (el.dataset.param && el.type === 'number') {
            if (el.value === '' || isNaN(parseFloat(el.value))) return;
            setAt(params, el.dataset.param, parseFloat(el.value));
            const range = root.querySelector(`[data-range="${el.dataset.param}"]`);
            if (range) range.value = el.value;
            changed(el.dataset.param);
        }
    });
    root.addEventListener('change', e => {
        const el = e.target;
        if (!el.dataset.param) return;
        if (el.type === 'checkbox') setAt(params, el.dataset.param, el.checked);
        else if (el.tagName === 'SELECT') setAt(params, el.dataset.param, el.value);
        else return;
        toggleFields();
        changed(el.dataset.param);
    });
    root.addEventListener('click', e => {
        const choice = e.target.closest('[data-choice] [data-value]');
        if (choice) {
            setAt(params, choice.closest('[data-choice]').dataset.choice, choice.dataset.value);
            syncInputs();
            changed(choice.closest('[data-choice]').dataset.choice);
            return;
        }
        const tab = e.target.closest('[data-tab], [data-tab-jump]');
        if (tab) {
            const name = tab.dataset.tab || tab.dataset.tabJump;
            root.querySelectorAll('[data-tab]').forEach(b => b.classList.toggle('is-active', b.dataset.tab === name));
            root.querySelectorAll('[data-panel]').forEach(panel => { panel.hidden = panel.dataset.panel !== name; });
            if (tab.dataset.tabJump) root.querySelector('.ds-controls').scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }
        const modeButton = e.target.closest('[data-mode]');
        if (modeButton) { mode = modeButton.dataset.mode; applyMode(); return; }
        const viewButton = e.target.closest('[data-view]');
        if (viewButton) { setView(viewButton.dataset.view); return; }
        const autoButton = e.target.closest('[data-auto-style]');
        if (autoButton) { autoDesign(autoButton.dataset.autoStyle, autoButton); return; }
        const photoPick = e.target.closest('[data-photo-pick]');
        if (photoPick) {
            e.preventDefault();
            params.mockup.photo = +photoPick.dataset.photoPick;
            paramsInput.value = JSON.stringify(params);
            autoOnLoad = true;
            if (mode !== 'mockup') { mode = 'mockup'; applyMode(); } else showPhoto();
            return;
        }
        if (e.target.closest('[data-tap-place]')) {
            if (mode !== 'mockup') { mode = 'mockup'; applyMode(); }
            startTapping();
            return;
        }
        if (e.target.closest('[data-auto-place]')) {
            if (mode !== 'mockup') { autoOnLoad = true; mode = 'mockup'; applyMode(); } else autoPlace();
            return;
        }
        const applyButton = e.target.closest('[data-apply-ref]');
        if (applyButton) { applyReference(applyButton.dataset.applyRef, applyButton); return; }
        if (e.target.closest('[data-mockup-reset]')) {
            Object.assign(params.mockup, { x: 0.62, y: 0.72, scale: 0.42, rotation: 0 });
            syncInputs();
            changed('mockup.x');
            return;
        }
        const styleButton = e.target.closest('[data-style]');
        if (styleButton) { motorStyle = styleButton.dataset.style; applyMode(); return; }
        const dimsButton = e.target.closest('[data-dims-toggle]');
        if (dimsButton) {
            showDims = !showDims;
            dimsButton.classList.toggle('is-active', showDims);
            rebuild();
            return;
        }
        const exportButton = e.target.closest('[data-export]');
        if (exportButton) exportModel(exportButton.dataset.export);
    });

    /* -------------------------------------------------- server: hitung */
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const post = (url, body) => fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(body),
    }).then(r => { if (!r.ok) throw new Error(r.status); return r.json(); });

    async function simulate() {
        try { renderResult(await post(root.dataset.simulateUrl, { params })); } catch (e) { /* biarkan hasil terakhir */ }
    }

    async function autoDesign(style, button) {
        button.classList.add('is-busy');
        try {
            const data = await post(root.dataset.autoUrl, {
                motor: params.motor, style, target_rpm: params.target_rpm,
                custom_cc: params.custom_cc, custom_hp: params.custom_hp, custom_rpm: params.custom_rpm,
            });
            const mockup = params.mockup;
            params = { ...data.params, mockup };   // posisi di foto motor tidak ikut berubah
            syncInputs();
            changed();
            renderResult(data.result);
            root.querySelectorAll('[data-auto-style]').forEach(b => b.classList.toggle('is-active', b === button));
        } finally {
            button.classList.remove('is-busy');
        }
    }

    /* ------------------------------------------------------ panel hasil */
    const fmt = (v, d = 0) => Number(v).toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });
    function renderResult(r) {
        const set = (key, text) => root.querySelectorAll(`[data-r="${key}"]`).forEach(el => { el.textContent = text; });
        set('gain_hp', (r.gain_hp >= 0 ? '+' : '') + fmt(r.gain_hp, 2));
        set('stock_hp', fmt(r.stock_hp, 1));
        set('peak_hp', fmt(r.peak_hp, 2));
        set('peak_rpm', fmt(r.peak_rpm));
        set('tuned_rpm', fmt(r.tuned_rpm));
        set('band', r.band);
        set('ideal.header_length', fmt(r.ideal.header_length));
        set('ideal.header_diameter', fmt(r.ideal.header_diameter, 1));
        set('silencer_volume', fmt(r.silencer_volume, 2));
        set('volume_ratio', fmt(r.volume_ratio, 1));
        set('noise_db', fmt(r.noise_db));
        set('fit.overall', r.fit.overall);
        Object.entries(r.fit).forEach(([key, value]) => {
            set(`fit.${key}`, value + '%');
            const bar = root.querySelector(`[data-fit="${key}"]`);
            if (bar) { bar.style.width = value + '%'; bar.dataset.level = value >= 80 ? 'good' : value >= 50 ? 'mid' : 'low'; }
        });
        root.querySelector('[data-score]').dataset.level = r.fit.overall >= 75 ? 'good' : r.fit.overall >= 45 ? 'mid' : 'low';
        const noise = root.querySelector('[data-noise]');
        noise.textContent = r.noise_legal ? 'Lolos ≤ 80 dB' : 'Di atas 80 dB';
        noise.dataset.level = r.noise_legal ? 'good' : 'low';
        const advice = root.querySelector('[data-advice]');
        advice.replaceChildren(...r.advice.map(text => Object.assign(document.createElement('li'), { textContent: text })));
        drawChart(root.querySelector('[data-chart]'), r);
    }

    function drawChart(svg, r) {
        const W = 320, H = 150, pad = { l: 26, r: 6, t: 8, b: 18 };
        const pts = r.curve;
        const maxHp = Math.max(...pts.map(p => p.modified), ...pts.map(p => p.stock)) * 1.08;
        const minR = pts[0].rpm, maxR = pts.at(-1).rpm;
        const x = rpm => pad.l + ((rpm - minR) / (maxR - minR)) * (W - pad.l - pad.r);
        const y = hp => H - pad.b - (hp / maxHp) * (H - pad.t - pad.b);
        const path = key => pts.map((p, i) => `${i ? 'L' : 'M'}${x(p.rpm).toFixed(1)},${y(p[key]).toFixed(1)}`).join('');
        const ns = 'http://www.w3.org/2000/svg';
        const el = (tag, attrs, text) => {
            const node = document.createElementNS(ns, tag);
            Object.entries(attrs).forEach(([k, v]) => node.setAttribute(k, v));
            if (text !== undefined) node.textContent = text;
            return node;
        };
        const nodes = [];
        for (let rpm = Math.ceil(minR / 2000) * 2000; rpm <= maxR; rpm += 2000) {
            nodes.push(el('line', { x1: x(rpm), x2: x(rpm), y1: pad.t, y2: H - pad.b, class: 'ds-grid' }));
            nodes.push(el('text', { x: x(rpm), y: H - 4, 'text-anchor': 'middle', class: 'ds-axis' }, `${rpm / 1000}rb`));
        }
        for (let hp = 0; hp <= maxHp; hp += maxHp > 20 ? 5 : 2) {
            nodes.push(el('text', { x: pad.l - 4, y: y(hp) + 3, 'text-anchor': 'end', class: 'ds-axis' }, hp));
        }
        nodes.push(el('path', { d: `${path('modified')}L${x(maxR)},${y(0)}L${x(minR)},${y(0)}Z`, class: 'ds-area' }));
        nodes.push(el('path', { d: path('stock'), class: 'ds-line-stock' }));
        nodes.push(el('path', { d: path('modified'), class: 'ds-line-mod' }));
        nodes.push(el('line', { x1: x(r.tuned_rpm), x2: x(r.tuned_rpm), y1: pad.t, y2: H - pad.b, class: 'ds-tune' }));
        nodes.push(el('circle', { cx: x(r.peak_rpm), cy: y(r.peak_hp), r: 3.5, class: 'ds-peak' }));
        svg.replaceChildren(...nodes);
    }

    renderResult(JSON.parse(root.querySelector('[data-initial-result]').textContent));
    syncInputs();

    /* ------------------------------------------------------- referensi */
    const overlay = root.querySelector('[data-overlay]');
    const overlayToggle = root.querySelector('[data-overlay-toggle]');
    const overlaySelect = root.querySelector('[data-overlay-select]');
    const overlayOpacity = root.querySelector('[data-overlay-opacity]');
    const paintOverlay = () => {
        if (!overlay || !overlayToggle) return;
        overlay.hidden = !overlayToggle.checked;
        overlay.src = overlaySelect.value;
        overlay.style.opacity = overlayOpacity.value / 100;
        if (overlayToggle.checked && mode !== 'mockup') setView('side');
    };
    [overlayToggle, overlaySelect, overlayOpacity].forEach(el => el?.addEventListener('input', paintOverlay));

    const previewFiles = (input, box) => box?.replaceChildren(...[...input.files].map(file => {
        const img = Object.assign(document.createElement('img'), { src: URL.createObjectURL(file), alt: file.name });
        const wrap = document.createElement('span');
        wrap.className = 'ds-ref is-new';
        wrap.appendChild(img);
        return wrap;
    }));
    root.querySelector('[data-ref-input]')?.addEventListener('change', e => {
        const box = root.querySelector('[data-ref-preview]');
        previewFiles(e.target, box);
        box.querySelectorAll('.ds-ref').forEach(card => {
            const span = Object.assign(document.createElement('span'), { className: 'justify-between' });
            const apply = Object.assign(document.createElement('button'), { type: 'button', className: 'ds-apply', textContent: 'Terapkan' });
            apply.dataset.applyRef = card.querySelector('img').src;
            span.append(Object.assign(document.createElement('em'), { textContent: 'Baru', className: 'not-italic' }), apply);
            card.appendChild(span);
        });
    });

    // Foto motor baru: langsung bisa dicoba di mode Di motor (sebelum disimpan).
    root.querySelector('[data-motor-input]')?.addEventListener('change', e => {
        const fresh = [...e.target.files].map(file => URL.createObjectURL(file));
        if (!fresh.length) return;
        photos = [...savedPhotos, ...fresh];
        params.mockup.photo = savedPhotos.length;
        autoOnLoad = true;
        paramsInput.value = JSON.stringify(params);
        mode = 'mockup';
        applyMode();
        root.querySelector('.ds-stage').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // "Terapkan": baca foto contoh → ubah bentuk, proporsi & finishing, lalu bandingkan dari samping.
    const SHAPE_LABEL = { gp: 'Racing GP', round: 'Bulat', oval: 'Oval', hexagon: 'Hexagon', megaphone: 'Megaphone' };
    const FINISH_LABEL = { satin: 'Titanium satin', stainless: 'Stainless poles', burnt: 'Titanium bakar', black: 'Hitam doff', carbon: 'Karbon' };
    function applyReference(url, button, { initial = false } = {}) {
        const status = root.querySelector('[data-apply-status]');
        button?.classList.add('is-busy');
        const img = new Image();
        img.onload = () => {
            button?.classList.remove('is-busy');
            const found = analyzeReference(img);
            status.hidden = initial;
            if (!found) { status.hidden = false; status.textContent = 'Knalpot di foto ini tidak terbaca (latar terlalu ramai). Coba foto dengan latar polos.'; return; }
            const sil = params.silencer;
            if (!initial) { // ukuran perkiraan untuk hitungan tenaga
                if (found.megaphone) sil.shape = 'megaphone';
                else if (sil.shape === 'megaphone') sil.shape = 'round';
                sil.width = Math.round(THREE.MathUtils.clamp(sil.length * found.ratio, 50, 180));
                sil.height = ['round', 'hexagon', 'megaphone'].includes(sil.shape) ? sil.width : Math.round(sil.width * 0.78);
                sil.finish = found.finish;
            }
            const cut = makeCutout(img, found);
            refDesign = { ...cut, lengthMm: sil.length / Math.max(found.silencerShare, 0.3), share: found.silencerShare, button };
            refImg.src = cut.url;
            params.source = { type: 'reference', index: 0, share: found.silencerShare };
            if (overlay) overlay.hidden = true;
            if (overlayToggle) overlayToggle.checked = false;
            syncInputs();
            if (initial) { applyMode(); return; }
            changed();
            status.textContent = 'Desain sekarang memakai knalpot dari foto referensi ini (bentuk & warna asli). Lihat di Realistis, CAD, Motor 3D, atau Di foto. Estimasi tenaga memakai ukuran perkiraan: silincer Ø' + sil.width + ' mm.';
            if (mode !== 'real' && mode !== 'motor' && mode !== 'mockup') mode = 'real';
            applyMode();
            root.querySelector('.ds-stage').scrollIntoView({ behavior: 'smooth', block: 'start' });
        };
        img.onerror = () => { button?.classList.remove('is-busy'); status.hidden = false; status.textContent = 'Foto tidak bisa dibaca.'; };
        img.src = url;
    }

    // Urutan foto referensi saat disimpan (yang dicentang Hapus dilewati, foto baru di belakang).
    function finalRefIndex() {
        let index = 0;
        for (const b of root.querySelectorAll('[data-apply-ref]')) {
            const removed = b.closest('.ds-ref')?.querySelector('input[name="remove_references[]"]')?.checked;
            if (b === refDesign?.button) return removed ? -1 : index;
            if (!removed) index++;
        }
        return -1;
    }

    root.querySelector('[data-source-reset]')?.addEventListener('click', () => {
        refDesign = null;
        params.source = { type: 'parametric' };
        paramsInput.value = JSON.stringify(params);
        applyMode();
    });

    // Desain tersimpan memakai foto referensi → muat ulang potongannya.
    if (params.source?.type === 'reference') {
        const saved = root.querySelectorAll('[data-apply-ref]')[params.source.index];
        if (saved) applyReference(saved.dataset.applyRef, saved, { initial: true });
    }

    /* ------------------------------------------------- ekspor & simpan */
    function refStudioImage(type, quality) {
        const W = 1600, H = 1000;
        const canvas = Object.assign(document.createElement('canvas'), { width: W, height: H });
        const g = canvas.getContext('2d');
        g.fillStyle = '#fbfbfc';
        g.fillRect(0, 0, W, H);
        const scale = Math.min((W * 0.82) / refDesign.canvas.width, (H * 0.7) / refDesign.canvas.height);
        const w = refDesign.canvas.width * scale, h = refDesign.canvas.height * scale;
        g.save(); // bayangan elips lembut di bawah knalpot
        g.filter = 'blur(18px)';
        g.fillStyle = 'rgba(0,0,0,0.16)';
        g.beginPath();
        g.ellipse(W / 2, (H + h) / 2 + 12, w * 0.44, Math.max(14, h * 0.07), 0, 0, Math.PI * 2);
        g.fill();
        g.restore();
        g.drawImage(refDesign.canvas, (W - w) / 2, (H - h) / 2, w, h);
        return canvas.toDataURL(type, quality);
    }

    function snapshot(type = 'image/png', quality) {
        if (refDesign) return refStudioImage(type, quality);
        const previous = mode;
        if (previous !== 'real') { mode = 'real'; applyMode(); }
        draw();
        const data = renderer.domElement.toDataURL(type, quality);
        if (previous !== 'real') { mode = previous; applyMode(); }
        return data;
    }

    // Foto motor + knalpot 3D digabung pada resolusi asli foto.
    function mockupImage() {
        const iw = photoEl.naturalWidth, ih = photoEl.naturalHeight;
        const k = Math.min(1, 2400 / Math.max(iw, ih));
        const W = Math.round(iw * k), H = Math.round(ih * k);
        const canvas = Object.assign(document.createElement('canvas'), { width: W, height: H });
        const g = canvas.getContext('2d');
        g.drawImage(photoEl, 0, 0, W, H);
        const ratio = renderer.getPixelRatio();
        renderer.setPixelRatio(1);
        renderer.setSize(W, H, false);
        placeMockup(W, H, { x: 0, y: 0, w: W, h: H });
        renderer.render(scene, ortho);
        g.drawImage(renderer.domElement, 0, 0, W, H);
        renderer.setPixelRatio(ratio);
        resize();
        return canvas.toDataURL('image/png');
    }

    function download(href, name) {
        const a = Object.assign(document.createElement('a'), { href, download: name });
        document.body.appendChild(a);
        a.click();
        a.remove();
    }

    function exportModel(type) {
        const name = (root.querySelector('input[name="name"]')?.value || 'desain-knalpot').trim().replace(/[^\w-]+/g, '-').toLowerCase();
        if (type === 'png') { download(snapshot(), `${name}.png`); return; }
        if (type === 'mockup') {
            if (!photos.length) { root.querySelector('[data-tab-jump="mockup"]')?.click(); return; }
            if (mode !== 'mockup') { mode = 'mockup'; applyMode(); }
            const go = () => download(mockupImage(), `${name}-di-motor.png`);
            photoEl.complete && photoEl.naturalWidth ? go() : photoEl.addEventListener('load', go, { once: true });
            return;
        }
        if (refDesign) return;   // foto referensi tidak punya data 3D untuk STL
        // STL dalam milimeter (scene memakai 1 satuan = 100 mm).
        const exportGroup = buildModel(params, mats, false).group;
        exportGroup.scale.setScalar(1 / MM);
        exportGroup.updateMatrixWorld(true);
        const stl = new STLExporter().parse(exportGroup, { binary: true });
        const url = URL.createObjectURL(new Blob([stl], { type: 'model/stl' }));
        download(url, `${name}.stl`);
        setTimeout(() => URL.revokeObjectURL(url), 2000);
    }

    root.addEventListener('submit', () => {
        if (refDesign) {
            const index = finalRefIndex();
            params.source = index < 0 ? { type: 'parametric' } : { type: 'reference', index, share: refDesign.share };
        }
        paramsInput.value = JSON.stringify(params);
        try { root.querySelector('[data-thumbnail-input]').value = snapshot('image/jpeg', 0.84); } catch (e) { /* pratinjau opsional */ }
    });
}

document.querySelectorAll('[data-design-sim]').forEach(init);
