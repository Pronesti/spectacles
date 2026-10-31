import {performanceSummary} from '../src/providerDraft.ts'
import assert from 'node:assert/strict'
import {after,before,test} from 'node:test'
import {createElement} from 'react'
import {renderToStaticMarkup} from 'react-dom/server'
import {createServer} from 'vite'
import react from '@vitejs/plugin-react'
import {fileURLToPath} from 'node:url'
import {performance,venue,owner} from './provider-fixtures.mjs'
let server,Picker,PerformanceFeedback,VenuePreview,Fields,Context,openRecovery
before(async()=>{
 server=await createServer({root:fileURLToPath(new URL('..',import.meta.url)),configFile:false,cacheDir:fileURLToPath(new URL('../node_modules/.cache/provider-ui-tests',import.meta.url)),plugins:[react()],server:{middlewareMode:true,hmr:false,ws:false},appType:'custom'})
 ;({PerformancePicker:Picker,PerformanceFeedback}=await server.ssrLoadModule('/src/components/PerformancePicker.tsx'))
 ;({VenuePreview}=await server.ssrLoadModule('/src/components/VenuePreview.tsx'))
 ;({VenueDraftFields:Fields}=await server.ssrLoadModule('/src/components/VenueDraftFields.tsx'))
 ;({ConcertRecoveryContext:Context}=await server.ssrLoadModule('/src/components/ConcertRecovery.tsx'))
 ;({openRecovery}=await server.ssrLoadModule('/src/concertRecovery.ts'))
})
after(async()=>{await server?.close()})
const providers={enabled:true,sessionKey:owner,onUnauthorized(){},client:new Proxy({}, {get(){return()=>{throw Error('SSR/restoration must not request provider data')}}})}
function render(Component,props,fields={}){
 const map=new Map(),storage={getItem:k=>map.get(k)??null,setItem:(k,v)=>map.set(k,v),removeItem:k=>map.delete(k)}
 const first=openRecovery(owner,undefined,storage)
 for(const [key,value]of Object.entries(fields))first.changeField(key,value)
 const restored=openRecovery(owner,undefined,storage)
 return renderToStaticMarkup(createElement(Context.Provider,{value:{controller:restored,changed(){}}},createElement(Component,props)))
}
test('provider preview restores as an explicit Resume/Dismiss choice without fetching or applying',()=>{
 const summary=performanceSummary(performance)
 const html=render(Picker,{providers,filters:{period:'past',artistMbid:performance.artist.ref.externalId},onApply(){throw Error('must not apply')}},{performancePreview:summary})
 assert.match(html,/Resume review/);assert.match(html,/Dismiss preview/);assert.match(html,/Search setlist.fm/)
 assert.doesNotMatch(html,/<form|Apply performance details/)
})
test('Venue preview renders attribution and separate period controls without guessing a city pin',()=>{
 const enriched={...venue,values:{...venue.values,address:'Humboldt 450',aliases:['Arena Ñ'],image:{url:'https://upload.wikimedia.org/test.jpg',thumbnailUrl:'https://thumb.wikimedia.org/test.jpg',sourceUrl:'https://commons.wikimedia.org/wiki/File:Test.jpg',credit:'BugWarp',license:'CC BY-SA 4.0',licenseUrl:'https://creativecommons.org/licenses/by-sa/4.0/'}}}
 const html=render(VenuePreview,{venue:enriched,providers,onApplyPerformance(){}})
 for(const label of ['Humboldt 450','Arena Ñ','BugWarp','CC BY-SA 4.0','Image source','Past performances','Upcoming performances'])assert.ok(html.includes(label),label)
 assert.doesNotMatch(html,/Venue location:/)
})
test('unfinished Venue metadata editor restores signs, whitespace and aliases before confirmation',()=>{
 const editor={name:'Arena Ñ ',city:'',address:'Humboldt ',aliasesText:'One\nTwo ',lat:'-',lng:'.',selection:'public-proof',ref:venue.ref,metadata:venue.values,url:venue.url}
 const html=render(Fields,{onConfirm(){throw Error('must not confirm')},onCancel(){}},{venueEditor:editor})
 for(const text of ['Arena Ñ ','Humboldt ','One\nTwo ','value="-"','value="."','Use Venue details'])assert.ok(html.includes(text),text)
})

test('failed performance review presents the error and available manual recovery',()=>{
 const manual={artist:performance.artist,venue:performance.venue,date:performance.date,tourName:'Vivir sin aire',time:null,timezone:null}
 const html=renderToStaticMarkup(createElement(PerformanceFeedback,{loading:false,error:'Provider details are unavailable.',manual,onUseManual(){}}))
 assert.match(html,/role="alert"/);assert.match(html,/Provider details are unavailable/);assert.match(html,/Use available details manually/)
 const noManual=renderToStaticMarkup(createElement(PerformanceFeedback,{loading:false,error:'Unavailable',manual:null}))
 assert.doesNotMatch(noManual,/Use available details manually/)
})
