import { CANVAS } from '../field/rivers.js';

export class Grain {
  constructor(theme) {
    this.theme = theme;
    this.tile = null;
  }

  build() {
    if (this.tile) return this.tile;
    const { size, amplitude, alpha } = this.theme.grain;
    const ground = this.theme.ground;

    const c = document.createElement('canvas');
    c.width = c.height = size;
    const cx = c.getContext('2d');
    const img = cx.createImageData(size, size);
    const data = img.data;

    for (let i = 0; i < data.length; i += 4) {
      const dv = (Math.random() * 2 - 1) * amplitude;
      data[i]     = Math.max(0, Math.min(255, ground[0] + dv)) | 0;
      data[i + 1] = Math.max(0, Math.min(255, ground[1] + dv)) | 0;
      data[i + 2] = Math.max(0, Math.min(255, ground[2] + dv)) | 0;
      data[i + 3] = alpha;
    }
    cx.putImageData(img, 0, 0);
    this.tile = c;
    return c;
  }

  applyToPage(target = document.body) {
    const tile = this.build();
    try {
      const dpr = Math.min(2, window.devicePixelRatio || 1);
      target.style.backgroundImage = `url(${tile.toDataURL()})`;
      target.style.backgroundSize = `${this.theme.grain.size / dpr}px ${this.theme.grain.size / dpr}px`;
    } catch (e) {
      /* tainted canvas or data-URI limits — grain on the plate only */
    }
  }

  paint(ctx, deviceWidth, deviceHeight) {
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.fillStyle = ctx.createPattern(this.build(), 'repeat');
    ctx.fillRect(0, 0, deviceWidth, deviceHeight);
  }
}
