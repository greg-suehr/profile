import { River } from './River.js';
import { RIVERS, COMPOSITION, CANVAS } from './rivers.js';
import { STRANDS, TRIBUTARY_SUBSET } from './strands.js';

export class Field {
  constructor({ rivers = RIVERS, composition = COMPOSITION, canvas = CANVAS, strands = STRANDS } = {}) {
    this.canvas = canvas;
    this.composition = composition;
    this.rivers = {};

    const subset = TRIBUTARY_SUBSET.map((i) => strands[i]);

    for (const key of Object.keys(rivers)) {
      const spec = rivers[key];
      const seated = {
        ...spec,
        points: spec.points.map((p) => [
          p[0] * composition.scale + composition.dx,
          p[1] * composition.scale + composition.dy,
        ]),
        w0: spec.w0 * composition.scale,
        w1: spec.w1 * composition.scale,
      };
      this.rivers[key] = new River(seated, spec.subset ? subset : strands);
    }
    this.keys = Object.keys(this.rivers);
    this.trunkKey = 'trunk';
  }

  get(key) { return this.rivers[key]; }
  get trunk() { return this.rivers[this.trunkKey]; }
  scaled(n) { return n * this.composition.scale; }
}
