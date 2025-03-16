# Database Conventions

This document outlines the key conventions used in the database schema.

## Column Naming

### Primary Keys
- For tables with UUID primary keys, use `id` as the column name
- For tables with string primary keys that represent types or categories, use the type name as the column name (e.g., `type` in `connection_types` table)

### Foreign Keys
- For UUID foreign keys, use `{table_name}_id` (e.g., `parent_id`, `child_id`)
- For string foreign keys that reference type tables, use the type name (e.g., `type` in `connections` table references `type` in `connection_types` table)

### Type Tables
Tables that define types or categories (like `span_types` and `connection_types`) follow these conventions:
- Use the type name as the primary key (e.g., `type_id` in `span_types`, `type` in `connection_types`)
- Include a `name` column for display purposes
- Include a `description` column for documentation
- Include an `allowed_span_types` JSON column if the type has restrictions on which span types it can connect

## Examples

### Span Types Table
```sql
CREATE TABLE span_types (
    type_id VARCHAR PRIMARY KEY,  -- e.g., 'person', 'organisation'
    name VARCHAR NOT NULL,        -- e.g., 'Person', 'Organization'
    description TEXT,
    metadata JSONB DEFAULT '{}'
);
```

### Connection Types Table
```sql
CREATE TABLE connection_types (
    type VARCHAR PRIMARY KEY,     -- e.g., 'family', 'membership'
    name VARCHAR NOT NULL,        -- e.g., 'Family', 'Membership'
    description TEXT,
    allowed_span_types JSONB      -- e.g., {'parent': ['person'], 'child': ['person']}
);
```

### Connections Table
```sql
CREATE TABLE connections (
    id UUID PRIMARY KEY,
    parent_id UUID REFERENCES spans(id),
    child_id UUID REFERENCES spans(id),
    type VARCHAR REFERENCES connection_types(type),  -- Note: uses type, not type_id
    connection_span_id UUID REFERENCES spans(id)
);
```

## Important Notes
1. The `type` column in `connection_types` is the source of truth for connection types
2. The `type` column in `connections` references the `type` column in `connection_types`
3. This convention differs from the `type_id` convention used in `span_types` for historical reasons
4. When adding new connection types, ensure they are added to the `connection_types` table first
5. When querying connections by type, use the `type` column, not `type_id` 