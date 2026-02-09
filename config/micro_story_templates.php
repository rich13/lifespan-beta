<?php

/**
 * Micro-story template configuration.
 *
 * TEMPLATE LANGUAGE (edit sentences here without touching PHP):
 *
 * - Each sentence is a 'template' string with placeholders in curly braces: {placeholder}.
 * - 'data_methods' maps each placeholder to a provider name (implemented in MicroStoryService).
 *   The provider returns the value (often HTML with links) that replaces the placeholder.
 * - 'condition' selects when this template is used (e.g. hasStartYear, hasStartAndEndYear).
 *   The first template whose condition matches is used.
 *
 * AVAILABLE PLACEHOLDERS:
 *
 * For SPANS (person, place, thing, etc.):
 *   name, start_date, end_date  → createSpanLink, createDateLink
 *   occupation, creator         → getOccupation, createCreatorLink (when in data_methods)
 *
 * For CONNECTIONS (residence, employment, education, family, etc.):
 *   subject, object             → links to subject/object span
 *   predicate                   → link to connection type (forward_predicate)
 *   start_date, end_date        → links to connection span dates
 *   For 'during' only: phase, organisation  → phase name and linked organisation
 *
 * Adding a new placeholder requires implementing its provider in MicroStoryService.
 * Rephrasing or adding sentence variants is config-only: duplicate a template block,
 * change the 'template' string and/or 'condition'.
 */
