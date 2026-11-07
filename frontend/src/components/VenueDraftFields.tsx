import { CreationSelection } from './CreationSelection'
import { Icon } from './Icon'
import { prepareVenueDraft, validVenueDraft } from '../venueDraft'
import { Button } from 'react-aria-components'
import type { VenueSuggestion, VenueValues } from '../catalogProviders'
import { useRecoveryField } from './ConcertRecovery'
import { useEffect, useId, useRef, useState } from 'react'
export function VenueDraftFields({ initial, onConfirm, onCancel }: { initial?: VenueSuggestion; onConfirm: (values: VenueValues, selection: string | null, initial?: VenueSuggestion) => void; onCancel: () => void }) {
  const [editor, setEditor] = useRecoveryField('venueEditor', initial ? { name: initial.values.name, city: initial.values.city, address: initial.values.address ?? '', aliasesText: initial.values.aliases.join('\n'), lat: initial.values.lat === null ? '' : String(initial.values.lat), lng: initial.values.lng === null ? '' : String(initial.values.lng), selection: initial.selection, ref: initial.ref, metadata: initial.values, linkedRefs: initial.linkedRefs, url: initial.url, venueUrl: initial.venueUrl, venueSources: initial.venueSources } : null)
  const [error, setError] = useState('')
  const [expanded, setExpanded] = useState<boolean | null>(null)
  const formId = useId()
  const showForm = !!editor && (expanded ?? !validVenueDraft(editor))
  const nameInput = useRef<HTMLInputElement>(null)
  useEffect(() => { if (showForm) nameInput.current?.focus() }, [showForm])
  if (!editor) return null
  const change = (patch: Partial<typeof editor>) => setEditor({ ...editor, ...patch, ...(patch.name !== undefined && patch.name !== editor.name ? { selection: null, ref: undefined } : {}) })
  const cancel = () => { setEditor(null); onCancel() }
  const confirm = () => {
    try { const { values, selection, source } = prepareVenueDraft(editor); onConfirm(values, selection, source); setEditor(null) }
    catch (failure) { setError(failure instanceof Error ? failure.message : 'Check the Venue details.') }

  }
  return <section className="venue-draft-review">
    <CreationSelection label="Venue" name={editor.name} detail={editor.city} actions={<>
      <Button type="button" className="icon-button" aria-label={showForm ? 'Close Venue form' : 'Edit Venue'} aria-expanded={showForm} aria-controls={formId} onPress={() => setExpanded(!showForm)}><Icon name="edit" size={18} /></Button>
      <Button type="button" className="icon-button" aria-label="Clear Venue" onPress={cancel}><Icon name="close" size={18} /></Button>
    </>} />
    {showForm && <div id={formId} className="venue-draft-fields">
    <label>Venue name<input ref={nameInput} maxLength={255} value={editor.name} onChange={e => change({ name: e.target.value })} /></label><label>City<input maxLength={255} value={editor.city} onChange={e => change({ city: e.target.value })} /></label><details><summary>Address and map details (optional)</summary><label>Full address<textarea maxLength={2000} value={editor.address} onChange={e => change({ address: e.target.value })} /></label><label>Aliases, one per line<textarea maxLength={2000} value={editor.aliasesText} onChange={e => change({ aliasesText: e.target.value })} /></label>
    <div className="form-columns"><label>Latitude<input inputMode="decimal" value={editor.lat} maxLength={100} onChange={e => change({ lat: e.target.value })} /></label><label>Longitude<input inputMode="decimal" value={editor.lng} maxLength={100} onChange={e => change({ lng: e.target.value })} /></label></div>
    </details>
    {error && <p role="alert">{error}</p>}<Button type="button" onPress={confirm}>Use Venue details</Button><Button type="button" onPress={cancel}>Cancel Venue editor</Button>
    </div>}
  </section>
}
