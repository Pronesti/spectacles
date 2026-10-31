import { catalogUuid } from './catalog.ts'
import { decodeProviderRef, decodeVenueImage, decodeVenueValues, decodePerformanceSummary } from './catalogProviders.ts'
import type { RecoverySnapshot } from './concertRecovery.ts'
// Schemas validate storage structure, not completion: blank and partial form values are recoverable.
type Check = (value: unknown) => boolean
const text = (max: number): Check => v => typeof v === 'string' && v.length <= max
const nullable = (check: Check): Check => v => v === null || check(v)
const optional = (check: Check): Check => v => v === undefined || check(v)
const array = (check: Check, max: number): Check => v => Array.isArray(v) && v.length <= max && v.every(check)
const number: Check = v => typeof v === 'number' && Number.isFinite(v)
const bool: Check = v => typeof v === 'boolean'
const enumeration = (...values: unknown[]): Check => v => values.includes(v)
const object = (shape: Record<string, Check>): Check => v => typeof v === 'object' && v !== null && !Array.isArray(v) && Object.keys(v).every(k => Object.hasOwn(shape, k)) && Object.entries(shape).every(([k, check]) => check((v as Record<string, unknown>)[k]))
const uuid: Check = catalogUuid

const ref: Check = v => { try { if (!object({ provider: text(32), resourceType: text(32), externalId: text(128) })(v)) return false; decodeProviderRef(v); return true } catch { return false } }
const image: Check = v => { try { if (!object({url:text(2048),thumbnailUrl:text(2048),sourceUrl:text(2048),credit:text(2000),license:text(255),licenseUrl:text(2048)})(v)) return false; decodeVenueImage(v); return true } catch { return false } }
const metadata: Check = v => { try { if (!object({name:text(255),city:text(255),region:nullable(text(255)),countryCode:nullable(text(2)),address:nullable(text(2000)),aliases:array(text(255),100),lat:nullable(number),lng:nullable(number),timezone:nullable(text(255)),image:nullable(image)})(v)) return false; decodeVenueValues(v); return true } catch { return false } }
const exactDate = object({year:number,month:number,day:number})
const summaryShape = object({ref,artistName:text(255),venueName:text(255),city:text(255),date:exactDate,url:text(2048),locator:text(32768),localEventId:nullable(catalogUuid)})
const summary: Check = v => { try { if (!summaryShape(v)) return false; decodePerformanceSummary(v); return true } catch { return false } }
const refKind = (...kinds:string[]): Check => v => ref(v) && kinds.includes((v as {resourceType:string}).resourceType)
const scope = object({artistUuid:optional(catalogUuid),artistMbid:optional(catalogUuid)})
const newFields = { placeQuery:optional(text(200)),tourProviderQuery:optional(text(200)),
 performanceFilters:optional(object({period:enumeration('past','upcoming'),artistUuid:optional(catalogUuid),artistMbid:optional(catalogUuid),venueId:optional(text(128)),venueUrl:optional(text(2048)),tourId:optional(text(128)),tourName:optional(text(255)),date:optional(text(32)),year:optional(number),countryCode:optional(text(2)),q:optional(text(200))})),
 performancePreview:optional(nullable(summary)),performanceDecision:optional(nullable(enumeration('apply','detach'))),
 venuePreviewRef:optional(nullable(ref)),venueHistoryTab:optional(enumeration('past','upcoming')),venueHistorySource:optional(nullable(refKind('venue'))),activeProviderArtist:optional(nullable(scope)),
 venueEditor:optional(nullable(object({name:text(255),city:text(255),address:text(2000),aliasesText:text(2000),lat:text(100),lng:text(100),selection:nullable(text(32768)),ref:optional(ref),metadata:optional(metadata),linkedRefs:optional(array(refKind('place','venue'),5)),url:optional(text(2048)),venueUrl:optional(text(2048)),venueSources:optional(array(object({externalId:text(128),url:text(2048)}),6))}))),
}

