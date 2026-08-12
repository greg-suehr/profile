import { Field } from './field/Field.js';
import { Stage } from './render/Stage.js';
import { Puzzle } from './puzzle/Puzzle.js';
import { PuzzleTransport } from './puzzle/PuzzleTransport.js';
import { hitRadius, nearestMarker } from './puzzle/hit.js';
import { Chrome } from './ui/Chrome.js';
import bone from './themes/bone.js';

export class Piece {
  constructor(root, { theme = bone, endpoint, token } = {}) {
    this.root = root;
    this.theme = theme;

    this.canvas = root.querySelector('[data-st="canvas"]');
    this.field = new Field();
    this.stage = new Stage({ canvas: this.canvas, field: this.field, theme });

    this.puzzle = new Puzzle({
      transport: new PuzzleTransport({ endpoint, token }),
      count: this.stage.markers.count,
      theme,
    });

    this.chrome = new Chrome({
      root,
      rule: root.querySelector('[data-st="rule"]'),
      reveal: root.querySelector('[data-st="reveal"]'),
      count: this.stage.markers.count,
      theme,
    });

    this.hover = -1;
    this.stage.state = () => ({ lit: this.puzzle.lit, hover: this.hover });

    this._bindPuzzle();
    this._bindPointer();
  }

  mount() {
    this.stage.attach();
    this.chrome.progress(0, (s) => this.puzzle.hueForStep(s));
    return this;
  }

  destroy() { this.stage.detach(); }

  _bindPuzzle() {
    const hue = (s) => this.puzzle.hueForStep(s);
    this.puzzle.addEventListener('advance', (e) => {
      this.hover = -1;
      this.chrome.progress(e.detail.step, hue);
    });
    this.puzzle.addEventListener('solved', (e) => this.chrome.solved(e.detail.reward));
    this.puzzle.addEventListener('locked', () => this.chrome.locked());
    this.puzzle.addEventListener('error', () => { this.hover = -1; });
  }

  _radius() {
    return hitRadius(this.canvas.getBoundingClientRect().width || 900, this.field.canvas.width);
  }

  _markerAt(clientX, clientY) {
    const [x, y] = this.stage.toLogical(clientX, clientY);
    return nearestMarker(this.stage.markers, x, y, this._radius());
  }

  _press(clientX, clientY) {
    this.chrome.touched();
    const index = this._markerAt(clientX, clientY);
    if (!this.puzzle.selectable(index)) return;
    this.puzzle.press(index, (i, colour) => this.stage.markers.descriptorOf(i, colour));
  }

  _bindPointer() {
    const c = this.canvas;

    c.addEventListener('mousemove', (e) => {
      if (this.puzzle.solved || this.puzzle.busy) { this.hover = -1; return; }
      const index = this._markerAt(e.clientX, e.clientY);
      this.hover = this.puzzle.selectable(index) ? index : -1;
      c.style.cursor = this.hover >= 0 ? 'pointer' : 'default';
    });

    c.addEventListener('mouseleave', () => { this.hover = -1; });
    c.addEventListener('click', (e) => this._press(e.clientX, e.clientY));
    c.addEventListener('touchstart', (e) => {
      const t = e.changedTouches[0];
      this._press(t.clientX, t.clientY);
      e.preventDefault();
    }, { passive: false });
  }
}

export default Piece;
