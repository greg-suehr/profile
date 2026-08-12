import { rampAt } from '../math/palette.js';

export class Puzzle extends EventTarget {
  constructor({ transport, count, theme }) {
    super();
    this.transport = transport;
    this.count = count;
    this.theme = theme;

    this.step = 0;
    this.lit = new Map();   // marker index -> { river, s, colour }
    this.busy = false;
    this.solved = false;
    this.locked = false;
  }

  hueForStep(step) {
    return rampAt(this.theme.markers.stops, step / (this.count - 1));
  }

  _emit(type, detail = {}) {
    this.dispatchEvent(new CustomEvent(type, { detail }));
  }

  selectable(index) {
    return !this.solved && !this.busy && !this.locked && index >= 0 && !this.lit.has(index);
  }

  async press(index, describe) {
    if (!this.selectable(index)) return;
    this.busy = true;

    let result;
    try {
      result = await this.transport.pick(index);
    } catch (e) {
      this.busy = false;
      this._emit('error', { error: e });
      return;
    }

    if (result.locked) {
      this.locked = true;
      this.busy = false;
      this._emit('locked', { retryAfter: result.retryAfter });
      return;
    }

    if (result.ok) {
      const colour = this.hueForStep(this.step);
      this.lit.set(index, describe(index, colour));
      this.step = result.step;
      this.busy = false;
      this._emit('advance', { step: this.step, index, colour });

      if (result.done) {
        this.solved = true;
        this._emit('solved', { reward: result.reward });
      }
      return;
    }

    await this._unwind();
  }

  async _unwind() {
    const order = [...this.lit.keys()].reverse();
    this._emit('reset', { count: order.length });
    for (let i = 0; i < order.length; i++) {
      await wait(i === 0 ? 220 : 95);
      this.lit.delete(order[i]);
    }
    await wait(120);
    this.step = 0;
    this.busy = false;
    this._emit('advance', { step: 0, index: -1, colour: null });
  }
}

const wait = (ms) => new Promise((r) => setTimeout(r, ms));
