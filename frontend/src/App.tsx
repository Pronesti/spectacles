import { useEffect, useState } from 'react'
import { createDemoWorkspace, daysUntil, decodeWorkspace, filteredConcerts, formatDate, relation, sortConcerts, todayKey, uniqueVenues, WORKSPACE_KEY } from './domain'
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
import type { AuthMode, Credentials, ProfilePatch } from './accountApi'
import { useAccount } from './useAccount'
import { AuthView } from './components/AuthView'
import { AccountMenu } from './components/AccountMenu'
import { ProfileView } from './components/ProfileView'
import './App.css'
import './account.css'

const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000').replace(/\/$/, '')
const accountClient = createAccountClient(apiBaseUrl)
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

function initialWorkspace(): { data: Workspace; issue: string } {
  try {
    const raw = localStorage.getItem(WORKSPACE_KEY)
    if (raw === null) return { data: createDemoWorkspace(), issue: '' }
    const saved = decodeWorkspace(raw)
    return saved ? { data: saved, issue: '' } : { data: createDemoWorkspace(), issue: 'Your saved preview could not be read. This temporary demo will not overwrite it.' }
  } catch { return { data: createDemoWorkspace(), issue: 'Browser storage is unavailable. Changes will last for this session only.' } }
}

function App() {
  const [initial] = useState(initialWorkspace)
  const [workspace, setWorkspace] = useState(initial.data)
  const [storageIssue, setStorageIssue] = useState(initial.issue)
  const [today, setToday] = useState(todayKey)
  const [page, setPage] = useState<Page>('dashboard')
  const account = useAccount(accountClient)
  const [authView, setAuthView] = useState<AuthMode | null>('login')
  const [exploringDemo, setExploringDemo] = useState(false)
  const [accountNotice, setAccountNotice] = useState('')
  const [query, setQuery] = useState(''), [filter, setFilter] = useState('all')
  const [venueFilter, setVenueFilter] = useState<string | undefined>()
  const [form, setForm] = useState<{ concert?: Concert; date?: string } | null>(null)
  const [detailId, setDetailId] = useState<string | null>(null)
  const [setlistId, setSetlistId] = useState('concert-6')
  const [toast, setToast] = useState(''), [undo, setUndo] = useState<Workspace | null>(null)
  const [healthOpen, setHealthOpen] = useState(false)
  const [attempt, setAttempt] = useState(0)
  const [connection, setConnection] = useState<Connection>({ status: 'loading', message: 'Checking the API…' })

  useEffect(() => {
    const timer = setInterval(() => setToday(todayKey()), 60_000)
    return () => clearInterval(timer)
  }, [])
  useEffect(() => {
    if (initial.issue) return
    try { localStorage.setItem(WORKSPACE_KEY, JSON.stringify(workspace)) }
    // oxlint-disable-next-line react/set-state-in-effect -- Surface a failed external storage write without discarding the session.
    catch { setStorageIssue('Your last changes could not be saved. Keep this tab open; browser storage may be full or unavailable.') }
  }, [workspace, initial.issue])
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

  const activeAuthView = account.user ? null : authView ?? (!exploringDemo && account.error ? 'login' : null)
  useEffect(() => {
    const title = account.status === 'checking' ? 'Checking your session' : activeAuthView === 'login' ? 'Welcome back' : activeAuthView === 'register' ? 'Make it your own' : page === 'profile' ? 'Your profile' : 'Your life, live.'
    document.title = `Spectacles — ${title}`
  }, [activeAuthView, account.status, page])

  const focusMain = () => requestAnimationFrame(() => document.getElementById('main-content')?.focus({ preventScroll: true }))
  const clearOverlays = () => { setForm(null); setDetailId(null); setHealthOpen(false); setUndo(null); setToast('') }
  const changeEntry = (view: AuthMode | null) => {
    setAuthView(view); setAccountNotice('')
    if (view) { setExploringDemo(false); if (account.status === 'unavailable') account.refresh() }
    window.scrollTo({ top: 0, behavior: 'instant' })
  }
  const enterPreview = () => {
    clearOverlays(); setExploringDemo(true); account.clearError(); changeEntry(null); setPage('dashboard'); focusMain()
  }
  const submitAccount = async (mode: AuthMode, input: Credentials): Promise<void> => {
    if (mode === 'register') await account.register(input)
    else await account.login(input)
    clearOverlays(); setExploringDemo(false); changeEntry('login'); setPage('dashboard'); focusMain()
  }
  const saveProfile = async (patch: ProfilePatch): Promise<void> => {
    await account.save(patch)
    setToast('Account profile saved.')
  }
  const signOut = () => {
    setAccountNotice('')
    void account.logout().then(() => {
      clearOverlays(); setExploringDemo(false); setPage('dashboard'); changeEntry('login')
    }).catch(error => setAccountNotice(accountErrorMessage(error)))
  }

  const commit = (data: Workspace, message: string, undoable = false) => { setUndo(undoable ? workspace : null); setWorkspace(data); setToast(message) }
  const updateConcert = (concert: Concert, message: string, undoable = false) => commit({ ...workspace, concerts: workspace.concerts.map(c => c.id === concert.id ? concert : c) }, message, undoable)
  const saveConcert = (concert: Concert, venue?: Venue) => {
    const exists = workspace.concerts.some(c => c.id === concert.id)
    commit({ ...workspace, venues: venue ? [...workspace.venues, venue] : workspace.venues, concerts: exists ? workspace.concerts.map(c => c.id === concert.id ? concert : c) : [...workspace.concerts, concert] }, exists ? 'Concert updated.' : 'A new night to look forward to.')
    setForm(null)
  }
  const navigate = (id: Page) => { setPage(id); setDetailId(null); window.scrollTo({ top: 0, behavior: 'instant' }); requestAnimationFrame(() => document.getElementById('main-content')?.focus({ preventScroll: true })) }
  const openSetlist = (id: string) => { setSetlistId(id); navigate('setlists') }
  const detail = workspace.concerts.find(c => c.id === detailId)
  const upcoming = sortConcerts(workspace.concerts.filter(c => ['upcoming', 'today'].includes(relation(c.date, today))))
  const next = upcoming[0]
  const recent = sortConcerts(workspace.concerts.filter(c => relation(c.date, today) === 'past' && c.setlist.length), true)[0]
  const artistCount = new Set(workspace.concerts.flatMap(c => c.artists)).size
  const songCount = workspace.concerts.reduce((count, c) => count + c.setlist.filter(e => e.type === 'song').length, 0)
  const venues = uniqueVenues(workspace)
  const pageInfo = pageCopy[page]
  const visibleConcerts = filteredConcerts(workspace.concerts, workspace.venues, query, filter, today, venueFilter)

  if (account.status === 'checking' || account.status === 'unavailable' && !exploringDemo) return <main className="account-gate"><Soundmark /><h1>Spectacles<span className="lime-dot">.</span></h1><p role={account.status === 'checking' ? 'status' : 'alert'}>{account.status === 'checking' ? 'Checking your session…' : account.error}</p>{account.status === 'unavailable' && <div><button className="button primary" onClick={account.refresh}>Try again</button><button className="button secondary" onClick={enterPreview}>Explore the demo</button></div>}</main>
  if (activeAuthView) return <AuthView key={activeAuthView} mode={activeAuthView} busy={account.busy} notice={account.error || accountNotice} onSwitch={changeEntry} onExplore={enterPreview} onSubmit={submitAccount} />

  return <div className="app-shell"><a className="skip-link" href="#main-content">Skip to content</a>
    <aside className="sidebar"><button className="brand" onClick={() => navigate('dashboard')} aria-label="Spectacles dashboard"><Soundmark /><span>Spectacles<span className="brand-period">.</span></span></button>
      <div className="nav-eyebrow">YOUR BACKSTAGE</div><nav aria-label="Main navigation">{navigation.map(item => <button className={`nav-link ${page === item.id ? 'active' : ''}`} key={item.id} onClick={() => navigate(item.id)} aria-current={page === item.id ? 'page' : undefined}><Icon name={item.icon} /><span>{item.label}</span>{item.id === 'concerts' && <small>{workspace.concerts.length}</small>}</button>)}</nav>
      <div className="sidebar-bottom"><div className="workspace-label"><Icon name="layers" size={19} /><span>Demo workspace<small>{storageIssue ? 'Temporary session' : 'Saved in this browser'}</small></span></div><button className={`connection-indicator ${connection.status}`} onClick={() => setHealthOpen(true)}><i />{connection.status === 'connected' ? 'API connected' : connection.status === 'loading' ? 'Checking API…' : 'API unavailable'}<Icon name="right" size={13} /></button><p>For the love of live music.</p></div>
    </aside>
    <div className="main-shell"><header className="topbar"><span className="breadcrumb"><span>Spectacles</span><Icon name="right" size={13} /><strong>{page === 'profile' ? 'Profile' : navigation.find(n => n.id === page)!.label}</strong></span><div className="topbar-right"><span className="preview-label"><i />Demo concert collection</span><button className="icon-button topbar-search" aria-label="Search concerts" onClick={() => { setQuery(''); setFilter('all'); setVenueFilter(undefined); navigate('concerts'); requestAnimationFrame(() => document.getElementById('concert-search')?.focus()) }}><Icon name="search" /></button><button className={`mobile-api ${connection.status}`} onClick={() => setHealthOpen(true)} aria-label={connection.status === 'connected' ? 'API connected' : connection.status === 'loading' ? 'Checking API' : 'API unavailable'}><i /></button><AccountMenu user={account.user} busy={account.busy} onProfile={() => navigate('profile')} onAuth={changeEntry} onSignOut={signOut} /></div></header>
      <main id="main-content" tabIndex={-1}>
        {storageIssue && <p className="storage-warning" role="alert">{storageIssue}</p>}
        {accountNotice && <p className="account-error" role="alert">{accountNotice}</p>}
        <p className="demo-notice">Demo collection · saved in this browser, shared across sign-ins.</p>
        <div className="page-heading"><div><p className="eyebrow">{pageInfo[0]}</p><h1>{pageInfo[1]}</h1><p className="page-subtitle">{pageInfo[2]}</p></div>{page !== 'map' && page !== 'setlists' && page !== 'profile' && <button className="button primary add-concert" onClick={() => setForm({})}><Icon name="plus" size={21} />Add concert</button>}</div>
        {page === 'dashboard' && <div className="dashboard-content"><section className="stats-strip" aria-label="Collection statistics">{[[workspace.concerts.length, 'Concerts', 'music'], [artistCount, 'Artists', 'heart'], [venues.length, 'Venues', 'pin'], [songCount, 'Songs saved', 'list']].map(([value, label, icon]) => <div className="stat" key={label}><span className="stat-label">{label}<Icon name={icon as IconName} size={17} /></span><strong>{value}</strong><small>{label === 'Concerts' ? `${upcoming.length} still to come` : label === 'Artists' ? 'A soundtrack of your own' : label === 'Venues' ? 'Places that feel like home' : 'Ready for another listen'}</small></div>)}</section>
          {next ? <section className="next-concert" aria-label="Next concert countdown"><img className="stage-photo" src={stagePhoto} alt="A live band silhouetted against lime stage lights" fetchPriority="high" /><div className="next-concert-shade" /><div className="next-concert-copy"><span className="next-label"><i />UP NEXT</span><h2>{next.title}</h2><p><Icon name="pin" size={16} />{workspace.venues.find(v => v.id === next.venueId)?.name ?? 'Venue to confirm'}<span>·</span>{formatDate(next.date)}</p><button className="button photo-button" onClick={() => setDetailId(next.id)}>View concert<Icon name="arrow" size={18} /></button></div><div className="countdown"><span>{daysUntil(next.date, today) === 0 ? 'Tonight' : daysUntil(next.date, today)}</span><small>{daysUntil(next.date, today) === 0 ? 'THE WAIT IS OVER' : 'DAYS TO GO'}</small></div><span className="photo-caption">THE NEXT GREAT MEMORY</span></section> : <section className="next-concert no-upcoming"><Icon name="music" size={40} /><h2>Something to look forward to.</h2><p>Add your next concert and let the countdown begin.</p><button className="button lime" onClick={() => setForm({})}><Icon name="plus" />Add a concert</button></section>}
          <div className="dashboard-lower"><section className="upcoming-section"><div className="section-heading"><div><h2>Coming up<span className="lime-dot">.</span></h2><p>Good things on the horizon.</p></div><button className="text-button" onClick={() => { setFilter('upcoming'); setQuery(''); setVenueFilter(undefined); navigate('concerts') }}>View all<Icon name="arrow" size={16} /></button></div><ConcertList compact concerts={upcoming.slice(0, 3)} venues={workspace.venues} today={today} onOpen={c => setDetailId(c.id)} /></section>
            <section className="setlist-preview"><span className="setlist-preview-icon"><Icon name="music" size={23} /></span><p className="eyebrow">THE LAST ENCORE</p><h2>Still ringing<br />in your ears.</h2>{recent ? <><p>{recent.title} · {formatDate(recent.date)}</p><ol>{recent.setlist.filter(e => e.type === 'song').slice(0, 3).map((e, i) => <li key={e.id}><span>{String(i + 1).padStart(2, '0')}</span>{e.title}</li>)}</ol><button className="text-button" onClick={() => openSetlist(recent.id)}>Revisit the setlist<Icon name="arrow" size={17} /></button></> : <><p>The show ends. The songs stay.</p><button className="text-button" onClick={() => navigate('setlists')}>Start a setlist<Icon name="arrow" size={17} /></button></>}</section></div>
          <Heatmap concerts={workspace.concerts} today={today} onOpen={c => setDetailId(c.id)} />
        </div>}
        {page === 'concerts' && <section><div className="collection-toolbar"><label className="search-field"><Icon name="search" size={19} /><span className="sr-only">Search artists or venues</span><input id="concert-search" placeholder="Search artists or venues" value={query} onChange={e => setQuery(e.target.value)} /></label><div className="filter-tabs" aria-label="Filter concerts">{[['all', 'All concerts'], ['upcoming', 'Upcoming'], ['past', 'Past'], ['unconfirmed', 'Date to confirm']].map(([value, label]) => <button key={value} aria-pressed={filter === value} className={filter === value ? 'active' : ''} onClick={() => setFilter(value)}>{label}</button>)}</div></div>{venueFilter && <div className="active-filter"><Icon name="pin" size={15} />{workspace.venues.find(v => v.id === venueFilter)?.name}<button className="icon-button" aria-label="Clear venue filter" onClick={() => setVenueFilter(undefined)}><Icon name="close" size={15} /></button></div>}<div className="collection-summary"><span>{visibleConcerts.length} concert{visibleConcerts.length === 1 ? '' : 's'}</span><span>All dates in each concert’s local time</span></div><ConcertList concerts={visibleConcerts} venues={workspace.venues} today={today} onOpen={c => setDetailId(c.id)} onEdit={c => setForm({ concert: c })} onTicket={c => updateConcert({ ...c, ticketsBought: !c.ticketsBought }, 'Ticket preference saved.')} /></section>}
        {page === 'calendar' && <CalendarView concerts={workspace.concerts} venues={workspace.venues} today={today} onOpen={c => setDetailId(c.id)} onAdd={date => setForm({ date })} />}
        {page === 'map' && <VenueMap workspace={workspace} onViewConcerts={venue => { setVenueFilter(venue.id); setFilter('all'); setQuery(''); navigate('concerts') }} />}
        {page === 'setlists' && <SetlistsView key={setlistId} concerts={workspace.concerts} venues={workspace.venues} selectedId={setlistId} onSelect={setSetlistId} onUpdate={updateConcert} />}
        {page === 'profile' && account.user && <ProfileView key={JSON.stringify(account.user)} user={account.user} counts={{ concerts: workspace.concerts.length, artists: artistCount, venues: venues.length }} busy={account.busy} onSave={saveProfile} onCancel={() => navigate('dashboard')} />}
        <footer className="page-footer"><span>Spectacles<span className="brand-period">.</span> <span>A personal archive of being there.</span></span><span>Sample dates & lineups · Browser-local preview</span></footer>
      </main>
    </div>
    {form && <ConcertForm concert={form.concert} initialDate={form.date} venues={workspace.venues} onClose={() => setForm(null)} onSave={saveConcert} />}
    {detail && <Dialog title={detail.title} drawer onClose={() => setDetailId(null)}><div className="concert-detail"><div className="detail-photo"><img src={stagePhoto} alt="Light spilling across a live concert crowd" /></div><p className={`detail-date ${relation(detail.date, today)}`}><Icon name="calendar" />{formatDate(detail.date, true)}{detail.time && ` · ${detail.time}`}</p><h3>{workspace.venues.find(v => v.id === detail.venueId)?.name ?? 'Venue to confirm'}</h3><p className="muted">{workspace.venues.find(v => v.id === detail.venueId)?.city ?? 'Location to confirm'}</p><p className="detail-lineup"><span className="eyebrow">LINEUP</span>{detail.artists.join(' · ')}</p><div className="detail-flags"><label><input type="checkbox" checked={detail.interested} onChange={() => updateConcert({ ...detail, interested: !detail.interested }, 'Interest preference saved.')} /><Icon name="heart" size={18} />Interested</label><label><input type="checkbox" checked={detail.ticketsBought} onChange={() => updateConcert({ ...detail, ticketsBought: !detail.ticketsBought }, 'Ticket preference saved.')} /><Icon name="ticket" size={18} />Tickets bought</label></div>{detail.notes && <div className="detail-notes"><span className="eyebrow">NOTES</span><p>{detail.notes}</p></div>}<button className="button primary" onClick={() => openSetlist(detail.id)}><Icon name="list" size={18} />{detail.setlist.length ? 'View & edit setlist' : 'Start the setlist'}<Icon name="arrow" size={18} /></button><button className="button secondary" onClick={() => { setDetailId(null); setForm({ concert: detail }) }}><Icon name="edit" size={18} />Edit concert</button><button className="text-button delete-concert" onClick={() => { commit({ ...workspace, concerts: workspace.concerts.filter(c => c.id !== detail.id) }, `${detail.title} removed.`, true); setDetailId(null) }}><Icon name="trash" size={17} />Delete concert</button><p className="hint">Sample concert · Your changes stay in this browser.</p></div></Dialog>}
    {healthOpen && <Dialog title="API connection" onClose={() => setHealthOpen(false)}><div className={`health-content ${connection.status}`}><span className="health-status">{connection.status}</span><p role="status" aria-live="polite">{connection.message}</p><code>{apiBaseUrl}/api/health</code>{connection.status === 'error' && <p className="hint">Check the API URL and the backend’s allowed frontend origin.</p>}<p className="muted">This checks the Laravel health endpoint. The design preview stores concert edits in this browser.</p><button className="button primary" disabled={connection.status === 'loading'} onClick={() => setAttempt(v => v + 1)}><Icon name="refresh" size={17} />{connection.status === 'loading' ? 'Checking…' : 'Check again'}</button></div></Dialog>}
    {toast && <div className="toast" role="status"><span className="toast-check"><Icon name="check" size={17} /></span><span>{toast}</span>{undo && <button onClick={() => { setWorkspace(undo); setUndo(null); setToast('Restored to your collection.') }}>Undo</button>}<button className="icon-button" onClick={() => { setToast(''); setUndo(null) }} aria-label="Dismiss notification"><Icon name="close" size={17} /></button></div>}
  </div>
}

export default App
