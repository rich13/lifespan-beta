<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Connection types to include or exclude in biography (Life in sentences)
    |--------------------------------------------------------------------------
    |
    | connection_types_include: If set, only these connection type_ids appear.
    |   null = no restriction (all types allowed, subject to exclude rules).
    |
    | connection_types_exclude: These connection type_ids are never included.
    |
    | exclude_connection_rules: Finer rules. A connection is excluded if it
    |   matches any rule. Each rule is [ connection_type_id, object_type_id?, object_subtype? ].
    |   - connection_type_id: required (e.g. 'created').
    |   - object_type_id: optional; if set, the connection's object (child) must have this type_id.
    |   - object_subtype: optional; if set, the object's metadata->subtype must match (for things).
    |   Omit object_* to exclude all connections of that type.
    |
    */
    'connection_types_include' => null,

    'connection_types_exclude' => [],

    'exclude_connection_rules' => [
        ['connection_type_id' => 'created', 'object_type_id' => 'thing', 'object_subtype' => 'photo'],
        ['connection_type_id' => 'created', 'object_type_id' => 'note'],
    ],
];
