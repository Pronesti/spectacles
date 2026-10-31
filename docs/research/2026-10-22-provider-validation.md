# MusicBrainz and Geoapify: Argentina-first live validation

Validated on 22 October 2026. Research only; no application implementation, migrations, commits, or provider selection changes.

## Recommendation

**MusicBrainz is a strong candidate for artist identities and an optional composition identifier for songs. Geoapify is a viable candidate for interactive venue suggestions and prefilled geographic details. Neither should automatically determine identity from the first search result.**

MusicBrainz did not demonstrate enough event, tour, or venue coverage to be the only catalog behind attendance creation. Geoapify does not provide the concert date, artists, tour, or setlist. Together they can reduce typing, but they do not complete the event-import requirement. An event/setlist source still needs separate validation and acceptable retention/editing rights, as discussed in the [source research](2026-10-22-normalization-data-sources.md).

| Capability | Observed result | Proposed role |
|---|---|---|
| MusicBrainz artists | Intended artist first in all 9 selected searches across AR, UY, CL, MX, CO, BR | Preferred identity candidate, with selection confirmation |
| MusicBrainz compositions | Intended work first in 5 of 6 searches; wrong composition first for “Eres” | Optional work MBID; composer/recording context and manual fallback |
| MusicBrainz venues | Relevant candidates in 4 of 8 searches | Optional cross-reference, insufficient as the main venue search |
| MusicBrainz events | Intended event in 1 of 4 successful exact-date searches; 1 further query remained unavailable | Opportunistic enrichment |
| MusicBrainz tours | 1 of 4 named tour searches returned a Tour series | Optional identifier; keep tour entry optional/manual |
| Geoapify venue search | 8 clear venue matches first, 1 provisional complex/room match, 1 wrong first result with the venue at rank 4 | User-selected candidates, geographic prefill |
| Geoapify autocomplete | 4/4 intended venues first with near-venue bias; city-reference controls failed to put the venue first in both cities | Conditional assistance; do not assume city-level prefix search is reliable |

These are observations from a small, deliberately selected sample, not regional accuracy estimates or production service-level measurements.

## Method and evidence

The baseline manifests were fixed before their batches: [MusicBrainz, 32 cases](evidence/2026-10-22-provider-validation/musicbrainz-manifest.json) and [Geoapify, 10 venue searches plus 4 autocomplete cases](evidence/2026-10-22-provider-validation/geoapify-manifest.json). Argentina received most of the venue cases; the remaining examples cover Uruguay, Colombia, Mexico, and Brazil. Chile was included in artist validation, but its venue coverage was not tested.

All provider data came from documented HTTP APIs. No HTML scraping was necessary for these lookups. Public venue/operator pages supplied address and naming checks. Requests were made sequentially per provider, with an identifying User-Agent for MusicBrainz. MusicBrainz batches paused on consecutive failures and resumed more slowly. Same-query retries are retained alongside their failures. Changed-query experiments and candidate hydration are separate follow-ups and do not improve the original denominators.

Evidence contains request URLs without the Geoapify key, UTC timestamps, HTTP status, elapsed time, and raw responses. The key was read from the Git-ignored root `.env`; it is absent from the evidence. See the [evidence index](evidence/2026-10-22-provider-validation/index.json), [final MusicBrainz search summary](evidence/2026-10-22-provider-validation/musicbrainz-search-summary.json), and [baseline Geoapify summary](evidence/2026-10-22-provider-validation/geoapify-search-summary.json). Each case below maps to those files; individual response files retain provider fields omitted by the summaries.

Saved HTTP evidence includes 53 MusicBrainz responses: 42 HTTP 200 and 11 HTTP 503 busy responses. At baseline-case level, 31 of 32 eventually received HTTP 200 and one exact query remained blocked. HTTP 200 with zero results is distinguished from an unavailable search. All 23 saved Geoapify responses were HTTP 200, including responses with empty candidate lists. This measures access during this session only.

## MusicBrainz

### Artists

Queries used `artist:"name"`. An MBID, canonical name, type, geographic context, and sometimes disambiguation were available directly in search results.

| Case | Input / country | Intended result and relevant detail |
|---|---|---|
| A01 | Soda Stereo / AR | First; `3f8a5e5b-c24b-4068-9f1c-afad8829e06b` |
| A02 | El Mato a un Policia Motorizado / AR | First; canonical accents restored: El Mató a un Policía Motorizado |
| A03 | WOS / AR | First; Argentina/Person distinguishes other similarly named artists |
| A04 | Eruca Sativa / AR | First; Argentina/Group |
| A05 | No Te Va Gustar / UY | First; Uruguay/Group |
| A06 | Los Bunkers / CL | First; a separate Colombian salsa group also returned |
| A07 | Zoé / MX | First; Mexican rock band, among 556 total indexed matches |
| A08 | Aterciopelados / CO | First; Colombia/Group |
| A09 | Os Paralamas do Sucesso / BR | First; area Rio de Janeiro; `country` absent in this result |

