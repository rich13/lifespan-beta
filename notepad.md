# Project Notes

## MusicBrainz Import Implementation (March 2024)

### Current State
- Basic import functionality is working for albums
- Track import is implemented but needs testing
- Using MusicBrainz API v2 with proper user agent
- Following rate limiting guidelines

### Implementation Details
1. Band Selection
   - Users can select a band from existing spans
   - Search functionality to find matching MusicBrainz artist
   - Filters out non-band artists

2. Album Selection
   - Fetches discography from MusicBrainz
   - Filters albums based on:
     - Must be primary type "Album"
     - Must have release date
     - No secondary types
     - No disambiguation
     - Excludes compilations, live albums, etc.
   - Sorts by release date

3. Track Import
   - Fetches tracks from first release of each album
   - Includes track metadata:
     - Title
     - Length
     - ISRC
     - Artist credits
     - Release date
   - Creates track spans with subtype "recording"
   - Creates "contains" connections between albums and tracks

### Known Issues
- Need to verify track import is working correctly
- May need to handle multi-disc albums better
- Could add support for track ordering

### Next Steps
1. Test track import thoroughly
2. Add support for track ordering
3. Consider adding support for multi-disc albums
4. Add validation for track data
5. Consider adding support for track artwork

### API Usage
- Using MusicBrainz API v2
- Endpoints used:
  - `/artist` for artist search
  - `/release-group` for album listing
  - `/release` for track data
- Proper includes for related data:
  - `media+recordings` for track data
  - `artist-credits` for artist information

### Data Model
- Albums are created as spans with:
  - type_id: "thing"
  - subtype: "album"
  - metadata: musicbrainz_id, type, disambiguation
- Tracks are created as spans with:
  - type_id: "thing"
  - subtype: "recording"
  - metadata: musicbrainz_id, isrc, length, artist_credits
- Connections:
  - "created" between band and album
  - "contains" between album and tracks 