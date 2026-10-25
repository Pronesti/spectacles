# Event and setlist providers: regional coverage and editable imports

Research date: 22 October 2026. Argentina first, with examples from Uruguay, Chile, Mexico, Colombia, and Brazil. No implementation, database changes, commits, purchases, account registrations, or provider messages.

## Decision

**No reviewed provider has yet passed both requirements: useful regional event/setlist coverage and permission for permanent, user-editable collaborative imports.**

setlist.fm has the strongest observed regional setlist content in this comparison, but its standard terms conflict with the required imports. MusicBrainz explicitly permits reusing its core event/setlist data; coverage remains inadequate in the tested cases. JamBase deserves a production licensing discussion for events, but its displayed setlists are not an independent alternative.

Recommended next selection step: obtain explicit written permission from setlist.fm for the exact attendance workflow, and evaluate JamBase's production agreement separately for events. Neither is approved by this research. Without a suitable agreement, retain manual entry and use appropriately licensed open metadata where available.

## What was actually validated

- Read 11 selected setlist.fm performance pages representing 9 concert/date/location examples in 6 countries. Ten contained songs, including one explicitly incomplete list; one was empty. This is an exploratory presence check, not a random sample, coverage percentage, or verification against recordings.
- Compared upcoming Ty Segall listings across Songkick, Bandsintown, JamBase, the artist's label, and a venue. Regional event presence was demonstrated; a venue disagreement was also found.
- Reviewed current published API documentation, source dependencies, and storage/editing restrictions. Some research-browser results use cached page versions; this is not a freshness or availability benchmark.
- Made seven new MusicBrainz API requests for five known regional performances, including two bounded retries. Every query eventually returned HTTP 200 with zero matches.
- Attempted a Buenos Aires open-data CSV download. HTTP 200 contained a rejection page instead of CSV; payload validation is **blocked**, not an empty-dataset result.
- Checked configured variable names without displaying credentials. There are no project keys for setlist.fm, Songkick, Bandsintown, JamBase, or Ticketmaster. Their authenticated API results, quotas, and issued entitlements were **not tested**. No public demo or another application's credentials were used.

The [evidence directory](evidence/2026-10-22-event-setlist-validation/) contains [coverage observations](evidence/2026-10-22-event-setlist-validation/coverage-observations.json), a [rights review with clause locations](evidence/2026-10-22-event-setlist-validation/rights-review.json), and raw MusicBrainz responses. Restricted providers' complete setlists and page payloads were not copied into local evidence. Existing same-day MusicBrainz findings are reused explicitly below.

## Storage and editing assessment

“Permitted” below concerns the identified data license, not complete access to every provider field or an endorsement of the application. “Unresolved” means a production agreement or explicit permission is missing; technical API availability does not settle it.

| Source | Permanent editable import | Access / scraping | Practical result |
|---|---|---|---|
| setlist.fm | Conflicts with standard terms | Documented API; key required; scraping restricted | Seek a suitable agreement |
| MusicBrainz core Events, including setlist | Permitted under CC0 | Public API and dumps; no scraping needed | Eligible data, insufficient tested coverage |
| JamBase events | Own-database ingestion documented; permanence and user edits unresolved | Licensed API | Conditional event candidate |
| JamBase website setlists | No independent reuse grant established | Observed lists credit setlist.fm | Does not resolve the setlist licensing question |
| Songkick | Standard terms conflict with the workflow | Licensed API; new-key applications currently unavailable | Poor standard-contract fit |
| Bandsintown | Permanent editable use not established by session caching permission | API scoped to authorized artists/teams unless approved otherwise | Requires broader partnership permission |
| Ticketmaster Discovery | Indefinite diary retention/editing unresolved | API key; no scraping needed for authorized API use | Possible event supplement, not validated for Argentina |
| Concert Archives | Requires an appropriate export/license agreement | No public API; tailored exports considered | Possible historical supplement; confirm upstream rights |
| Buenos Aires cultural activities | Reuse/adaptation permitted with license conditions | Published downloads; tested CSV blocked | Limited historical supplement, no verified setlists |
| Direct venues, artists, promoters | Depends on their permission/feed agreement | Public pages exist; general third-party feeds not established here | Potentially useful regional partnerships |

### setlist.fm: the terms are the blocking issue

