# Lifespan

Lifespan is a sophisticated temporal data management system that allows you to track and connect entities across time. It provides a flexible framework for modeling historical data, relationships, and temporal connections with precise date handling and rich metadata support.

## Core Concepts

### Spans

A span represents any entity that exists in time. The system supports several core types of spans:

- **Person**: Individuals with birth dates, death dates, and biographical information
- **Organisation**: Companies, institutions, and other organized groups
- **Event**: Specific occurrences or happenings
- **Place**: Physical locations or geographical entities
- **Period**: Defined periods of time
- **Connection**: Special spans that represent temporal relationships between other spans

Each span includes:
- Temporal data (start/end dates with configurable precision)
- Rich metadata specific to its type
- Access control settings
- Source documentation
- Connection tracking

### Connections

Connections represent relationships between spans. The system supports various types of connections, each with forward and inverse relationships:

- **Family**: Parent-child relationships
- **Education**: Student-institution relationships
- **Employment**: Employee-organization relationships
- **Membership**: Member-group relationships
- **Participation**: Participant-event relationships
- **Residence**: Person-place relationships
- **Travel**: Traveler-destination relationships
- **General Relationships**: Other relationships between people

Each connection can have its own temporal existence and metadata, allowing for precise tracking of when relationships began and ended.

## Key Features

### Temporal Precision

- Flexible date handling (year, month, or day precision)
- Support for ongoing spans (no end date)
- Placeholder spans for entities with unknown dates
- Validation of date patterns and combinations

### Rich Metadata

- Type-specific metadata schemas
- Customizable fields and validation
- Support for arrays and nested data
- Source documentation and notes

### Access Control

- Three access levels: private, shared, and public
- Granular user permissions
- Personal span management
- Administrative controls

### Connection Management

- Bidirectional relationships
- Temporal connection tracking
- Materialized view for efficient querying
- Automatic metadata synchronization

## Getting Started

1. **Database Setup**
   ```bash
   createdb -h localhost -U your_user lifespan
   ```

2. **Environment Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Dependencies**
   ```bash
   composer install
   npm install
   ```

4. **Database Migration**
   ```bash
   php artisan migrate
   ```

## Development Guidelines

1. **Database Changes**
   - Use migrations for schema changes
   - Follow established naming conventions
   - Maintain foreign key relationships
   - Consider indexing strategy

2. **Testing**
   - Write tests for new features
   - Use factories for test data
   - Follow existing test patterns
   - Ensure proper error handling

3. **UI/UX**
   - Use provided Blade components
   - Follow Bootstrap patterns
   - Maintain mobile responsiveness
   - Consider accessibility

## API Documentation

The system provides a comprehensive API for:
- Span management (CRUD operations)
- Connection management
- Metadata handling
- Access control
- Search and filtering

Detailed API documentation is available at `/docs/api` when running in development mode.

## Contributing

When contributing to Lifespan:
1. Follow the established coding standards
2. Add tests for new functionality
3. Update documentation as needed
4. Use type-specific importers for data migration
