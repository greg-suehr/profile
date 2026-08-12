import { rgb } from '../math/palette.js';

export class Plate {
  constructor({ field, theme, grain }) {
    this.field = field;
    this.theme = theme;
    this.grain = grain;
    this.canvas = document.createElement('canvas');
    this.ctx = this.canvas.getContext('2d');
  }

  bake(scale) {
    const { width: W, height: H } = this.field.canvas;
    const ctx = this.ctx;

    this.canvas.width = Math.max(1, Math.round(W * scale));
    this.canvas.height = Math.max(1, Math.round(H * scale));
    ctx.setTransform(scale, 0, 0, scale, 0, 0);
    ctx.clearRect(0, 0, W, H);

    ctx.fillStyle = `rgb(${rgb(this.theme.ground)})`;
    ctx.fillRect(0, 0, W, H);

    this._keyLight(ctx, W, H);
    this._vignette(ctx, W, H);

    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    this._ticks(ctx, W, H);
    this._bed(ctx);

    this.grain.paint(ctx, this.canvas.width, this.canvas.height);
    return this.canvas;
  }

  _keyLight(ctx, W, H) {
    const k = this.theme.plate.key;
    const g = ctx.createRadialGradient(k.x, k.y, k.inner, k.x, k.y, k.outer);
    g.addColorStop(0, `rgba(${rgb(k.tint)},${k.a0})`);
    g.addColorStop(0.5, `rgba(${rgb(k.tint)},${k.a1})`);
    g.addColorStop(1, `rgba(${rgb(this.theme.ground)},0)`);
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, H);
  }

  _vignette(ctx, W, H) {
    const v = this.theme.plate.vignette;
    const g = ctx.createRadialGradient(v.x, v.y, v.inner, v.x, v.y, v.outer);
    g.addColorStop(0, `rgba(${rgb(v.tint)},0)`);
    g.addColorStop(0.58, `rgba(${rgb(v.tint)},${v.max * 0.5})`);
    g.addColorStop(1, `rgba(${rgb(v.tint)},${v.max})`);
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, H);
  }

  _ticks(ctx, W, H) {
    const { tickInset: i, tickLength: l } = this.theme.frame;
    ctx.strokeStyle = `rgba(${rgb(this.theme.plate.strand)},${this.theme.plate.tickAlpha})`;
    ctx.lineWidth = 0.8;
    const corners = [[i, i, 1, 1], [W - i, i, -1, 1], [i, H - i, 1, -1], [W - i, H - i, -1, -1]];
    for (const c of corners) {
      ctx.beginPath();
      ctx.moveTo(c[0], c[1]); ctx.lineTo(c[0] + c[2] * l, c[1]);
      ctx.moveTo(c[0], c[1]); ctx.lineTo(c[0], c[1] + c[3] * l);
      ctx.stroke();
    }
  }

  _bed(ctx) {
    const SEGMENTS = 240;
    const CHUNK = 6;
    const colour = rgb(this.theme.plate.strand);
    const scale = this.theme.plate.strandAlpha;

    for (const key of this.field.keys) {
      const river = this.field.get(key);
      for (const strand of river.strands) {
        for (let c = 0; c < SEGMENTS; c += CHUNK) {
          let sum = 0;
          let count = 0;
          ctx.beginPath();
          const end = Math.min(SEGMENTS, c + CHUNK);
          for (let i = c; i <= end; i++) {
            const s = i / SEGMENTS;
            const p = river.strandPoint(strand, s);
            sum += river.strandAlpha(strand, s);
            count++;
            if (i === c) ctx.moveTo(p[0], p[1]); else ctx.lineTo(p[0], p[1]);
          }
          const a = (sum / count) * scale;
          if (a < 0.0025) continue;
          ctx.strokeStyle = `rgba(${colour},${a.toFixed(4)})`;
          ctx.lineWidth = strand.lw;
          ctx.stroke();
        }
      }
    }
  }
}
