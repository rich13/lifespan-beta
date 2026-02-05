<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches administrative hierarchy (P131 parent chain, P150 children) from Wikidata.
 * Used during geocoding (with limited depth) and optionally by CLI sync for full chain.
 * Uses a short timeout per request to avoid hanging.
 */
class WikidataPlaceHierarchyFetcher
{
    private const WIKIDATA_API = 'https://www.wikidata.org/w/api.php';
    private const TIMEOUT_SECONDS = 5;
    private const P131_LOCATED_IN = 'P131';
    private const P150_CONTAINS = 'P150';
    private const DEFAULT_MAX_PARENT_DEPTH = 10;

    /**
     * Fetch parent chain (P131) and child IDs (P150) for a place. Returns null on failure/timeout.
     *
     * @param string $wikidataId Q ID e.g. Q84
     * @param int $maxParentDepth Max parent levels to follow (1 = direct parent only; use during geocoding for speed)
     * @return array{parent_chain: array<array{id: string, label: string}>, child_ids: array<string>}|null
     */
    public function fetchHierarchy(string $wikidataId, int $maxParentDepth = self::DEFAULT_MAX_PARENT_DEPTH): ?array
    {
        if (!preg_match('/^Q\d+$/', $wikidataId)) {
            return null;
        }

        $parentChain = $this->fetchParentChain($wikidataId, $maxParentDepth);
        $childIds = $this->fetchChildIds($wikidataId);

        return [
            'parent_chain' => $parentChain,
            'child_ids' => $childIds,
        ];
    }

    /**
     * @return array<array{id: string, label: string}>
     */
    private function fetchParentChain(string $wikidataId, int $maxParentDepth = self::DEFAULT_MAX_PARENT_DEPTH): array
    {
        $chain = [];
        $currentId = $wikidataId;
        $depth = 0;

        while ($depth < $maxParentDepth) {
            $entity = $this->fetchEntity($currentId);
            if ($entity === null) {
                break;
            }

            $parentId = $this->extractFirstClaimValueId($entity, self::P131_LOCATED_IN);
            if ($parentId === null || $parentId === $currentId) {
                break;
            }

            $parentEntity = $this->fetchEntity($parentId);
            $label = $parentEntity !== null ? $this->getEntityLabel($parentEntity) : null;
            $chain[] = ['id' => $parentId, 'label' => $label ?? $parentId];
            $currentId = $parentId;
            $depth++;
        }

        return $chain;
    }

    /**
     * @return array<string>
     */
    private function fetchChildIds(string $wikidataId): array
    {
        $entity = $this->fetchEntity($wikidataId);
        if ($entity === null) {
            return [];
        }
        return $this->extractAllClaimValueIds($entity, self::P150_CONTAINS);
    }

    /**
     * Single entity fetch with short timeout. No cache — for background use only.
     */
    private function fetchEntity(string $entityId): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => config('app.user_agent', 'LifespanApp/1.0'),
            ])->timeout(self::TIMEOUT_SECONDS)->get(self::WIKIDATA_API, [
                'action' => 'wbgetentities',
                'format' => 'json',
                'ids' => $entityId,
                'languages' => 'en',
                'props' => 'labels|claims',
            ]);

            if (!$response->successful()) {
                Log::warning('Wikidata place hierarchy fetch failed', [
                    'entity_id' => $entityId,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $data = $response->json();
            $entities = $data['entities'] ?? [];
            $entity = $entities[$entityId] ?? null;

            if ($entity === null || isset($entity['missing'])) {
                return null;
            }

            $entity['id'] = $entityId;
            return $entity;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('Wikidata place hierarchy fetch timeout or connection error', [
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::warning('Wikidata place hierarchy fetch error', [
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function getEntityLabel(array $entity): ?string
    {
        $labels = $entity['labels']['en'] ?? null;
        if ($labels !== null && isset($labels['value'])) {
            return $labels['value'];
        }
        return null;
    }

    private function extractFirstClaimValueId(array $entity, string $propertyId): ?string
    {
        $claims = $entity['claims'][$propertyId] ?? [];
        $main = $claims[0] ?? null;
        if ($main === null) {
            return null;
        }
        return $this->getValueIdFromClaim($main);
    }

    /**
     * @return array<string>
     */
    private function extractAllClaimValueIds(array $entity, string $propertyId): array
    {
        $claims = $entity['claims'][$propertyId] ?? [];
        $ids = [];
        foreach ($claims as $claim) {
            $id = $this->getValueIdFromClaim($claim);
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    private function getValueIdFromClaim(array $claim): ?string
    {
        $snak = $claim['mainsnak'] ?? $claim;
        if (($snak['snaktype'] ?? '') !== 'value') {
            return null;
        }
        $value = $snak['datavalue']['value'] ?? null;
        if (!is_array($value) || empty($value['id'])) {
            return null;
        }
        return $value['id'];
    }
}
