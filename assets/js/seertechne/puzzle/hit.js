export function hitRadius(renderedWidth, logicalWidth) {
  return Math.max(26, 46 * (logicalWidth / Math.max(320, renderedWidth)));
}

export function nearestMarker(layer, x, y, radius) {
  let best = -1;
  let bestDistance = radius;
  for (let i = 0; i < layer.count; i++) {
    const p = layer.positionOf(i);
    const d = Math.hypot(p[0] - x, p[1] - y);
    if (d < bestDistance) { bestDistance = d; best = i; }
  }
  return best;
}
