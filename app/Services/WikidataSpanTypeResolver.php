<?php

namespace App\Services;

/**
 * Map a Wikidata item onto a span type using "instance of" (P31).
 * A class is followed through "subclass of" (P279) until it hits a known root.
 * A human wins over any other class on the same item.
 */
class WikidataSpanTypeResolver
{
    /**
     * @var array<string, array{type_id: string, subtype: ?string}>
     */
    private const DIRECT = [
        'Q5' => ['type_id' => 'person', 'subtype' => 'public_figure'],
        'Q215380' => ['type_id' => 'band', 'subtype' => null],
        'Q5741069' => ['type_id' => 'band', 'subtype' => null],
        'Q571' => ['type_id' => 'thing', 'subtype' => 'book'],
        'Q7725634' => ['type_id' => 'thing', 'subtype' => 'book'],
        'Q47461344' => ['type_id' => 'thing', 'subtype' => 'book'],
        'Q11424' => ['type_id' => 'thing', 'subtype' => 'film'],
        'Q482994' => ['type_id' => 'thing', 'subtype' => 'album'],
        'Q7366' => ['type_id' => 'thing', 'subtype' => 'track'],
        'Q134556' => ['type_id' => 'thing', 'subtype' => 'track'],
    ];

    /** @var list<string> */
    private const DISAMBIGUATION = ['Q4167410', 'Q22808320'];

    private const MAX_DEPTH = 4;

    public function __construct(
        private readonly WikimediaService $wikimediaService
    ) {}

    /**
     * @param  array<string, mixed>|null  $entity
     * @return array{status: string, type_id?: string, subtype?: ?string, via?: string}
     */
    public function resolve(?array $entity): array
    {
        if (! is_array($entity)) {
            return ['status' => 'unknown'];
        }

        $instanceOf = $this->claimIds($entity, 'P31');
        if ($instanceOf === []) {
            return ['status' => 'unknown'];
        }

        $found = [];
        $sawDisambiguation = false;
        foreach ($instanceOf as $id) {
            if (in_array($id, self::DISAMBIGUATION, true)) {
                $sawDisambiguation = true;
                continue;
            }

            $match = $this->matchClass($id, 0, []);
            if ($match === null) {
                continue;
            }

            if ($match['type_id'] === 'person') {
                return [
                    'status' => 'matched',
                    'type_id' => 'person',
                    'subtype' => 'public_figure',
                    'via' => $match['via'],
                ];
            }

            $found[$match['type_id'].'|'.($match['subtype'] ?? '')] = $match;
        }

        if (count($found) === 1) {
            $match = array_values($found)[0];

            return [
                'status' => 'matched',
                'type_id' => $match['type_id'],
                'subtype' => $match['subtype'],
                'via' => $match['via'],
            ];
        }

        if (count($found) > 1) {
            return ['status' => 'ambiguous'];
        }

        if ($sawDisambiguation) {
            return ['status' => 'disambiguation'];
        }

        return ['status' => 'unknown'];
    }

    /**
     * @param  array<string, true>  $seen
     * @return array{type_id: string, subtype: ?string, via: string}|null
     */
    private function matchClass(string $id, int $depth, array $seen): ?array
    {
        if (isset(self::DIRECT[$id])) {
            return self::DIRECT[$id] + ['via' => $id];
        }

        if ($depth >= self::MAX_DEPTH || isset($seen[$id])) {
            return null;
        }

        $seen[$id] = true;
        $class = $this->wikimediaService->getWikidataEntity($id);
        if (! is_array($class)) {
            return null;
        }

        foreach ($this->claimIds($class, 'P279') as $parentId) {
            $match = $this->matchClass($parentId, $depth + 1, $seen);
            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    /**
     * Preferred claims win. Deprecated claims are ignored.
     *
     * @param  array<string, mixed>  $entity
     * @return list<string>
     */
    private function claimIds(array $entity, string $property): array
    {
        $claims = $entity['claims'][$property] ?? [];
        if (! is_array($claims)) {
            return [];
        }

        $active = array_values(array_filter(
            $claims,
            fn ($claim) => is_array($claim) && ($claim['rank'] ?? 'normal') !== 'deprecated'
        ));
        $preferred = array_values(array_filter(
            $active,
            fn ($claim) => ($claim['rank'] ?? '') === 'preferred'
        ));
        $ranked = $preferred !== [] ? $preferred : $active;

        $ids = [];
        foreach ($ranked as $claim) {
            $id = $claim['mainsnak']['datavalue']['value']['id'] ?? null;
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
