import { mix, rgb } from '../math/palette.js';

export class ParticleField {
  constructor({ field, theme }) {
    this.field = field;
    this.theme = theme;
    this.cfg = theme.particles;
    this.items = [];
    this._seedAll();
  }

  _seed(riverKey, s) {
    const { baseVelocity, speedDivisor, depth } = this.cfg;
    const u = Math.random() * 2 - 1;
    const lateral = (u < 0 ? -1 : 1) * Math.pow(Math.abs(u), 1.45);
    const depthFactor = depth[0] + depth[1] * (1 - Math.abs(lateral));
    return {
      river: riverKey,
      s,
      lateral,
      v: ((baseVelocity * depthFactor) * (0.78 + Math.random() * 0.46)) / speedDivisor,
      colour: null,
      sprite: null,
      alpha: 0.10 + Math.random() * 0.16,
      size: 0.62 + Math.random() * 0.66,
      phase: Math.random() * 6.28,
      bright: 0.38 + Math.random() * 0.82,
    };
  }

  _seedAll() {
    const keys = this.field.keys;
    const trunk = this.field.trunkKey;
    const tributaries = keys.filter((k) => k !== trunk);
    for (let i = 0; i < this.cfg.count; i++) {
      const onTrunk = Math.random() < 0.30;
      const key = onTrunk ? trunk : tributaries[(Math.random() * tributaries.length) | 0];
      const s = onTrunk ? 0.30 + Math.random() * 0.70 : Math.random();
      this.items.push(this._seed(key, s));
    }
  }

  _respawn(p) {
    const trunk = this.field.trunkKey;
    const tributaries = this.field.keys.filter((k) => k !== trunk);
    Object.assign(p, this._seed(tributaries[(Math.random() * tributaries.length) | 0], 1));
    p.sprite = null;
  }

  _handoff(p, river) {
    const q = river.at(0);
    const d = river.tangent(0);
    const off = p.lateral * river.width(0);
    const x = q[0] - d[1] * off;
    const y = q[1] + d[0] * off;

    const trunk = this.field.trunk;
    const s = trunk.nearestS(x, y);
    const lateral = trunk.lateralOf(s, x, y);
    p.river = this.field.trunkKey;
    p.s = s;
    p.lateral = lateral < -1 ? -1 : lateral > 1 ? 1 : lateral;
  }

  /**
   * @param {number} dt      milliseconds since last frame
   * @param {number} t       absolute timestamp, for the breathing term
   * @param {Map}    lit     marker index -> { river, s, colour } for lit markers
   */
  step(dt, t, lit) {
    const { breathe, breatheRate } = this.cfg;
    for (const p of this.items) {
      const river = this.field.get(p.river);
      const previous = p.s;
      p.s += river.dir * p.v * dt * (1 + breathe * Math.sin(t * breatheRate + p.phase));

      if (lit && lit.size) {
        for (const m of lit.values()) {
          if (m.river !== p.river) continue;
          const crossed = river.dir > 0
            ? previous < m.s && p.s >= m.s
            : previous > m.s && p.s <= m.s;
          if (crossed) {
            p.colour = mix(p.colour, m.colour, 0.72);
            p.sprite = null; // colour changed
          }
        }
      }

      if (river.dir > 0 && p.s > 1) this._respawn(p);
      else if (river.dir < 0 && p.s <= 0) this._handoff(p, river);
    }
  }

  draw(ctx, sprites, t) {
    const { colour: base, alphaScale, litAlphaScale, size, litSize } = this.cfg;
    ctx.globalCompositeOperation = 'lighter';

    for (const p of this.items) {
      const river = this.field.get(p.river);
      const s = p.s < 0 ? 0 : p.s > 1 ? 1 : p.s;
      const q = river.at(s);
      const d = river.tangent(s);
      const off = p.lateral * river.width(s) * 0.94 * (1 + 0.08 * Math.sin(s * 7 + p.phase + t * 0.00006));
      const x = q[0] - d[1] * off;
      const y = q[1] + d[0] * off;

      const fade = 0.22 + 0.78 * river.envelope(s);
      const a = (p.colour ? p.alpha * litAlphaScale : p.alpha * alphaScale) * fade * p.bright;
      if (a < 0.004) continue;

      const z = p.size * (p.colour ? litSize : size);
      ctx.globalAlpha = a > 1 ? 1 : a;
      if (p.sprite === null) p.sprite = sprites.get(p.colour || base);
      ctx.drawImage(p.sprite, x - z / 2, y - z / 2, z, z);
    }

    ctx.globalAlpha = 1;
    ctx.globalCompositeOperation = 'source-over';
  }
}
