import assert from 'node:assert/strict'
import {test} from 'node:test'
import {createWizard,finishWizard} from '../src/concertWizard.ts'
import {applyPerformance,applyManualPerformance,detachPerformance,performanceSummary,venueHistoryFilters} from '../src/providerDraft.ts'
import {performance,venue} from './provider-fixtures.mjs'
test('full group apply preserves personal fields, rows and stable Attendance identity',()=>{
 const before=createWizard(),id=before.draft.concert.id
 before.draft.concert.notes='My note';before.draft.concert.ticketsBought=true
 before.draft.concert.time='21:00';before.draft.timezoneText='America/Argentina/Buenos_Aires'
 before.draft.concert.setlist=[{id:'00000000-0000-4000-8000-000000000021',type:'note',title:'My section',artist:'',tape:false}]
 const after=applyPerformance(before,performance,[])
 assert.equal(after.draft.concert.id,id);assert.equal(after.draft.concert.notes,'My note')
 assert.deepEqual(after.draft.artists.map(a=>a.name),['Maná']);assert.deepEqual(after.draft.tours.map(t=>t.name),['Drama y luz'])
 assert.equal(after.draft.dateText,'2026-10-30');assert.equal(after.draft.concert.time,'');assert.equal(after.draft.timezoneText,'')
 assert.deepEqual(after.draft.concert.setlist,before.draft.concert.setlist)
 const submitted=finishWizard(after)
 assert.equal(submitted.pendingCatalog.event.selection,'public-performance-proof')
 assert.equal(submitted.pendingCatalog.venue.selection,'public-venue-proof')
 assert.equal(submitted.concert.catalog.tours[0].draft,undefined)
 assert.equal(submitted.venue.selection,undefined)
 assert.equal(detachPerformance(after).draft.providerEvent,undefined)
})
test('missing Tour clears previous choices; source summary retains no full cached Event',()=>{
 const state=createWizard();state.draft.tours=[{name:'Old tour',catalogId:null}]
 const result=applyPerformance(state,{...performance,tour:null},[])
 assert.deepEqual(result.draft.tours,[])
 assert.equal(performanceSummary(performance).localEvent,undefined)
 assert.equal(performanceSummary(performance).selection,undefined)
 assert.deepEqual(venueHistoryFilters([venue.ref],'upcoming',venue.url),{period:'upcoming',venueId:'73d6ca11',venueUrl:venue.url})
 assert.equal(venueHistoryFilters([],'past'),null)
})
test('explicit manual fallback retains the API Tour name without Event or Tour source identity',()=>{
 const state=applyManualPerformance(createWizard(),{artist:performance.artist,venue:performance.venue,date:performance.date,tourName:'Tour without HTML ID',time:null,timezone:null})
 const saved=finishWizard(state)
 assert.equal(saved.pendingCatalog.event,undefined)
 assert.equal(saved.pendingCatalog.tours,undefined)
 assert.deepEqual(saved.concert.catalog.tours,[{name:'Tour without HTML ID',catalogId:null}])
 assert.equal(saved.pendingCatalog.venue.selection,'public-venue-proof')
})

test('choosing another local Venue removes the prior source binding while retaining personal fields',async()=>{
 const {applyLocalVenue,detachPerformance}=await import('../src/providerDraft.ts')
 const {createWizard,finishWizard}=await import('../src/concertWizard.ts')
 const state=detachPerformance(applyPerformance(createWizard(),performance,[]))
 state.draft.concert.notes='Keep me'
 const chosen={id:'00000000-0000-4000-8000-000000000099',name:'Other Venue',city:null,region:null,country_code:null,lat:null,lng:null,timezone:null}
 const next=applyLocalVenue(state,chosen,[])
 assert.equal(next.draft.providerVenue,undefined)
 assert.equal(next.draft.pendingVenue.catalogId,chosen.id)
 assert.equal(next.draft.concert.notes,'Keep me')
 assert.equal(finishWizard(next).pendingCatalog?.venue,undefined)
})
test('a known provider Venue retains its proof and source URL for authoritative save metadata',async()=>{
 const {applyProviderVenue}=await import('../src/providerDraft.ts')
 const next=applyProviderVenue(createWizard(),{...venue,localId:'00000000-0000-4000-8000-000000000099',venueUrl:venue.url})
 assert.equal(next.draft.providerVenue.venueUrl,venue.url)
 assert.equal(next.draft.pendingVenue.catalogId,undefined)
 next.draft.concert.title='Concert'
 next.draft.precision='unknown'
 assert.equal(finishWizard(next).pendingCatalog.venue.selection,venue.selection)
 assert.equal(venueHistoryFilters([venue.ref],'past'),null)
})
test('history selects the canonical URL for the chosen explicit Venue identity',()=>{
 const old={provider:'setlist_fm',resourceType:'venue',externalId:'63d626d3'}
 const oldUrl='https://www.setlist.fm/venue/rosemont-horizon-rosemont-il-usa-63d626d3.html'
 const sources=[{externalId:venue.ref.externalId,url:venue.url},{externalId:old.externalId,url:oldUrl}]
 assert.equal(venueHistoryFilters([venue.ref,old],'past',undefined,sources),null)
 assert.deepEqual(venueHistoryFilters([old],'past',venue.url,sources),{period:'past',venueId:old.externalId,venueUrl:oldUrl})
 assert.equal(venueHistoryFilters([old],'past',venue.url),null)
})
