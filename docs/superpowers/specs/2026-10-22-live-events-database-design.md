# Spectacles database design

Date: 2026-10-22

Status: Written specification for review, based on the approved conversational design and selected autocomplete providers. Implementation and commits are not authorized.

## Purpose and scope

Spectacles is a personal, non-commercial app that helps users track past and future live events. Personal tracking is the primary goal; normalizing events, artists, venues, tours, and songs is the secondary goal. Music, theatre, comedy, dance, and other live event types are supported. Music-specific information is optional for other event types.

The interface supports multiple languages. Application code, database identifiers, and system-defined stored values use English. Catalog names and user content retain their original language and spelling; interface localization does not translate them.

The model separates shared Event facts from an Attendance containing personal or collaboratively maintained details. Accepted collaborators share one Attendance and one editable setlist. Each member has independent interest, ticket-purchase, and seat information.

This specification defines the logical database structure, relationships, access boundaries, and integrity rules. It does not select a production database engine, design authentication, prescribe migrations, or authorize product implementation or an implementation plan. The existing Laravel backend has the default User model and installer migrations; the event domain is new.

## Agreed boundaries

- An Event can have multiple artists. Each festival day is a separate Event.
- Each Attendance belongs to one Event and has one owner and one or more accepted members.
- A user can belong to only one Attendance for a given Event.
- Attendance is private by default. Acceptance grants access to the shared Attendance and exposes the companion relationship to its members. There is no public attendance-sharing feature in this design.
- All accepted members can edit the shared setlist, notes, and complementary links. The owner manages invitations, membership removal, ownership transfer, and Attendance deletion.
- Interest and tickets bought are independent per-member booleans. There are no planned, attended, missed, or other Attendance lifecycle states. Dates do not automatically change either flag.
- Tours are optional. An empty tour relationship covers both unknown tour information and no tour; no separate tour-knowledge state is stored.
- Venue names come from the shared Venue record. There are no historical names or per-Event venue overrides.
- Manual catalog records are allowed without provider identifiers. Every record still receives its own Spectacles UUID. Duplicate-merging workflows are outside scope.
- setlist.fm supplies Event and setlist autocomplete; MusicBrainz supplies artist and song autocomplete; Geoapify supplies Venue autocomplete. Suggestions are selected and confirmed by the user.
- Every field and setlist entry remains manually enterable. Provider results, identifiers, or successful network requests are not required to save an otherwise valid Attendance.
- Setlist prefill is a manually triggered, one-time operation per Attendance. It can combine several selected artist performances before confirmation. Accepted members share this operation; it is not separate per member or shared across all Attendances for an Event. Individual song and artist autocomplete remains available during manual editing. There is no provider synchronization, update comparison, or automatic replacement of manual edits.

## Record identifiers

Every application record described in this specification has both identifiers below. This includes Users, catalog and Attendance records, setlist entries, all join-table rows, provider-reference rows, and Song links.

| Field | Rule |
| --- | --- |
| `id` | Internal surrogate primary key. Relationships and database foreign keys reference this value. |
| `external_id` | Spectacles-owned UUID, non-null, immutable, and unique within its table. Generated when the record is created, independently of any provider. Used to identify the record in application-facing URLs and API references. |

Changing names, interface language, or provider associations does not change `external_id`. It is present on manual records with no provider references. A provider identifier is never reused as the record's own UUID, even when that provider also uses UUIDs. UUID version and physical storage are implementation decisions; no additional identifier registry is required.

Join tables also have their own `id` and UUID `external_id`, while retaining the unique foreign-key pairs defined below. Adding a row identity does not permit duplicate relationships. Public UUIDs resolve to internal records; existing access and collaboration rules still apply.

## Language and localization

English is the technical language of the application. Table and column names, code identifiers, comments, translation keys, API field names, and system-defined type/status/provider/resource codes use English. Values such as `other`, `pending`, `song`, and `note` remain stable regardless of the interface language. A translated display label is never persisted in place of its code.

