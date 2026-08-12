import { rgb } from '../math/palette.js';

export class Chrome {
  constructor({ root, rule, reveal, count, theme, nudgeAfter = 9000 }) {
    this.root = root;
    this.rule = rule;
    this.reveal = reveal;
    this.count = count;
    this.theme = theme;
    this.idle = `rgba(${rgb(theme.markers.neutral)},.09)`;

    this._nudge = setTimeout(() => this.root.classList.add('is-nudging'), nudgeAfter);
  }

  touched() {
    clearTimeout(this._nudge);
    this.root.classList.remove('is-nudging');
  }

  progress(step, hueForStep) {
    if (!step) { this.rule.style.background = this.idle; return; }
    const segment = 100 / this.count;
    const parts = [];
    for (let i = 0; i < step; i++) {
      const c = `rgb(${rgb(hueForStep(i))})`;
      parts.push(`${c} ${(i * segment).toFixed(2)}%`, `${c} ${((i + 1) * segment).toFixed(2)}%`);
    }
    parts.push(`${this.idle} ${(step * segment).toFixed(2)}%`);
    this.rule.style.background = `linear-gradient(to right,${parts.join(',')})`;
  }

  solved(reward) {
    if (reward && this.reveal) this.reveal.textContent = reward;
    setTimeout(() => this.root.classList.add('is-solved'), 900);
  }

  locked() { this.root.classList.add('is-locked'); }
}
