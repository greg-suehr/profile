import { Plate } from './Plate.js';
import { Grain } from './Grain.js';
import { SpriteCache } from './Sprites.js';
import { ParticleField } from './Particles.js';
import { MarkerLayer } from './Markers.js';
import { Rim } from './Rim.js';

export class Stage {
  constructor({ canvas, field, theme }) {
    this.canvas = canvas;
    this.ctx = canvas.getContext('2d');
    this.field = field;
    this.theme = theme;

    this.grain = new Grain(theme);
    this.plate = new Plate({ field, theme, grain: this.grain });
    this.sprites = new SpriteCache();
    this.particles = new ParticleField({ field, theme });
    this.markers = new MarkerLayer({ field, theme });
    this.rim = new Rim({ theme, canvas: field.canvas });

    this.scale = 1;
    this.running = false;
    this.lastFrame = 0;
    this.time = 0;

    this.reduced = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

    this.state = () => ({ lit: new Map(), hover: -1 });

    this._onResize = this._debounce(() => this.resize(), 140);
    this._onVisibility = () => (document.hidden ? this.stop() : this.start());
  }

  attach() {
    this.grain.applyToPage();
    window.addEventListener('resize', this._onResize);
    document.addEventListener('visibilitychange', this._onVisibility);
    this.resize();
    this.start();
  }

  detach() {
    this.stop();
    window.removeEventListener('resize', this._onResize);
    document.removeEventListener('visibilitychange', this._onVisibility);
  }

  resize() {
    const { width: W, height: H } = this.field.canvas;
    const cssWidth = this.canvas.parentNode.clientWidth || 900;
    const dpr = Math.min(2, window.devicePixelRatio || 1);

    this.canvas.style.height = `${(cssWidth * H) / W}px`;
    this.canvas.width = Math.round(cssWidth * dpr);
    this.canvas.height = Math.round(((cssWidth * H) / W) * dpr);
    this.scale = (cssWidth / W) * dpr;

    this.rim.invalidate();
    this.markers.invalidate();
    this.plate.bake(this.scale);
  }

  start() {
    if (this.running) return;
    this.running = true;
    this.lastFrame = 0;
    requestAnimationFrame((ts) => this._frame(ts));
  }

  stop() { this.running = false; }

  toLogical(clientX, clientY) {
    const r = this.canvas.getBoundingClientRect();
    const k = r.width / this.field.canvas.width;
    return [(clientX - r.left) / k, (clientY - r.top) / k];
  }

  _frame(ts) {
    if (!this.running) return;
    const dt = Math.min(50, ts - (this.lastFrame || ts));
    this.lastFrame = ts;
    this.time = ts;

    const { lit, hover } = this.state();
    if (!this.reduced) this.particles.step(dt, ts, lit);

    const ctx = this.ctx;
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    ctx.drawImage(this.plate.canvas, 0, 0);
    ctx.setTransform(this.scale, 0, 0, this.scale, 0, 0);
    ctx.lineCap = 'round';

    this.particles.draw(ctx, this.sprites, ts);

    ctx.globalCompositeOperation = 'lighter';
    for (const [index, m] of lit) this.markers.drawGlow(ctx, index, m.colour);
    ctx.globalCompositeOperation = 'source-over';

    const mk = this.theme.markers;
    for (let i = 0; i < this.markers.count; i++) {
      const entry = lit.get(i);
      if (entry) this.markers.draw(ctx, i, mk.litAlpha, entry.colour, mk.litGrow);
      else if (hover === i) this.markers.draw(ctx, i, this.markers.markers[i].dim * mk.hoverAlpha, null, mk.hoverGrow);
      else this.markers.draw(ctx, i, this.markers.markers[i].dim, null, 1);
    }

    this.rim.draw(ctx);
    requestAnimationFrame((t) => this._frame(t));
  }

  _debounce(fn, ms) {
    let handle = null;
    return (...args) => {
      clearTimeout(handle);
      handle = setTimeout(() => fn(...args), ms);
    };
  }
}