`artists.type`, `events.type`, and `attendance_links.type` are string columns. The application restricts them to supported English codes. They are not unrestricted user text. Define the supported code lists with the application; no database enum or separate type table is required. Artist type remains nullable, Event type remains required, and link type defaults to `other`.

User-facing interface text, including labels, buttons, validation messages, and descriptions of system-defined values, comes from translation resources with English keys. Spanish and other translated interface strings belong in those resources, not in application logic, database seed values, or database translation tables. API error codes and field identifiers remain stable English identifiers; displayed messages can be localized.

The prohibition on Spanish in technical identifiers and controlled values does not prohibit original-language content. Artist, Song, Venue, Tour, and Event names/titles, addresses, disambiguation text, setlist display text/notes, seat descriptions, and user-entered notes or link labels are content, not interface translations. Preserve their original language, Unicode characters, accents, punctuation, and spelling. Do not generate English replacements, transliterations, or per-language copies of these records. Changing interface language must not change stored content, external identifiers, or entity identity.

### User preference and formatting

| Existing table | Additional field | Rule |
| --- | --- | --- |
| `users` | `external_id`, nullable `locale` | `external_id` follows the shared UUID rule above. `locale` stores an optional preferred interface language tag, for example `en` or `es-AR`. It is language metadata, not a translated label. |

Use a supported saved preference when available; otherwise use a supported browser language, then the configured application default. Supported languages and the default interface language remain configuration choices. English technical identifiers do not require the interface to default to English.

Dates, times, numbers, and country display labels are formatted for the chosen interface language. Their stored date components, local times, coordinates, country codes, and IANA time zones retain the existing language-independent representation. Choosing a language does not convert an Event to another time zone or invent missing date precision.

Catalog and Attendance records are shared across interface languages. Collaborators can use different languages while editing the same Attendance and setlist. No per-locale name/title columns, catalog translation tables, translated JSON fields, or per-language copies of Attendances are required. Provider suggestions can use available language support for descriptive presentation, but changing request language must not automatically rename saved entities or translate original names and content.

## Shared catalog

The common `id` / UUID `external_id` rules apply to every table below. Foreign-key types must match their referenced internal primary keys. Domain records have creation and update timestamps. Join tables enforce uniqueness of their referenced pair.

Names are nonempty display values, not unique keys. Different artists, venues, tours, or songs may share a name. A provider reference can establish a match; a name alone does not establish identity.

| Table | Fields | Relationships and rules |
| --- | --- | --- |
| `artists` | `id`, `external_id`, `name`, nullable `type`, nullable `disambiguation` | A type can distinguish a person from a group. Disambiguation distinguishes artists with the same name. Group membership and artist biographies are outside this design. |
| `venues` | `id`, `external_id`, `name`, nullable `street`, `house_number`, `postal_code`, `city`, `region`, `country_code`, `lat`, `lng`, and `timezone` | Address components are ordinary nullable columns. `lat` is latitude; `lng` is longitude. Venue seating plans and geographical catalogs are not required. |
| `tours` | `id`, `external_id`, `name` | A tour can involve multiple artists and Events. |
| `songs` | `id`, `external_id`, `title`, nullable `disambiguation` | Represents an underlying song/composition, independent of a particular performance or recording. Disambiguation distinguishes compositions with the same title. |
| `events` | `id`, `external_id`, `title`, `type`, nullable `venue_id`, nullable `year`, `month`, `day`, `local_start_time`, and `timezone` | References one Venue when known. Supports incomplete dates as described below. |
| `event_artists` | `id`, `external_id`, `event_id`, `artist_id`, nullable `position` | Unique `(event_id, artist_id)`. Optional position controls lineup display order. |
| `tour_artists` | `id`, `external_id`, `tour_id`, `artist_id` | Unique `(tour_id, artist_id)`. |
| `event_tours` | `id`, `external_id`, `event_id`, `tour_id` | Unique `(event_id, tour_id)`. Zero rows is valid. |

