import { catmullRom, arcLengthResample, smoothstep } from '../math/curve.js';

export class River {
  constructor(spec, strands, { divisions = 600, samplesPerSegment = 26 } = {}) {
    this.dir = spec.dir;
    this.fin = spec.fin;
    this.fout = spec.fout;
    this.head = spec.head || null;
    this.bulge = spec.bulge;
    this.w0 = spec.w0;
    this.w1 = spec.w1;
    this.strands = strands;

    const curve = arcLengthResample(catmullRom(spec.points, samplesPerSegment), divisions);
    this.pts = curve.points;
    this.tgs = curve.tangents;
    this.divisions = curve.divisions;
    this.length = curve.length;
  }

  at(s) {
    s = s < 0 ? 0 : s > 1 ? 1 : s;
    const f = s * this.divisions;
    const k = f | 0;
    const g = f - k;
    const k2 = k < this.divisions ? k + 1 : k;
    return [
      this.pts[k * 2] + (this.pts[k2 * 2] - this.pts[k * 2]) * g,
      this.pts[k * 2 + 1] + (this.pts[k2 * 2 + 1] - this.pts[k * 2 + 1]) * g,
    ];
  }

  tangent(s) {
    s = s < 0 ? 0 : s > 1 ? 1 : s;
    const k = Math.round(s * this.divisions);
    return [this.tgs[k * 2], this.tgs[k * 2 + 1]];
  }

  width(s) {
    let w = this.w0 + (this.w1 - this.w0) * smoothstep(0, 1, s);
    w *= 1 + this.bulge * Math.sin(Math.PI * Math.pow(s, 0.8));
    if (this.head) w *= this.head[1] + (1 - this.head[1]) * smoothstep(0, this.head[0], s);
    return w;
  }

  envelope(s) {
    return smoothstep(this.fin[0], this.fin[1], s) * (1 - smoothstep(this.fout[0], this.fout[1], s));
  }

  strandAlpha(strand, s) {
    let a = this.envelope(s) * strand.a;
    if (strand.r) {
      a *= smoothstep(strand.r[0], strand.r[0] + 0.14, s) * (1 - smoothstep(strand.r[1] - 0.14, strand.r[1], s));
    }
    return a;
  }

  strandPoint(strand, s) {
    const p = this.at(s);
    const d = this.tangent(s);
    const wobble =
      strand.u *
      (1 + 0.075 * Math.sin(s * 3.1 + strand.ph)) *
      (1 + 0.030 * Math.sin(s * strand.pk * 2.6 + strand.ph * 1.7));
    const off = wobble * this.width(s);
    return [p[0] - d[1] * off, p[1] + d[0] * off];
  }

  lateralOf(s, x, y) {
    const q = this.at(s);
    const d = this.tangent(s);
    const off = d[0] * (y - q[1]) - d[1] * (x - q[0]);
    return off / this.width(s);
  }

  nearestS(x, y, stride = 3) {
    let best = 0;
    let bestDist = Infinity;
    for (let k = 0; k <= this.divisions; k += stride) {
      const dx = this.pts[k * 2] - x;
      const dy = this.pts[k * 2 + 1] - y;
      const d = dx * dx + dy * dy;
      if (d < bestDist) { bestDist = d; best = k; }
    }
    return best / this.divisions;
  }
}
