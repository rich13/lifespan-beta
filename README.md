# Lifespan Beta

A Laravel-based rewrite of the Lifespan application, focusing on maintainability, testability, and clean architecture.

## Current Status

Beta is in early development, running alongside the Alpha version. It uses its own database (`lifespan_beta`) to avoid affecting the production system.

### Completed
- [x] Initial Laravel setup
- [x] Database schema migration to Laravel format
- [x] Separate Beta database setup

### In Progress
- [ ] Model creation for core entities
- [ ] Authentication system that works with both Alpha and Beta
- [ ] Initial Beta routes (/beta/*)

### Planned
- [ ] Homepage reimplementation
- [ ] Span viewing system
- [ ] Modern UI with Blade components
- [ ] Comprehensive test suite
- [ ] Data migration tools

## Architecture Decisions

### Database
- Using PostgreSQL with proper foreign keys and constraints
- JSON fields for flexible metadata
- Improved indexing for performance
- Clear separation from Alpha database

### Models (Planned)
```php
Span
├── UUID primary key
├── Belongs to SpanType
├── Has many Connections
└── Belongs to many Users

SpanType
├── String primary key
└── Has many Spans

Connection
├── UUID primary key
├── Belongs to Span (parent)
└── Belongs to Span (child)
```

### Authentication
- Shared authentication with Alpha (planned)
- Laravel Breeze for auth scaffolding
- Role-based access control

## Development Guidelines

1. **Database Changes**
   - All schema changes through Laravel migrations
   - Document complex queries in model methods
   - Use Eloquent connections where possible

2. **Testing**
   - Write tests for all new features
   - Follow existing test patterns
   - Use factories for test data

3. **UI/UX**
   - Use Blade components for consistency
   - Follow Bootstrap 5 patterns
   - Mobile-first approach

## Getting Started

1. **Setup Database**
   ```bash
   createdb -h localhost -U your_user lifespan_beta
   ```

2. **Environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Dependencies**
   ```bash
   composer install
   npm install
   ```

4. **Database**
   ```bash
   php artisan migrate
   ```

## Migration Plan

The transition from Alpha to Beta will be gradual:

1. **Phase 1 - Current**
   - Set up Beta infrastructure
   - Create core models and migrations
   - Implement basic auth

2. **Phase 2 - Development**
   - Build new features in Beta
   - Create comprehensive test suite
   - Document all architectural decisions

3. **Phase 3 - Migration**
   - Create data migration tools
   - Test with production data copies
   - Plan cutover strategy

4. **Phase 4 - Launch**
   - Final data migration
   - Switch to Beta
   - Archive Alpha

## Contributing

When working on Beta:
1. Update this README with new features/changes
2. Document architectural decisions
3. Keep Alpha working throughout development
4. Add tests for new functionality