`artists.disambiguation` and `songs.disambiguation` are optional short descriptive text, such as "Chilean alternative rock band" or "Gustavo Cerati composition". They can be populated from a confirmed suggestion or entered manually. They are display and selection aids, not unique keys, provider identifiers, or a replacement for confirming identity. A manual Artist or Song does not require disambiguation.

Venue address fields are independently nullable; missing components do not prevent manual creation. `street`, `house_number`, `postal_code`, `city`, and `region` are text. House numbers and postal codes retain letters, leading zeros, and punctuation, for example `26-35` or `C1414BBC`. `country_code`, when supplied, is an ISO 3166-1 alpha-2 code, stored in uppercase. `timezone`, when supplied, is an IANA time zone.

`venues.lat` and `venues.lng` are numeric and must either both be null or both be supplied. Latitude is between -90 and 90 inclusive; longitude is between -180 and 180 inclusive. Zero is a valid coordinate and is not a substitute for unknown location. Database-specific coordinate types and precision remain implementation decisions.

Event artist relationships describe the Event lineup. Tour artist relationships describe a tour's participating artists. Neither relationship automatically supplies the other: an Event can contain supporting or guest artists beyond a linked tour's roster.

Venue and date alone do not uniquely identify an Event: a venue can host separate concerts on the same day. Artist-specific provider records may also belong to one concert. Neither case establishes an automatic grouping rule.

Users may create missing catalog records. Administrators correct existing shared catalog and Event facts. Administrator authorization must be represented when authentication and authorization are designed; this specification does not select a role package or an authentication mechanism.

### Dates and times

`events.year`, `month`, and `day` preserve the precision actually known:

| Known information | Stored values |
| --- | --- |
| No date yet | All three null |
| Year only | Year populated; month and day null |
| Year and month | Year and month populated; day null |
| Exact date | All three populated and forming a valid calendar date |

A month requires a year. A day requires both a month and a year. Month/day ranges and complete calendar dates must be validated. No substitute January 1 date is generated for a year-only entry.

`local_start_time` is a nullable local wall-clock time. Null means unknown; midnight is a real time. `timezone` stores an IANA time zone when known. A Venue's time zone can supply the Event value on creation; the Event retains its own value. A precise instant can be derived only when a complete date, time, and time zone are available. No guessed UTC timestamp is needed for incomplete records.

Venue name changes affect every Event referencing that Venue. Event-specific date/time values remain on the Event.

## Attendance and collaboration

| Table | Fields | Relationships and rules |
| --- | --- | --- |
| `attendances` | `id`, `external_id`, `event_id`, `owner_user_id`, nullable `notes` | One Event, one owning User, one shared ordered setlist, and accepted members. Unique `(id, event_id)` supports the membership foreign key below. |
| `attendance_members` | `id`, `external_id`, `attendance_id`, `event_id`, `user_id`, `interested`, `tickets_bought` | Both booleans are non-null and default false. Unique `(user_id, event_id)`. |
| `attendance_member_seats` | `id`, `external_id`, `attendance_member_id`, nullable `section_label`, `row_label`, `seat_label`, and descriptive `label` | Zero or more seat/location rows per member. At least one descriptive field must be populated. |
| `attendance_invitations` | `id`, `external_id`, `attendance_id`, `inviter_user_id`, `recipient_user_id`, `status` | Status is `pending`, `accepted`, `declined`, or `discarded`. At most one pending invitation for a given Attendance and recipient. |
| `attendance_links` | `id`, `external_id`, `attendance_id`, `url`, nullable `label`, `type` | Shared complementary links, including photo albums, social posts, and sources. `type` is non-null and defaults to `other`. It is a descriptive classification rather than a separate normalized catalog. |

`attendance_members.event_id` is deliberately repeated to enforce one Attendance per User per Event in the database. A composite foreign key from `(attendance_id, event_id)` to `attendances(id, event_id)` prevents a member from claiming a different Event. The Event of an existing Attendance is treated as fixed; changing it is not an ordinary collaboration edit.

These constraints also prevent duplicate `(attendance_id, user_id)` memberships. A second unique constraint for that pair is unnecessary. Add an index for Attendance member lookup if the other indexes do not support it.

