import { SelectField } from './SelectField'
import type { ReactNode } from 'react'
import type { SetlistEntry } from '../domain'
import { Icon } from './Icon'
export function SetlistEntryFields({ entry, onChange, songLookup, performerLookup }: { entry: SetlistEntry; onChange: (patch: Partial<SetlistEntry>) => void; songLookup?: ReactNode; performerLookup?: ReactNode }) {
  return <><div className="entry-editor-top"><SelectField label="Entry type" className="entry-select" value={entry.type} onChange={value => onChange({ type: value as 'song' | 'note' })} options={[{ id: 'song', label: 'Song' }, { id: 'note', label: 'Note / section' }]} /><label className="tape-checkbox"><input type="checkbox" disabled={entry.type === 'note'} checked={entry.tape} onChange={e => onChange({ tape: e.target.checked })} /><Icon name="tape" size={17} />Tape / playback</label></div>{entry.type === 'song' && songLookup}<div className="entry-editor-bottom"><label>{entry.type === 'song' ? 'Song title' : 'Note'}<input autoFocus required maxLength={2000} value={entry.title} onChange={e => onChange({ title: e.target.value })} /></label>{entry.type === 'song' && <label>Performer<input maxLength={255} value={entry.artist} onChange={e => onChange({ artist: e.target.value })} /></label>}</div>{entry.type === 'song' && performerLookup}</>
}
