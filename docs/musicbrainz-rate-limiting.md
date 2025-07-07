# MusicBrainz Rate Limiting Implementation

## Overview

This document describes the rate limiting implementation for the MusicBrainz API integration in the Lifespan Beta application.

## MusicBrainz API Requirements

According to the [MusicBrainz API documentation](http://wiki.musicbrainz.org/XMLWebService), all users of the API must ensure that each of their client applications never make more than **ONE call per second**.

## Implementation Details

### Rate Limiting Strategy

The application implements a client-side rate limiting mechanism that ensures compliance with MusicBrainz's requirements:

1. **Minimum Interval**: 1.0 second between requests
2. **Cache-based Tracking**: Uses Laravel's cache system to track the last request time
3. **Automatic Delays**: Automatically waits if requests are made too quickly
4. **Retry Logic**: Handles 503 rate limit errors with automatic retry

### Key Components

#### MusicBrainzImportService

The main service class (`app/Services/MusicBrainzImportService.php`) contains the rate limiting logic:

- `respectRateLimit()`: Ensures minimum 1-second intervals between requests
- `makeRateLimitedRequest()`: Wraps HTTP requests with rate limiting and retry logic
- Cache key: `musicbrainz_rate_limit` (stored for 60 seconds)

#### Rate Limiting Flow

1. **Request Initiation**: When a MusicBrainz API call is made
2. **Time Check**: Check the last request time from cache
3. **Delay Calculation**: Calculate required delay to maintain 1-second minimum
4. **Wait if Needed**: Use `usleep()` to wait the required time
5. **Update Cache**: Store current timestamp for next request
6. **Make Request**: Execute the actual HTTP request
7. **Handle 503 Errors**: If rate limit is hit, wait 2 seconds and retry once

### Error Handling

The implementation includes robust error handling:

- **503 Rate Limit Errors**: Automatically retry after 2-second delay
- **Other Errors**: Pass through without retry
- **Logging**: Comprehensive logging for debugging and monitoring

### Usage

All MusicBrainz API calls should go through the `MusicBrainzImportService`:

```php
$service = new MusicBrainzImportService();

// These methods automatically handle rate limiting
$artists = $service->searchArtist('Artist Name');
$details = $service->getArtistDetails($mbid);
$discography = $service->getDiscography($mbid);
$tracks = $service->getTracks($releaseGroupId);
```

### Controllers

The following controllers have been updated to use the service:

- `MusicBrainzImportController`: Admin interface for importing music data
- `DesertIslandDiscsStepImportController`: Import functionality for Desert Island Discs data

### Testing

Comprehensive tests are included in `tests/Feature/MusicBrainzRateLimitTest.php`:

- Rate limiting timing verification
- 503 error retry logic
- Cache usage validation
- Non-rate-limit error handling

## Monitoring

The implementation includes detailed logging:

- Rate limiting delays
- 503 error retries
- Request timing information
- Error conditions

Log entries can be found in the Laravel log files with the prefix `musicbrainz_rate_limit`.

## Configuration

The rate limiting is configured with these constants in `MusicBrainzImportService`:

- `$minRequestInterval = 1.0`: Minimum seconds between requests
- `$rateLimitKey = 'musicbrainz_rate_limit'`: Cache key for tracking
- Cache TTL: 60 seconds

## Compliance

This implementation ensures full compliance with MusicBrainz's rate limiting requirements while providing:

- Automatic compliance (no manual intervention required)
- Graceful error handling
- Comprehensive logging
- Test coverage
- Consistent behavior across all API calls

## Future Considerations

- Consider implementing exponential backoff for repeated 503 errors
- Monitor cache performance for high-traffic scenarios
- Consider distributed rate limiting for multi-server deployments 