The [published terms](https://www.setlist.fm/help/terms), last updated 14 October 2019, limit retention to short caching, restrict modification and competing products, and require attributed presentation as supplied. Commercial use needs separate permission. The caching clause does **not** specify a numerical maximum; do not substitute Songkick's 24-hour limit.

Our intended permanent edited copy conflicts with those conditions. A manual click, private attendance, one-time import, or attribution alone does not establish an exception. Separating imported values from local edits also does not authorize retaining the imported copy. Editing a setlist on their website is different from editing an imported copy in Spectacles.

This is a product-fit reading of published conditions, not a statement that individual concert facts are universally protected or that a negotiated license is unavailable.

### Songkick and Bandsintown

[Songkick API terms](https://www.songkick.com/developer/api-terms-of-use), sections 4.3–4.6 and 4.10, restrict competing use and modification, cap cached copies at 24 hours, and prohibit combining its concert data with third-party, user-generated, or manually added content. Section 11 requires deleting copies on termination. This conflicts with the mixed-source editable diary. Its [key application page](https://www.songkick.com/api_key_requests/new) currently says new applications cannot be processed; business partnerships are a separate route.

[Bandsintown's data application terms](https://corp.bandsintown.com/data-applications-terms), section 3.d, describe session-based caching with notification and removal obligations. This does not establish permanent diary retention. Its [current access guidance](https://help.artists.bandsintown.com/en/articles/7053475-what-is-the-bandsintown-api) limits access to artists and authorized teams, normally one artist per key. General cross-artist attendance creation needs specific approval. Its documented event payload is useful for dates, venues, lineups, and links; no ordered song-list endpoint was established.

### JamBase: distinguish events, trial data, and syndicated setlists

The [bulk-ingestion guide](https://data.jambase.com/api/docs/bulk-ingest) expressly describes maintaining events in an application's own database, with identifiers, updates, cancellations, and removals. This is stronger technical support for local storage than a display-only API. It does not establish indefinite retention after subscription termination, user modification, collaborative sharing, or an exemption from any contractual update/removal requirements. The approved manual, one-time import requirement remains unchanged.

The provider's [published evaluation terms](https://www.jambase.com/wp-content/uploads/2023/12/jambase-trial-api-and-data-evaluation-tos-20231229.pdf), a document dated December 2023, limit use to evaluation, prohibit modifying Data, and require deletion on termination. Its applicability to a newly issued trial or current production plan must be confirmed. It is not evidence that a paid production agreement necessarily has identical restrictions—or that a free trial permits the product workflow. No trial was activated.

JamBase's [Café Tacvba concert in Mexico City, 5 December 2024](https://www.jambase.com/show/cafe-tacvba-foro-sol-20241205) displays songs with an explicit setlist.fm credit. The [Ty Segall artist page](https://www.jambase.com/band/ty-segall) does the same for its latest setlist. A separate website displaying these songs does not demonstrate authority to sublicense permanent edited copies. No documented JamBase API entitlement to such setlist reuse was verified.

### Ticketmaster and Concert Archives

[Ticketmaster's API terms](https://developer.ticketmaster.com/support/terms-of-use/) restrict stored event content to reasonable service periods and require removal within 24 hours of an owner's request. They do not supply a clear perpetual editable attendance license. [Discovery documentation](https://developer.ticketmaster.com/products-and-docs/apis/discovery-api/v2/) explicitly includes Mexico in event coverage, but country-code support must not be treated as proof of populated Argentine results. No authenticated regional search was possible. No ordered-setlist capability was established in the reviewed API documentation.

[Concert Archives](https://www.concertarchives.org/data-requests) states that it has no public API and considers tailored spreadsheet exports, with licensing and fees normally involved for larger exports. Its [terms](https://www.concertarchives.org/terms-of-use) do not establish unrestricted reuse; commercial/personal-gain copying and modification require advance written permission. The [Soda Stereo Buenos Aires record](https://www.concertarchives.org/concerts/me-veras-volver--6) exposes historical event metadata, but its setlist remained a loading placeholder in the research browser. [Release notes](https://www.concertarchives.org/release-notes) explicitly describe refreshing setlist.fm lists. Any export agreement must identify which setlist data it can license independently.

## setlist.fm regional examples

The following checks establish that relevant data is present. “Songs present” does not assert completeness or factual accuracy. Multiple artist records from one concert are deliberately counted separately.

| Case | Performance | Website observation |
|---|---|---|
| SFM-01 | [Soda Stereo, River Plate, Buenos Aires — 2007-12-21](https://www.setlist.fm/setlist/soda-stereo/2007/estadio-river-plate-buenos-aires-argentina-4bd68356.html) | Songs, Me verás volver tour, guests, tapes, encores |
| SFM-02 | [Taylor Swift, River Plate — 2023-11-09](https://www.setlist.fm/setlist/taylor-swift/2023/estadio-river-plate-buenos-aires-argentina-43a18b7b.html) | Songs, tour and named sections |
| SFM-03 | [Sabrina Carpenter, same concert](https://www.setlist.fm/setlist/sabrina-carpenter/2023/estadio-river-plate-buenos-aires-argentina-53a18b7d.html) | Songs, opening-act note, cover/tape distinctions |
| SFM-04 | [LOUTA, same concert](https://www.setlist.fm/setlist/louta/2023/estadio-river-plate-buenos-aires-argentina-4ba18b42.html) | Songs, opening-act note and guest |
| SFM-05 | [El Mató, Niceto, Buenos Aires — 2013-03-01](https://www.setlist.fm/setlist/el-mato-a-un-policia-motorizado/2013/niceto-club-buenos-aires-argentina-23db04a7.html) | Event exists; **no songs yet** |
| SFM-06 | [Eruca Sativa, Teatro Vorterix, Buenos Aires — 2024-06-01](https://www.setlist.fm/setlist/eruca-sativa/2024/teatro-vorterix-buenos-aires-argentina-3ba86c48.html) | Songs, guests/covers; website distinguishes doors, scheduled and actual start |
| SFM-07 | [No Te Va Gustar, Atlántida, Uruguay — 2024-02-03](https://www.setlist.fm/setlist/no-te-va-gustar/2024/rambla-atlantida-uruguay-2bac78fa.html) | Songs, festival and 30 años tour |
| SFM-08 | [Los Bunkers, Estadio Nacional, Santiago — 2024-04-27](https://www.setlist.fm/setlist/los-bunkers/2024/estadio-nacional-santiago-chile-63abfed7.html) | Songs, Ven aquí tour, stage headings and covers; initial 429 followed by successful read |
| SFM-09 | [Café Tacvba, Festival City, Querétaro — 2024-04-27](https://www.setlist.fm/setlist/cafe-tacvba/2024/lienzo-charro-hermanos-ramirez-santiago-de-queretaro-mexico-13a86515.html) | Songs, tour, medley/covers; **explicitly marked incomplete** |
| SFM-10 | [Os Paralamas, Coala Festival, São Paulo — 2024-09-07](https://www.setlist.fm/setlist/os-paralamas-do-sucesso/2024/memorial-da-america-latina-sao-paulo-brazil-33576079.html) | Songs, medleys and tape entry |
| SFM-11 | [Soda Stereo, El Campín, Bogotá — 2020-02-29](https://www.setlist.fm/setlist/soda-stereo/2020/estadio-el-campin-bogota-colombia-73999e95.html) | Songs, Gracias totales tour; notes distinguish live guests from prerecorded video performers |

### What the API could supply if licensed

The [search endpoint](https://api.setlist.fm/docs/1.0/resource__1.0_search_setlists.html) supports artist MBID/name, date, venue, city, country, and tour filters. No website scraping is needed for these documented queries once authorized access exists.

The [setlist schema](https://api.setlist.fm/docs/1.0/json_Setlist.html) has an artist, venue, tour name, ordered sets/songs, an attribution URL, setlist ID, version ID, event date, and update timestamp. The documented object has no performance-start field; website time displays must not be assumed available through this API. `lastUpdated` is the edit timestamp, not the show time. A tour name is not a normalized tour ID.

The [song schema](https://api.setlist.fm/docs/1.0/json_Song.html) provides a title, guest artist, original cover artist, notes, and tape flag, but no composition identifier. An original cover artist is not necessarily on stage. Mixed live/prerecorded performances and medley titles need deliberate interpretation; the import cannot infer every work or performer automatically.

The River Plate example would require combining three artist-specific records into the approved single Attendance setlist. Source records alone do not prove the order of all performances. These are mapping observations, not authorization to add fields or change the design now.

## Future events: actual regional observations

The artist's [label tour page](https://www.dragcity.com/tours) lists Ty Segall at Querétaro on 24 October, Niceto in Buenos Aires on 7 November, Blondie in Santiago on 10 November, and Cine Joia in São Paulo on 15 November 2026.

- [Songkick](https://www.songkick.com/concerts/43301711-ty-segall-at-niceto-club) exposes the Buenos Aires concert date, artist and venue.
- [Bandsintown](https://www.bandsintown.com/a/268215-ty-segall) lists all four regional stops, including Cine Joia for São Paulo.
- [JamBase](https://www.jambase.com/band/ty-segall) lists Buenos Aires, Santiago and São Paulo. Its [São Paulo event page](https://www.jambase.com/show/ty-segall-audio-club-20261115) names **Audio Club**, in conflict with the label and Bandsintown. This is a documented discrepancy, not an independently established correction or an API result.
- [Niceto's own agenda](https://nicetoclub.com/agenda-niceto-club/) lists the Buenos Aires show at 21:00. It also lists a different Club 69 show at 23:59 that day. **Venue plus date is insufficient as a universal event identity.**

These observations concern a single artist's selected tour stops, not a comprehensive event catalog comparison. Future songs should not be inferred from another show's list or an average setlist. A ticket/venue time may describe doors or scheduled start rather than actual performance time.

Primary regional ticket links exist, including [Venti](https://venti.live/evento/ty-segall-en-niceto-club-18-a-os) and [Passline](https://www.passline.com/eventos/ty-segall-en-blondie). Browser extraction was unavailable or incomplete; no supported general third-party discovery feed or reuse permission was established here. Public pages or embedded structured metadata would still require an appropriate extraction/reuse basis. No internal endpoints were reverse-engineered.

## Open data: compatible rights, limited usable coverage

### MusicBrainz

The official [database documentation source](https://raw.githubusercontent.com/metabrainz/musicbrainz-docs/main/musicbrainz_database/index.rst) explicitly classifies **Event time and setlist as core data**. The [license page](https://musicbrainz.org/doc/About/Data_License) assigns core data to CC0. Those fields can therefore support stored, normalized, edited copies without the restrictive cache model. Supplementary annotations, ratings, tags, and other separately licensed fields must be distinguished; hosted commercial API access has its own [service conditions](https://musicbrainz.org/doc/MusicBrainz_API).

MusicBrainz's [setlist syntax](https://musicbrainz.org/doc/Event/Setlist) can represent artist sections, songs/works and notes, with optional work/artist MBIDs. It can be transformed into the agreed relational entries; missing MBIDs still require matching or manual input.

However, the [five new known-performance queries](evidence/2026-10-22-event-setlist-validation/musicbrainz-summary.json)—Eruca Sativa in Buenos Aires, Los Bunkers in Santiago, No Te Va Gustar in Atlántida, Café Tacvba in Querétaro, and Os Paralamas in São Paulo—each returned zero event matches after successful access. This is not proof that no alias or alternative relationship can locate them.

The [earlier same-day Taylor Swift event lookup](evidence/2026-10-22-provider-validation/mb-d03.json) did find River Plate, performers, tour and date, but its setlist was empty. The [prior validation report](2026-10-22-provider-validation.md) already documents other regional gaps. Eligible licensing does not make this a complete event/setlist provider.

### Buenos Aires cultural activities

The [municipal dataset](https://data.buenosaires.gob.ar/dataset/actividades-culturales) identifies its license as CC-BY-2.5-AR. That [license](https://creativecommons.org/licenses/by/2.5/ar/deed.en) permits copying and adaptation, including commercial use, with attribution, a license link and notice of changes.

The page's latest displayed annual resources are for 2022, although its metadata update is dated June 2026. A metadata update must not be confused with current event coverage. It describes Ministry of Culture activities, not the entire commercial concert market. The [CSV attempt](evidence/2026-10-22-event-setlist-validation/buenos-aires-cultural-2022.json) was blocked by a rejection page; rows, artist completeness and song lists were not validated. This is a possible narrow historical supplement, not the primary attendance-creation feed.

## Requirements to put in a provider agreement

The following is a concrete inquiry outline, **not a message sent to anyone**:

> We are evaluating a personal live-event diary. A user manually triggers a one-time import of a selected concert and its setlists. We store normalized event, artist, venue, tour and song facts plus ordered performance entries in our database. Users can correct, reorder, add or remove entries and share a private Attendance with accepted companions, who can collaborate. Imports do not refresh automatically. Please confirm which data you can license for this use, and the following rights and obligations.

1. Indefinite retention of imports and edited derivatives, including historical attendance records after API access or a subscription ends.
2. Normalization, correction, song matching to MusicBrainz, combination with other permitted sources, and manual additions.
3. Private collaborative access, explicit sharing, and user export of their attendance history.
4. Attribution and source-link requirements, including how to label user-modified data.
5. Commercial eligibility, volume/cost limits, and any competition/exclusivity restrictions.
6. Handling provider deletions, factual corrections, cancellation notices, backups and contract termination; specifically whether user-entered additions can remain.
7. Whether the licensor controls upstream setlist rights and can grant these permissions, rather than merely offering a display integration.

Permission for user edits need not mean writing those edits back to the provider. That is a separate capability and is not required by the approved design.

## Outcome and remaining limits

The provider shortlist is narrower: negotiate setlist.fm for regional setlists; consider JamBase for events only under suitable production terms; use MusicBrainz core data opportunistically; keep independent manual entry available. Songkick and Bandsintown are not drop-in solutions to permanent editable imports under the reviewed standard conditions. Licensed regional publisher feeds remain a useful option to investigate directly.

Authenticated commercial-provider coverage and negotiated rights remain unverified. These need provider credentials/agreements, not additional website extraction. No emails were sent and no paid or trial entitlements were activated. The approved database design, manual import policy, private sharing model, and application code remain unchanged.
