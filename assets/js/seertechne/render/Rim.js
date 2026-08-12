import { rgb } from '../math/palette.js';

export class Rim {
  constructor({ theme, canvas }) {
    this.theme = theme;
    this.size = canvas;
    this.bands = null;
  }

  invalidate() { this.bands = null; }

  _build(ctx) {
    const { width: W, height: H } = this.size;
    const R = this.theme.rim.width;
    const c = rgb(this.theme.ground);
    const make = (x0, y0, x1, y1) => {
      const g = ctx.createLinearGradient(x0, y0, x1, y1);
      g.addColorStop(0, `rgba(${c},1)`);
      g.addColorStop(1, `rgba(${c},0)`);
      return g;
    };
    this.bands = [
      [make(0, 0, R, 0), 0, 0, R, H],
      [make(W, 0, W - R, 0), W - R, 0, R, H],
      [make(0, 0, 0, R), 0, 0, W, R],
      [make(0, H, 0, H - R), 0, H - R, W, R],
    ];
  }

  draw(ctx) {
    if (!this.bands) this._build(ctx);
    for (const [fill, x, y, w, h] of this.bands) {
      ctx.fillStyle = fill;
      ctx.fillRect(x, y, w, h);
    }
  }
}
