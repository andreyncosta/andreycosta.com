/* Eixo client: SVG charts from inline JSON, confirmations and print. No dependencies. */
(function () {
  'use strict';
  var NS = 'http://www.w3.org/2000/svg';

  function el(tag, attrs, text) {
    var n = document.createElementNS(NS, tag);
    for (var k in attrs) n.setAttribute(k, attrs[k]);
    if (text != null) n.textContent = text;
    return n;
  }
  function day(d) { return Date.parse(d + 'T12:00:00Z') / 864e5; }
  function fmt(v) { return (Math.round(v * 1000) / 1000).toLocaleString('pt-BR'); }
  function br(d) { var p = d.split('-'); return p[2] + '/' + p[1] + '/' + p[0].slice(2); }

  function draw(fig) {
    var data = JSON.parse(document.getElementById(fig.getAttribute('data-chart')).textContent);
    var pts = data.points.filter(function (p) { return p.d && typeof p.v === 'number'; });
    var box = fig.querySelector('.chart-canvas');
    if (!pts.length) { box.textContent = 'Sem valores numéricos para desenhar.'; return; }
    var W = 720, H = 300, L = 56, R = 16, T = 34, B = 34;
    var xs = pts.map(function (p) { return day(p.d); });
    var vals = [];
    pts.forEach(function (p) { vals.push(p.v); if (p.r) { if (p.r.low != null) vals.push(p.r.low); if (p.r.high != null) vals.push(p.r.high); } });
    var x0 = Math.min.apply(null, xs), x1 = Math.max.apply(null, xs);
    (data.markers || []).forEach(function (m) { var d = day(m.date); if (d >= x0 - 365 && d <= x1 + 30) { x0 = Math.min(x0, d); x1 = Math.max(x1, d); } });
    if (x1 === x0) { x0 -= 15; x1 += 15; }
    var y0 = Math.min.apply(null, vals), y1 = Math.max.apply(null, vals);
    var pad = (y1 - y0 || Math.abs(y1) || 1) * 0.12; y0 = Math.max(0, y0 - pad); y1 += pad;
    var X = function (d) { return L + (d - x0) / (x1 - x0) * (W - L - R); };
    var Y = function (v) { return H - B - (v - y0) / (y1 - y0) * (H - T - B); };
    var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H, role: 'img', 'aria-label': 'Gráfico da série; os valores estão na tabela.' });

    // Lab reference band per point, up to the next point (each lab's own range).
    pts.forEach(function (p, i) {
      if (!p.r) return;
      var lo = p.r.low != null ? p.r.low : y0, hi = p.r.high != null ? p.r.high : y1;
      var xa = i === 0 ? X(xs[i]) - 6 : (X(xs[i - 1]) + X(xs[i])) / 2;
      var xb = i === pts.length - 1 ? X(xs[i]) + 6 : (X(xs[i]) + X(xs[i + 1])) / 2;
      svg.appendChild(el('rect', { x: xa, y: Y(hi), width: Math.max(2, xb - xa), height: Math.max(1, Y(lo) - Y(hi)), class: 'band' }));
    });
    for (var g = 0; g <= 4; g++) {
      var v = y0 + (y1 - y0) * g / 4, y = Y(v);
      svg.appendChild(el('line', { x1: L, x2: W - R, y1: y, y2: y, class: 'grid' }));
      svg.appendChild(el('text', { x: L - 6, y: y + 4, 'text-anchor': 'end', class: 'tick' }, fmt(v)));
    }
    // Dose-change markers on top.
    (data.markers || []).forEach(function (m) {
      var d = day(m.date); if (d < x0 || d > x1) return;
      var x = X(d);
      svg.appendChild(el('line', { x1: x, x2: x, y1: T - 8, y2: H - B, class: 'marker' }));
      var t = el('text', { x: x, y: T - 12, 'text-anchor': 'middle', class: 'marker-label' }, '◆');
      t.appendChild(el('title', {}, br(m.date) + ' · ' + m.label));
      svg.appendChild(t);
    });
    // Line, broken where lab/method (or measuring conditions) change.
    var path = '';
    pts.forEach(function (p, i) {
      if (p.b && i > 0) {
        var xm = (X(xs[i - 1]) + X(xs[i])) / 2;
        svg.appendChild(el('line', { x1: xm, x2: xm, y1: T, y2: H - B, class: 'break' }));
      }
      path += (i === 0 || p.b ? 'M' : 'L') + X(xs[i]).toFixed(1) + ',' + Y(p.v).toFixed(1) + ' ';
    });
    svg.appendChild(el('path', { d: path, class: 'line' }));
    pts.forEach(function (p, i) {
      var c = el('circle', { cx: X(xs[i]), cy: Y(p.v), r: p.op ? 5 : 4, class: p.op ? 'dot open' : 'dot' });
      c.appendChild(el('title', {}, br(p.d) + ': ' + (p.op || '') + fmt(p.v) + ' ' + (data.unit || '') + (p.lab ? ' · ' + p.lab : '')));
      svg.appendChild(c);
    });
    svg.appendChild(el('text', { x: L, y: H - 10, class: 'tick' }, br(pts[0].d)));
    svg.appendChild(el('text', { x: W - R, y: H - 10, 'text-anchor': 'end', class: 'tick' }, br(pts[pts.length - 1].d)));
    svg.appendChild(el('text', { x: L, y: 14, class: 'tick' }, data.unit || ''));
    box.appendChild(svg);
  }

  document.addEventListener('DOMContentLoaded', function () {
    Array.prototype.forEach.call(document.querySelectorAll('figure[data-chart]'), draw);
    document.addEventListener('click', function (e) {
      var t = e.target.closest('[data-confirm]');
      if (t && !window.confirm(t.getAttribute('data-confirm'))) e.preventDefault();
      if (e.target.closest('[data-print]')) window.print();
    });
  });
})();
