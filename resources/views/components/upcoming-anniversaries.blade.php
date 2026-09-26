@props(['date' => null])

@php
    $targetDate = $date ? \Carbon\Carbon::parse($date) : \App\Helpers\DateHelper::getCurrentDate();

    $allAnniversaries = \App\Helpers\AnniversaryHelper::getUpcomingAnniversaries($targetDate, 60);

    $startsEvents = [];
    $endsEvents = [];
    foreach ($allAnniversaries as $event) {
        $type = $event['type'] ?? null;
        if ($type === 'birthday' || $type === 'album_anniversary' || $type === 'film_anniversary') {
            $startsEvents[] = $event;
        } elseif ($type === 'death_anniversary') {
            $endsEvents[] = $event;
        }
    }
    $startsEvents = array_slice($startsEvents, 0, 5);
    $endsEvents = array_slice($endsEvents, 0, 5);

    $resolveCreatedBy = function (\App\Models\Span $thingSpan) {
        $creator = $thingSpan->connectionsAsObject()
            ->where('type_id', 'created')
            ->whereHas('parent', function ($query) {
                $query->whereIn('type_id', ['person', 'band', 'organisation']);
            })
            ->with('parent')
            ->first()
            ?->parent;

        if ($creator) {
            return $creator;
        }

        if (! empty($thingSpan->metadata['creator'])) {
            return \App\Models\Span::find($thingSpan->metadata['creator']);
        }

        return null;
    };

    $resolvePhotoUrl = function ($span) {
        $photoConnection = \App\Models\Connection::where('type_id', 'features')
            ->where('child_id', $span->id)
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'thing')
                    ->whereJsonContains('metadata->subtype', 'photo');
            })
            ->with(['parent'])
            ->first();

        if (!$photoConnection || !$photoConnection->parent) {
            return null;
        }

        $photoSpan = $photoConnection->parent;
        $metadata = $photoSpan->metadata ?? [];
        $url = $metadata['thumbnail_url']
            ?? $metadata['medium_url']
            ?? $metadata['large_url']
            ?? null;

        if (!$url && !empty($metadata['filename'])) {
            $url = route('images.proxy', ['spanId' => $photoSpan->id, 'size' => 'medium']);
        }

        return $url;
    };

    $nameLink = function ($span) {
        return '<a href="' . e(route('spans.show', $span)) . '" class="text-decoration-none fw-bold">'
            . e($span->name) . '</a>';
    };

    $dateLink = function (\Carbon\Carbon $d) {
        $slug = $d->format('Y-m-d');

        return '<a href="' . e(url('/date/' . $slug)) . '" class="text-decoration-none">'
            . e($d->format('j F Y')) . '</a>';
    };

    $historicalDate = function ($span, string $edge) {
        $year = $edge === 'end' ? $span->end_year : $span->start_year;
        $month = $edge === 'end' ? $span->end_month : $span->start_month;
        $day = $edge === 'end' ? $span->end_day : $span->start_day;

        if (! $year || ! $month || ! $day) {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromDate((int) $year, (int) $month, (int) $day);
        } catch (\Throwable $e) {
            return null;
        }
    };

    $daysBadge = function (int $days) {
        $label = 'In ' . $days . ' ' . \Illuminate\Support\Str::plural('day', $days);

        return '<span class="badge upcoming-anniversary-days-badge me-2">' . e($label) . '</span>';
    };

    $todayBadge = function (\Carbon\Carbon $d) {
        $slug = $d->format('Y-m-d');

        return '<a href="' . e(url('/date/' . $slug)) . '" class="badge upcoming-anniversary-days-badge me-2 text-decoration-none">Today</a>';
    };

    $rowFromEvent = function (array $event) use ($resolvePhotoUrl, $resolveCreatedBy, $nameLink, $dateLink, $historicalDate, $daysBadge, $todayBadge) {
        $span = $event['span'];
        $type = $event['type'];
        $days = (int) $event['days_until'];
        $date = $event['date'];
        $photoUrl = ($span->subtype ?? null) === 'album' ? null : $resolvePhotoUrl($span);
        $milestone = ($event['significance'] ?? 0) >= 50;
        $isToday = $days === 0;

        $name = $nameLink($span);
        $when = $dateLink($date);
        $whenBadgeHtml = $isToday ? $todayBadge($date) : $daysBadge($days);

        if ($type === 'birthday') {
            $age = (int) $event['age'];
            $ageHtml = $milestone
                ? '<strong class="text-warning">' . e((string) $age) . '</strong>'
                : '<strong>' . e((string) $age) . '</strong>';

            $born = $historicalDate($span, 'start');
            $bornWhen = $born ? $dateLink($born) : null;

            if ($isToday && $age === 0) {
                $sentence = $name . ' was born.';
            } elseif ($age === 0) {
                $sentence = $name . ' will be born on ' . ($bornWhen ?: $when) . '.';
            } elseif ($bornWhen) {
                $sentence = $name . ' turns ' . $ageHtml . ', born on ' . $bornWhen . '.';
            } else {
                $sentence = $name . ' turns ' . $ageHtml . '.';
            }
        } elseif ($type === 'death_anniversary') {
            $years = (int) $event['years'];
            $yearsHtml = $milestone
                ? '<strong class="text-warning">' . e((string) $years) . '</strong>'
                : '<strong>' . e((string) $years) . '</strong>';
            $ys = $yearsHtml . ' ' . \Illuminate\Support\Str::plural('year', $years);

            $deathOccurred = $historicalDate($span, 'end');
            $deathWhen = $deathOccurred ? $dateLink($deathOccurred) : $when;
            $sentence = $ys . ' since ' . $name . '\'s death on ' . $deathWhen . '.';
        } elseif ($type === 'album_anniversary') {
            $years = (int) $event['years'];
            $yearsHtml = $milestone
                ? '<strong class="text-warning">' . e((string) $years) . '</strong>'
                : '<strong>' . e((string) $years) . '</strong>';
            $ys = $yearsHtml . ' ' . \Illuminate\Support\Str::plural('year', $years);

            $creator = $resolveCreatedBy($span);

            $who = $creator
                ? $name . ' by ' . $nameLink($creator)
                : $name;

            $released = $historicalDate($span, 'start');
            $releasedWhen = $released ? $dateLink($released) : $when;
            $sentence = $ys . ' since ' . $who . ' was released on ' . $releasedWhen . '.';
        } elseif ($type === 'film_anniversary') {
            $years = (int) $event['years'];
            $yearsHtml = $milestone
                ? '<strong class="text-warning">' . e((string) $years) . '</strong>'
                : '<strong>' . e((string) $years) . '</strong>';
            $ys = $yearsHtml . ' ' . \Illuminate\Support\Str::plural('year', $years);

            $director = $resolveCreatedBy($span);

            $who = $director
                ? $name . ', directed by ' . $nameLink($director)
                : $name;

            $released = $historicalDate($span, 'start');
            $releasedWhen = $released ? $dateLink($released) : $when;
            $sentence = $ys . ' since ' . $who . ' was released on ' . $releasedWhen . '.';
        } else {
            $sentence = $name;
        }

        return [
            'span' => $span,
            'photoUrl' => $photoUrl,
            'whenBadgeHtml' => $whenBadgeHtml,
            'sentenceHtml' => $sentence,
            'is_today' => $isToday,
        ];
    };

    $startsEntries = array_map($rowFromEvent, $startsEvents);
    $endsEntries = array_map($rowFromEvent, $endsEvents);

    $hasAny = count($startsEntries) > 0 || count($endsEntries) > 0;
