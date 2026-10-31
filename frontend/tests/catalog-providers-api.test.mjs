import assert from 'node:assert/strict'
import {test} from 'node:test'
import {decodePerformancePreview,decodeTour,decodeVenue,decodeProviderPage} from '../src/catalogProviders.ts'
import {createCatalogProvidersClient,CatalogProvidersError} from '../src/catalogProvidersApi.ts'
import {performance,tour,venue} from './provider-fixtures.mjs'
test('provider IDs and show/performance identities are distinct',()=>{
 assert.equal(decodePerformancePreview(performance).ref.externalId,'b4cc196')
 assert.equal(decodeTour({...tour,url:'https://www.setlist.fm/search?artist=4bd7afb6&tour=bdac5de',ref:{...tour.ref,externalId:'bdac5de'}}).ref.externalId,'bdac5de')
 assert.throws(()=>decodePerformancePreview({...performance,localEventId:'b4cc196'}))
 assert.throws(()=>decodePerformancePreview({...performance,ref:{...performance.ref,externalId:'2b893c8a'}}))
})
test('invalid metadata and duplicate identities reject',()=>{
 assert.throws(()=>decodeVenue({...venue,values:{...venue.values,lat:12}}))
 assert.throws(()=>decodeVenue({...venue,values:{...venue.values,aliases:['a','a']}}))
 assert.throws(()=>decodeProviderPage({data:[tour,tour],meta:{partial:false,next_cursor:null}},decodeTour))
 assert.throws(()=>decodeTour({...tour,selection:'x'.repeat(32769)}))
 assert.throws(()=>decodeTour({...tour,url:'https://evil.example/tour'}))
})
test('backend transport encodes filters and retains cookie/cancellation boundary',async()=>{
 let request
 const client=createCatalogProvidersClient('https://spectacles.test/',async(url,options)=>{request={url,options};return new Response(JSON.stringify({data:[tour],meta:{next_cursor:'next',partial:true}}))})
 const page=await client.tours({artistMbid:performance.artist.ref.externalId},'Ñ & more')
 assert.equal(page.nextCursor,'next');assert.equal(page.partial,true)
 assert.equal(new URL(request.url).searchParams.get('q'),'Ñ & more')
 assert.equal(request.options.credentials,'include');assert.equal(request.options.redirect,'error')
})
test('source failures retain status and never retry mutations',async()=>{
 let calls=0
 const client=createCatalogProvidersClient('',async()=>{calls++;return new Response('{}',{status:429,headers:{'Retry-After':'7'}})})
 await assert.rejects(client.performances({period:'past',venueId:'source-id'}),e=>e instanceof CatalogProvidersError&&e.status===429&&e.retryAfter===7)
 assert.equal(calls,1)
})
test('unlinked saved artist receives the specific identity explanation',async()=>{
 const client=createCatalogProvidersClient('',async()=>new Response(JSON.stringify({errors:{artistUuid:['Select an Artist with a MusicBrainz identity or enter details manually.']}}),{status:422}))
 await assert.rejects(client.performances({period:'past',artistUuid:performance.artist.ref.externalId}),e=>e instanceof CatalogProvidersError&&e.status===422&&/linked to MusicBrainz/.test(e.message))
})
test('an empty setlist.fm 202 is explained as a source failure',async()=>{
 const client=createCatalogProvidersClient('',async()=>new Response(JSON.stringify({reason:'setlistfm_not_ready'}),{status:503}))
 await assert.rejects(client.performances({period:'past',artistMbid:performance.artist.ref.externalId}),e=>e instanceof CatalogProvidersError&&/setlist.fm did not provide a page/.test(e.message))
})
test('Venue source URLs survive normalization and are sent as encoded lookup/history parameters',async()=>{
 const source=venue.url, candidate=decodeVenue({...venue,venueUrl:source})
 assert.equal(candidate.venueUrl,source)
 assert.throws(()=>decodeVenue({...venue,venueUrl:'https://evil.example/venue/arena-73d6ca11.html'}))
 assert.throws(()=>decodeVenue({...venue,venueUrl:'https://www.setlist.fm/venue/arena-other.html'}))
 let request
 const client=createCatalogProvidersClient('',async(url)=>{request=new URL(url,'https://spectacles.test');return new Response(JSON.stringify({data:request.pathname.endsWith('/performances')?[performance]:[venue],meta:{next_cursor:null,partial:true}}))})
 await client.venue(venue.ref.externalId,source)
 assert.equal(request.searchParams.get('venueUrl'),source)
 await client.performances({period:'past',venueId:venue.ref.externalId,venueUrl:source})
 assert.equal(request.searchParams.get('venueUrl'),source)
})
test('each linked Venue has its own canonical URL and mismatched mappings reject',()=>{
 const old={provider:'setlist_fm',resourceType:'venue',externalId:'63d626d3'}
 const oldUrl='https://www.setlist.fm/venue/rosemont-horizon-rosemont-il-usa-63d626d3.html'
 const value=decodeVenue({...venue,linkedRefs:[old],venueSources:[{externalId:venue.ref.externalId,url:venue.url},{externalId:old.externalId,url:oldUrl}]})
 assert.equal(value.venueSources[1].url,oldUrl)
 assert.throws(()=>decodeVenue({...venue,linkedRefs:[old],venueSources:[{externalId:old.externalId,url:venue.url}]}))
})
