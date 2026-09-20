<?php

namespace App\Support;

use App\Models\Connection;
use App\Models\Span;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Batched lookups that are not in a span’s connections dump.
 * Cards call these instead of repeating the same query per row or per card.
 */
final class SpanShowLookups
{
    /**
     * First featured-photo URL for each span, in one query.
     *
     * @param  list<string>  $spanIds
     * @return Collection<string, string>
     */
    public static function firstFeaturedPhotoUrlBySpanId(array $spanIds): Collection
    {
        $spanIds = array_values(array_filter(array_unique($spanIds)));
        if ($spanIds === []) {
            return collect();
        }

        return Connection::where('type_id', 'features')
            ->whereIn('child_id', $spanIds)
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'thing')
                    ->whereJsonContains('metadata->subtype', 'photo');
            })
            ->with(['parent'])
            ->get()
            ->groupBy('child_id')
            ->map(fn ($connections) => self::photoUrlFromSpan($connections->first()?->parent))
            ->filter();
    }

    public static function photoUrlFromSpan(?Span $span): ?string
    {
        if (! $span) {
            return null;
        }

        $metadata = $span->metadata ?? [];
        $url = $metadata['thumbnail_url']
            ?? $metadata['medium_url']
            ?? $metadata['large_url']
            ?? null;

        if (is_string($url) && $url !== '') {
            return $url;
        }

        if (! empty($metadata['filename'])) {
            return route('images.proxy', ['spanId' => $span->id, 'size' => 'thumbnail']);
        }

        return null;
    }

    /**
     * Desert Island Discs sets that contain these tracks, in one query.
     * Not in an album’s connections dump — those sets belong to other spans.
     *
     * @param  list<string>  $trackIds
     * @return Collection<string, Collection<int, Connection>>
     */
    public static function desertIslandDiscsByTrackId(array $trackIds): Collection
    {
        $trackIds = array_values(array_filter(array_unique($trackIds)));
        if ($trackIds === []) {
            return collect();
        }

        return Connection::where('type_id', 'contains')
            ->whereIn('child_id', $trackIds)
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'set')
                    ->whereJsonContains('metadata->subtype', 'desertislanddiscs');
            })
            ->with([
                'parent:id,name',
                'parent.connectionsAsObject' => function ($query) {
                    $query->where('type_id', 'created')
                        ->whereHas('parent', fn ($parentQuery) => $parentQuery->where('type_id', 'person'))
                        ->with(['parent:id,name']);
                },
                'parent.connectionsAsObject.parent',
            ])
            ->get()
            ->groupBy('child_id');
    }

    /**
     * Other films that share this film’s actors or director.
     * Not in this film’s connections dump — those films belong to other spans.
     *
     * @param  list<string>  $actorIds
     * @return Collection<int, Span>
     */
    public static function filmsRelatedByCastOrDirector(Span $film, array $actorIds, ?string $directorId): Collection
    {
        $actorIds = array_values(array_filter(array_unique($actorIds)));
        if ($actorIds === [] && ! $directorId) {
            return collect();
        }

        $user = Auth::user();
        $query = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'film')
            ->where('id', '!=', $film->id)
            ->where(function ($outer) use ($actorIds, $directorId) {
                if ($actorIds !== []) {
                    $outer->whereHas('connectionsAsSubject', function ($subQuery) use ($actorIds) {
                        $subQuery->where('type_id', 'features')
                            ->whereIn('child_id', $actorIds)
                            ->whereHas('child', fn ($childQuery) => $childQuery->where('type_id', 'person'));
                    });
                }

                if ($directorId) {
                    $directorConstraint = function ($subQuery) use ($directorId) {
                        $subQuery->where('type_id', 'created')
                            ->where('parent_id', $directorId)
                            ->whereHas('parent', fn ($parentQuery) => $parentQuery->where('type_id', 'person'));
                    };
                    if ($actorIds !== []) {
                        $outer->orWhereHas('connectionsAsObject', $directorConstraint);
                    } else {
                        $outer->whereHas('connectionsAsObject', $directorConstraint);
                    }
                }
            });

        if (! $user) {
            $query->where('access_level', 'public');
        } elseif (! $user->is_admin) {
            $query->where(function ($accessQuery) use ($user) {
                $accessQuery->where('access_level', 'public')
                    ->orWhere('owner_id', $user->id)
                    ->orWhere(function ($sharedQuery) use ($user) {
                        $sharedQuery->where('access_level', 'shared')
                            ->whereExists(function ($permissionQuery) use ($user) {
                                $permissionQuery->select('id')
                                    ->from('span_permissions')
                                    ->whereColumn('span_permissions.span_id', 'spans.id')
                                    ->where('span_permissions.user_id', $user->id);
                            });
                    });
            });
        }

        return $query
            ->with([
                'connectionsAsSubject' => function ($relationQuery) use ($actorIds) {
                    if ($actorIds !== []) {
                        $relationQuery->where('type_id', 'features')
                            ->whereIn('child_id', $actorIds)
                            ->with(['child:id,name']);
                    }
                },
                'connectionsAsObject' => function ($relationQuery) {
                    $relationQuery->where('type_id', 'created')
                        ->with(['parent:id,name']);
                },
            ])
            ->get();
    }

    /**
     * Other connections between the same subject and object with the same type.
     * Not in a connection-span’s dump — those rows belong to sibling connection spans.
     *
     * @return Collection<int, Connection>
     */
    public static function siblingConnections(Connection $current): Collection
    {
        return Connection::where('type_id', $current->type_id)
            ->where('parent_id', $current->parent_id)
            ->where('child_id', $current->child_id)
            ->where('id', '!=', $current->id)
            ->whereNotNull('connection_span_id')
            ->with(['connectionSpan'])
            ->get()
            ->filter(fn ($connection) => $connection->connectionSpan !== null)
            ->sortBy(function ($connection) {
                $span = $connection->connectionSpan;
                $year = $span->start_year ?? PHP_INT_MAX;
                $month = $span->start_month ?? PHP_INT_MAX;
                $day = $span->start_day ?? PHP_INT_MAX;

                return sprintf('%08d-%02d-%02d', $year, $month, $day);
            })
            ->values();
    }

    /**
     * A span’s other connections, excluding one connection id.
     * Used on a connection-span page, where the subject is a different span.
     *
     * @return Collection<int, Connection>
     */
    public static function otherConnectionsOf(Span $span, string $excludeConnectionId): Collection
    {
        $user = Auth::user();

        $asSubject = $span->connectionsAsSubjectWithAccess($user)
            ->where('id', '!=', $excludeConnectionId)
            ->whereNotNull('connection_span_id')
            ->with(['connectionSpan', 'subject', 'object', 'type'])
            ->get();

        $asObject = $span->connectionsAsObjectWithAccess($user)
            ->where('id', '!=', $excludeConnectionId)
            ->whereNotNull('connection_span_id')
            ->with(['connectionSpan', 'subject', 'object', 'type'])
            ->get();

        return $asSubject->merge($asObject);
    }
}