Member flags and seat rows belong to that user's membership. Each member manages their own values. Accepted members can see the shared Attendance, its members, and their member information; per-member storage is not a second private sharing layer. No shared information or companion relationship becomes public by default.

Creating an Attendance also creates its owner's membership in one transaction. The owner must always be a member. An owner can leave a retained Attendance only after transferring ownership to another accepted member. Owner membership, membership removal, and ownership transfer are transaction-level integrity requirements; the physical enforcement mechanism must fit the database engine selected later.

### Invitation acceptance

1. Only the Attendance owner can invite another existing User. Issuing an invitation does not create membership or expose a companion relationship.
2. If the recipient already has any membership for that Event, discard the invitation automatically. Do not ask them to select, replace, or combine Attendances.
3. A recipient without an existing membership may accept or decline. A pending invitation grants only the information needed to identify the invitation; it does not grant access to private Attendance details.
4. Acceptance rechecks eligibility and creates membership atomically. If another operation has already created membership for the same User and Event, discard this invitation and preserve the existing Attendance.
5. The unique `(user_id, event_id)` constraint is the final protection against concurrent creation or acceptance. Accepted membership grants collaboration access and exposes the companion relationship within the Attendance.

An invitation skipped before creation need not leave a database row. A pending invitation that becomes ineligible can be marked `discarded`. An owner can revoke a pending invitation by removing it. Invitation statuses do not introduce Attendance lifecycle states.

## Relational setlists

Every Attendance has one ordered list of entries. A separate `setlists` table is unnecessary because there are no alternate lists or list versions in scope. Setlists are not stored in JSON.

| Table | Fields | Relationships and rules |
| --- | --- | --- |
| `setlist_entries` | `id`, `external_id`, `attendance_id`, nullable `song_id`, `position`, `type`, `is_tape`, nullable `display_text`, nullable `notes` | Type is `song` or `note`. `is_tape` is non-null and defaults to false. Unique `(attendance_id, position)`. Positions are positive integers; gaps are permitted. |
| `setlist_entry_artists` | `id`, `external_id`, `setlist_entry_id`, `artist_id` | Unique `(setlist_entry_id, artist_id)`. Records the artists performing that song at this Event. |

Entry rules:

- A known song entry references `songs.id`. Optional display text can retain a particular title spelling or performance label.
- An unidentified song entry has a null `song_id` and nonempty `display_text`. It can be linked to a Song later.
- A note entry has a null `song_id`, nonempty `display_text`, and `is_tape = false`, for example an intermission. Performing-artist relationships apply to song entries.
- On a song entry, `is_tape = true` means prerecorded playback rather than a live rendition. It can reference a known Song or remain unidentified with display text. The flag belongs to this entry, not to the Song: the same Song can appear live and from tape at different points in the same list. Recorded-artist credits do not establish live performing-artist relationships.
- An incomplete song entry may temporarily lack identified performers. Multiple performers are supported, including guests who are absent from the Event lineup.
- A Song may appear repeatedly in one Attendance, including with different performers. There is no unique `(attendance_id, song_id)` constraint.
- Covers and arrangements can reference the same underlying Song. The entry's artists identify who performed it on that occasion.

Manual entry, user-confirmed setlist autocomplete, and subsequent collaborative edits all populate these same relational rows. No provider payload or response is the authoritative editable setlist.

For overlapping edits to the same shared value, the last successful save wins. An older form can overwrite a newer saved value; this behavior is accepted. No edit-version field, stale-save rejection, or change history is required. A setlist reorder must save atomically and retain unique positions. Saving shared details does not grant permission to change another member's flags or seats.

Song-occurrence queries follow `songs -> setlist_entries -> attendances -> events`. Personal results additionally join `attendance_members` for the selected User. Live-rendition counts include only song entries with an identified `song_id` and `is_tape = false`. A view of all setlist appearances can explicitly include tape entries, with their playback status visible. Count entries for occurrences and distinct Events for Event counts; a repeated song must not accidentally count as multiple Events. Unidentified songs enter normalized statistics after they are linked.

