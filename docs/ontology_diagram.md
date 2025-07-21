# Lifespan System Ontology Diagram

## Core Entities

### Span Types (Primary Entities)
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                                SPAN TYPES                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│ • person      - A person or individual                                      │
│ • organisation - An organization or institution                             │
│ • place       - A physical location or place                               │
│ • event       - A historical or personal event                             │
│ • thing       - A human-made item that exists in time                      │
│ • band        - A musical group or ensemble                                │
│ • set         - A collection of spans and connections                      │
│ • connection  - A temporal connection between spans                        │
│ • role        - A role, position, or function                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Connection Types (Relationships)
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            CONNECTION TYPES                                │
├─────────────────────────────────────────────────────────────────────────────┤
│ Subject → Object (Forward Predicate / Inverse Predicate)                   │
├─────────────────────────────────────────────────────────────────────────────┤
│ person → person        family: related to / related to                     │
│ person → person        relationship: has relationship with / has rel...    │
│ person → person        friend: is friend of / is friend of                 │
│ person → organisation  employment: worked at / employed                    │
│ person → organisation  education: studied at / educated                    │
│ person → organisation  membership: member of / has member                  │
│ person → band          membership: member of / has member                  │
│ person → place         residence: lived in / was home to                   │
│ person → place         travel: traveled to / was visited by                │
│ person → thing         ownership: owned / was owned by                     │
│ person → event         participation: participated in / had participant    │
│ person → role          has_role: has role / held by                        │
│ person → set           has_set: has set / set of                          │
│ person → thing         created: created / created by                       │
│ organisation → event   participation: participated in / had participant    │
│ organisation → place   located: located in / location of                   │
│ place → place          located: located in / location of                   │
│ event → place          located: located in / location of                   │
│ thing → thing          contains: contains / contained in                   │
│ connection → organisation at_organisation: at / hosted role               │
│ connection → connection during: during / includes                          │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Database Schema

### Core Tables Structure
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                                SPANS TABLE                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│ Primary Key: id (UUID)                                                      │
│ Foreign Keys:                                                               │
│   • type_id → span_types.type_id                                            │
│   • owner_id → users.id                                                     │
│   • updater_id → users.id                                                   │
│   • parent_id → spans.id (self-referential)                                │
│   • root_id → spans.id (self-referential)                                  │
│                                                                             │
│ Temporal Fields:                                                            │
│   • start_year, start_month, start_day                                     │
│   • end_year, end_month, end_day                                           │
│   • start_precision, end_precision                                         │
│                                                                             │
│ Metadata:                                                                   │
│   • name, slug, description, notes                                         │
│   • metadata (JSONB), sources (JSONB)                                      │
│   • state, access_level, permissions                                       │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                             CONNECTIONS TABLE                               │
├─────────────────────────────────────────────────────────────────────────────┤
│ Primary Key: id (UUID)                                                      │
│ Foreign Keys:                                                               │
│   • parent_id → spans.id (Subject)                                         │
│   • child_id → spans.id (Object)                                           │
│   • type_id → connection_types.type                                        │
│   • connection_span_id → spans.id (Connection span)                        │
│                                                                             │
│ Constraints:                                                                │
│   • Unique: connection_span_id (1:1 with connection span)                  │
│   • Temporal constraints enforced via triggers                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Relationship Patterns

### 1. Subject-Object-Predicate (SPO) Model
```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   Subject   │───▶│ Connection  │───▶│   Object    │
│   (Span)    │    │   (Span)    │    │   (Span)    │
└─────────────┘    └─────────────┘    └─────────────┘
       │                   │                   │
       │                   │                   │
       │              ┌─────────────┐          │
       │              │Connection   │          │
       └──────────────│Type         │──────────┘
                      │(Predicate)  │
                      └─────────────┘
```

### 2. Connection Span Pattern
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           CONNECTION SPAN PATTERN                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Person A ──┐                                                              │
│             ├───▶ Connection Span (with temporal data) ◀───┐               │
│  Person B ──┘                                               │               │
│                                                             │               │
│  Connection Record:                                         │               │
│  • parent_id: Person A                                      │               │
│  • child_id: Person B                                       │               │
│  • type_id: "family"                                        │               │
│  • connection_span_id: Connection Span UUID                 │               │
│                                                             │               │
│  Connection Span:                                           │               │
│  • type_id: "connection"                                    │               │
│  • name: "Person A - Person B Family Connection"            │               │
│  • start_year: 1990, end_year: 2020                        │               │
│  • metadata: {relationship_type: "parent"}                 │               │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Hierarchical Structure

