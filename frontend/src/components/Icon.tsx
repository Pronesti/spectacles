import type { CSSProperties } from 'react'

const paths = {
  dashboard: 'M3 10 12 3l9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z',
  music: 'M9 18V5l12-2v13M9 8l12-2M9 18c0 2-6 4-6 0s6-4 6 0Zm12-2c0 2-6 4-6 0s6-4 6 0Z',
  calendar: 'M4 5h16a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1ZM3 10h18M7 3v4m10-4v4M7 14h2m4 0h2m-8 4h2',
  pin: 'M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0ZM15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
  list: 'M9 6h12M9 12h12M9 18h12M3 6h1m-1 6h1m-1 6h1',
  plus: 'M12 4v16M4 12h16',
  arrow: 'M4 12h16m-6-6 6 6-6 6',
  left: 'm15 5-7 7 7 7',
  right: 'm9 5 7 7-7 7',
  down: 'm6 9 6 6 6-6',
  up: 'm6 15 6-6 6 6',
  search: 'M16 16l5 5M18 10A8 8 0 1 1 2 10a8 8 0 0 1 16 0Z',
  close: 'm6 6 12 12M6 18 18 6',
  edit: 'm15 4 5 5M3 21l5-1L21 7a2 2 0 0 0-5-5L3 15Z',
  trash: 'M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7',
  ticket: 'M3 5h18v5a2 2 0 0 0 0 4v5H3v-5a2 2 0 0 0 0-4ZM15 5v3m0 3v2m0 3v3',
  check: 'm5 12 4 4L19 6',
  clock: 'M12 7v5l3 2m7-2A10 10 0 1 1 2 12a10 10 0 0 1 20 0Z',
  globe: 'M2 12h20M12 2c6 5 6 15 0 20-6-5-6-15 0-20Zm10 10A10 10 0 1 1 2 12a10 10 0 0 1 20 0Z',
  layers: 'm2 7 10-5 10 5-10 5Zm0 5 10 5 10-5M2 17l10 5 10-5',
  tape: 'M3 5h18v14H3ZM8 14h8l2 5H6ZM9 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm10 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM9 10h6',
  heart: 'M20 4c-3-3-7 0-8 2-1-2-5-5-8-2-5 5 2 11 8 16 6-5 13-11 8-16Z',
  minus: 'M4 12h16',
  note: 'M5 3h14v18H5ZM9 8h6m-6 4h6m-6 4h3',
  refresh: 'M20 7a9 9 0 1 0 1 8M20 2v5h-5',
  user: 'M20 21v-2a6 6 0 0 0-6-6h-4a6 6 0 0 0-6 6v2M16 6a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
  logout: 'M9 4H3v16h6M12 12h9m-4-4 4 4-4 4',
  eye: 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Zm13 0a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
  eyeOff: 'M3 3l18 18M10 5h2c6 0 10 7 10 7a23 23 0 0 1-3 4M6 6a21 21 0 0 0-4 6s4 7 10 7a13 13 0 0 0 5-1M10 10a3 3 0 0 0 4 4',
} as const

export type IconName = keyof typeof paths
export function Icon({ name, size = 20, style }: { name: IconName; size?: number; style?: CSSProperties }) {
  return <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.65" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" style={style}><path d={paths[name]} /></svg>
}

export function Soundmark() {
  return <span className="soundmark" aria-hidden="true">{[12, 25, 37, 25, 16].map((height, i) => <i key={i} style={{ height }} />)}</span>
}