These records do not prove physical attendance: the model intentionally stores interest and ticket purchase rather than a verified-attended flag. Statistics describe the setlists attached to the user's Events.

## Autocomplete and manual entry

Autocomplete reduces typing in the personal attendance form. The user chooses suggestions, reviews the resulting values, and saves local records. Suggestions do not independently create catalog records, an Event, an Attendance, or memberships. Abandoning the form does not persist its unconfirmed suggestions.

| Provider | Selected responsibility | Local representation |
| --- | --- | --- |
| setlist.fm | Find concerts by artist, date, venue, or location; suggest the selected performance's setlist and optional tour label | `events`, Event relationships, `event_provider_references`, and Attendance `setlist_entries` / `setlist_entry_artists` |
| MusicBrainz | Suggest and disambiguate artists and underlying songs/compositions | `artists` / `artist_provider_ids` and `songs` / `song_provider_ids`; song identity uses a work MBID when identified |
| Geoapify | Suggest venues and prefill available address, city, region, country, coordinates, and IANA time zone | `venues` / `venue_provider_ids` |

Existing local records are reused when their identity is established. A matching stored provider identity can identify an existing catalog record; a matching name alone cannot. Without a confirmed match, the user can choose an existing local record or create a manual one without provider identifiers. Selecting suggestions does not grant additional authority to change existing shared catalog or Event facts: administrators still correct those records, while accepted Attendance members edit their shared Attendance details.

### Event and setlist suggestions

- A setlist.fm result describes one artist's performance. Several selected performances can contribute to one Event and one Attendance's setlist, with their source references associated with that Event. The user confirms concert grouping and performance order; a provider setlist ID does not define the internal Event boundary.
- Select all contributing performances within the Attendance's one-time setlist prefill operation. Another Attendance for the same Event can have its own prefill operation and its own editable entries. This is the intended creation workflow, not a provider synchronization process; no prefill history table or database usage counter is required.
- Source artist MBIDs can support matching through MusicBrainz. A source venue name and city can seed a Geoapify lookup; a setlist.fm venue ID is not a Geoapify ID and does not establish a cross-provider match by itself.
- Known source dates prefill the existing date components. Unknown time stays null; an update timestamp or date-only value must not be converted into a performance start time. Tour labels can suggest an optional local Tour, but no tour ID is invented from a name and an empty tour remains valid.
- Selecting a setlist prefills ordinary ordered relational entries. Users can confirm, correct, reorder, add, remove, or manually identify entries. Existing entered or saved content is retained unless the user explicitly chooses to replace it. There is no subsequent provider refresh or upstream comparison.
- Song titles are resolved through MusicBrainz only when the underlying composition can be identified. Unresolved entries keep a null `song_id` and their display text; a title match is insufficient. Performer, composer, and original-cover-artist information must not be treated as interchangeable credits.
- Map a selected setlist.fm song's `tape` flag to `setlist_entries.is_tape`; the user can correct it manually. Preserve source headings and other performance qualifiers using note entries, display text, and notes. Do not infer live performers from tape or prerecorded-video credits, or infer distinct song identities from an ambiguous medley label. The entry types remain `song` and `note`; no version tables or JSON columns are required.

### Artist, song, and Venue suggestions

Artist candidates should be distinguishable by available type, area/country, and disambiguation. Confirmed Artist and Song records can retain their descriptive distinction in their nullable `disambiguation` column. Song candidates should use available writer or recording context to distinguish compositions sharing a title. A recording with no work relationship does not become a composition identifier. Spotify links remain optional playable references, separate from Song identity.

Venue lookup uses available name and city/country context. The user confirms the candidate and can correct values before creating a Venue. Provider confidence or the first result alone does not establish identity; transit stops, unrelated businesses, rooms, and renamed venues can share labels. Missing address components remain unknown. Venue time zones can supply the Event value under the existing date/time rules.

