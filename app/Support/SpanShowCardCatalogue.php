<?php

namespace App\Support;

use App\Models\Span;
use Illuminate\Support\Facades\Auth;

/**
 * Track which span-show cards share request facts, and which still query.
 * "This span" is eligibility on the real page; "Lab" is whether the experimental
 * page includes the card when that span type can show it.
 */
final class SpanShowCardCatalogue
{
    /**
     * @return list<array{
     *     id: string,
     *     name: string,
     *     status: 'shared'|'leftover'|'deferred'|'todo',
     *     span_types: list<string>,
     *     except_types?: list<string>,
     *     only_subtypes?: list<string>,
     *     except_subtypes?: list<string>,
     *     requires?: string,
     *     in_lab: bool,
     *     notes: string
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'id' => 'story',
                'name' => 'Story',
                'status' => 'shared',
                'span_types' => ['*'],
                'in_lab' => true,
                'notes' => 'Connections dump and family payload',
            ],
            [
                'id' => 'timeline',
                'name' => 'Timeline',
                'status' => 'shared',
                'span_types' => ['*'],
                'in_lab' => true,
                'notes' => 'This span from dump seed; You seed reused when present; other people still batch-timeline',
            ],
            [
                'id' => 'family',
                'name' => 'Family',
                'status' => 'shared',
                'span_types' => ['person'],
                'in_lab' => true,
                'notes' => 'Family-tree payload shared with story',
            ],
            [
                'id' => 'education',
                'name' => 'Education',
                'status' => 'shared',
                'span_types' => ['person'],
                'in_lab' => true,
                'notes' => 'Dump plus shared during/phase rows',
            ],
            [
                'id' => 'musician-discography',
                'name' => 'Musician discography',
                'status' => 'shared',
                'span_types' => ['person'],
                'requires' => 'musician_role',
                'in_lab' => true,
                'notes' => 'Created albums sliced from dump; cover art still deferred',
            ],
            [
                'id' => 'band-discography',
                'name' => 'Band discography',
                'status' => 'shared',
                'span_types' => ['band'],
                'in_lab' => true,
                'notes' => 'Created albums sliced from dump; cover art still deferred',
            ],
            [
                'id' => 'aka',
                'name' => 'AKA',
                'status' => 'shared',
                'span_types' => ['*'],
                'in_lab' => false,
                'notes' => 'has_name slice from dump',
            ],
            [
                'id' => 'works',
                'name' => 'Works',
                'status' => 'shared',
                'span_types' => ['person'],
                'in_lab' => false,
                'notes' => 'created things sliced from dump',
            ],
            [
                'id' => 'film',
                'name' => 'Films',
                'status' => 'shared',
                'span_types' => ['person'],
                'in_lab' => false,
                'notes' => 'features slice plus batched directors',
            ],
            [
                'id' => 'notes',
                'name' => 'Notes',
                'status' => 'shared',
                'span_types' => ['*'],
                'in_lab' => false,
                'notes' => 'annotatingNotes from the controller',
            ],
            [
                'id' => 'connections',
                'name' => 'Connections list',
                'status' => 'shared',
                'span_types' => ['*'],
                'in_lab' => false,
                'notes' => 'Same dump as the rest of the page',
            ],
            [
                'id' => 'blue-plaque',
                'name' => 'Blue plaque',
                'status' => 'shared',
                'span_types' => ['*'],
                'in_lab' => false,
                'notes' => 'bluePlaqueCardData from the controller',
            ],
            [
                'id' => 'unified-location',
                'name' => 'Location map',
                'status' => 'shared',
                'span_types' => ['*'],
                'in_lab' => false,
                'notes' => 'located/residence slice from dump',
            ],
            [
                'id' => 'employment',
                'name' => 'Employment',
                'status' => 'shared',
                'span_types' => ['person'],
                'in_lab' => true,
                'notes' => 'Jobs/roles plus nested at_organisation from the dump',
            ],
            [
                'id' => 'places-lived',
                'name' => 'Places lived',
                'status' => 'shared',
                'span_types' => ['person'],
                'in_lab' => true,
                'notes' => 'Residences plus place coordinates from the dump',
            ],
            [
                'id' => 'image-gallery',
                'name' => 'Image gallery',
                'status' => 'shared',
                'span_types' => ['*'],
                'in_lab' => true,
                'notes' => 'Photos of this span sliced from dump; related photos on a photo page still query',
            ],
            [
                'id' => 'compare',
                'name' => 'Comparison',
                'status' => 'shared',
                'span_types' => ['person'],
                'requires' => 'auth_not_self',
                'in_lab' => true,
                'notes' => 'This span from timeline seed; viewer personal span seeded in HTML',
            ],
            [
                'id' => 'desert-island-discs',
                'name' => 'Desert Island Discs',
                'status' => 'shared',
                'span_types' => ['person'],
                'requires' => 'desert_island_discs_set',
                'in_lab' => true,
                'notes' => 'Set sliced from dump; track albums and artists hydrated once',
            ],
            [
                'id' => 'employee',
                'name' => 'Employees',
                'status' => 'shared',
                'span_types' => ['organisation'],
                'in_lab' => true,
                'notes' => 'Inverse employment/roles sliced from dump; photos one batched lookup',
            ],
            [
                'id' => 'student',
                'name' => 'Students',
                'status' => 'shared',
                'span_types' => ['organisation'],
                'in_lab' => true,
                'notes' => 'Inverse education sliced from dump; photos one batched lookup',
            ],
            [
                'id' => 'lived-here',
                'name' => 'Lived here',
                'status' => 'shared',
                'span_types' => ['place'],
                'in_lab' => true,
                'notes' => 'Inverse residence/located sliced from dump; photos one batched lookup',
            ],
            [
                'id' => 'collections',
                'name' => 'Collections',
                'status' => 'shared',
                'span_types' => ['*'],
                'in_lab' => true,
                'notes' => 'Inverse contains sliced from dump; public collections only',
            ],
            [
                'id' => 'album-tracks',
                'name' => 'Album tracks',
                'status' => 'shared',
                'span_types' => ['thing'],
                'only_subtypes' => ['album'],
                'in_lab' => true,
                'notes' => 'Contains tracks sliced from dump; DID sets one batched lookup',
            ],
            [
                'id' => 'programme-episodes',
                'name' => 'Programme episodes',
                'status' => 'shared',
                'span_types' => ['thing'],
                'only_subtypes' => ['programme'],
                'in_lab' => true,
                'notes' => 'Contains episodes sliced from dump',
            ],
            [
                'id' => 'related-films',
                'name' => 'Related films',
                'status' => 'shared',
                'span_types' => ['thing'],
                'only_subtypes' => ['film'],
                'in_lab' => true,
                'notes' => 'Cast/director sliced from dump; overlapping films one batched lookup with directors eager-loaded',
            ],
            [
                'id' => 'plaque-featured',
                'name' => 'Plaque featured subject',
                'status' => 'shared',
                'span_types' => ['thing'],
                'only_subtypes' => ['plaque'],
                'in_lab' => true,
                'notes' => 'Features slice from dump; photo one batched lookup; story is the other span',
            ],
            [
                'id' => 'related-connections',
                'name' => 'Related connections',
                'status' => 'shared',
                'span_types' => ['connection'],
                'in_lab' => true,
                'notes' => 'Sibling connections one batched lookup (not this span’s dump)',
            ],
            [
                'id' => 'temporal-relations',
                'name' => 'Temporal relations',
                'status' => 'shared',
                'span_types' => ['connection'],
                'in_lab' => true,
                'notes' => 'During phases sliced from dump; subject’s other connections one batched lookup',
            ],
            [
                'id' => 'description',
                'name' => 'Description',
                'status' => 'deferred',
                'span_types' => ['*'],
                'except_types' => ['connection'],
                'except_subtypes' => ['private_individual'],
                'in_lab' => false,
                'notes' => 'AJAX for Wikipedia text we do not already have',
            ],
            [
                'id' => 'user-connection',
                'name' => 'Your connection',
                'status' => 'deferred',
                'span_types' => ['*'],
                'except_types' => ['place'],
                'requires' => 'auth_not_self',
                'in_lab' => false,
                'notes' => 'AJAX for the viewer’s personal span, which is new data',
            ],
            [
                'id' => 'cover-art',
                'name' => 'Cover art',
                'status' => 'deferred',
                'span_types' => ['thing'],
                'only_subtypes' => ['album'],
                'in_lab' => false,
                'notes' => 'Deferred image fetch; not a connections-dump problem',
            ],
        ];
    }

    /**
     * @return list<array{
     *     id: string,
     *     name: string,
     *     status: string,
     *     sharing_label: string,
     *     on_this_span: bool,
     *     in_lab: bool,
     *     notes: string
     * }>
     */
    public static function inventory(Span $span, SpanShowContext $context): array
    {
        $rows = [];
        foreach (self::definitions() as $card) {
            $rows[] = [
                'id' => $card['id'],
                'name' => $card['name'],
                'status' => $card['status'],
                'sharing_label' => self::sharingLabel($card['status']),
                'on_this_span' => self::appearsOnSpan($card, $span, $context),
                'in_lab' => (bool) $card['in_lab'] && self::typeEligible($card, $span),
                'notes' => $card['notes'],
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private static function typeEligible(array $card, Span $span): bool
    {
        $types = $card['span_types'] ?? ['*'];
        if ($types !== ['*'] && ! in_array($span->type_id, $types, true)) {
            return false;
        }

        if (in_array($span->type_id, $card['except_types'] ?? [], true)) {
            return false;
        }

        $subtype = $span->metadata['subtype'] ?? $span->subtype ?? null;
        if (isset($card['only_subtypes']) && ! in_array($subtype, $card['only_subtypes'], true)) {
            return false;
        }
        if (in_array($subtype, $card['except_subtypes'] ?? [], true)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private static function appearsOnSpan(array $card, Span $span, SpanShowContext $context): bool
    {
        if (! self::typeEligible($card, $span)) {
            return false;
        }

        return match ($card['requires'] ?? null) {
            'musician_role' => $context->connections->hasRoleNamed('Musician'),
            'auth_not_self' => self::authenticatedViewerIsNotSpan($span),
            'desert_island_discs_set' => $context->connections->desertIslandDiscsSet() !== null,
            default => true,
        };
    }

    private static function authenticatedViewerIsNotSpan(Span $span): bool
    {
        $user = Auth::user();
        if (! $user || ! $user->personal_span_id) {
            return false;
        }

        return $user->personal_span_id !== $span->id;
    }

    private static function sharingLabel(string $status): string
    {
        return match ($status) {
            'shared' => 'shared',
            'leftover' => 'leftover queries',
            'deferred' => 'deferred (new data)',
            default => 'not yet',
        };
    }
}
