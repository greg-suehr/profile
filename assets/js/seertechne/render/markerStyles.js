export const MARKER_STYLES = {
  whisper: { thickness: 3.6, span: 0.78, over: 2, weight: 0.55, rake: -5 },
  fine:    { thickness: 6.6, span: 0.88, over: 3, weight: 0.70, rake: 9 },
  mid:     { thickness: 4.4, span: 0.95, over: 4, weight: 0.86, rake: -12 },
  wide:    { thickness: 4.9, span: 1.02, over: 5, weight: 0.84, rake: 15 },
  deep:    { thickness: 5.4, span: 0.92, over: 5, weight: 1.00, rake: -8 },
};

export const MARKERS = [
  { river: 'upper', s: 0.875, style: 'whisper', dim: 0.105 },
  { river: 'lower', s: 0.865, style: 'deep',    dim: 0.170 },
  { river: 'upper', s: 0.780, style: 'mid',     dim: 0.152 },
  { river: 'lower', s: 0.765, style: 'fine',    dim: 0.126 },
  { river: 'upper', s: 0.675, style: 'deep',    dim: 0.185 },
  { river: 'lower', s: 0.655, style: 'wide',    dim: 0.142 },
  { river: 'upper', s: 0.550, style: 'fine',    dim: 0.120 },
  { river: 'lower', s: 0.535, style: 'whisper', dim: 0.098 },
  { river: 'upper', s: 0.420, style: 'wide',    dim: 0.147 },
  { river: 'lower', s: 0.410, style: 'mid',     dim: 0.158 },
  { river: 'trunk', s: 0.365, style: 'deep',    dim: 0.215 },
  { river: 'trunk', s: 0.550, style: 'mid',     dim: 0.163 },
  { river: 'trunk', s: 0.780, style: 'fine',    dim: 0.120 },
];
