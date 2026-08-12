import { rgb } from '../math/palette.js';
import { MARKER_STYLES, MARKERS } from './markerStyles.js';

export class MarkerLayer {
  constructor({ field, theme, markers = MARKERS, styles = MARKER_STYLES }) {
    this.field = field;
    this.theme = theme;
    this.markers = markers;
    this._grads = new Map();
    this.styles = {};
    for (const key of Object.keys(styles)) {
      const s = styles[key];
      this.styles[key] = {
        ...s,
        thickness: field.scaled(s.thickness),
        over: field.scaled(s.over),
      };
    }
  }

  get count() { return this.markers.length; }

  positionOf(i) {
    const m = this.markers[i];
    return this.field.get(m.river).at(m.s);
  }

  descriptorOf(i, colour) {
    const m = this.markers[i];
    return { river: m.river, s: m.s, colour };
  }

  draw(ctx, i, alpha, colour, grow = 1) {
    const m = this.markers[i];
    const style = this.styles[m.style];
    const river = this.field.get(m.river);
    const p = river.at(m.s);
    const d = river.tangent(m.s);
    const halfWidth = river.width(m.s);

    const angle = Math.atan2(d[0], -d[1]) + (style.rake * Math.PI) / 180;
    const half = (halfWidth * style.span * 0.92 + style.over * 1.05) * (1 + (grow - 1) * 0.30);
    const key = colour ? `${i}:lit` : grow === 1 ? `${i}:rest` : `${i}:hot`;
    this._lozenge(ctx, key, p[0], p[1], angle, half, style.thickness * grow, alpha * style.weight, colour);
  }

  drawGlow(ctx, i, colour) {
    const { glowRadius: r, glowAlpha: a } = this.theme.markers;
    const p = this.positionOf(i);
    const g = ctx.createRadialGradient(p[0], p[1], 0, p[0], p[1], r);
    g.addColorStop(0, `rgba(${rgb(colour)},${a})`);
    g.addColorStop(1, `rgba(${rgb(colour)},0)`);
    ctx.fillStyle = g;
    ctx.fillRect(p[0] - r, p[1] - r, r * 2, r * 2);
  }

  invalidate() { this._grads.clear(); }

  _gradient(ctx, key, half, alpha, colour) {
    let g = this._grads.get(key);
    if (g) return g;
    const c = rgb(colour || this.theme.markers.neutral);
    const a = alpha.toFixed(4);
    g = ctx.createLinearGradient(-half, 0, half, 0);
    g.addColorStop(0, `rgba(${c},0)`);
    g.addColorStop(0.20, `rgba(${c},${a})`);
    g.addColorStop(0.80, `rgba(${c},${a})`);
    g.addColorStop(1, `rgba(${c},0)`);
    this._grads.set(key, g);
    return g;
  }

  _lozenge(ctx, key, cx, cy, angle, half, thickness, alpha, colour) {
    ctx.save();
    ctx.translate(cx, cy);
    ctx.rotate(angle);
    ctx.fillStyle = this._gradient(ctx, key, half, alpha, colour);

    this._capsule(ctx, -half, -thickness / 2, half * 2, thickness, thickness / 2);
    ctx.fill();
    ctx.restore();
  }

  _capsule(ctx, x, y, w, h, r) {
    if (ctx.roundRect) { ctx.beginPath(); ctx.roundRect(x, y, w, h, r); return; }
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + w - r, y); ctx.arcTo(x + w, y, x + w, y + r, r);
    ctx.lineTo(x + w, y + h - r); ctx.arcTo(x + w, y + h, x + w - r, y + h, r);
    ctx.lineTo(x + r, y + h); ctx.arcTo(x, y + h, x, y + h - r, r);
    ctx.lineTo(x, y + r); ctx.arcTo(x, y, x + r, y, r);
    ctx.closePath();
  }
}