function schema(version: 1 | 2): Check {
const artistInput = object({ name: text(255), type: nullable(text(255)), disambiguation: nullable(text(255)), selection: nullable(text(4096)) })
const songInput = object({ title: text(255), disambiguation: nullable(text(255)), selection: nullable(text(4096)) })
const tourInput = object({ name: text(255), selection: nullable(text(version === 1 ? 4096 : 32768)) })
const pending = (input: Check, tour = false) => object({ key: uuid, input, mbid: optional(uuid), ...(version === 2 && tour ? { ref: optional(refKind('tour','series')) } : {}) })
const artistChoice = object({ name: text(255), catalogId: nullable(uuid), draft: optional(pending(artistInput)) })
const tourChoice = object({ name: text(255), catalogId: nullable(uuid), draft: optional(pending(tourInput,true)) })
const entry = object({ id: uuid, type: enumeration('song', 'note'), title: text(2000), artist: text(2000), tape: bool, songCatalogId: optional(uuid), artistCatalogId: optional(uuid), songDraft: optional(pending(songInput)), performerDraft: optional(pending(artistInput)) })
const venue = object({ id: uuid, name: text(255), city: text(255), neighborhood: text(255), lat: nullable(number), lng: nullable(number), catalogId: optional(uuid), timezone: optional(nullable(text(255))), ...(version === 2 ? { region: optional(nullable(text(255))), countryCode: optional(nullable(text(2))), address: optional(nullable(text(2000))), aliases: optional(array(text(255), 100)), image: optional(nullable(image)) } : {}) })
const datePart = nullable(v => number(v) && Number.isInteger(v) && Math.abs(v as number) <= 9999)
const choice = object({ name: text(255), catalogId: nullable(uuid) })
const concert = object({ id: uuid, title: text(255), artists: array(text(255), 100), venueId: nullable(v => v === 'new' || uuid(v)), date: object({ year: datePart, month: datePart, day: datePart }), eventType: optional(text(255)), catalog: optional(object({ eventId: optional(uuid), artistIds: array(nullable(uuid), 100), tours: array(choice, 100) })), time: text(32), timezone: nullable(text(255)), interested: bool, ticketsBought: bool, notes: text(2000), setlist: array(entry, 1000) })
const binding = (...kinds:string[]) => object({ key: uuid, ref:refKind(...kinds), selection: text(32768), linkedRefs:optional(array(refKind('place','venue'),5)),url:optional(text(2048)),venueUrl:optional(text(2048)),venueSources:optional(array(object({externalId:text(128),url:text(2048)}),6)) })
const state = object({ step: enumeration('artist', 'tour', 'event', 'setlist'), draft: object({ concert, artists: array(artistChoice, 100), tours: array(tourChoice, 100), eventId: optional(uuid), precision: enumeration('exact', 'month', 'year', 'unknown'), dateText: text(32), timezoneText: text(255), pendingVenue: optional(venue), venueName: text(150), city: text(100), lat: text(100), lng: text(100), ...(version === 2 ? { providerEvent: optional(binding('setlist')), providerVenue: optional(binding('place','venue')) } : {}) }) })
const index: Check = v => number(v) && Number.isInteger(v) && (v as number) >= 0 && (v as number) < 100
const action = (check: Check): Check => v => object({ add: check })(v) || object({ remove: index })(v)
const fields = object({ artistEditor: optional(nullable(object({ input: artistInput, mbid: optional(uuid) }))), artistDecision: optional(nullable(action(artistChoice))), tourEditor: optional(nullable(object({ input: tourInput, mbid: optional(uuid), context: optional(nullable(text(255))), ...(version === 2 ? { ref: optional(refKind('tour','series')) } : {}) }))), tourDecision: optional(nullable(action(tourChoice))), row: optional(nullable(entry)), songEditor: optional(nullable(songInput)), performerEditor: optional(nullable(artistInput)), artistQuery: optional(text(200)), tourQuery: optional(text(200)), eventQuery: optional(text(200)), venueQuery: optional(text(200)), songQuery: optional(text(200)), performerQuery: optional(text(200)), eventPreviewId: optional(nullable(uuid)), ...(version === 2 ? { ...newFields, historyPerformanceFilters:newFields.performanceFilters,historyPerformancePreview:newFields.performancePreview,historyPerformanceDecision:newFields.performanceDecision } : {}) })
return object({ version: enumeration(version), owner: uuid, state, fields })

}

const legacy = schema(1), current = schema(2)
export function decodeStoredSnapshot(raw: string, owner: string): RecoverySnapshot | null {
 if (!catalogUuid(owner) || raw.length > 2_000_000) return null
 try {
  const value: unknown = JSON.parse(raw)
  if (legacy(value)) return (value as {owner:string}).owner === owner ? {...value as Omit<RecoverySnapshot,'version'>,version:2} : null
  if (current(value) && (value as RecoverySnapshot).owner === owner) return value as RecoverySnapshot
  return null
 } catch { return null }
}
