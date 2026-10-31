import assert from 'node:assert/strict'
import {test} from 'node:test'
import {searchCreation,describeCreationMatch,mergeCreationMatches} from '../src/creationSearch.ts'
import {creationStepError} from '../src/creationPolicy.ts'
import {createWizard} from '../src/concertWizard.ts'
import {performance,venue} from './provider-fixtures.mjs'
const signal=()=>new AbortController().signal
const page=(items=[],total=items.length)=>({items,total,page:1,lastPage:1})
function setup(kind='artists',local=page()){
 const calls=[]
 const options={kind,catalog:{enabled:true,sessionKey:'a',onUnauthorized(){calls.push('unauthorized')},client:{async search(...args){calls.push(['local',...args.slice(0,3),args[4]]);return local}}},musicbrainz:{enabled:true,sessionKey:'a',onUnauthorized(){},client:{async search(...args){calls.push(['mb',...args.slice(0,2)]);return []}}},providers:{enabled:true,sessionKey:'a',onUnauthorized(){},client:{async tours(...args){calls.push(['tours',...args.slice(0,2)]);return {items:[],partial:true,nextCursor:null}},async places(q){calls.push(['places',q]);return {items:[venue],partial:false}},async performances(filters){calls.push(['performances',filters]);return {items:[performance],partial:true,nextCursor:'next'}}}}}
 return {options,calls}
}
test('creation browse requests only five locals; short Unicode input makes no request',async()=>{
 const {options,calls}=setup()
 await searchCreation(options,'',signal());assert.equal(calls.length,1);assert.equal(calls[0].at(-1),5)
 calls.length=0;await searchCreation(options,'é😀',signal());assert.equal(calls.length,0)
})
test('local matches suppress all online reads; empty page with nonzero total does not fall back',async()=>{
 const {options,calls}=setup('venues',page([],9))
 await searchCreation(options,'arena',signal());assert.equal(calls.length,1)
})
test('saved Events and online performances are both searched and duplicates use verified local identity',async()=>{
 const row={id:'saved-event',title:'Saved event',date:{year:2024,month:2,day:20},venue:null}
 const {options,calls}=setup('events',page([row]));options.performance={period:'past',artistMbid:performance.artist.ref.externalId}
 const duplicate={...performance,localEventId:row.id}; const other={...performance,ref:{...performance.ref,externalId:'another'}}
 options.providers.client.performances=async filters=>{calls.push(['performances',filters]);return {items:[duplicate,other],partial:true,nextCursor:'next'}}
 const result=await searchCreation(options,'2024-02-20',signal());assert.deepEqual(result.items.map(x=>x.type),['local','performance']);assert.equal(result.items[1].candidate,other);assert.equal(result.nextCursor,'next');assert.equal(result.partial,true)
 const later=mergeCreationMatches([{type:'performance',candidate:duplicate},{type:'local',kind:'events',row}]);assert.deepEqual(later.map(x=>x.type),['local'])
})
test('saved Events remain available when the online provider fails',async()=>{
 const row={id:'saved-event',title:'Saved event',date:{year:2024,month:2,day:20},venue:null}
 const {options}=setup('events',page([row]));options.performance={period:'past',artistMbid:performance.artist.ref.externalId};options.providers.client.performances=async()=>{throw Error('Source unavailable')}
 const result=await searchCreation(options,'2024-02-20',signal());assert.equal(result.items[0].row,row);assert.match(result.messages[0],/Source unavailable/)
})
test('saved and online Event pages advance independently',async()=>{
 const row={id:'saved-event',title:'Saved event',date:{year:2024,month:2,day:20},venue:null};const {options,calls}=setup('events',{...page([row],2),lastPage:2});options.performance={period:'past',artistMbid:performance.artist.ref.externalId}
 const first=await searchCreation(options,'2024-02-20',signal());assert.equal(first.nextPage,2);assert.equal(first.nextCursor,'next')
 calls.length=0;await searchCreation(options,'2024-02-20',signal(),2);assert.deepEqual(calls.map(c=>c[0]),['local'])
 calls.length=0;await searchCreation(options,'2024-02-20',signal(),1,'next');assert.deepEqual(calls.map(c=>c[0]),['performances']);assert.equal(calls[0][1].cursor,'next')
})
test('unaccented Tour query retains the accented local match and suppresses providers',async()=>{
 const row={id:'local-tour',name:'México lindo y querido',artists:[]}
 const {options,calls}=setup('tours',page([row],1))
 options.artist={artistMbid:performance.artist.ref.externalId}
 options.filters={artist_mbids:[performance.artist.ref.externalId],artist_uuids:[]}
 const result=await searchCreation(options,'mexico lindo y querido',signal())
 assert.equal(calls.length,1);assert.equal(calls[0][0],'local')
 assert.equal(result.items[0].row.name,'México lindo y querido')
})
test('local failure never initiates a remote fallback',async()=>{
 const {options,calls}=setup();options.catalog.client.search=async()=>{throw Error('local offline')}
 await assert.rejects(searchCreation(options,'Radiohead',signal()),/local offline/);assert.equal(calls.length,0)
})
test('single Tour query uses both providers with the same exact Artist scope and retains surviving results on source failure',async()=>{
 const {options,calls}=setup('tours');options.artist={artistMbid:performance.artist.ref.externalId};options.filters={artist_mbids:[performance.artist.ref.externalId],artist_uuids:[]}
 options.musicbrainz.client.search=async(kind,q,s,scope)=>{calls.push(['mb-scope',scope]);throw Error('MusicBrainz busy')}
 options.providers.client.tours=async(scope,q)=>{calls.push(['tour-scope',scope,q]);return {items:[performance.tour],partial:true,nextCursor:'next'}}
 const result=await searchCreation(options,'Tour',signal());assert.equal(result.items[0].type,'tour');assert.deepEqual(calls[1][1],options.filters);assert.deepEqual(calls[2][1],options.artist);assert.match(result.messages[0],/busy/);assert.equal(result.nextCursor,'next')
})
test('manual Artist never triggers global Tour discovery',async()=>{
 const {options,calls}=setup('tours');await searchCreation(options,'European',signal());assert.equal(calls.length,1)
})
test('Venue and Event use their unified query and preserve original selection payloads and cursor',async()=>{
 const {options,calls}=setup('venues');const v=await searchCreation(options,'Arena',signal());assert.equal(v.items[0].candidate,venue);assert.deepEqual(calls[1],['places','Arena'])
 options.kind='events';options.performance={period:'upcoming',artistMbid:performance.artist.ref.externalId,tourId:'tour-id',tourName:'Tour'}
 const p=await searchCreation(options,'Copenhagen',signal(),1,'cursor');assert.equal(p.items[0].candidate,performance);assert.equal(p.nextCursor,'next');assert.deepEqual(calls.at(-1)[1],{...options.performance,q:'Copenhagen',cursor:'cursor'})
 assert.match(describeCreationMatch(p.items[0]).label,/ at /)
})
test('exact event dates search local date fields and narrow past source listings by year',async()=>{
 const {options,calls}=setup('events');options.performance={period:'past',artistMbid:performance.artist.ref.externalId}
 await searchCreation(options,'2024-02-20',signal())
 assert.deepEqual(calls[0][2],{artist_mbid:performance.artist.ref.externalId,year:2024,month:2,day:20})
 assert.deepEqual(calls[1][1],{...options.performance,q:'2024-02-20',date:'2024-02-20',year:2024})
 calls.length=0;options.performance.period='upcoming';await searchCreation(options,'2026-11-24',signal());assert.equal(calls[1][1].date,'2026-11-24');assert.equal(calls[1][1].year,undefined)
 calls.length=0;await searchCreation(options,'2024-02-30',signal());assert.equal(calls[0][2].q,'2024-02-30');assert.equal(calls[1][1].date,undefined)
})
test('manual artist explains why online event search did not run',async()=>{
 const {options,calls}=setup('events');options.performance={period:'past'}
 const result=await searchCreation(options,'2025-05-20',signal());assert.equal(calls.length,1);assert.match(result.messages[0],/needs an identified Artist or Venue/)
})
test('aborted or guest creation searches issue no requests',async()=>{
 const {options,calls}=setup();const c=new AbortController();c.abort();await searchCreation(options,'Radiohead',c.signal);options.catalog.enabled=false;await searchCreation(options,'Radiohead',signal());assert.equal(calls.length,0)
})
test('creation cardinality blocks empty Artist, multiple restored choices and missing Venue without mutating broader domain drafts',()=>{
 const s=createWizard();assert.match(creationStepError(s,'artist'),/one Artist/)
 s.draft.artists=[{name:'A',catalogId:null}];assert.equal(creationStepError(s,'artist'),null);assert.equal(creationStepError(s,'tour'),null)
 s.draft.tours=[{name:'One',catalogId:null},{name:'Two',catalogId:null}];const before=structuredClone(s);assert.match(creationStepError(s,'tour'),/at most one/);assert.deepEqual(s,before)
 s.draft.tours=[];assert.match(creationStepError(s,'event'),/Venue/);s.draft.concert.venueId='00000000-0000-4000-8000-000000000001';assert.equal(creationStepError(s,'event'),null)
})
