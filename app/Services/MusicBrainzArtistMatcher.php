<?php

namespace App\Services;

/**
 * Rank MusicBrainz artist search hits and auto-accept only when the match is unambiguous.
 */
class MusicBrainzArtistMatcher
{
    public const AUTO_ACCEPT_MIN_SCORE = 90;

    public const AUTO_ACCEPT_MARGIN = 20;

    public const SINGLE_CANDIDATE_MIN_SCORE = 70;

    private const REJECT_PHRASES = [
        'tribute',
        'bootleg',
        'karaoke',
        'cover band',
        'parody',
        'spoof',
        'imitator',
        'impersonator',
        'fake',
        'unofficial',
    ];

    /**
     * @return list<string>
     */
    public function expectedTypes(?string $spanTypeId): array
    {
        return match ($spanTypeId) {
            'band' => ['Group', 'Orchestra', 'Choir'],
            'person' => ['Person'],
            default => ['Person', 'Group', 'Orchestra', 'Choir'],
        };
    }

    public function buildSearchQuery(string $artistName, ?string $spanTypeId = null): string
    {
        $phrase = $this->escapeLucenePhrase($artistName);
        $types = match ($spanTypeId) {
            'band' => 'type:group OR type:orchestra OR type:choir',
            'person' => 'type:person',
            default => 'type:person OR type:group OR type:orchestra OR type:choir',
        };

        return 'artist:"' . $phrase . '" AND (' . $types . ')';
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    public function rank(array $candidates, string $searchName, ?string $spanTypeId = null): array
    {
        $searchLower = mb_strtolower(trim($searchName));
        $expectedTypes = $this->expectedTypes($spanTypeId);

        $ranked = [];
        foreach ($candidates as $candidate) {
            $name = (string) ($candidate['name'] ?? '');
            $disambiguation = (string) ($candidate['disambiguation'] ?? '');
            $type = (string) ($candidate['type'] ?? '');
            $mbScore = (int) ($candidate['score'] ?? 0);
            $rejected = $this->isRejected($name, $disambiguation, $type);
            $exactName = mb_strtolower($name) === $searchLower;

            $rankScore = $mbScore;
            if ($exactName) {
                $rankScore += 40;
            }
            if (in_array($type, $expectedTypes, true)) {
                $rankScore += 20;
            } elseif ($type !== '') {
                $rankScore -= 30;
            }
            if ($disambiguation !== '') {
                $rankScore -= 5;
            }
            if ($rejected) {
                $rankScore -= 100;
            }

            $ranked[] = array_merge($candidate, [
                'rank_score' => $rankScore,
                'rejected' => $rejected,
                'exact_name' => $exactName,
            ]);
        }

        usort($ranked, function (array $left, array $right) {
            return ($right['rank_score'] <=> $left['rank_score'])
                ?: ((int) ($right['score'] ?? 0) <=> (int) ($left['score'] ?? 0));
        });

        return $ranked;
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array{status: string, artist?: array<string, mixed>, reason: string}
     */
    public function pickUnambiguous(array $candidates, string $searchName, ?string $spanTypeId = null): array
    {
        $ranked = $this->rank($candidates, $searchName, $spanTypeId);
        $viable = array_values(array_filter(
            $ranked,
            fn (array $candidate) => empty($candidate['rejected']) && ($candidate['rank_score'] ?? 0) >= 50
        ));

        if ($viable === []) {
            return [
                'status' => 'no_match',
                'reason' => 'No MusicBrainz artist was close enough to auto-accept',
            ];
        }

        $top = $viable[0];
        $second = $viable[1] ?? null;

        if ($second === null && ($top['rank_score'] ?? 0) >= self::SINGLE_CANDIDATE_MIN_SCORE) {
            return [
                'status' => 'matched',
                'artist' => $top,
                'reason' => 'Single viable MusicBrainz artist',
            ];
        }

        if ($second !== null && (($top['rank_score'] ?? 0) - ($second['rank_score'] ?? 0)) < self::AUTO_ACCEPT_MARGIN) {
            return [
                'status' => 'ambiguous',
                'reason' => 'Several MusicBrainz artists scored similarly',
            ];
        }

        if (($top['rank_score'] ?? 0) >= self::AUTO_ACCEPT_MIN_SCORE || !empty($top['exact_name'])) {
            return [
                'status' => 'matched',
                'artist' => $top,
                'reason' => 'Clear MusicBrainz artist match',
            ];
        }

        return [
            'status' => 'ambiguous',
            'reason' => 'Top MusicBrainz artist was not confident enough to auto-accept',
        ];
    }

    public function isRejected(string $name, string $disambiguation, string $type = ''): bool
    {
        if ($type === 'Other' || $type === 'Character') {
            return true;
        }

        $haystack = mb_strtolower($name . ' ' . $disambiguation);
        foreach (self::REJECT_PHRASES as $phrase) {
            if (str_contains($haystack, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function escapeLucenePhrase(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
