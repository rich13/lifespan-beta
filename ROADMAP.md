# Beta Development Roadmap

Each phase delivers working, tested functionality that can be demoed. Features build on each other incrementally.

## Phase 1: Foundation - Users, Spans, and Access Control
Goal: "We can represent users and their data as spans with proper access control"

### Mini-Release 1.0: Lifespan Hello World (1 day)
- [x] Basic Laravel Breeze setup
- [x] Basic layout template
  - Header with navigation
  - Main content area
  - Sidebar structure
- [x] Simple span model (just id, name, type)
- [x] Single seeded example span
- [x] Basic `/spans/:id` route and view
- [x] Basic error handling setup
- [x] Basic test structure
- [x] Logging foundation
  - Multiple channels (security, performance, spans, relationships)
  - Telescope integration for debugging
  - Performance tracking
  - Security audit logging
Demo: "We can view a single span in our layout with full logging"

Success Criteria:
1. Running `php artisan serve` shows our app
2. Visiting `/spans/1` shows our example span
3. Basic tests pass
4. Layout matches our design direction
5. Error pages work (404, etc.)

### Mini-Release 1.A: Basic Span Viewing (1-2 days)
- [x] Basic Laravel Breeze setup
- [x] Database migrations for spans and types
- [ ] Span model with essential fields
- [ ] Simple homepage with span listing
- [ ] Basic span detail view
Demo: "We can view a list of spans and their details"

### Mini-Release 1.B: Person Spans (2-3 days)
- [ ] Person span type implementation
- [ ] Person-specific fields and validation
- [ ] Basic person span viewing
- [ ] Person span creation form
Demo: "We can create and view person spans"

### Mini-Release 1.C: Core Editor (2-3 days)
- [ ] Base editor component
- [ ] Common fields editor
- [ ] Create/Update operations
- [ ] Basic validation
Demo: "We can edit basic span details"

### Mini-Release 1.D: Type System (1-2 days)
- [ ] SpanType model and migrations
- [ ] Default types configuration
- [ ] Type-specific validation
- [ ] Type selection in editor
Demo: "We can manage different types of spans"

### Mini-Release 1.E: User Integration (2-3 days)
- [ ] User model with admin flag
- [ ] Personal span creation on registration
- [ ] Profile view backed by personal span
- [ ] Basic user management
Demo: "Users exist as spans in the system"

### Mini-Release 1.F: Basic Access Control (2-3 days)
- [ ] User-span ownership
- [ ] Public/private toggle
- [ ] Basic permission checks
- [ ] Visibility filtering
Demo: "Basic access control is working"

### Mini-Release 1.G: Family Foundations (2-3 days)
- [ ] Parent/child relationship types
- [ ] Basic family relationship creation
- [ ] Family relationship validation
- [ ] Simple family view
Demo: "We can create basic family connections"

### Mini-Release 1.H: Admin Essentials (2-3 days)
- [ ] Admin dashboard
- [ ] User management
- [ ] SpanType management
- [ ] Basic admin operations
Demo: "Admins can manage the system"

### Mini-Release 1.I: API Foundations (2-3 days)
- [ ] Basic API authentication
- [ ] Core CRUD endpoints
- [ ] API documentation setup
- [ ] Basic API tests
Demo: "Core functionality is available via API"

### 1.1 Core Views & Basic Spans (Current)
- [x] Basic Laravel Breeze setup
- [x] Database migrations for spans and types
- [ ] Span model with essential fields (name, type, dates)
- [ ] SpanType model and default types
- [ ] Homepage with span listing
  - Basic filtering and sorting
  - Public/owned span visibility
- [ ] Individual span view (/spans/:span)
  - Basic span details
  - Type-specific layout structure
- [ ] Core editor system (/editor/:span)
  - Base editor component
  - Common fields for all spans
  - Type-specific editor sections
  - Create/Update/Delete operations
- [ ] Basic API structure and authentication
- [ ] Tests for core functionality
- [ ] Person span type implementation
  - Core person fields
  - Birth/death dates
  - Gender and other basic attributes
Demo: "We have a working span system with viewing and editing"

### 1.2 Family Relationships
- [ ] Family relationship types (parent/child)
- [ ] Person span editor family section
  - Add/edit parents
  - View/manage children
  - Prevent circular relationships