For a confirmed Geoapify place, map `street` to `venues.street`, `housenumber` to `house_number`, `postcode` to `postal_code`, `city` to `city`, `state` to `region`, `country_code` to its uppercase local equivalent, `lat` to `lat`, `lon` to `lng`, and `timezone.name` to `timezone`. A setlist.fm result's `venue.city.coords` locates the city and must not populate Venue coordinates as if it were the venue's location. Venue coordinates come from the confirmed place or manual entry.

Provider suggestions remain optional for nonmusic Events and unsupported or missing records. No results, incomplete lists, timeouts, unavailable credentials, or rate limits leave manual creation and editing available. Only user-confirmed values and associated references are saved; no external catalog mirror or automatic provider updates are required.

## Provider references

Service-supplied identifiers are stored as strings in `provider_resource_id`, qualified by `provider` and `resource_type`. They never replace the internal `id` or the Spectacles-owned UUID `external_id`. Provider values are stable application identifiers such as `setlist_fm`, `musicbrainz`, `geoapify`, `openstreetmap`, or `spotify`; credentials and API configuration are outside these tables. A reference namespace does not imply that its provider is selected for an additional autocomplete responsibility.

| Table | Parent foreign key | Purpose |
| --- | --- | --- |
| `artist_provider_ids` | `artist_id` | Identity references for artists. |
| `venue_provider_ids` | `venue_id` | Venue references, including Geoapify place handles and available typed OpenStreetMap references. |
| `tour_provider_ids` | `tour_id` | Tour identity references where a provider actually supplies an identifier. |
| `song_provider_ids` | `song_id` | Underlying-song identity references, such as a MusicBrainz work. |
| `event_provider_references` | `event_id` | Associated Event or artist-setlist source resources. An external resource need not have the same granularity as a Spectacles Event. |
| `song_links` | `song_id` | Playable versions, including Spotify tracks, without a separate recording catalog. |

Each row has an internal `id`, its own UUID `external_id`, its parent foreign key, `provider`, `resource_type`, `provider_resource_id`, and timestamps. `song_links` may also retain a URL and display label. The provider, resource type, and provider resource ID are required even when a URL is available. Provider IDs remain strings even when a particular service uses UUIDs or numbers. Source payload fields such as `mbid`, `place_id`, or `id` map to `provider_resource_id`; they do not supply the row's UUID.

For the four identity-reference tables, enforce unique `(provider, resource_type, provider_resource_id)` within that table. One identity reference cannot identify two internal entities. Allow multiple references from the same provider on one entity; there is no unique `(parent_id, provider)` constraint. Each row's own `external_id` also has the common UUID uniqueness constraint.

For `event_provider_references` and `song_links`, enforce unique `(parent_id, provider, resource_type, provider_resource_id)`. These are associations rather than claims of identical entity boundaries. A source spanning multiple days can be referenced by several daily Events, and one playable resource can be associated with multiple Songs when appropriate. Each association row has its own independent UUID `external_id`.

An Event reference to a single performance has an additional rule: its `(provider, resource_type, provider_resource_id)` can belong to only one Event. For setlist.fm, apply this uniqueness rule to resource types `setlist` and `setlist_version`. Enforce it in the database, including concurrent saves; the physical constraint mechanism depends on the database engine selected later. Resource types that span multiple Events retain only the parent-scoped uniqueness rule. The application defines the supported resource types and their scope; users cannot change scope to bypass uniqueness.

When a selected performance already has a single-performance reference, reuse its Event. Reject an attempt to attach that reference to another Event. Several different performance IDs can still belong to the same Event. If selected performances already reference different Events, they cannot be combined into one Event through autocomplete. This prevents duplicates with a known source identity; it does not require detection or merging of manual duplicates.

Each reference table has a real foreign key to its parent. There is no generic polymorphic `entity_type`/`entity_id` table without database-enforced parent relationships.

### Provider implications

