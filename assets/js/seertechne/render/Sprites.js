import { rgb } from '../math/palette.js';

export class SpriteCache {
  constructor(size = 36) {
    this.size = size;
    this.cache = new Map();
  }

  get(colour) {
    const key = rgb(colour);
    let sprite = this.cache.get(key);
    if (sprite) return sprite;

    const S = this.size;
    sprite = document.createElement('canvas');
    sprite.width = sprite.height = S;
    const ctx = sprite.getContext('2d');
    const g = ctx.createRadialGradient(S / 2, S / 2, 0, S / 2, S / 2, S / 2);
    g.addColorStop(0, `rgba(${key},1)`);
    g.addColorStop(0.16, `rgba(${key},.52)`);
    g.addColorStop(0.42, `rgba(${key},.14)`);
    g.addColorStop(1, `rgba(${key},0)`);
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, S, S);

    this.cache.set(key, sprite);
    return sprite;
  }
}