- [ ] Family tree visualization
  - Ancestors view
  - Descendants view
  - Siblings detection
- [ ] Family relationship API endpoints
- [ ] Tests for family relationships
Demo: "We can build and visualize family trees"

### 1.3 User-Span Integration
- [ ] User model with admin flag
- [ ] Automatic personal span creation on registration
- [ ] User profile backed by personal span
- [ ] Login/registration connected to spans
- [ ] API endpoints for user management
- [ ] Tests for user-span integration
- [ ] Personal span editor extensions
- [ ] Profile editing via span editor
- [ ] Link user's personal span to family relationships
- [ ] Family privacy controls
Demo: "Users exist as spans in the system"

### 1.4 Access Control
- [x] HasSpanAccess trait
- [ ] User-span ownership on creation
- [ ] Public/private toggle for spans
- [ ] Personal span privacy rules
- [ ] Filter span list by accessibility
- [ ] API authentication and authorization
- [ ] Tests for access rules
- [ ] Editor permission checks
- [ ] View permission checks
- [ ] Family relationship visibility rules
- [ ] Inherited access through family connections
Demo: "Users can control who sees their spans, with personal spans having special handling"

### 1.5 Essential Admin Features
- [ ] Admin dashboard layout
- [ ] User management (list, edit, disable)
- [ ] SpanType management (CRUD)
- [ ] RelationshipType management (CRUD)
- [ ] Bulk span operations (access levels, ownership)
- [ ] Admin activity logging
- [ ] Admin API endpoints
- [ ] Tests for admin features
- [ ] Admin-specific editor features
- [ ] Bulk edit interface
- [ ] Family relationship management tools
- [ ] Bulk family tree operations
Demo: "Admins can manage system configuration and user data"

### Future Logging Enhancements
These will be implemented as we build each feature:

1. **Authentication Logging** (Mini-Release 1.E)
   - Login attempts (successful and failed)
   - Password resets
   - Account creation
   - Permission changes

2. **Relationship Logging** (Mini-Release 1.G)
   - Relationship creation/modification
   - Family tree updates
   - Circular reference prevention
   - Validation failures

3. **Bulk Operation Logging** (Mini-Release 1.H)
   - Start/end of bulk operations
   - Progress tracking
   - Error summaries
   - Performance metrics

4. **API Access Logging** (Mini-Release 1.I)
   - Rate limiting
   - Authentication
   - Endpoint usage metrics
   - Error rates

5. **Cache Operation Logging** (Phase 2)
   - Cache hits/misses
   - Invalidation events
   - Storage metrics
   - Performance impact

6. **Query Performance Logging** (Phase 2)
   - Slow query detection
   - Index usage
   - Query patterns
   - Optimization opportunities

Each of these will include:
- Appropriate log levels
- Context data
- Performance metrics
- Security implications
- Telescope integration

### Phase 1 Demo Capabilities

As a regular user, I can:
- Register an account and get my personal span automatically
- Log in and see my profile (backed by my personal span)
- Create new spans with basic details (name, type, dates)
- Set spans as public or private
- See a list of my spans and other public spans
- View basic span details and relationships
- Access these features via both web UI and API
- View any accessible span's details page
- Edit my own spans using the appropriate editor
- See different editor interfaces based on span type
- Create new spans of any available type
- Add and edit my family relationships
- View my family tree (ancestors and descendants)
- Control who can see my family connections
- Navigate through connected family members

As an admin user, I can:
- See all spans in the system
- Manage users (create, edit, disable accounts)
- Create and edit span types
- Create and edit relationship types
- Perform bulk operations on spans
- View the admin activity log
- Access admin features via both web UI and API
- Access additional editor features for any span
- Edit system spans and type configurations
- Access bulk editing features
- Manage family relationships for any person
- Perform bulk operations on family trees
- Validate and fix family relationship issues

Example Demo Flow:
1. Admin setup:
   - Log in as admin
   - Create some span types (person, event, place)
   - Set up relationship types (was at, participated in)
   - View activity log
   - Configure different span types with type-specific fields
   - Show how editor adapts to different types
   - Configure person span type with family fields
   - Set up family relationship types

2. User registration:
   - New user registers
   - Shows their personal span creation
   - They can edit their profile
   - Edit profile using personal span editor
   - Show type-specific profile fields
   - Add family members to personal span
   - Show family tree visualization