return [
    'spans' => [
        'person' => [
            'templates' => [
                'with_dates' => [
                    'template' => '{name} lived between {start_date} and {end_date}.',
                    'ongoing_template' => '{name} was born {start_date}.',
                    'data_methods' => [
                        'name' => 'createSpanLink',
                        'start_date' => 'createDateLink',
                        'end_date' => 'createDateLink',
                    ],
                    'condition' => 'hasStartYear',
                ],
                'with_occupation' => [
                    'template' => '{name} worked as {occupation}.',
                    'data_methods' => [
                        'name' => 'createSpanLink',
                        'occupation' => 'getOccupation',
                    ],
                    'condition' => 'hasOccupation',
                ],
                'with_creator' => [
                    'template' => '{name} was created by {creator}.',
                    'data_methods' => [
                        'name' => 'createSpanLink',
                        'creator' => 'createSpanLink',
                    ],
                    'condition' => 'hasCreator',
                ],
            ],
        ],
        'thing' => [
            'templates' => [
                'with_dates' => [
                    'template' => '{name} was released between {start_date} and {end_date}.',
                    'ongoing_template' => '{name} was released {start_date}.',
                    'data_methods' => [
                        'name' => 'createSpanLink',
                        'start_date' => 'createDateLink',
                        'end_date' => 'createDateLink',
                    ],
                    'condition' => 'hasStartYear',
                ],
                'with_creator' => [
                    'template' => '{name} was created by {creator}.',
                    'data_methods' => [
                        'name' => 'createSpanLink',
                        'creator' => 'createSpanLink',
                    ],
                    'condition' => 'hasCreator',
                ],
            ],
        ],
        'place' => [
            'templates' => [
                'with_dates' => [
                    'template' => '{name} was founded between {start_date} and {end_date}.',
                    'ongoing_template' => '{name} was founded {start_date}.',
                    'data_methods' => [
                        'name' => 'createSpanLink',
                        'start_date' => 'createDateLink',
                        'end_date' => 'createDateLink',
                    ],
                    'condition' => 'hasStartYear',
                ],
            ],
        ],
        'organisation' => [
            'templates' => [
                'with_dates' => [
                    'template' => '{name} operated between {start_date} and {end_date}.',
                    'ongoing_template' => '{name} was founded {start_date}.',
                    'data_methods' => [
                        'name' => 'createSpanLink',
                        'start_date' => 'createDateLink',
                        'end_date' => 'createDateLink',
                    ],
                    'condition' => 'hasStartYear',
                ],
            ],
        ],
        'event' => [
            'templates' => [
                'with_dates' => [
                    'template' => '{name} occurred between {start_date} and {end_date}.',
                    'ongoing_template' => '{name} began {start_date}.',
                    'data_methods' => [
                        'name' => 'createSpanLink',
                        'start_date' => 'createDateLink',
                        'end_date' => 'createDateLink',
                    ],
                    'condition' => 'hasStartYear',
                ],
            ],
        ],
    ],
    'connections' => [
        'during' => [
            'templates' => [
                'with_start_and_end' => [
                    'template' => '{subject} was in {phase} at {organisation} between {start_date} and {end_date}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'phase' => 'createPhaseName',
                        'organisation' => 'createOrganisationFromDuring',
                        'start_date' => 'createDateLink',
                        'end_date' => 'createEndDateLink',
                    ],
                    'condition' => 'hasStartAndEndYear',
                ],
                'with_start_only' => [
                    'template' => '{subject} started {phase} at {organisation} on {start_date}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'phase' => 'createPhaseName',
                        'organisation' => 'createOrganisationFromDuring',
                        'start_date' => 'createDateLink',
                    ],
                    'condition' => 'hasStartYearOnly',
                ],
                'with_no_dates' => [
                    'template' => '{subject} was in {phase} at {organisation}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'phase' => 'createPhaseName',
                        'organisation' => 'createOrganisationFromDuring',
                    ],
                    'condition' => 'hasNoDates',
                ],
            ],
        ],
        'residence' => [
            'templates' => [
                'with_start_and_end' => [
                    'template' => '{subject} {predicate} {object} between {start_date} and {end_date}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                        'start_date' => 'createDateLink',
                        'end_date' => 'createEndDateLink',
                    ],
                    'condition' => 'hasStartAndEndYear',
                ],
                'with_start_only' => [
                    'template' => '{subject} {predicate} {object} from {start_date}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                        'start_date' => 'createDateLink',
                    ],
                    'condition' => 'hasStartYearOnly',
                ],
                'with_no_dates' => [
                    'template' => '{subject} {predicate} {object}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                    ],
                    'condition' => 'hasNoDates',
                ],
            ],
        ],
        'employment' => [
            'templates' => [
                'with_start_and_end' => [
                    'template' => '{subject} {predicate} {object} between {start_date} and {end_date}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                        'start_date' => 'createDateLink',
                        'end_date' => 'createEndDateLink',
                    ],
                    'condition' => 'hasStartAndEndYear',
                ],
                'with_start_only' => [
                    'template' => '{subject} {predicate} {object} from {start_date}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                        'start_date' => 'createDateLink',
                    ],
                    'condition' => 'hasStartYearOnly',
                ],
                'with_no_dates' => [
                    'template' => '{subject} {predicate} {object}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                    ],
                    'condition' => 'hasNoDates',
                ],
            ],
        ],
        'education' => [
            'templates' => [
                'with_start_and_end' => [
                    'template' => '{subject} {predicate} {object} between {start_date} and {end_date}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                        'start_date' => 'createDateLink',
                        'end_date' => 'createEndDateLink',
                    ],
                    'condition' => 'hasStartAndEndYear',
                ],
                'with_start_only' => [
                    'template' => '{subject} {predicate} {object} from {start_date}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                        'start_date' => 'createDateLink',
                    ],
                    'condition' => 'hasStartYearOnly',
                ],
                'with_no_dates' => [
                    'template' => '{subject} {predicate} {object}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                    ],
                    'condition' => 'hasNoDates',
                ],
            ],
        ],
        'family' => [
            'templates' => [
                'with_start_and_end' => [
                    'template' => '{subject} {predicate} {object} between {start_date} and {end_date}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                        'start_date' => 'createDateLink',
                        'end_date' => 'createEndDateLink',
                    ],
                    'condition' => 'hasStartAndEndYear',
                ],
                'with_start_only' => [
                    'template' => '{subject} {predicate} {object} since {start_date}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                        'start_date' => 'createDateLink',
                    ],
                    'condition' => 'hasStartYearOnly',
                ],
                'with_no_dates' => [
                    'template' => '{subject} {predicate} {object}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                    ],
                    'condition' => 'hasNoDates',
                ],
            ],
        ],
        'membership' => [
            'templates' => [
                'with_start_and_end' => [
                    'template' => '{subject} was {predicate} {object} between {start_date} and {end_date}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                        'start_date' => 'createDateLink',
                        'end_date' => 'createEndDateLink',
                    ],
                    'condition' => 'hasStartAndEndYear',
                ],
                'with_start_only' => [
                    'template' => '{subject} has been {predicate} {object} since {start_date}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                        'start_date' => 'createDateLink',
                    ],
                    'condition' => 'hasStartYearOnly',
                ],
                'with_no_dates' => [
                    'template' => '{subject} {predicate} {object}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                    ],
                    'condition' => 'hasNoDates',
                ],
            ],
        ],
        'created' => [
            'templates' => [
                'with_start_and_end' => [
                    'template' => '{subject} {predicate} {object} between {start_date} and {end_date}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                        'start_date' => 'createDateLink',
                        'end_date' => 'createEndDateLink',
                    ],
                    'condition' => 'hasStartAndEndYear',
                ],
                'with_start_only' => [
                    'template' => '{subject} {predicate} {object} on {start_date}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                        'start_date' => 'createDateLink',
                    ],
                    'condition' => 'hasStartYearOnly',
                ],
                'with_no_dates' => [
                    'template' => '{subject} {predicate} {object}.',
                    'data_methods' => [
                        'subject' => 'createSubjectLink',
                        'predicate' => 'createPredicateLink',
                        'object' => 'createObjectLink',
                    ],
                    'condition' => 'hasNoDates',
                ],
            ],
        ],
    ],
]; 