### Span Hierarchy
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            SPAN HIERARCHY                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Root Span (root_id)                                                       │
│  ├── Parent Span (parent_id)                                               │
│  │   ├── Child Span 1                                                      │
│  │   ├── Child Span 2                                                      │
│  │   └── Child Span 3                                                      │
│  └── Sibling Span                                                          │
│      ├── Grandchild Span 1                                                 │
│      └── Grandchild Span 2                                                 │
│                                                                             │
│  Examples:                                                                  │
│  • Event → Sub-event → Sub-sub-event                                       │
│  • Organisation → Department → Team                                         │
│  • Place → Country → City → Building                                        │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Access Control Model

### Permission Levels
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           ACCESS CONTROL MODEL                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Access Levels:                                                             │
│  • private: Only owner and admin                                           │
│  • shared: Owner, admin, and users with explicit permissions               │
│  • public: Visible to all users                                            │
│                                                                             │
│  Permission Modes:                                                          │
│  • own: User owns the span                                                 │
│  • inherited: Inherited from parent span                                   │
│  • explicit: Explicitly granted via user_spans table                       │
│                                                                             │
│  Connection Visibility:                                                     │
│  • Connections are only visible if both connected spans are accessible     │
│  • Access is determined by the most restrictive span's access level        │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Temporal Constraints

### Constraint Types
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           TEMPORAL CONSTRAINTS                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Single Constraint:                                                         │
│  • Only one connection of this type allowed between any two spans          │
│  • Example: family, employment (one job per person per organisation)       │
│                                                                             │
│  Non-overlapping Constraint:                                                │
│  • Multiple connections allowed but dates must not overlap                 │
│  • Example: residence, education, participation                            │
│                                                                             │
│  Precision Handling:                                                        │
│  • year, month, day precision levels                                       │
│  • Adjacent dates allowed (e.g., end of one = start of next)               │
│  • Precision mismatch handling configurable per constraint type            │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Special Patterns

### 1. Set Pattern
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              SET PATTERN                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Set Span (type: "set")                                                    │
│  ├── contains → Span 1                                                     │
│  ├── contains → Span 2                                                     │
│  ├── contains → Connection Span 1                                          │
│  └── contains → Connection Span 2                                          │
│                                                                             │
│  Examples:                                                                  │
│  • "My Favourite Albums" set contains album spans                         │
│  • "Desert Island Discs" set contains track spans                         │
│  • "Family Tree" set contains person spans and family connections          │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 2. Role Pattern
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              ROLE PATTERN                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Person ──▶ has_role ──▶ Role Span                                         │
│                                    │                                       │
│                                    └──▶ at_organisation ──▶ Organisation   │
│                                                                             │
│  Examples:                                                                  │
│  • Person → "Lead Singer" role → at "The Beatles" band                     │
│  • Person → "CEO" role → at "Apple Inc" organisation                       │
│  • Person → "Student" role → at "University of Oxford" organisation        │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3. Creation Pattern
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            CREATION PATTERN                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Creator (Person/Organisation) ──▶ created ──▶ Thing                       │
│                                                                             │
│  Examples:                                                                  │
│  • John Lennon → created → "Imagine" album                                 │
│  • Apple Inc → created → "iPhone" thing                                    │
│  • Shakespeare → created → "Hamlet" thing                                  │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Versioning System

### Version Tracking
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           VERSIONING SYSTEM                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Span Versions:                                                             │
│  • Complete snapshot of span state at point in time                       │
│  • Includes all fields: metadata, dates, relationships                     │
│  • Version number increments sequentially                                  │
│                                                                             │
│  Connection Versions:                                                       │
│  • Tracks changes to connection relationships                              │
│  • Links to span versions for temporal consistency                         │
│                                                                             │
│  Change Tracking:                                                           │
│  • Who made the change (changed_by)                                        │
│  • When the change was made (created_at)                                   │
│  • What changed (diff from previous version)                               │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Materialized Views

### Performance Optimizations
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        MATERIALIZED VIEWS                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  span_connections:                                                          │
│  • Pre-computed JSON aggregation of all connections per span               │
│  • Includes connection metadata and relationship direction                  │
│  • Refreshed via triggers on connection changes                            │
│                                                                             │
│  Benefits:                                                                  │
│  • Fast connection lookups without complex joins                           │
│  • Reduced query complexity for connection-heavy operations                │
│  • Consistent connection data structure across the application             │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Summary

The Lifespan system implements a sophisticated temporal knowledge graph with:

1. **Flexible Entity Types**: 9 core span types covering people, places, events, things, and relationships
2. **Rich Relationship Model**: 20+ connection types with bidirectional predicates and temporal constraints
3. **Temporal Awareness**: All entities and relationships can have temporal existence with configurable precision
4. **Access Control**: Multi-level permission system with inheritance and explicit grants
5. **Versioning**: Complete audit trail of all changes with temporal consistency
6. **Performance**: Materialized views and optimized queries for complex relationship traversal
7. **Extensibility**: JSON metadata schemas allow type-specific fields and validation

This ontology supports complex temporal queries like "Who lived in London during the 1960s?" or "What bands were active when The Beatles were together?" while maintaining data integrity and performance. 