3. Span management:
   - User creates a private span
   - Creates a public span
   - Admin can see both
   - Another user can only see public span
   - User creates spans of different types
   - Shows how editor adapts to each type
   - Demonstrates viewing vs editing permissions
   - Shows public/private span visibility
   - Create spans for family members
   - Connect family relationships
   - Show inheritance of dates (birth order, generations)
   - Demonstrate family privacy controls
   - Navigate through family connections

4. Basic API usage:
   - Show authentication
   - List spans
   - Create a span
   - Update visibility
   - Fetch span details
   - Create/update spans via API
   - Show type-specific field handling
   - Fetch family relationships
   - Add family members
   - Query family trees

This demo proves:
- Core user management works
- Span system is functional
- Access control is working
- Admin features are operational
- API provides core functionality
- Core viewing system works
- Editor system handles different span types
- Type-specific features work
- Editing permissions are enforced
- Family relationships work correctly
- Family trees can be built and visualized
- Family privacy is properly controlled
- Core person features are working

## Phase 2: Core Features - Relationships & Time
Goal: "We can connect spans and represent their temporal nature"

### 2.1 Basic Relationships
- [x] Database structure
- [x] Relationship/RelationshipType models
- [ ] Add relationships on span creation/edit
- [ ] Show relationships on span view
- [ ] Relationship API endpoints
- [ ] Tests for relationship management
Demo: "Users can connect related spans"

### 2.2 Temporal Features
- [ ] Date input with precision levels
- [ ] Start/end date validation
- [ ] Basic timeline view of a span
- [ ] Timeline API endpoints
- [ ] Tests for date handling
Demo: "Spans accurately represent time periods"

### 2.3 Enhanced Relationships
- [ ] Relationship spans (temporal relationships)
- [ ] Relationship type validation
- [ ] Relationship visualization
- [ ] Advanced relationship API endpoints
- [ ] Tests for complex relationships
Demo: "Users can create rich temporal relationships"

### 2.4 Advanced Admin Tools
- [ ] Data import/export system
- [ ] Span merge/split tools
- [ ] Relationship bulk operations
- [ ] System health monitoring
- [ ] Admin API endpoints for bulk operations
- [ ] Tests for advanced admin features
Demo: "Admins can perform complex data operations"

## Phase 3: UI & Experience
Goal: "The application is intuitive and efficient to use"

### 3.1 Search & Navigation
- [ ] Span search component
- [ ] Type-based filtering
- [ ] Quick span picker
- [ ] Admin search capabilities
- [ ] Search API endpoints
- [ ] Tests for search functionality
Demo: "Users can quickly find and reference spans"

### 3.2 Rich UI Components
- [ ] Enhanced date input
- [ ] Relationship visualization
- [ ] Drag-and-drop relationship creation
- [ ] Admin bulk operation UI
- [ ] API documentation and playground
- [ ] Tests for UI interactions
Demo: "The interface is intuitive and efficient"

### 3.3 Timeline & Analysis
- [ ] Multi-span timeline view
- [ ] Timeline filtering
- [ ] Export/sharing options
- [ ] Admin analytics dashboard
- [ ] System usage statistics
- [ ] Analytics API endpoints
- [ ] Tests for timeline and analytics
Demo: "Users can visualize data and admins can monitor system usage"

## Phase 4: Temporal Discovery & Exploration
Goal: "Users can discover meaningful temporal connections and patterns"

### 4.1 Allen Temporal Relations
- [ ] Implement Allen's interval algebra
- [ ] Temporal relationship detection
  - Before/After relationships
  - During/Contains relationships
  - Overlaps/Meets relationships
- [ ] Temporal distance calculations
- [ ] Age-based comparisons
- [ ] Tests for temporal logic
Demo: "System can identify temporal relationships between spans"

### 4.2 Discovery Interface
- [ ] Natural language query interface
  - "What was X doing when..."
  - "Who else was at Y when..."
  - "Find events that happened while..."
- [ ] Temporal comparison views
  - Side-by-side timelines
  - Age-aligned comparisons
  - Period overlaps
- [ ] Discovery API endpoints
- [ ] Tests for discovery features
Demo: "Users can ask and answer temporal questions"

