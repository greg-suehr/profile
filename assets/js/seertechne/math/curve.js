export const clamp01 = (x) => (x < 0 ? 0 : x > 1 ? 1 : x);

export function smoothstep(edge0, edge1, x) {
  if (edge1 === edge0) return x < edge0 ? 0 : 1;
  const t = clamp01((x - edge0) / (edge1 - edge0));
  return t * t * (3 - 2 * t);
}

export function catmullRom(points, perSegment) {
  const out = [];
  const n = points.length;
  for (let i = 0; i < n - 1; i++) {
    const p0 = points[Math.max(0, i - 1)];
    const p1 = points[i];
    const p2 = points[i + 1];
    const p3 = points[Math.min(n - 1, i + 2)];
    for (let j = 0; j < perSegment; j++) {
      const t = j / perSegment;
      const t2 = t * t;
      const t3 = t2 * t;
      out.push([
        0.5 * (2 * p1[0] + (-p0[0] + p2[0]) * t + (2 * p0[0] - 5 * p1[0] + 4 * p2[0] - p3[0]) * t2 + (-p0[0] + 3 * p1[0] - 3 * p2[0] + p3[0]) * t3),
        0.5 * (2 * p1[1] + (-p0[1] + p2[1]) * t + (2 * p0[1] - 5 * p1[1] + 4 * p2[1] - p3[1]) * t2 + (-p0[1] + 3 * p1[1] - 3 * p2[1] + p3[1]) * t3),
      ]);
    }
  }
  out.push([points[n - 1][0], points[n - 1][1]]);
  return out;
}

export function arcLengthResample(raw, divisions) {
  const cumulative = [0];
  for (let i = 1; i < raw.length; i++) {
    cumulative.push(cumulative[i - 1] + Math.hypot(raw[i][0] - raw[i - 1][0], raw[i][1] - raw[i - 1][1]));
  }
  const length = cumulative[cumulative.length - 1];
  const points = new Float64Array((divisions + 1) * 2);
  const tangents = new Float64Array((divisions + 1) * 2);

  let cursor = 0;
  for (let k = 0; k <= divisions; k++) {
    const target = (length * k) / divisions;
    while (cursor < cumulative.length - 2 && cumulative[cursor + 1] < target) cursor++;
    const span = cumulative[cursor + 1] - cumulative[cursor];
    const f = span ? (target - cumulative[cursor]) / span : 0;
    points[k * 2] = raw[cursor][0] + (raw[cursor + 1][0] - raw[cursor][0]) * f;
    points[k * 2 + 1] = raw[cursor][1] + (raw[cursor + 1][1] - raw[cursor][1]) * f;
  }
  for (let k = 0; k <= divisions; k++) {
    const a = Math.max(0, k - 3);
    const b = Math.min(divisions, k + 3);
    const dx = points[b * 2] - points[a * 2];
    const dy = points[b * 2 + 1] - points[a * 2 + 1];
    const h = Math.hypot(dx, dy) || 1;
    tangents[k * 2] = dx / h;
    tangents[k * 2 + 1] = dy / h;
  }
  return { points, tangents, length, divisions };
}
