import type { VenueSuggestion, VenueValues } from '../catalogProviders'
import { useRecoveryField } from './ConcertRecovery'
import { useEffect, useRef, useState } from 'react'
export function VenueDraftFields({ initial, onConfirm, onCancel }: { initial?: VenueSuggestion; onConfirm: (values: VenueValues, selection: string | null, initial?: VenueSuggestion) => void; onCancel: () => void }) {
  const [editor, setEditor] = useRecoveryField('venueEditor', initial ? { name: initial.values.name, city: initial.values.city, address: initial.values.address ?? '', aliasesText: initial.values.aliases.join('\n'), lat: initial.values.lat === null ? '' : String(initial.values.lat), lng: initial.values.lng === null ? '' : String(initial.values.lng), selection: initial.selection, ref: initial.ref, metadata: initial.values, linkedRefs: initial.linkedRefs, url: initial.url, venueUrl: initial.venueUrl, venueSources: initial.venueSources } : null)
  const [error, setError] = useState('')
  const nameInput = useRef<HTMLInputElement>(null)
  useEffect(() => { nameInput.current?.focus() }, [])
  if (!editor) return null
  const change = (patch: Partial<typeof editor>) => setEditor({ ...editor, ...patch, ...(patch.name !== undefined && patch.name !== editor.name ? { selection: null, ref: undefined } : {}) })
  const cancel = () => { setEditor(null); onCancel() }
  const confirm = () => {
    if (!editor.name.trim() || (editor.lat === '') !== (editor.lng === '') || editor.lat !== '' && (!Number.isFinite(Number(editor.lat)) || !Number.isFinite(Number(editor.lng)) || Math.abs(Number(editor.lat)) > 90 || Math.abs(Number(editor.lng)) > 180)) { setError('Enter a Venue name and both valid coordinates, or leave both coordinates blank.'); return }
    const values: VenueValues = { region: null, countryCode: null, timezone: null, image: null, ...editor.metadata, name: editor.name.trim(), city: editor.city.trim(), address: editor.address.trim() || null, aliases: [...new Set(editor.aliasesText.split('\n').map(a => a.trim()).filter(Boolean))], lat: editor.lat === '' ? null : Number(editor.lat), lng: editor.lng === '' ? null : Number(editor.lng) }
    if (values.aliases.length > 100 || values.aliases.some(a => a.length > 255)) { setError('Use at most 100 aliases of 255 characters each.'); return }
    const source = editor.ref && editor.selection && editor.url ? { ref: editor.ref, selection: editor.selection, values, linkedRefs: editor.linkedRefs ?? [], url: editor.url, venueUrl: editor.venueUrl, venueSources: editor.venueSources, localId: null } : undefined
    onConfirm(values, editor.selection, source); setEditor(null)
  }
  return <section className="venue-draft-fields"><h4>Review Venue details</h4><p className="hint">{editor.selection ? 'Provider match selected. Nothing is saved until Save concert.' : 'Manual Venue'}</p>
    <label>Venue name<input ref={nameInput} maxLength={255} value={editor.name} onChange={e => change({ name: e.target.value })} /></label><label>City<input maxLength={255} value={editor.city} onChange={e => change({ city: e.target.value })} /></label><details><summary>Address and map details (optional)</summary><label>Full address<textarea maxLength={2000} value={editor.address} onChange={e => change({ address: e.target.value })} /></label><label>Aliases, one per line<textarea maxLength={2000} value={editor.aliasesText} onChange={e => change({ aliasesText: e.target.value })} /></label>
    <div className="form-columns"><label>Latitude<input inputMode="decimal" value={editor.lat} maxLength={100} onChange={e => change({ lat: e.target.value })} /></label><label>Longitude<input inputMode="decimal" value={editor.lng} maxLength={100} onChange={e => change({ lng: e.target.value })} /></label></div>
    </details>
    {error && <p role="alert">{error}</p>}<button type="button" onClick={confirm}>Use Venue details</button><button type="button" onClick={cancel}>Cancel Venue editor</button>
  </section>
}
