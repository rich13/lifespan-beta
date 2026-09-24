<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DesertIslandDiscsExploreService
{
    /**
     * Castaways the viewer may open, ordered by name.
     *
     * @return Collection<int, array{name: string, set_key: string}>
     */
    public function catalogue(?User $user): Collection
    {
        $sets = Span::query()
            ->where('type_id', 'set')
            ->where(function ($query) {
                $query->whereJsonContains('metadata->subtype', 'desertislanddiscs')
                    ->orWhere('metadata->subtype', 'desertislanddiscs');
            })
            ->viewableBy($user)
            ->get();

        if ($sets->isEmpty()) {
            return collect();
        }

        $peopleBySetId = Connection::query()
            ->where('type_id', 'created')
            ->whereIn('child_id', $sets->pluck('id'))
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'person');
            })
            ->with('parent')
            ->get()
            ->unique('child_id')
            ->keyBy('child_id');

        return $sets
            ->map(function (Span $set) use ($peopleBySetId, $user) {
                $person = $peopleBySetId->get($set->id)?->parent;
                $visiblePerson = $person && $person->hasPermission($user, 'view') ? $person : null;

                return [
                    'name' => $visiblePerson?->name ?? $this->nameFromSet($set),
                    'set_key' => $set->getRouteKey(),
                ];
            })
            ->sortBy(fn (array $row) => Str::lower($row['name']))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function setPayload(Span $set, ?User $user = null): array
    {
        $person = Connection::query()
            ->where('type_id', 'created')
            ->where('child_id', $set->id)
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'person');
            })
            ->with('parent')
            ->first()
            ?->parent;

        if ($person && ! $person->hasPermission($user, 'view')) {
            $person = null;
        }

        $contents = Connection::query()
            ->where('type_id', 'contains')
            ->where('parent_id', $set->id)
            ->with(['connectionSpan', 'child'])
            ->get()
            ->filter(fn (Connection $connection) => $connection->child !== null);

        $tracks = $contents->filter(fn (Connection $connection) => $this->subtype($connection->child) === 'track');
        $books = $contents->filter(fn (Connection $connection) => $this->subtype($connection->child) === 'book');

        $trackIds = $tracks->map(fn (Connection $connection) => $connection->child->id)->values();
        $bookIds = $books->map(fn (Connection $connection) => $connection->child->id)->values();
        $artistsByChildId = $this->creatorsFor($trackIds->merge($bookIds));
        $albumsByTrackId = $this->albumsFor($trackIds);
        $albumIds = $albumsByTrackId->map(fn (Span $album) => $album->id)->values();
        $albumArtistsByAlbumId = $this->creatorsFor($albumIds);

        $favourite = $set->getMeta('favourite_track');

        $trackPayloads = $tracks
            ->map(function (Connection $connection) use ($artistsByChildId, $albumsByTrackId, $albumArtistsByAlbumId, $favourite) {
                $track = $connection->child;
                $album = $albumsByTrackId->get((string) $track->id);
                $artist = $artistsByChildId->get((string) $track->id)
                    ?? ($album ? $albumArtistsByAlbumId->get((string) $album->id) : null);
                $position = $connection->connectionSpan?->getMeta('position');

                return [
                    'id' => $track->id,
                    'position' => is_numeric($position) ? (int) $position : null,
                    'name' => $track->name,
                    'url' => route('spans.show', $track),
                    'description' => $connection->connectionSpan?->description,
                    'is_favourite' => $this->namesMatch(is_string($favourite) ? $favourite : null, $track->name),
                    'artist' => $artist ? $this->spanLink($artist) : null,
                    'album' => $album ? $this->albumLink($album) : null,
                ];
            })
            ->sortBy(fn (array $track) => sprintf('%03d-%s', $track['position'] ?? 999, Str::lower($track['name'])))
            ->values();

        $bookPayloads = $books
            ->map(function (Connection $connection) use ($artistsByChildId) {
                $book = $connection->child;
                $author = $artistsByChildId->get((string) $book->id);

                return [
                    'name' => $book->name,
                    'url' => route('spans.show', $book),
                    'author' => $author ? $this->spanLink($author) : null,
                ];
            })
            ->values();

        return [
            'set' => [
                'name' => $set->name,
                'url' => route('spans.show', $set),
                'broadcast' => $set->human_readable_start_date,
                'presenter' => $this->nullableString($set->getMeta('presenter')),
                'luxury' => $this->nullableString($set->getMeta('luxury')),
                'favourite_track' => $this->nullableString($favourite),
            ],
            'person' => $person ? $this->spanLink($person) : [
                'name' => $this->nameFromSet($set),
                'url' => null,
            ],
            'books' => $bookPayloads,
            'tracks' => $trackPayloads,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $childIds
     * @return Collection<string, Span>
     */
    private function creatorsFor(Collection $childIds): Collection
    {
        if ($childIds->isEmpty()) {
            return collect();
        }

        return Connection::query()
            ->where('type_id', 'created')
            ->whereIn('child_id', $childIds)
            ->whereHas('parent', function ($query) {
                $query->whereIn('type_id', ['person', 'band']);
            })
            ->with('parent')
            ->get()
            ->filter(fn (Connection $connection) => $connection->parent !== null)
            ->unique('child_id')
            ->mapWithKeys(fn (Connection $connection) => [(string) $connection->child_id => $connection->parent]);
    }

    /**
     * @param  Collection<int, mixed>  $trackIds
     * @return Collection<string, Span>
     */
    private function albumsFor(Collection $trackIds): Collection
    {
        if ($trackIds->isEmpty()) {
            return collect();
        }

        return Connection::query()
            ->where('type_id', 'contains')
            ->whereIn('child_id', $trackIds)
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'thing')
                    ->where(function ($subtype) {
                        $subtype->whereJsonContains('metadata->subtype', 'album')
                            ->orWhere('metadata->subtype', 'album');
                    });
            })
            ->with('parent')
            ->get()
            ->filter(fn (Connection $connection) => $connection->parent !== null)
            ->unique('child_id')
            ->mapWithKeys(fn (Connection $connection) => [(string) $connection->child_id => $connection->parent]);
    }

    /**
     * @return array{name: string, url: string}
     */
    private function spanLink(Span $span): array
    {
        return [
            'name' => $span->name,
            'url' => route('spans.show', $span),
        ];
    }

    /**
     * @return array{name: string, url: string, year: int|null, id: string, cover_url: string|null, cover_large_url: string|null, needs_cover: bool}
     */
    private function albumLink(Span $album): array
    {
        return [
            'id' => $album->id,
            'name' => $album->name,
            'url' => route('spans.show', $album),
            'year' => $album->start_year ? (int) $album->start_year : null,
            'cover_url' => $album->storedCoverArtUrl('small'),
            'cover_large_url' => $album->storedCoverArtUrl('large') ?: $album->storedCoverArtUrl('small'),
            'needs_cover' => $album->needsCoverArtFetch(),
        ];
    }

    private function subtype(Span $span): ?string
    {
        $subtype = $span->getMeta('subtype');

        return is_string($subtype) ? $subtype : null;
    }

    private function nameFromSet(Span $set): string
    {
        $name = preg_replace("/'s Desert Island Discs.*$/i", '', $set->name) ?? $set->name;
        $name = trim($name);

        return $name !== '' ? $name : $set->name;
    }

    private function namesMatch(?string $favourite, string $trackName): bool
    {
        if ($favourite === null || trim($favourite) === '') {
            return false;
        }

        return Str::lower(trim($favourite)) === Str::lower(trim($trackName));
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
