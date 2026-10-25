import { catalogUuid } from './catalog.ts'
export type MusicBrainzKind = 'artist' | 'work' | 'series'
export type MusicBrainzCandidate = { id: string; kind: MusicBrainzKind; label: string; artistType: string | null; disambiguation: string | null; area: string | null; country: string | null; writers: string[]; local: { id: string; label: string; artistType: string | null; disambiguation: string | null } | null; selection: string }
export function decodeMusicBrainzPage(value: unknown, kind: MusicBrainzKind): MusicBrainzCandidate[] {
  const bad = (): never => { throw new Error('Invalid MusicBrainz response') }
  const object = (v: unknown): Record<string, unknown> => typeof v === 'object' && v !== null && !Array.isArray(v) ? v as Record<string, unknown> : bad()
  const text = (v: unknown, max = 255): string => typeof v === 'string' && v.trim() && v.length <= max ? v : bad()
  const nullable = (v: unknown): string | null => v === null ? null : text(v)
  const data = object(value).data
  if (!Array.isArray(data) || data.length > 10) return bad()
  const ids = new Set<string>()
  return data.map(raw => {
    const r = object(raw)
    if (!catalogUuid(r.id) || r.kind !== kind || ids.has(r.id)) return bad()
    ids.add(r.id)
    const local = r.local === null ? null : object(r.local)
    if (local && !catalogUuid(local.id)) return bad()
    if (!Array.isArray(r.writers) || r.writers.length > 20) return bad()
    return { id: r.id, kind, label: text(r.label), artistType: nullable(r.artistType), disambiguation: nullable(r.disambiguation), area: nullable(r.area), country: nullable(r.country), writers: r.writers.map(w => text(w)), selection: text(r.selection, 4096), local: local ? { id: local.id as string, label: text(local.label), artistType: nullable(local.artistType), disambiguation: nullable(local.disambiguation) } : null }
  })
}
