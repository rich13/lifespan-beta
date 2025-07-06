<?php

return [
    'person' => [
        'story_template' => '{birth}{current_role}{band_memberships}{residence}{longest_residence}{education}{work}{most_recent_job}{relationships}{current_relationship}{longest_relationship}{parents}{children}{siblings}',
        'sentences' => [
            'birth' => [
                'template' => '{name} was {birth_preposition} {birth_date} in {birth_location}.',
                'fallback_template' => '{name} was {birth_preposition} {birth_date}.',
                'data_methods' => [
                    'name' => 'getName',
                    'birth_preposition' => 'getBirthPreposition',
                    'birth_date' => 'getHumanReadableBirthDate',
                    'birth_location' => 'getBirthLocation',
                ],
                'condition' => 'hasStartYear',
            ],
            'residence' => [
                'template' => 'During {possessive} life, {subject} {has_verb} lived in {places}.',
                'data_methods' => [
                    'possessive' => 'getPossessivePronoun',
                    'subject' => 'getPronoun',
                    'has_verb' => 'getHasVerb',
                    'places' => 'getResidencePlaces',
                ],
                'condition' => 'hasResidences',
            ],
            'longest_residence' => [
                'template' => '{possessive_cap} longest period of living in one place was in {place} ({duration}).',
                'data_methods' => [
                    'possessive_cap' => 'getPossessivePronounCapitalized',
                    'place' => 'getLongestResidencePlace',
                    'duration' => 'getLongestResidenceDuration',
                ],
                'condition' => 'hasResidences',
            ],
            'education' => [
                'template' => '{subject} studied at {institutions}.',
                'data_methods' => [
                    'subject' => 'getPronoun',
                    'institutions' => 'getEducationInstitutions',
                ],
                'condition' => 'hasEducation',
            ],
            'work' => [
                'template' => '{subject} {has_verb} worked for {organisations}.',
                'data_methods' => [
                    'subject' => 'getPronoun',
                    'has_verb' => 'getHasVerb',
                    'organisations' => 'getWorkOrganisations',
                ],
                'condition' => 'hasWork',
            ],
            'most_recent_job' => [
                'template' => '{possessive_cap} most recent job was at {organisation}.',
                'data_methods' => [
                    'possessive_cap' => 'getPossessivePronounCapitalized',
                    'organisation' => 'getMostRecentJobOrganisation',
                ],
                'condition' => 'hasWork',
            ],
            'band_memberships' => [
                'template' => '{subject} {object_is_verb} a member of {bands}.',
                'single_template' => '{subject} {object_is_verb} a member of {band}.',
                'data_methods' => [
                    'subject' => 'getPronoun',
                    'object_is_verb' => 'getObjectIsVerb',
                    'bands' => 'getBandMembershipNames',
                    'band' => 'getFirstBandMembershipName',
                ],
                'condition' => 'hasBandMemberships',
            ],
            'relationships' => [
                'template' => '{subject} {has_verb} had relationships with {count} people.',
                'single_template' => '{subject} {has_verb} had a relationship with {partner}.',
                'data_methods' => [
                    'subject' => 'getPronoun',
                    'has_verb' => 'getHasVerb',
                    'count' => 'getRelationshipCount',
                    'partner' => 'getFirstRelationshipPartner',
                ],
                'condition' => 'hasRelationships',
            ],
            'current_relationship' => [
                'template' => '{possessive_cap} current relationship {is_verb} with {partner}.',
                'data_methods' => [
                    'possessive_cap' => 'getPossessivePronounCapitalized',
                    'is_verb' => 'getIsVerb',
                    'partner' => 'getCurrentRelationshipPartner',
                ],
                'condition' => 'hasCurrentRelationship',
            ],
            'longest_relationship' => [
                'template' => '{possessive_cap} longest relationship was with {partner} ({duration}).',
                'data_methods' => [
                    'possessive_cap' => 'getPossessivePronounCapitalized',
                    'partner' => 'getLongestRelationshipPartner',
                    'duration' => 'getLongestRelationshipDuration',
                ],
                'condition' => 'hasRelationships',
            ],
            'parents' => [
                'template' => '{subject} {is_verb} the child of {parents}.',
                'data_methods' => [
                    'subject' => 'getPronoun',
                    'is_verb' => 'getIsVerb',
                    'parents' => 'getParentNames',
                ],
                'condition' => 'hasParents',
            ],
            'children' => [
                'template' => '{subject} {has_verb} {count} children, {child_names}.',
                'single_template' => '{subject} {has_verb} one child, {child_names}.',
                'data_methods' => [
                    'subject' => 'getPronoun',
                    'has_verb' => 'getHasVerb',
                    'count' => 'getChildCount',
                    'child_names' => 'getChildNames',
                ],
                'condition' => 'hasChildren',
            ],
            'siblings' => [
                'template' => '{subject} {has_verb} {count} siblings, {sibling_names}.',
                'single_template' => '{subject} {has_verb} one sibling, {sibling_names}.',
                'data_methods' => [
                    'subject' => 'getPronoun',
                    'has_verb' => 'getHasVerb',
                    'count' => 'getSiblingCount',
                    'sibling_names' => 'getSiblingNames',
                ],
                'condition' => 'hasSiblings',
            ],
            'has_role' => [
                'template' => '{subject} {object_is_verb} a {roles}.',
                'single_template' => '{subject} {object_is_verb} a {role}.',
                'data_methods' => [
                    'subject' => 'getPronoun',
                    'object_is_verb' => 'getObjectIsVerb',
                    'roles' => 'getRoleNames',
                    'role' => 'getFirstRoleName',
                ],
                'condition' => 'hasRoles',
            ],
            'current_role' => [
                'template' => '{subject} {is_verb} a {current_role}.',
                'data_methods' => [
                    'subject' => 'getPronoun',
                    'is_verb' => 'getIsVerb',
                    'current_role' => 'getCurrentRole',
                ],
                'condition' => 'hasCurrentRole',
            ],
        ],
    ],
    'band' => [
        'story_template' => '{formation}{member_names}{discography}',
        'sentences' => [
            'formation' => [
                'template' => '{name} was formed in {formation_date} in {formation_location}.',
                'fallback_template' => '{name} was formed in {formation_date}.',
                'data_methods' => [
                    'name' => 'getName',
                    'formation_date' => 'getHumanReadableFormationDate',
                    'formation_location' => 'getFormationLocation',
                ],
                'condition' => 'hasStartYear',
            ],
            'member_names' => [
                'template' => 'The members {tense_verb} {member_names}.',
                'data_methods' => [
                    'tense_verb' => 'getTenseVerb',
                    'member_names' => 'getBandMemberNames',
                ],
                'condition' => 'hasMembers',
            ],
            'discography' => [
                'template' => 'They {have_verb} released {album_count} albums, most recently {latest_album}.',
                'single_template' => 'They {have_verb} released one album: {album}.',
                'empty_template' => 'They {have_verb} not released any albums yet.',
                'data_methods' => [
                    'have_verb' => 'getHasVerb',
                    'album_count' => 'getAlbumCount',
                    'latest_album' => 'getLatestAlbum',
                    'album' => 'getFirstAlbum',
                ],
                'condition' => 'hasDiscography',
            ],
        ],
    ],
]; 