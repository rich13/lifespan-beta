<?php

return [
    'person' => [
        'story_template' => '{birth}{age}{current_role}{band_memberships}{residence}{longest_residence}{education}{education_phases}{work}{most_recent_job}{relationships}{current_relationship}{parents}{children}{siblings}',
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
            'age' => [
                'template' => '{subject} {is_verb} {age} years old.',
                'deceased_template' => '{subject} lived to the age of {age}.',
                'data_methods' => [
                    'subject' => 'getPronoun',
                    'is_verb' => 'getIsVerb',
                    'age' => 'getAge',
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
            'education_phases' => [
                'template' => '{education_phases_sentence}',
                'data_methods' => [
                    'education_phases_sentence' => 'getEducationPhasesSentence',
                ],
                'condition' => 'hasEducationPhases',
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
                    'have_verb' => 'getHaveVerb',
                    'album_count' => 'getAlbumCount',
                    'latest_album' => 'getLatestAlbum',
                    'album' => 'getFirstAlbum',
                ],
                'condition' => 'hasDiscography',
            ],
        ],
    ],
    'thing_album' => [
        'story_template' => '{release_date}{creator}{tracks}',
        'sentences' => [
            'release_date' => [
                'template' => '{name} was released in {release_date}.',
                'data_methods' => [
                    'name' => 'getName',
                    'release_date' => 'getHumanReadableReleaseDate',
                ],
                'condition' => 'hasStartYear',
            ],
            'creator' => [
                'template' => 'It was created by {creator}.',
                'single_template' => 'It was created by {creator}.',
                'data_methods' => [
                    'creator' => 'getCreator',
                ],
                'condition' => 'hasCreator',
            ],
            'tracks' => [
                'template' => 'It contains {track_count} tracks.',
                'single_template' => 'It contains one track.',
                'empty_template' => 'It has no tracks.',
                'data_methods' => [
                    'track_count' => 'getTrackCount',
                ],
                'condition' => 'hasTracks',
            ],
        ],
    ],
    'thing_track' => [
        'story_template' => '{track_artist_sentence}{track_release_date_sentence}{track_album_sentence}',
        'sentences' => [
            'track_artist_sentence' => [
                'template' => '{name} is a track by {track_artist}.',
                'data_methods' => [
                    'name' => 'getName',
                    'track_artist' => 'getTrackArtist',
                ],
                'condition' => 'hasTrackArtist',
            ],
            'track_release_date_sentence' => [
                'template' => 'It was released on {track_release_date}.',
                'data_methods' => [
                    'track_release_date' => 'getTrackReleaseDate',
                ],
                'condition' => 'hasTrackReleaseDate',
            ],
            'track_album_sentence' => [
                'template' => 'It appears on {track_album}.',
                'data_methods' => [
                    'track_album' => 'getTrackAlbum',
                ],
                'condition' => 'hasTrackAlbum',
            ],
        ],
    ],
    'thing_photo' => [
        'story_template' => '{photo_subject}{photo_date}{photo_age}',
        'sentences' => [
            'photo_subject' => [
                'template' => 'This is a photo of {featured_span}.',
                'data_methods' => [
                    'featured_span' => 'getFeaturedSpanName',
                ],
                'condition' => 'hasFeaturedSpan',
            ],
            'photo_date' => [
                'template' => 'It was taken {date_preposition} {photo_date}.',
                'data_methods' => [
                    'date_preposition' => 'getPhotoDatePreposition',
                    'photo_date' => 'getPhotoDate',
                ],
                'condition' => 'hasPhotoDate',
            ],
            'photo_age' => [
                'template' => 'At the time, {age}.',
                'data_methods' => [
                    'age' => 'getFeaturedSpanAgeAtPhotoDate',
                ],
                'condition' => 'hasFeaturedSpanAgeAtPhotoDate',
            ],
        ],
    ],
    'thing_plaque' => [
        'story_template' => '{plaque_features}{plaque_location}',
        'sentences' => [
            'plaque_features' => [
                'template' => 'This plaque features {featured_span}.',
                'data_methods' => [
                    'featured_span' => 'getPlaqueFeatures',
                ],
                'condition' => 'hasPlaqueFeatures',
            ],
            'plaque_location' => [
                'template' => 'It\'s located at {location}.',
                'data_methods' => [
                    'location' => 'getPlaqueLocation',
                ],
                'condition' => 'hasPlaqueLocation',
            ],
        ],
    ],
    'person_at_date' => [
        'story_template' => '{dead_at_date}{not_yet_born_at_date}{age_at_date}{residence_at_date}{employment_at_date}{education_at_date}{education_phase_at_date}{relationship_at_date}',
        'sentences' => [
            'dead_at_date' => [
                'template' => 'On {date}, {name} had been dead for {years_dead}.',
                'data_methods' => [
                    'date' => 'getAtDateDisplay',
                    'name' => 'getName',
                    'years_dead' => 'getYearsDeadAtDate',
                ],
                'condition' => 'wasDeadAtDate',
            ],
            'not_yet_born_at_date' => [
                'template' => 'On {date}, {name} would not be born for another {years_until_birth}.',
                'data_methods' => [
                    'date' => 'getAtDateDisplay',
                    'name' => 'getName',
                    'years_until_birth' => 'getYearsUntilBirthAtDate',
                ],
                'condition' => 'notYetBornAtDate',
            ],
            'age_at_date' => [
                'template' => 'On {date}, {name} was {age} years old.',
                'data_methods' => [
                    'date' => 'getAtDateDisplay',
                    'name' => 'getName',
                    'age' => 'getAgeAtDate',
                ],
                'condition' => 'hasAgeAtDate',
            ],
            'residence_at_date' => [
                'template' => 'At this time, {subject} lived in {place}.',
                'data_methods' => [
                    'subject' => 'getPronoun',
                    'place' => 'getResidenceAtDate',
                ],
                'condition' => 'hasResidenceAtDate',
            ],
            'employment_at_date' => [
                'template' => '{subject} worked as {role} at {organisation}.',
                'fallback_template' => '{subject} worked as {role}.',
                'data_methods' => [
                    'subject' => 'getPronoun',
                    'role' => 'getEmploymentRoleAtDate',
                    'organisation' => 'getEmploymentOrganisationAtDate',
                ],
                'condition' => 'hasEmploymentAtDate',
            ],
            'education_at_date' => [
                'template' => '{subject} went to {institution}.',
                'data_methods' => [
                    'subject' => 'getPronoun',
                    'institution' => 'getEducationAtDate',
                ],
                'condition' => 'hasEducationAtDate',
            ],
            'education_phase_at_date' => [
                'template' => 'At this time, {subject} was in {education_phase}.',
                'data_methods' => [
                    'subject' => 'getPronoun',
                    'education_phase' => 'getEducationPhaseAtDate',
                ],
                'condition' => 'hasEducationPhaseAtDate',
            ],
            'relationship_at_date' => [
                'template' => '{subject} was in a relationship with {person}.',
                'data_methods' => [
                    'subject' => 'getPronoun',
                    'person' => 'getRelationshipAtDate',
                ],
                'condition' => 'hasRelationshipAtDate',
            ],
        ],
    ],
    'role' => [
        'story_template' => '{role_type}{total_holders}{current_holders}',
        'sentences' => [
            'role_type' => [
                'template' => '{name} is a role.',
                'data_methods' => [
                    'name' => 'getName',
                ],
                'condition' => 'isRole',
            ],
            'total_holders' => [
                'template' => 'It\'s been held by a total of {total_count} people that we know of.',
                'single_template' => 'It\'s been held by one person that we know of.',
                'empty_template' => 'It hasn\'t been held by anyone that we know of.',
                'data_methods' => [
                    'total_count' => 'getTotalRoleHoldersCount',
                ],
                'condition' => 'hasTotalRoleHolders',
            ],
            'current_holders' => [
                'template' => 'It\'s currently held by {current_holders}.',
                'single_template' => 'It\'s currently held by {current_holder}.',
                'empty_template' => 'It\'s currently vacant.',
                'data_methods' => [
                    'current_holders' => 'getCurrentRoleHolders',
                    'current_holder' => 'getFirstCurrentRoleHolder',
                ],
                'condition' => 'hasCurrentRoleHolders',
            ],
        ],
    ],
]; 