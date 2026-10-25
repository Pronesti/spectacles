import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react'
import { daysUntil, filteredConcerts, formatDate, relation, sortConcerts, todayKey, uniqueVenues } from './domain'
import type { Concert, Venue, Workspace } from './domain'
import { Icon, Soundmark } from './components/Icon'
import type { IconName } from './components/Icon'
import { ConcertForm } from './components/ConcertForm'
import { ConcertList } from './components/ConcertList'
import { Heatmap } from './components/Heatmap'
import { CalendarView } from './components/CalendarView'
import { VenueMap } from './components/VenueMap'
import { SetlistsView } from './components/SetlistsView'
import { Dialog } from './components/Dialog'
import stagePhoto from './assets/concert-stage.png'
import { accountErrorMessage, createAccountClient } from './accountApi'
import type { AccountUser, AuthMode, Credentials, ProfilePatch } from './accountApi'
import { useAccount } from './useAccount'
import { createCatalogClient } from './catalogApi'
import type { CatalogAccess } from './components/CatalogPicker'
import { AuthView } from './components/AuthView'
import { AccountMenu } from './components/AccountMenu'
import { ProfileView } from './components/ProfileView'
import { CollectionError, createCollectionClient, emptyCollection } from './collectionApi'
import './App.css'
import './account.css'
import './catalog.css'

const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000').replace(/\/$/, '')
const accountClient = createAccountClient(apiBaseUrl)
const catalogClient = createCatalogClient(apiBaseUrl)
const collectionClient = createCollectionClient(apiBaseUrl)
type Connection = { status: 'loading' | 'connected' | 'error'; message: string }
type Page = 'dashboard' | 'concerts' | 'calendar' | 'map' | 'setlists' | 'profile'
const navigation: { id: Page; label: string; icon: IconName }[] = [
  { id: 'dashboard', label: 'Dashboard', icon: 'dashboard' }, { id: 'concerts', label: 'Concerts', icon: 'music' },
  { id: 'calendar', label: 'Calendar', icon: 'calendar' }, { id: 'map', label: 'Venue map', icon: 'pin' }, { id: 'setlists', label: 'Setlists', icon: 'list' },
]
const pageCopy: Record<Page, [string, string, string]> = {
  dashboard: ['YOUR PERSONAL ENCORE', 'Your life, live.', 'The nights you never want to forget.'],
  concerts: ['YOUR COLLECTION', 'Every night has a story.', 'The ones behind you. The ones still to come.'],
  calendar: ['SAVE THE DATE', 'Make room for live music.', 'A few dates worth looking forward to.'],
  map: ['FAMILIAR PLACES, NEW MEMORIES', 'Your city. Your stages.', 'The places that bring it all together.'],
  setlists: ['ONE MORE SONG', 'The songs stay with you.', 'A little closer to being there again.'],
  profile: ['BACKSTAGE, JUST FOR YOU', 'A little more you.', 'The person behind the memories.'],
}

