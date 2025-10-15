<?php

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