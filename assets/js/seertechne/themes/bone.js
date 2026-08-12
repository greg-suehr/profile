export const bone = {
  name: 'bone',

  ground: [22, 21, 20],

  plate: {
    strand: [226, 221, 211],
    strandAlpha: 0.29,
    tickAlpha: 0.07,
    key: { x: 330, y: 170, inner: 40, outer: 860, tint: [58, 56, 53], a0: 0.075, a1: 0.03 },
    vignette: { x: 566, y: 298, inner: 80, outer: 820, tint: [11, 10, 10], max: 0.13 },
  },

  grain: { size: 190, amplitude: 58, alpha: 42 },

  particles: {
    colour: [222, 217, 207],
    count: 1050,
    speedDivisor: 26,
    baseVelocity: 0.00088,
    depth: [0.70, 0.62],
    alphaScale: 1.05,
    litAlphaScale: 1.9,
    size: 5.4,
    litSize: 6.4,
    breathe: 0.16,
    breatheRate: 0.00013,
  },

  markers: {
    neutral: [228, 224, 215],
    stops: [[104, 101, 97], [130, 127, 122], [157, 154, 148], [184, 180, 173], [212, 207, 198], [240, 236, 226]],
    hoverAlpha: 2.7,
    hoverGrow: 1.38,
    litAlpha: 0.95,
    litGrow: 1.18,
    glowRadius: 25,
    glowAlpha: 0.12,
  },

  rim: { width: 92 },

  frame: { tickInset: 34, tickLength: 18 },
};

export default bone;