An empty `country` is not evidence that an artist lacks a geographic identity. Likewise, a score of 100 is not a guarantee of uniqueness. Artist selection should display type, area/country, and disambiguation when present.

Candidate hydration was also successful. [Soda Stereo detail response](evidence/2026-10-22-provider-validation/mb-d01.json), requested with `inc=aliases+url-rels`, included a Spotify artist URL and links to other services. This proves useful cross-links exist for this artist; it does not establish complete Spotify coverage or validate the linked service itself.

### Songs: composition identity works, but links are incomplete

MusicBrainz distinguishes a work from its recordings and associates performers indirectly through recordings. That matches the accepted separation between song identity and an external recording link. [MusicBrainz Work documentation](https://musicbrainz.org/doc/Work).

| Case | Intended song | Search outcome |
|---|---|---|
| W01 | Siguiendo la luna | Correct first; Sergio Rotman composer/lyricist, 13 recording relationships |
| W02 | De música ligera | Correct first from an accentless query; Cerati/Bosio composition, 19 recording relationships; another English-version work also returned |
| W03 | El fantasma de Canterville | Correct first; Charly García composition, 16 recording relationships |
| W04 | Nada personal — Soda Stereo | Correct first; Gustavo Cerati writer; other compositions share the title |
| W05 | Eres — Café Tacvba | **Wrong intended identity at rank 1:** Ismael Serrano composition; intended work not identified in the returned title-search page |
| W06 | País tropical | Correct first from an accentless query; Jorge Ben Jor composer/lyricist |

The cross-recording check was concrete: the León Gieco recording `1bfd067b-4ea8-48a6-8a09-d8e59700f5d2` and the León Gieco & Alejandro Davio recording `5a122519-3611-4a56-96f6-a2050d0359ee` both reference work `8b21973e-836a-441b-9e4d-4e67e7114d75`. See [first recording](evidence/2026-10-22-provider-validation/mb-d07.json) and [second recording](evidence/2026-10-22-provider-validation/mb-d08.json). This demonstrates a common composition ID across distinct recording credits, including multiple credited artists.

For “Eres,” adding performer context to a **recording** search found Café Tacvba recordings: `recording:"Eres" AND artist:"Café Tacvba"`. However, the first three recording lookups all had empty relationship arrays, so this experiment did **not** resolve a composition MBID. One included ISRC `USMC10300331`; none of those three supplied a Spotify URL. Evidence: [performer search](evidence/2026-10-22-provider-validation/mb-f01-followup.json), [recording 1](evidence/2026-10-22-provider-validation/mb-d02.json), [recording 2](evidence/2026-10-22-provider-validation/mb-d05.json), [recording 3](evidence/2026-10-22-provider-validation/mb-d06.json).

Consequently, work MBIDs should be optional. Keep a local song ID even when the work cannot be found. Never assign the first title match automatically, use a recording MBID as if it were a work MBID, or assume a performer filter guarantees a composition link. External Spotify recording resolution remains an additional step; it was not validated through Spotify in this task.

### Venues

The baseline used venue names without city filters to expose ambiguity; this is not a test of optimized, geographically constrained queries.

| Case | Query | Outcome |
|---|---|---|
| P01 | Niceto Club | Successful retry; zero results |
| P02 | Teatro Colón | Bogotá first, Buenos Aires second; separate Buenos Aires rooms also returned |
| P03 | Luna Park | Buenos Aires third, behind Melbourne and Coney Island; Buenos Aires address Av. Madero 420 |
| P04 | Quality Espacio | Zero results |
| P05 | Teatro de Verano | Zero results |
| P06 | Foro Sol | Zero results for the old-name query; no alternate-name MB search performed |
| P07 | Movistar Arena | Buenos Aires and Bogotá returned as distinct places |
| P08 | Allianz Parque | São Paulo venue returned first, with address and coordinates |

Four of eight queries returned relevant venue candidates. Zero results mean these queries found no match, not that the place is absent under every alias. MusicBrainz is useful for additional venue identifiers when available, but these results do not support it as the sole venue-entry source.

### Events, tours, and setlists

Event queries used artist plus `begin:YYYY-MM-DD`.

| Case | Event | Outcome |
|---|---|---|
| E01 | Soda Stereo, Buenos Aires, 2007-12-21 | Exact query HTTP 503 on both attempts; **blocked**, not a catalog miss |
| E02 | Taylor Swift, Buenos Aires, 2023-11-09 | Correct event returned |
| E03 | El Mató, Niceto, 2013-03-01 | Successful retry; zero results |
| E04 | Soda Stereo, Bogotá, 2020-02-29 | Zero results |
| E05 | Ty Segall, Niceto, 2026-11-07 | Successful retry; zero results; the date appears in the [venue's future agenda](https://nicetoclub.com/agenda-niceto-club/) |

A separate broader Soda Stereo event search succeeded and returned three older events, none on 2007-12-21. That is supplemental evidence only; it does not change E01's blocked exact-query status. [Broader search response](evidence/2026-10-22-provider-validation/mb-f02.json).

The [Taylor Swift event lookup](evidence/2026-10-22-provider-validation/mb-d03.json) supplied the date, a time string, Taylor Swift plus support acts Sabrina Carpenter and LOUTA, River Plate's canonical place, the Eras Tour series, and a setlist.fm URL. Its own `setlist` was empty. The time has no explicit zone in the event response; do not silently treat it as a UTC timestamp or a verified actual performance time.

Tour searches use the `series` entity and must check its type. “The Eras Tour” returned a Tour series; “Me verás volver,” “Gracias totales,” and “World's Hottest Tour” returned zero matches. [Eras Tour hydration](evidence/2026-10-22-provider-validation/mb-d04.json) included Buenos Aires event relationships and marked the 10 November entry `cancelled: true`. The presence of an event in a tour must not imply it took place.

This demonstrates the desired event/artist/place/tour relationships when data exists. It does not demonstrate sufficient regional coverage or an editable setlist-import source. A link to setlist.fm does not transfer that provider's data rights.

## Geoapify

The baseline venue search used `/v1/geocode/search`, a venue plus city string, a country filter, JSON format, and five candidates. Autocomplete used `/v1/geocode/autocomplete`. Candidate hydration used `/v2/place-details?features=details`; spatial POI checks used `/v2/places`. These are exposed APIs, so scraping is unnecessary. [Place Details documentation](https://apidocs.geoapify.com/docs/place-details/), [Places documentation](https://apidocs.geoapify.com/docs/places/).

### Full venue searches

| Case | Venue / city | Observed result and limit |
|---|---|---|
| G01 | Niceto Club / Buenos Aires | Correct first; Niceto Vega 5510, coordinates, IANA time zone; matches the [venue](https://nicetoclub.com/nosotros/) |
| G02 | Teatro Colón / Buenos Aires | Correct first; Cerrito 628; matches the [theatre](https://teatrocolon.org.ar/en-vivo/) |
| G03 | Luna Park / Buenos Aires | Correct venue first, without house number; a second candidate supplies 420, matching the [current official site](https://www.lunapark.com.ar/) |
| G04 | Movistar Arena / Buenos Aires | Correct first; Humboldt 450; matches the [official site footer](https://login.movistararena.com.ar/Account/Registro/Documento) |
| G05 | Quality Espacio / Córdoba | “Espacio Quality” first, classified sports centre, on Eduardo Crespo without number; **provisional**, exact performance room not verified |
| G06 | Anfiteatro Humberto de Nito / Rosario | Named venue first with coordinates; Av. Manuel Belgrano differs from the [municipal listing](https://www.rosario.gob.ar/inicio/centros-culturales-y-teatros), Av. Diario La Capital 1750 |
| G07 | Teatro de Verano / Montevideo | Ramón Collazo venue first; a park-path address rather than the entrance description on the [official site](https://teatrodeverano.montevideo.gub.uy/) |
| G08 | Movistar Arena / Bogotá | Arena first; same-name TransMilenio station also returned. Carrera 27A 26-35 differs from [official Diagonal 61C 26-35](https://movistararena.co/contacto/) |
| G09 | Foro Sol / Mexico City | **Wrong first:** unrelated “Foro” on Calle Atletas. Renamed Estadio GNP Seguros at rank 4 |
| G10 | Allianz Parque / São Paulo | Same stadium first as Nubank Parque, with Rua Turiassu 1840; name reflects [current sponsor branding](https://nubank.com.br/nubank-parque), address differs from published event entrances |

The original manifest's Luna Park expectation included Madero 470/Bouchard 465. The live official website instead publishes **Madero 420**. This corrects the test expectation, not the provider data; the second candidate's 420 is not counted as an address error. The frozen manifest is retained for transparency.

Quality's [official page](https://qualitycenter.com/conocenos/) describes a complex with four rooms at Cruz Roja Argentina 200. A follow-up with that full address returned the address and coordinates, but no venue name. This helps geographic entry without proving which room hosted an event. Preserve manual room/venue selection.

For the Mexico naming test, the [operator's announcement](https://prensaocesa.prowly.com/328206-estadio-gnp-seguros) supports the Foro Sol / Estadio GNP Seguros relationship. Searching the new name returned the intended stadium first. That follow-up does not erase the old-name search failure. [Original response](evidence/2026-10-22-provider-validation/geo-g09.json), [new-name response](evidence/2026-10-22-provider-validation/geo-f01.json).

Venue identity, coordinates, and the appropriate visitor entrance are separate questions. Several candidates can be useful without supplying a complete or officially matching address. No precise-coordinate or entrance-routing accuracy benchmark was performed.

### Autocomplete and confidence

All four baseline prefixes found the intended venue first: “Niceto Cl,” “Teatro Colo,” and “Movistar Ar” in Buenos Aires, plus “Movistar Ar” in Bogotá. However, those requests were biased to coordinates near the known venues. They are favorable local-search cases, not evidence that knowing only the city is sufficient.

Two deliberately less favorable follow-ups used approximate central city reference points:

- Buenos Aires, `-58.3816,-34.6037`: “Movistar Ar” returned five phone-shop candidates; the arena was absent from the first five. [Response](evidence/2026-10-22-provider-validation/geo-f04.json).
- Bogotá, `-74.0721,4.7110`: the first “Movistar Arena” candidate was a transit station, with the arena second. A detail lookup confirmed `operator: TransMilenio`, `amenity: bus_station`, and the station's Wikidata ID. [Autocomplete](evidence/2026-10-22-provider-validation/geo-f05.json), [station details](evidence/2026-10-22-provider-validation/geo-d02.json).

Therefore the original 4/4 result must not be used as a general autocomplete success claim. Full venue name plus explicit city performed better in these controls. Candidate labels should include location and category; a detail lookup can disambiguate entries whose geocoding category is absent.

Provider confidence is not an identity probability. The unrelated “Foro” received confidence 1, while the intended GNP candidate had confidence 0. Correct prefix matches also sometimes had confidence 0. A single threshold would accept mistakes and reject useful candidates.

### Place details, categories, and identifiers

Hydrating the selected [Niceto autocomplete candidate](evidence/2026-10-22-provider-validation/geo-d01.json) succeeded and supplied its website, address, coordinates, time zone, and underlying OSM identity `way/432976394`. The venue is categorized as `adult.nightclub`; limiting concert venues to stadiums and theatres would exclude it.

Niceto's search, autocomplete, and details responses contained **different Geoapify `place_id` strings for the same venue**. A provider result ID is therefore a lookup handle, not sufficient proof that two local venue records represent different places. The underlying OSM type and numeric ID can supply an additional cross-reference when returned. OSM node, way, and relation IDs must retain their type. They still do not guarantee that every room or complex has a single permanent object.

Spatial Places API experiments around Bogotá also exposed a limitation: both `name=Movistar` and `name=Movistar Arena` with `categories=sport,entertainment` returned no candidates. A nearby `sport.stadium` request without `name` did return the arena and its OSM/Wikidata identifiers. The reason for the name-filter discrepancy was not established. These endpoints must not be treated as interchangeable searches with guaranteed equivalent recall. [Name-filter response](evidence/2026-10-22-provider-validation/geo-f06.json), [category control](evidence/2026-10-22-provider-validation/geo-f07.json).

## Implications for the proposed attendance flow

1. Artist search can suggest MusicBrainz matches with canonical accents and useful disambiguation. Persist the selected provider ID alongside the existing local identity.
2. Venue search can start with a full name and selected city/country, then show candidates. Hydrate the selected candidate and let the user correct incomplete addresses or room selection. Keep local IDs authoritative and external cross-references optional.
3. Event and tour fields remain editable/manual whenever no reliable candidate is available. MusicBrainz can enrich an identified event, but the tested pair cannot supply a complete regional event catalog.
4. Songs can reference a MusicBrainz work when confidently identified. Keep performer credits separate from composers and recording credits. Missing work links must not block manual setlist entry or local repeat-song tracking.
5. A provider outage or zero-result search must leave manual attendance creation available. This is particularly relevant given the observed MusicBrainz 503 responses.

These are design recommendations, not implemented behavior. Licensing, attribution, commercial hosted-service terms, and the retention issues identified in the prior research remain separate selection requirements. Live API success does not settle them.

## Scope and remaining uncertainty

No app code, schema, database data, or approved database spec was changed. The requested credential setup consists of root `.env.example`, a Git-ignored `.env`, and the root ignore rule. Evidence and this report remain local and uncommitted.

This exercise did not validate Spotify API access; comprehensive tour aliases; festival-day mapping; worldwide venue coverage; event time accuracy; commercial provider approval; historical venue-name persistence; sustained availability; or every Café Tacvba recording. It found enough evidence to recommend MusicBrainz as an identity candidate and Geoapify as conditional venue assistance, while leaving the final provider choice open.
