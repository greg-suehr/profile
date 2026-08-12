export function rampAt(stops, u) {
  const f = clampUnit(u) * (stops.length - 1);
  const i = Math.min(Math.floor(f), stops.length - 2);
  const k = f - i;
  const a = stops[i];
  const b = stops[i + 1];
  return [a[0] + (b[0] - a[0]) * k, a[1] + (b[1] - a[1]) * k, a[2] + (b[2] - a[2]) * k];
}

export const clampUnit = (x) => (x < 0 ? 0 : x > 1 ? 1 : x);

export const rgb = (c) => `${c[0] | 0},${c[1] | 0},${c[2] | 0}`;

export const rgba = (c, a) => `rgba(${rgb(c)},${a.toFixed(4)})`;

export function mix(from, to, k) {
  if (!from) return to.slice();
  return [from[0] + (to[0] - from[0]) * k, from[1] + (to[1] - from[1]) * k, from[2] + (to[2] - from[2]) * k];
}