- Geoapify search, autocomplete, and detail responses can return different `place_id` strings for the same venue. Treat them as external lookup handles, allow several references on one Venue, and do not create duplicate Venues merely because these handles differ. Available underlying OpenStreetMap identifiers retain their object type: `node`, `way`, and `relation` are separate `resource_type` namespaces. Identity still requires confirmation. See the [Geoapify validation](../../research/2026-10-22-provider-validation.md) and [Place Details API](https://apidocs.geoapify.com/docs/place-details/).
- setlist.fm uses MusicBrainz identifiers for artists, its own venue identifiers, and a tour name in the documented setlist response. A name is not a tour identifier. Store the stable source setlist ID as resource type `setlist`. A version ID is optional and uses resource type `setlist_version`; if retained, its Event must also hold the corresponding stable setlist reference. A different version cannot create another Event for the same performance. Referencing source data does not create a synchronization requirement. See the [API documentation](https://api.setlist.fm/docs/1.0/index.html) and [setlist response](https://api.setlist.fm/docs/1.0/resource__1.0_setlist__setlistId_.html).
- MusicBrainz distinguishes [works](https://musicbrainz.org/doc/Work) from [recordings](https://musicbrainz.org/doc/Recording). Spotify identifies [catalog tracks](https://developer.spotify.com/documentation/web-api/reference/get-track). Work identity belongs in `song_provider_ids`; playable track references belong in `song_links`. A track identifier is not the Song's primary identity.

The provider selection records the user's product direction for a personal, non-commercial autocomplete experience. Integration must use documented APIs and satisfy applicable access, attribution, and data-use conditions; this selection does not assert a new provider permission. The dated [event/setlist research](../../research/2026-10-22-event-setlist-provider-validation.md) records the remaining permission uncertainty for saving user-confirmed suggestions. It is research evidence, not a change to the selected providers or an authorization to implement.

The spec requires no automatic provider refresh, network job, alternate-provider selection algorithm, or duplicate-merging process.

## Deletion and integrity

- Restrict deletion when another retained domain record uses the parent. A Venue used by an Event, an Event with Attendances, an Artist used by a lineup, tour, or setlist entry, a Tour linked to an Event, and a Song used by a setlist entry cannot be deleted. Deleting a Song must not silently turn known entries into unidentified songs. Deleting an Event must not remove users' Attendances.
- Provider metadata does not block deletion of an otherwise deletable parent. Delete its owned provider-ID rows, Event provider references, or Song links with it. Likewise, deleting an otherwise deletable Event removes its `event_artists` and `event_tours` rows; deleting an otherwise deletable Tour removes its `tour_artists` rows. These removals do not delete the related Artists or Tours.
- An owner-authorized Attendance deletion removes that Attendance's memberships, member seats, invitations, setlist entries, entry-artist joins, and complementary links. It does not delete shared catalog records or another Attendance for the same Event.
- Removing an ordinary member removes their seat rows and collaboration access while retaining the shared setlist, notes, and links. Owner removal follows the ownership-transfer rule above.
- Account deletion must not bypass ownership or reference restrictions. An account-deletion workflow is outside this design.
- Index foreign keys used for joins and lookup. The membership, setlist-position, provider-identity, and join-pair unique constraints serve both integrity and their corresponding queries.

## Acceptance examples for later implementation

These are requirements to verify when implementation is separately authorized, not tests run as part of this document task.

1. A User records a past Event with only a year and a future Event with a full date but unknown time. Neither requires invented date/time values.
2. An Event has several artists and zero or several tours. Two festival days are represented by two Events.
3. Two accepted members edit one shared Attendance setlist while retaining separate interest flags, ticket flags, and multiple seat/location rows.
4. An unaccepted invitation creates no membership or public companion relationship. An invite for a User already linked to that Event is discarded, including under concurrent acceptance or Attendance creation.
5. The database rejects membership with an Event inconsistent with its Attendance and rejects a second membership for the same User and Event.
6. A setlist includes repeated songs, multiple performing artists, an unidentified song, and a note. Reordering preserves unique entry positions.
7. Two different performers cover the same Song in different Events. A query using the Song ID finds both without requiring matching titles or Spotify IDs.
8. A user selects a setlist.fm performance to prefill relational entries, confirms the suggestions, and saves. Manual changes remain until collaborators edit them; no background update changes the list. Individual song autocomplete remains available during editing.
9. A manual Venue without provider references can be used immediately. Adding several provider references later retains its internal identity. A renamed Venue displays its shared current name on past Events.
10. Users can create missing catalog records; administrators correct shared facts. Referenced catalog deletion is rejected, while deleting an owned Attendance removes only its dependent personal/shared records.
11. One Song can have several Spotify track links while retaining one internal song identity. Provider resource namespaces and uniqueness constraints prevent accidental identity collisions.
12. Event/setlist suggestions come from setlist.fm, artist/song suggestions from MusicBrainz, and Venue suggestions from Geoapify. The user can create the same otherwise valid Attendance manually when any or all providers are unavailable.
13. Several artist-specific setlist.fm records can contribute to one confirmed concert; two separate concerts at the same venue on the same date remain separate Events. Suggestions do not merge them automatically.
14. An ambiguous song stays unidentified until confirmed; a recording ID is not stored as a work ID. Different Geoapify handles for a confirmed existing Venue do not automatically create another Venue.
15. Cancelling a form persists no unconfirmed suggestions. Choosing autocomplete cannot overwrite existing saved shared facts or setlist edits without the applicable authority and explicit user action.
16. Two Artists with the same name, or two Songs with the same title, can have different disambiguation text and external identities. Disambiguation remains optional and does not impose a uniqueness constraint.
17. A setlist contains the same identified Song once live and once from tape. Both remain visible; its live-rendition occurrence count is one, and tape playback alone does not qualify an Event for a live-rendition Event count. Note entries cannot be marked as tape.
18. Venue creation accepts incomplete addresses and preserves text house numbers and postal codes. It rejects a single supplied coordinate or coordinates outside their valid ranges, accepts zero coordinates, maps Geoapify `lon` to `lng`, and never uses city coordinates as venue coordinates.
19. Two collaborators use different interface languages to view the same Attendance. Interface labels and formatting differ, while catalog names, setlist content, notes, external identities, and saved date/time values remain unchanged.
20. A localized link-type label is saved as the English code `other`; invitation statuses and entry types also retain their English codes. No translated interface label is stored as a domain value. Original-language names and user text, including Spanish content, retain their spelling and accents.
21. A supported `users.locale` preference takes precedence over browser language; an absent or unsupported preference falls back to a supported browser language and then the configured application default. Language selection does not alter an Event's time zone or date precision.
22. Every described record, including a User, join row, provider reference, and manually entered record, has its own required unique UUID `external_id`. It remains stable after edits and is independent of any service-supplied identifier. Public references resolve through that UUID while foreign keys continue to reference internal `id` values.
23. A MusicBrainz MBID is stored as a string `provider_resource_id` on an `artist_provider_ids` row, alongside `provider = musicbrainz` and `resource_type = artist`. That row and its parent Artist each have their own separate UUID `external_id`. Existing provider-resource and relationship-pair uniqueness rules still reject duplicates.
24. Two members save conflicting edits to the same shared value. The last successful save wins. A reorder either saves all changed positions or saves none; it cannot leave duplicate positions. Member-specific permissions still apply.
25. A setlist.fm performance already references an Event. Selecting it again reuses that Event. The database rejects linking its single-performance ID to a second Event, including concurrent attempts and references selected through different versions. A source covering several festival days can still reference several daily Events.
26. Deleting an unused Song removes its provider-ID rows and playable links. A Song used by a setlist entry cannot be deleted. Provider metadata alone does not block deletion of an unused catalog record or an Event with no Attendances.
27. The application accepts supported English type codes and rejects unsupported codes for Artists, Events, and Attendance links. The database stores strings, nullable Artist type remains valid, and omitted link type becomes `other`.
28. One Attendance prefill combines several selected artist performances before confirmation. Its members share that operation. A separate Attendance for the same Event can use its own prefill. Later manual editing and individual song or artist autocomplete remain available.

## Completion and authorization

The database design is documented for review. This document does not authorize migrations, product code, dependency installation, schema or data changes, an implementation plan, commits, pushes, pull requests, or deployments. Those actions require subsequent user authorization.