function App() {
  const [collection, setCollection] = useState<{ owner: string; data: Workspace; status: 'loading' | 'ready' | 'error'; error: string }>({ owner: '', data: emptyCollection(), status: 'loading', error: '' })
  const [collectionRetry, setCollectionRetry] = useState(0)
  const [saving, setSaving] = useState(false)
  const locked = useRef(false)
  const session = useRef('')
  const [today, setToday] = useState(todayKey)
  const [page, setPage] = useState<Page>('dashboard')
  const account = useAccount(accountClient)
  const owner = account.user?.id ?? ''
  useLayoutEffect(() => { session.current = owner }, [owner])
  const [authView, setAuthView] = useState<AuthMode | null>('login')
  const [preservingDraft, setPreservingDraft] = useState(false)
  const workspace = collection.owner === owner && owner || !owner && preservingDraft ? collection.data : emptyCollection()
  const [accountNotice, setAccountNotice] = useState('')
  const [query, setQuery] = useState(''), [filter, setFilter] = useState('all')
  const [venueFilter, setVenueFilter] = useState<string | undefined>()
  const [form, setForm] = useState<{ concert?: Concert; date?: string } | null>(null)
  const [detailId, setDetailId] = useState<string | null>(null)
  const [setlistId, setSetlistId] = useState('')
  const [toast, setToast] = useState(''), [undo, setUndo] = useState<Workspace | null>(null)
  const [healthOpen, setHealthOpen] = useState(false)
  const [attempt, setAttempt] = useState(0)
  const [connection, setConnection] = useState<Connection>({ status: 'loading', message: 'Checking the API…' })

  const expireSession = account.expireSession
  const expireCatalogSession = useCallback(() => {
    if (!expireSession()) return
    setPreservingDraft(true); setAuthView(null)
    setAccountNotice('Your session expired. Your draft remains open, but you must sign in before saving to the database.')
  }, [expireSession])
  const catalog: CatalogAccess = { client: catalogClient, enabled: account.status === 'authenticated' && Boolean(account.user) && !account.busy, sessionKey: account.user?.id ?? 'guest', onUnauthorized: expireCatalogSession }

  useEffect(() => {
    const timer = setInterval(() => setToday(todayKey()), 60_000)
    return () => clearInterval(timer)
  }, [])
  useEffect(() => {
    if (!owner) return
    const controller = new AbortController()
    // oxlint-disable-next-line react/set-state-in-effect -- Reset account-scoped state before an external collection read.
    setCollection(current => ({ owner, data: current.owner === owner ? current.data : emptyCollection(), status: current.owner === owner ? 'ready' : 'loading', error: '' }))
    void collectionClient.load(controller.signal).then(data => {
      if (!controller.signal.aborted && session.current === owner) setCollection({ owner, data, status: 'ready', error: '' })
    }).catch(error => {
      if (!controller.signal.aborted && session.current === owner) setCollection({ owner, data: emptyCollection(), status: 'error', error: error instanceof Error ? error.message : 'Unable to load your collection.' })
    })
    return () => controller.abort()
  }, [owner, collectionRetry])
  useEffect(() => {
    if (!toast || undo) return
    const timer = setTimeout(() => setToast(''), 5000)
    return () => clearTimeout(timer)
  }, [toast, undo])

  useEffect(() => {
    let active = true
    const controller = new AbortController()
    const timeout = setTimeout(() => controller.abort(), 10000)
    async function checkConnection() {
      setConnection({ status: 'loading', message: 'Checking the API…' })
      try {
        const response = await fetch(`${apiBaseUrl}/api/health`, { headers: { Accept: 'application/json' }, signal: controller.signal })
        if (!response.ok) throw new Error(`API returned HTTP ${response.status}.`)
        const health = await response.json()
        if (health.status !== 'ok') throw new Error('The API returned an unexpected response.')
        if (active) setConnection({ status: 'connected', message: 'The Laravel API is connected.' })
      } catch (error) {
        if (!active) return
        setConnection({ status: 'error', message: controller.signal.aborted ? 'The API request timed out. Check that the backend is running.' : error instanceof Error ? error.message : 'Unable to reach the API.' })
      } finally { clearTimeout(timeout) }
    }
    void checkConnection()
    return () => { active = false; clearTimeout(timeout); controller.abort() }
  }, [attempt])

  const activeAuthView = account.user ? null : authView ?? (!preservingDraft && account.error ? 'login' : null)
  useEffect(() => {
    const title = account.status === 'checking' ? 'Checking your session' : activeAuthView === 'login' ? 'Welcome back' : activeAuthView === 'register' ? 'Make it your own' : page === 'profile' ? 'Your profile' : 'Your life, live.'
    document.title = `Spectacles — ${title}`
  }, [activeAuthView, account.status, page])

  const focusMain = () => requestAnimationFrame(() => document.getElementById('main-content')?.focus({ preventScroll: true }))
  const clearOverlays = () => { setForm(null); setDetailId(null); setHealthOpen(false); setUndo(null); setToast('') }
  const changeEntry = (view: AuthMode | null) => {
    setAuthView(view); setAccountNotice('')
    if (view && account.status === 'unavailable') account.refresh()
    window.scrollTo({ top: 0, behavior: 'instant' })
  }
  const enterPreview = () => {
    clearOverlays(); setPreservingDraft(false); account.clearError(); changeEntry('login'); setPage('dashboard')
  }
  const submitAccount = async (mode: AuthMode, input: Credentials): Promise<void> => {
    const user = mode === 'register' ? await account.register(input) : await account.login(input)
    if (preservingDraft && user.id === collection.owner) {
      setPreservingDraft(false); setAuthView(null); setAccountNotice(''); focusMain(); return
    }
    clearOverlays(); setPreservingDraft(false); changeEntry('login'); setPage('dashboard'); focusMain()
  }
  const saveProfile = async (patch: ProfilePatch): Promise<AccountUser> => {
    const user = await account.save(patch)
    setToast('Account profile saved.')
    return user
  }
  const signOut = () => {
    setAccountNotice('')
    void account.logout().then(() => {
      clearOverlays(); setPreservingDraft(false); setPage('dashboard'); changeEntry('login')
    }).catch(error => setAccountNotice(accountErrorMessage(error)))
  }

  const commit = async (data: Workspace, message: string, undoable = false): Promise<boolean> => {
    if (locked.current || !owner || collection.status !== 'ready') return false
    locked.current = true; setSaving(true)
    const before = workspace
    const changed = data.concerts.find(c => JSON.stringify(c) !== JSON.stringify(before.concerts.find(old => old.id === c.id)))
    const removed = before.concerts.find(c => !data.concerts.some(current => current.id === c.id))
    try {
      const result = removed ? await collectionClient.remove(removed.id) : changed ? await collectionClient.save(changed, data.venues.find(v => v.id === changed.venueId), before.concerts.some(c => c.id === changed.id)) : before
      if (session.current !== owner) return false
      setCollection({ owner, data: result, status: 'ready', error: '' }); setAccountNotice(''); setUndo(undoable ? before : null); setToast(message)
      return true
    } catch (error) {
      if (session.current === owner) {
        setAccountNotice(error instanceof Error ? error.message : 'Unable to save your collection.')
        if (error instanceof CollectionError && error.status === 401) expireCatalogSession()
      }
      return false
    } finally { locked.current = false; setSaving(false) }
  }
  const updateConcert = (concert: Concert, message: string, undoable = false) => commit({ ...workspace, concerts: workspace.concerts.map(c => c.id === concert.id ? concert : c) }, message, undoable)
  const saveConcert = async (concert: Concert, venue?: Venue): Promise<void> => {
    const exists = workspace.concerts.some(c => c.id === concert.id)
    const saved = await commit({ ...workspace, venues: venue ? [...workspace.venues.filter(v => v.id !== venue.id), venue] : workspace.venues, concerts: exists ? workspace.concerts.map(c => c.id === concert.id ? concert : c) : [...workspace.concerts, concert] }, exists ? 'Concert updated in the database.' : 'Concert saved in the database.')
    if (saved) setForm(null)
    else throw new Error('The database save was not confirmed. Check your connection or session. Your draft is still here.')
  }
  const navigate = (id: Page) => { setPage(id); setDetailId(null); window.scrollTo({ top: 0, behavior: 'instant' }); requestAnimationFrame(() => document.getElementById('main-content')?.focus({ preventScroll: true })) }
  const openSetlist = (id: string) => { setSetlistId(id); navigate('setlists') }
  const detail = workspace.concerts.find(c => c.id === detailId)
  const upcoming = sortConcerts(workspace.concerts.filter(c => ['upcoming', 'today'].includes(relation(c.date, today))))
  const next = upcoming[0]
  const recent = sortConcerts(workspace.concerts.filter(c => relation(c.date, today) === 'past' && c.setlist.length), true)[0]
  const artistCount = new Set(workspace.concerts.flatMap(c => c.artists.map((name, index) => c.catalog?.artistIds[index] ?? name))).size
  const songCount = workspace.concerts.reduce((count, c) => count + c.setlist.filter(e => e.type === 'song').length, 0)
  const venues = uniqueVenues(workspace)
  const pageInfo = pageCopy[page]
  const visibleConcerts = filteredConcerts(workspace.concerts, workspace.venues, query, filter, today, venueFilter)

  if (account.status === 'checking' || account.status === 'unavailable' && !preservingDraft) return <main className="account-gate"><Soundmark /><h1>Spectacles<span className="lime-dot">.</span></h1><p role={account.status === 'checking' ? 'status' : 'alert'}>{account.status === 'checking' ? 'Checking your session…' : account.error}</p>{account.status === 'unavailable' && <div><button className="button primary" onClick={account.refresh}>Try again</button></div>}</main>
  if (!preservingDraft && (activeAuthView || !owner)) return <AuthView key={activeAuthView} mode={activeAuthView ?? 'login'} busy={account.busy} notice={account.error || accountNotice} onSwitch={changeEntry} onExplore={enterPreview} onSubmit={submitAccount} />

  return <div className="app-shell"><a className="skip-link" href="#main-content">Skip to content</a>
    <aside className="sidebar"><button className="brand" onClick={() => navigate('dashboard')} aria-label="Spectacles dashboard"><Soundmark /><span>Spectacles<span className="brand-period">.</span></span></button>
      <div className="nav-eyebrow">YOUR BACKSTAGE</div><nav aria-label="Main navigation">{navigation.map(item => <button className={`nav-link ${page === item.id ? 'active' : ''}`} key={item.id} onClick={() => navigate(item.id)} aria-current={page === item.id ? 'page' : undefined}><Icon name={item.icon} /><span>{item.label}</span>{item.id === 'concerts' && <small>{workspace.concerts.length}</small>}</button>)}</nav>
      <div className="sidebar-bottom"><div className="workspace-label"><Icon name="layers" size={19} /><span>Your collection<small>{saving ? 'Saving…' : 'Saved to your account'}</small></span></div><button className={`connection-indicator ${connection.status}`} onClick={() => setHealthOpen(true)}><i />{connection.status === 'connected' ? 'API connected' : connection.status === 'loading' ? 'Checking API…' : 'API unavailable'}<Icon name="right" size={13} /></button><p>For the love of live music.</p></div>
    </aside>
    <div className="main-shell"><header className="topbar"><span className="breadcrumb"><span>Spectacles</span><Icon name="right" size={13} /><strong>{page === 'profile' ? 'Profile' : navigation.find(n => n.id === page)!.label}</strong></span><div className="topbar-right"><span className="preview-label"><i />Your account collection</span><button className="icon-button topbar-search" aria-label="Search concerts" onClick={() => { setQuery(''); setFilter('all'); setVenueFilter(undefined); navigate('concerts'); requestAnimationFrame(() => document.getElementById('concert-search')?.focus()) }}><Icon name="search" /></button><button className={`mobile-api ${connection.status}`} onClick={() => setHealthOpen(true)} aria-label={connection.status === 'connected' ? 'API connected' : connection.status === 'loading' ? 'Checking API' : 'API unavailable'}><i /></button><AccountMenu user={account.user} busy={account.busy} onProfile={() => navigate('profile')} onAuth={changeEntry} onSignOut={signOut} /></div></header>
      <main id="main-content" tabIndex={-1} aria-busy={saving}>
        {owner && collection.status === 'loading' && <p role="status">Loading your collection…</p>}
        {owner && collection.status === 'error' && <p role="alert">{collection.error} <button onClick={() => setCollectionRetry(value => value + 1)}>Try again</button></p>}
        {saving && <p role="status">Saving to the database…</p>}
        {accountNotice && <p className="account-error" role="alert">{accountNotice}</p>}
        <p className="demo-notice">Your collection is saved to your account.</p>
        <fieldset disabled={saving || collection.status !== 'ready'} style={{ border: 0, padding: 0, margin: 0, minWidth: 0 }}><div className="page-heading"><div><p className="eyebrow">{pageInfo[0]}</p><h1>{pageInfo[1]}</h1><p className="page-subtitle">{pageInfo[2]}</p></div>{page !== 'map' && page !== 'setlists' && page !== 'profile' && <button className="button primary add-concert" onClick={() => setForm({})}><Icon name="plus" size={21} />Add concert</button>}</div>
        {page === 'dashboard' && <div className="dashboard-content"><section className="stats-strip" aria-label="Collection statistics">{[[workspace.concerts.length, 'Concerts', 'music'], [artistCount, 'Artists', 'heart'], [venues.length, 'Venues', 'pin'], [songCount, 'Songs saved', 'list']].map(([value, label, icon]) => <div className="stat" key={label}><span className="stat-label">{label}<Icon name={icon as IconName} size={17} /></span><strong>{value}</strong><small>{label === 'Concerts' ? `${upcoming.length} still to come` : label === 'Artists' ? 'A soundtrack of your own' : label === 'Venues' ? 'Places that feel like home' : 'Ready for another listen'}</small></div>)}</section>
          {next ? <section className="next-concert" aria-label="Next concert countdown"><img className="stage-photo" src={stagePhoto} alt="A live band silhouetted against lime stage lights" fetchPriority="high" /><div className="next-concert-shade" /><div className="next-concert-copy"><span className="next-label"><i />UP NEXT</span><h2>{next.title}</h2><p><Icon name="pin" size={16} />{workspace.venues.find(v => v.id === next.venueId)?.name ?? 'Venue to confirm'}<span>·</span>{formatDate(next.date)}</p><button className="button photo-button" onClick={() => setDetailId(next.id)}>View concert<Icon name="arrow" size={18} /></button></div><div className="countdown"><span>{daysUntil(next.date, today) === 0 ? 'Tonight' : daysUntil(next.date, today)}</span><small>{daysUntil(next.date, today) === 0 ? 'THE WAIT IS OVER' : 'DAYS TO GO'}</small></div><span className="photo-caption">THE NEXT GREAT MEMORY</span></section> : <section className="next-concert no-upcoming"><Icon name="music" size={40} /><h2>Something to look forward to.</h2><p>Add your next concert and let the countdown begin.</p><button className="button lime" onClick={() => setForm({})}><Icon name="plus" />Add a concert</button></section>}
          <div className="dashboard-lower"><section className="upcoming-section"><div className="section-heading"><div><h2>Coming up<span className="lime-dot">.</span></h2><p>Good things on the horizon.</p></div><button className="text-button" onClick={() => { setFilter('upcoming'); setQuery(''); setVenueFilter(undefined); navigate('concerts') }}>View all<Icon name="arrow" size={16} /></button></div><ConcertList compact concerts={upcoming.slice(0, 3)} venues={workspace.venues} today={today} onOpen={c => setDetailId(c.id)} /></section>
            <section className="setlist-preview"><span className="setlist-preview-icon"><Icon name="music" size={23} /></span><p className="eyebrow">THE LAST ENCORE</p><h2>Still ringing<br />in your ears.</h2>{recent ? <><p>{recent.title} · {formatDate(recent.date)}</p><ol>{recent.setlist.filter(e => e.type === 'song').slice(0, 3).map((e, i) => <li key={e.id}><span>{String(i + 1).padStart(2, '0')}</span>{e.title}</li>)}</ol><button className="text-button" onClick={() => openSetlist(recent.id)}>Revisit the setlist<Icon name="arrow" size={17} /></button></> : <><p>The show ends. The songs stay.</p><button className="text-button" onClick={() => navigate('setlists')}>Start a setlist<Icon name="arrow" size={17} /></button></>}</section></div>
          <Heatmap concerts={workspace.concerts} today={today} onOpen={c => setDetailId(c.id)} />
        </div>}
        {page === 'concerts' && <section><div className="collection-toolbar"><label className="search-field"><Icon name="search" size={19} /><span className="sr-only">Search artists or venues</span><input id="concert-search" placeholder="Search artists or venues" value={query} onChange={e => setQuery(e.target.value)} /></label><div className="filter-tabs" aria-label="Filter concerts">{[['all', 'All concerts'], ['upcoming', 'Upcoming'], ['past', 'Past'], ['unconfirmed', 'Date to confirm']].map(([value, label]) => <button key={value} aria-pressed={filter === value} className={filter === value ? 'active' : ''} onClick={() => setFilter(value)}>{label}</button>)}</div></div>{venueFilter && <div className="active-filter"><Icon name="pin" size={15} />{workspace.venues.find(v => v.id === venueFilter)?.name}<button className="icon-button" aria-label="Clear venue filter" onClick={() => setVenueFilter(undefined)}><Icon name="close" size={15} /></button></div>}<div className="collection-summary"><span>{visibleConcerts.length} concert{visibleConcerts.length === 1 ? '' : 's'}</span><span>All dates in each concert’s local time</span></div><ConcertList concerts={visibleConcerts} venues={workspace.venues} today={today} onOpen={c => setDetailId(c.id)} onEdit={c => setForm({ concert: c })} onTicket={c => updateConcert({ ...c, ticketsBought: !c.ticketsBought }, 'Ticket preference saved.')} /></section>}
        {page === 'calendar' && <CalendarView concerts={workspace.concerts} venues={workspace.venues} today={today} onOpen={c => setDetailId(c.id)} onAdd={date => setForm({ date })} />}
        {page === 'map' && <VenueMap workspace={workspace} onViewConcerts={venue => { setVenueFilter(venue.id); setFilter('all'); setQuery(''); navigate('concerts') }} />}
        {page === 'setlists' && <SetlistsView key={setlistId} catalog={catalog} concerts={workspace.concerts} venues={workspace.venues} selectedId={setlistId} onSelect={setSetlistId} onUpdate={updateConcert} />}
        {page === 'profile' && account.user && <ProfileView key={account.user.id} user={account.user} counts={{ concerts: workspace.concerts.length, artists: artistCount, venues: venues.length }} busy={account.busy} onSave={saveProfile} onCancel={() => navigate('dashboard')} />}
        </fieldset><footer className="page-footer"><span>Spectacles<span className="brand-period">.</span> <span>A personal archive of being there.</span></span><span>Your concert collection</span></footer>
      </main>
    </div>
    {form && <ConcertForm busy={saving} onSignIn={!owner ? () => changeEntry('login') : undefined} disabled={saving || !owner} catalog={catalog} concert={form.concert} initialDate={form.date} venues={workspace.venues} onClose={() => { if (!saving) setForm(null) }} onSave={saveConcert} />}
    {detail && <Dialog title={detail.title} drawer onClose={() => setDetailId(null)}><fieldset disabled={saving || !owner} style={{ display: 'contents' }}><div className="concert-detail"><div className="detail-photo"><img src={stagePhoto} alt="Light spilling across a live concert crowd" /></div><p className={`detail-date ${relation(detail.date, today)}`}><Icon name="calendar" />{formatDate(detail.date, true)}{detail.time && ` · ${detail.time}`}</p><h3>{workspace.venues.find(v => v.id === detail.venueId)?.name ?? 'Venue to confirm'}</h3><p className="muted">{workspace.venues.find(v => v.id === detail.venueId)?.city || 'Location to confirm'}</p><p className="detail-lineup"><span className="eyebrow">LINEUP</span>{detail.artists.join(' · ') || 'Lineup to confirm'}</p><div className="detail-flags"><label><input type="checkbox" checked={detail.interested} onChange={() => updateConcert({ ...detail, interested: !detail.interested }, 'Interest preference saved.')} /><Icon name="heart" size={18} />Interested</label><label><input type="checkbox" checked={detail.ticketsBought} onChange={() => updateConcert({ ...detail, ticketsBought: !detail.ticketsBought }, 'Ticket preference saved.')} /><Icon name="ticket" size={18} />Tickets bought</label></div>{detail.notes && <div className="detail-notes"><span className="eyebrow">NOTES</span><p>{detail.notes}</p></div>}<button className="button primary" onClick={() => openSetlist(detail.id)}><Icon name="list" size={18} />{detail.setlist.length ? 'View & edit setlist' : 'Start the setlist'}<Icon name="arrow" size={18} /></button><button className="button secondary" onClick={() => { setDetailId(null); setForm({ concert: detail }) }}><Icon name="edit" size={18} />Edit concert</button><button className="text-button delete-concert" onClick={() => { void commit({ ...workspace, concerts: workspace.concerts.filter(c => c.id !== detail.id) }, `${detail.title} removed.`, true).then(saved => { if (saved) setDetailId(null) }) }}><Icon name="trash" size={17} />Delete concert</button><p className="hint">Saved to your account.</p></div></fieldset></Dialog>}
    {preservingDraft && activeAuthView && <Dialog title="Sign in again" onClose={() => setAuthView(null)}><AuthView mode={activeAuthView} busy={account.busy} notice={account.error || accountNotice} onSwitch={changeEntry} onExplore={() => setAuthView(null)} onSubmit={submitAccount} /></Dialog>}
    {healthOpen && <Dialog title="API connection" onClose={() => setHealthOpen(false)}><div className={`health-content ${connection.status}`}><span className="health-status">{connection.status}</span><p role="status" aria-live="polite">{connection.message}</p><code>{apiBaseUrl}/api/health</code>{connection.status === 'error' && <p className="hint">Check the API URL and the backend’s allowed frontend origin.</p>}<p className="muted">This checks the Laravel health endpoint. Your collection is stored in the database.</p><button className="button primary" disabled={connection.status === 'loading'} onClick={() => setAttempt(v => v + 1)}><Icon name="refresh" size={17} />{connection.status === 'loading' ? 'Checking…' : 'Check again'}</button></div></Dialog>}
    {toast && <div className="toast" role="status"><span className="toast-check"><Icon name="check" size={17} /></span><span>{toast}</span>{undo && <button onClick={() => { void commit(undo, 'Restored to your collection.') }}>Undo</button>}<button className="icon-button" onClick={() => { setToast(''); setUndo(null) }} aria-label="Dismiss notification"><Icon name="close" size={17} /></button></div>}
  </div>
}

export default App