@endphp

@if($hasAny)
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="h6 mb-0">
                <i class="bi bi-calendar-check text-info me-2"></i>
                Anniversaries
            </h3>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-6">
                    @forelse($startsEntries as $row)
                        <div class="card mb-2{{ !empty($row['is_today']) ? ' border-primary bg-primary-subtle shadow-sm' : '' }}">
                            <div class="card-body py-2">
                                @include('components.upcoming-anniversaries-thumb', ['row' => $row])
                                <p class="mb-0 small">{!! $row['whenBadgeHtml'] !!}{!! $row['sentenceHtml'] !!}</p>
                                <div class="clearfix"></div>
                            </div>
                        </div>
                    @empty
                        <p class="small text-muted mb-0">None in the next 60 days.</p>
                    @endforelse
                </div>
                <div class="col-md-6">
                    @forelse($endsEntries as $row)
                        <div class="card mb-2{{ !empty($row['is_today']) ? ' border-primary bg-primary-subtle shadow-sm' : '' }}">
                            <div class="card-body py-2">
                                @include('components.upcoming-anniversaries-thumb', ['row' => $row])
                                <p class="mb-0 small">{!! $row['whenBadgeHtml'] !!}{!! $row['sentenceHtml'] !!}</p>
                                <div class="clearfix"></div>
                            </div>
                        </div>
                    @empty
                        <p class="small text-muted mb-0">None in the next 60 days.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endif
