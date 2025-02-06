# Lifespan Beta Development Blog

## Day 1: Setting Up the Foundation

### Initial Setup
- Created new Laravel project in `/beta`
- Set up PostgreSQL database `lifespan_beta`
- Configured environment for development
- Decided to use PHP's built-in server on port 8888

### Database Design Lessons
- Started with migrations but hit several important lessons:
  1. Order matters critically with foreign keys
  2. Self-referential tables need special handling
  3. UUID fields need to be consistent across related tables
  4. Circular dependencies need careful planning

### Migration Challenges Solved
- **Problem 1**: Migrations running in wrong order
  - Solution: Used timestamps to enforce correct sequence
  - Learned: Laravel runs migrations alphabetically within same timestamp

- **Problem 2**: Circular references in spans table
  - Issue: Table needed to reference itself for parent/child relationships
  - Solution: Split foreign key creation into two steps
  - Created table first, then added self-referential constraints

- **Problem 3**: Complex dependency chain
  ```
  users
    ↓
  span_types
    ↓
  spans ← (self-referential)
    ↓
  relationship_types
    ↓
  relationships
    ↓
  user_spans
  ```

### Database Structure Highlights
- Using UUIDs for all IDs (better for distributed systems)
- Proper foreign key constraints throughout
- Rich metadata support with JSONB
- Careful indexing strategy
- Temporal data with precision levels
- Flexible relationship system

### Key Technical Decisions
1. **UUID Usage**
   - All tables using UUID primary keys
   - Consistent across relationships
   - Better for future scaling

2. **PostgreSQL Features**
   - Using JSONB for flexible metadata
   - Using proper constraints
   - Leveraging index types

3. **Date Handling**
   - Separate year/month/day fields
   - Precision levels for dates
   - Nullable end dates for ongoing spans

4. **Relationship Design**
   - Type-based relationships
   - Self-referential span structure
   - Support for temporal relationships

### Logging Strategy
- Using Laravel's built-in logging system with multiple channels:
  ```
  stack   → For production (combines daily and slack)
  daily   → Rotating daily files
  single  → Single file for development
  stderr  → For running in containers
  slack   → For critical notifications
  ```

- **Log Levels** (in order of severity):
  1. `emergency`: System is unusable
  2. `alert`: Action must be taken immediately
  3. `critical`: Critical conditions
  4. `error`: Error conditions
  5. `warning`: Warning conditions
  6. `notice`: Normal but significant events
  7. `info`: Interesting events
  8. `debug`: Detailed debug information

- **What We're Logging**:
  1. Authentication events
  2. Span creation/modification
  3. Relationship changes
  4. Access control decisions
  5. Performance metrics
  6. Database query issues
  7. API usage

- **Custom Channels**:
  - `spans`: Track span-related operations
  - `relationships`: Monitor relationship changes
  - `security`: Auth and access control
  - `performance`: Query and cache metrics

### Next Steps
- Create test data
- Implement basic span viewing
- Set up authentication
- Begin work on the editor

### Development Tips
1. Always plan database dependencies before creating migrations
2. Test migrations from scratch frequently
3. Use `migrate:fresh` during development
4. Document decisions and reasons
5. Think about future scaling
6. Consider data integrity from the start

### Questions to Consider
- How will we handle data migration from Alpha?
- What indexing strategies might need optimization?
- How to handle very large family trees?
- Performance implications of self-referential queries? 