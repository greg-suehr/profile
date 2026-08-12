import { Piece } from './Piece.js';
import bone from './themes/bone.js';

export function boot(selector = '[data-st="piece"]') {
  const root = document.querySelector(selector);
  if (!root) return null;
  const piece = new Piece(root, {
    theme: bone,
    endpoint: root.dataset.stEndpoint,
    token: root.dataset.stToken,
  }).mount();
  if (root.dataset.stExpose === '1') window.__piece = piece;
  return piece;
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => boot());
} else {
  boot();
}

export { Piece, bone };