### 4.3 Temporal Patterns & Insights
- [ ] Pattern detection
  - Life stage comparisons
  - Generational patterns
  - Contemporary connections
- [ ] Automated insights
  - "On this day" suggestions
  - "Similar age" connections
  - "Shared experience" detection
- [ ] Pattern visualization
- [ ] Tests for pattern detection
Demo: "System can suggest interesting temporal connections"

### 4.4 Interactive Exploration
- [ ] Visual timeline exploration
  - Zoom and pan through time
  - Filter and highlight relationships
  - Follow temporal connections
- [ ] "What if" scenarios
  - Temporal alignment tools
  - Alternative timeline views
  - Relationship exploration
- [ ] Exploration API endpoints
- [ ] Tests for exploration features
Demo: "Users can explore and discover temporal relationships"

### Phase 4 Demo Capabilities

As a regular user, I can:
- Ask natural language questions about temporal relationships
- Compare life stages between different people
- Discover connections through time
- Explore "what if" scenarios
- Receive automated temporal insights
- Visualize temporal patterns
- Access discovery features via API

Example Demo Flow:
1. Personal Discovery:
   - "What was my grandmother doing at my age?"
   - "Who else in my family tree was alive in 1950?"
   - "Show me all events that happened while I was in college"

2. Historical Comparisons:
   - Compare life stages with historical figures
   - Find contemporary relationships
   - Explore generational patterns

3. Pattern Exploration:
   - Discover shared experiences
   - Identify temporal coincidences
   - Follow chains of relationships through time

4. Interactive Analysis:
   - Align different timelines
   - Explore "what if" scenarios
   - Visualize temporal patterns

This demo proves:
- Complex temporal queries work
- Discovery interface is intuitive
- Pattern detection is meaningful
- Exploration tools are engaging

## Core Tools & Dependencies

### Authentication & Authorization
- Laravel Breeze (basic auth scaffolding)
- Laravel Sanctum (API authentication)
- Spatie Permission (role management)
- Laravel Socialite (optional: social auth)

### Development & Testing
- Laravel Telescope (debugging/monitoring)
- Laravel Dusk (browser testing)
- PHPUnit (unit/feature testing)
- Factory/Faker (test data generation)
- Laravel Pint (code style)

### Frontend & UI
- Bootstrap 5 (base styling)
- Alpine.js (lightweight interactivity)
- Laravel Livewire (dynamic components)
- Laravel Blade Components
- Mix/Vite (asset compilation)

### Data & Search
- Laravel Scout + Meilisearch
- Laravel Model Settings
- Laravel Query Builder
- Laravel Excel (data import/export)

### Visualization & Temporal
- Carbon (PHP date handling)
- Luxon (JS date handling)
- D3.js (visualization library)
- Timeline.js (timeline visualization)
- Cytoscape.js (relationship graphs)

### Monitoring & Performance
- Laravel Horizon (queue monitoring)
- Laravel Telescope (debugging)
- Laravel Debug Bar
- Clockwork (performance monitoring)

## Development Guidelines
1. Each feature should be:
   - Independently testable
   - Demonstrable to users
   - Built on working foundations
   - Available via both web UI and API
   
2. Before starting each section:
   - Write acceptance tests
   - Create minimal UI mockups
   - Design API endpoints
   - Review similar features in Alpha

3. After completing each section:
   - Run full test suite
   - Document new features
   - Update API documentation
   - Update this roadmap

4. Admin Feature Guidelines:
   - Always include activity logging
   - Require confirmation for destructive actions
   - Provide bulk operation capabilities
   - Include clear error handling
   - Add admin-specific tests

5. API Development Guidelines:
   - Build API alongside web features
   - Use consistent response formats
   - Version from the start
   - Document with OpenAPI/Swagger
   - Include API-specific tests
   - Provide example requests/responses

6. Temporal Query Guidelines:
   - Focus on meaningful relationships
   - Make complex queries accessible
   - Provide clear visualizations
   - Enable serendipitous discovery
   - Balance precision with usability

7. Library Usage Guidelines:
   - Prefer established, well-maintained libraries
   - Evaluate license compatibility
   - Check Laravel compatibility
   - Consider performance impact
   - Plan for potential library updates
   - Document usage and configuration
   - Create abstractions where appropriate