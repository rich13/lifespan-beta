<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create users table first
        // Note: User names are stored in their personal spans (spans table), not here.
        // Each user has a one-to-one relationship with a personal span (type='person')
        // through the personal_span_id column. The personal span's name field is used
        // as the user's display name throughout the application.
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // 2. Create span_types table (uses string primary key)
        Schema::create('span_types', function (Blueprint $table) {
            $table->string('type_id')->primary();
            $table->string('name');
            $table->string('description');
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();
        });

        // 2.5 Create temporal_constraints table
        Schema::create('temporal_constraints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('type'); // single, non_overlapping, etc.
            $table->json('configuration')->nullable(); // For future extensibility
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default constraints with fixed UUIDs
        $singleConstraintId = '018e4c08-0000-0000-0000-000000000001';
        $nonOverlappingConstraintId = '018e4c08-0000-0000-0000-000000000002';
        
        DB::table('temporal_constraints')->insert([
            [
                'id' => $singleConstraintId,
                'name' => 'Single Connection',
                'type' => 'single',
                'description' => 'Only one connection of this type is allowed between any two spans.',
                'configuration' => json_encode(['allow_precision_mismatch' => false]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $nonOverlappingConstraintId,
                'name' => 'Non-overlapping',
                'type' => 'non_overlapping',
                'description' => 'Multiple connections are allowed but their dates must not overlap.',
                'configuration' => json_encode([
                    'allow_precision_mismatch' => true,
                    'adjacent_dates_allowed' => true
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // 3. Create connection_types table (uses string primary key)
        Schema::create('connection_types', function (Blueprint $table) {
            $table->string('type')->primary();
            $table->string('forward_predicate');
            $table->string('forward_description');
            $table->string('inverse_predicate');
            $table->string('inverse_description');
            $table->json('allowed_span_types')->nullable();
            $table->string('constraint_type')->default('single'); // simple enum: 'single' or 'non_overlapping'
            $table->timestamps();
        });

        // 4. Modify users table to add our custom fields
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('personal_span_id')->nullable()->unique()->after('id');
            $table->boolean('is_admin')->default(false)->after('password');
            $table->softDeletes();
        });

        // 5. Create spans table
        Schema::create('spans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type_id');
            $table->boolean('is_personal_span')->default(false);
            
            // Hierarchical structure
            $table->uuid('parent_id')->nullable();
            $table->uuid('root_id')->nullable();
            
            // Dates
            $table->integer('start_year')->nullable();
            $table->integer('start_month')->nullable();
            $table->integer('start_day')->nullable();
            $table->integer('end_year')->nullable();
            $table->integer('end_month')->nullable();
            $table->integer('end_day')->nullable();
            $table->string('start_precision')->default('year');
            $table->string('end_precision')->default('year');
            
            // State and metadata
            $table->string('state')->default('draft');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->jsonb('sources')->nullable();
            
            // Permissions and access control
            $table->integer('permissions')->default(0644);
            $table->string('permission_mode')->default('own');
            $table->enum('access_level', ['private', 'shared', 'public'])->default('private');
            
            // Ownership and tracking
            $table->uuid('owner_id');
            $table->uuid('updater_id');
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys (except self-referential ones)
            $table->foreign('type_id')->references('type_id')->on('span_types');
            $table->foreign('owner_id')->references('id')->on('users');
            $table->foreign('updater_id')->references('id')->on('users');

            // Indexes
            $table->index('type_id');
            $table->index('parent_id');
            $table->index('root_id');
            $table->index('owner_id');
            $table->index('updater_id');
            $table->index('start_year');
            $table->index(['start_year', 'start_month', 'start_day'], 'spans_start_date_index');
            $table->index(['end_year', 'end_month', 'end_day'], 'spans_end_date_index');
            $table->index(['start_precision', 'end_precision'], 'spans_precision_index');
            $table->index('permission_mode');
        });

        // Add self-referential foreign keys after table creation
        Schema::table('spans', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('spans')->nullOnDelete();
            $table->foreign('root_id')->references('id')->on('spans')->nullOnDelete();
        });

        // 6. Add foreign key from users to spans for personal_span_id
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('personal_span_id')->references('id')->on('spans');
        });

        // 7. Create connections table
        Schema::create('connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id');
            $table->uuid('child_id');
            $table->string('type_id');
            $table->uuid('connection_span_id');
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            // Foreign keys
            $table->foreign('parent_id')->references('id')->on('spans')->onDelete('cascade');
            $table->foreign('child_id')->references('id')->on('spans')->onDelete('cascade');
            $table->foreign('type_id')->references('type')->on('connection_types')->onDelete('cascade');
            $table->foreign('connection_span_id')->references('id')->on('spans')->onDelete('cascade');

            // Indexes
            $table->index('parent_id');
            $table->index('child_id');
            $table->index('type_id');
            $table->index('connection_span_id');

            // Unique constraint - only on connection_span_id
            // Unique constraint - now based on connection_span_id
            $table->unique('connection_span_id');
            
            // Add the constraint that will be dropped later
            $table->unique(['parent_id', 'child_id', 'type_id']);
        });

        // Create the trigger function and trigger for temporal constraints
        DB::statement('DROP TRIGGER IF EXISTS enforce_temporal_constraint ON connections;');
        DB::statement('DROP FUNCTION IF EXISTS check_temporal_constraint;');

        // Recreate the trigger function with the new column
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION check_temporal_constraint()
            RETURNS TRIGGER AS $$
            DECLARE
                connection_span RECORD;
                validation_id UUID;
                has_complete_end_date BOOLEAN;
                constraint_type TEXT;
            BEGIN
                -- Get the connection span record
                SELECT * INTO connection_span FROM spans WHERE id = NEW.connection_span_id;

                -- Get the temporal constraint type
                SELECT tc.type INTO constraint_type 
                FROM connection_types ct
                JOIN temporal_constraints tc ON tc.id = ct.temporal_constraint_id
                WHERE ct.type = NEW.type_id;

                -- Check if we have a complete end date
                has_complete_end_date := connection_span.end_year IS NOT NULL AND 
                                       connection_span.end_month IS NOT NULL AND 
                                       connection_span.end_day IS NOT NULL;

                -- Generate a UUID for the validation record
                validation_id := gen_random_uuid();

                -- Record the start of validation
                INSERT INTO validation_monitoring (
                    id,
                    connection_id,
                    connection_span_id,
                    validation_layer,
                    validation_type,
                    validation_result,
                    context,
                    created_at,
                    updated_at
                ) VALUES (
                    validation_id,
                    NEW.id,
                    NEW.connection_span_id,
                    'postgres',
                    'constraint_validation',
                    'success',
                    jsonb_build_object(
                        'constraint_type', constraint_type,
                        'start_year', connection_span.start_year,
                        'start_month', connection_span.start_month,
                        'start_day', connection_span.start_day,
                        'end_year', connection_span.end_year,
                        'end_month', connection_span.end_month,
                        'end_day', connection_span.end_day
                    ),
                    NOW(),
                    NOW()
                );

                -- Apply constraint-specific validation
                IF constraint_type = 'single' THEN
                    IF EXISTS (
                        SELECT 1 FROM connections
                        WHERE parent_id = NEW.parent_id
                        AND child_id = NEW.child_id
                        AND type_id = NEW.type_id
                        AND id != NEW.id
                    ) THEN
                        UPDATE validation_monitoring 
                        SET validation_result = 'failure',
                            error_message = 'Only one connection of this type is allowed between these spans',
                            updated_at = NOW()
                        WHERE id = validation_id;
                        RAISE EXCEPTION 'Only one connection of this type is allowed between these spans';
                    END IF;
                END IF;

                -- Validate start date components
                IF connection_span.start_month IS NOT NULL AND (connection_span.start_month < 1 OR connection_span.start_month > 12) THEN
                    UPDATE validation_monitoring 
                    SET validation_result = 'failure',
                        error_message = 'Start month must be between 1 and 12',
                        updated_at = NOW()
                    WHERE id = validation_id;
                    RAISE EXCEPTION 'Start month must be between 1 and 12';
                END IF;

                IF connection_span.start_day IS NOT NULL AND (connection_span.start_day < 1 OR connection_span.start_day > 31) THEN
                    UPDATE validation_monitoring 
                    SET validation_result = 'failure',
                        error_message = 'Start day must be between 1 and 31',
                        updated_at = NOW()
                    WHERE id = validation_id;
                    RAISE EXCEPTION 'Start day must be between 1 and 31';
                END IF;

                -- Only validate end dates if we have a complete end date
                IF has_complete_end_date THEN
                    -- Validate end date components
                    IF connection_span.end_month < 1 OR connection_span.end_month > 12 THEN
                        UPDATE validation_monitoring 
                        SET validation_result = 'failure',
                            error_message = 'End month must be between 1 and 12',
                            updated_at = NOW()
                        WHERE id = validation_id;
                        RAISE EXCEPTION 'End month must be between 1 and 12';
                    END IF;

                    IF connection_span.end_day < 1 OR connection_span.end_day > 31 THEN
                        UPDATE validation_monitoring 
                        SET validation_result = 'failure',
                            error_message = 'End day must be between 1 and 31',
                            updated_at = NOW()
                        WHERE id = validation_id;
                        RAISE EXCEPTION 'End day must be between 1 and 31';
                    END IF;

                    -- Check that end date is not before start date
                    IF connection_span.end_year < connection_span.start_year THEN
                        UPDATE validation_monitoring 
                        SET validation_result = 'failure',
                            error_message = 'End year cannot be before start year',
                            updated_at = NOW()
                        WHERE id = validation_id;
                        RAISE EXCEPTION 'End year cannot be before start year';
                    END IF;

                    IF connection_span.end_year = connection_span.start_year AND 
                       connection_span.end_month < connection_span.start_month THEN
                        UPDATE validation_monitoring 
                        SET validation_result = 'failure',
                            error_message = 'End month cannot be before start month in the same year',
                            updated_at = NOW()
                        WHERE id = validation_id;
                        RAISE EXCEPTION 'End month cannot be before start month in the same year';
                    END IF;

                    IF connection_span.end_year = connection_span.start_year AND 
                       connection_span.end_month = connection_span.start_month AND 
                       connection_span.end_day < connection_span.start_day THEN
                        UPDATE validation_monitoring 
                        SET validation_result = 'failure',
                            error_message = 'End day cannot be before start day in the same month',
                            updated_at = NOW()
                        WHERE id = validation_id;
                        RAISE EXCEPTION 'End day cannot be before start day in the same month';
                    END IF;
                END IF;

                -- Validate that connection_span_id references a valid span of type 'connection'
                IF NOT EXISTS (
                    SELECT 1 FROM spans 
                    WHERE id = NEW.connection_span_id 
                    AND type_id = 'connection'
                ) THEN
                    UPDATE validation_monitoring 
                    SET validation_result = 'failure',
                        error_message = 'Invalid connection_span_id',
                        updated_at = NOW()
                    WHERE id = validation_id;
                    RAISE EXCEPTION 'Invalid connection_span_id';
                END IF;

                -- Validate that connection_type exists
                IF NOT EXISTS (
                    SELECT 1 FROM connection_types 
                    WHERE type = NEW.type_id
                ) THEN
                    UPDATE validation_monitoring 
                    SET validation_result = 'failure',
                        error_message = 'Invalid connection type',
                        updated_at = NOW()
                    WHERE id = validation_id;
                    RAISE EXCEPTION 'Invalid connection type';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        // Create the trigger
        DB::statement('CREATE TRIGGER enforce_temporal_constraint BEFORE INSERT OR UPDATE ON connections FOR EACH ROW EXECUTE FUNCTION check_temporal_constraint();');

        // 8. Create user_spans table (for access control)
        Schema::create('user_spans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('span_id');
            $table->string('access_level')->default('viewer'); // viewer, editor, owner
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('span_id')->references('id')->on('spans')->onDelete('cascade');

            // Indexes
            $table->index('user_id');
            $table->index('span_id');
            $table->index('access_level');

            // Prevent duplicate user-span pairs
            $table->unique(['user_id', 'span_id']);
        });

        // 9. Create span_permissions table
        Schema::create('span_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('span_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('group_id')->nullable(); // For future expansion
            $table->enum('permission_type', ['view', 'edit']);
            $table->timestamps();

            // Unique constraint to prevent duplicate permissions
            $table->unique(['span_id', 'user_id', 'group_id', 'permission_type']);

            $table->index('span_id');
            $table->index('user_id');
        });

        // Insert default span types
        DB::table('span_types')->insert([
            [
                'type_id' => 'person',
                'name' => 'Person',
                'description' => 'A person or individual',
                'metadata' => json_encode([
                    'schema' => [
                        'birth_name' => [
                            'type' => 'text',
                            'label' => 'Birth Name',
                            'component' => 'text-input',
                            'help' => "Person's name at birth if different from primary name",
                            'required' => false
                        ],
                        'gender' => [
                            'type' => 'select',
                            'label' => 'Gender',
                            'component' => 'select',
                            'options' => ['male', 'female', 'other'],
                            'help' => 'Gender identity',
                            'required' => false
                        ],
                        'nationality' => [
                            'type' => 'text',
                            'label' => 'Nationality',
                            'component' => 'text-input',
                            'help' => 'Primary nationality',
                            'required' => false
                        ],
                        'occupation' => [
                            'type' => 'text',
                            'label' => 'Primary Occupation',
                            'component' => 'text-input',
                            'help' => 'Main occupation or role',
                            'required' => false
                        ]
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type_id' => 'organisation',
                'name' => 'Organisation',
                'description' => 'An organization or institution',
                'metadata' => json_encode([
                    'schema' => [
                        'org_type' => [
                            'type' => 'select',
                            'label' => 'Organisation Type',
                            'component' => 'select',
                            'options' => [
                                'business', 'educational', 'government',
                                'non-profit', 'religious', 'other'
                            ],
                            'help' => 'Type of organization',
                            'required' => true
                        ],
                        'industry' => [
                            'type' => 'text',
                            'label' => 'Industry',
                            'component' => 'text-input',
                            'help' => 'Primary industry or sector',
                            'required' => false
                        ],
                        'size' => [
                            'type' => 'select',
                            'label' => 'Size',
                            'component' => 'select',
                            'options' => ['small', 'medium', 'large'],
                            'help' => 'Size of organization',
                            'required' => false
                        ]
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type_id' => 'event',
                'name' => 'Event',
                'description' => 'A historical or personal event',
                'metadata' => json_encode([
                    'schema' => [
                        'event_type' => [
                            'type' => 'select',
                            'label' => 'Event Type',
                            'component' => 'select',
                            'options' => [
                                'personal', 'historical', 'cultural',
                                'political', 'other'
                            ],
                            'help' => 'Type of event',
                            'required' => true
                        ],
                        'significance' => [
                            'type' => 'text',
                            'label' => 'Significance',
                            'component' => 'text-input',
                            'help' => 'Why this event is significant',
                            'required' => false
                        ],
                        'location' => [
                            'type' => 'text',
                            'label' => 'Location',
                            'component' => 'text-input',
                            'help' => 'Where the event took place',
                            'required' => false
                        ]
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type_id' => 'place',
                'name' => 'Place',
                'description' => 'A physical location or place',
                'metadata' => json_encode([
                    'schema' => [
                        'place_type' => [
                            'type' => 'select',
                            'label' => 'Place Type',
                            'component' => 'select',
                            'options' => [
                                'city', 'country', 'region',
                                'building', 'landmark', 'other'
                            ],
                            'help' => 'Type of place',
                            'required' => true
                        ],
                        'coordinates' => [
                            'type' => 'text',
                            'label' => 'Coordinates',
                            'component' => 'text-input',
                            'help' => 'Geographic coordinates',
                            'required' => false
                        ],
                        'country' => [
                            'type' => 'text',
                            'label' => 'Country',
                            'component' => 'text-input',
                            'help' => 'Country where this place is located',
                            'required' => false
                        ]
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type_id' => 'connection',
                'name' => 'Connection',
                'description' => 'A temporal connection between spans',
                'metadata' => json_encode([
                    'schema' => [
                        'connection_type' => [
                            'type' => 'select',
                            'label' => 'Connection Type',
                            'component' => 'select',
                            'options' => [
                                'family', 'education', 'work',
                                'residence', 'relationship', 'other'
                            ],
                            'help' => 'Type of connection',
                            'required' => true
                        ],
                        'role' => [
                            'type' => 'text',
                            'label' => 'Role',
                            'component' => 'text-input',
                            'help' => 'Role or position in this connection',
                            'required' => false
                        ],
                        'notes' => [
                            'type' => 'textarea',
                            'label' => 'Notes',
                            'component' => 'textarea',
                            'help' => 'Additional notes about this connection',
                            'required' => false
                        ]
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // Insert default connection types
        DB::table('connection_types')->insert([
            [
                'type' => 'employment',
                'forward_predicate' => 'worked at',
                'forward_description' => 'Worked at',
                'inverse_predicate' => 'employed',
                'inverse_description' => 'Employed',
                'constraint_type' => 'non_overlapping',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'residence',
                'forward_predicate' => 'lived in',
                'forward_description' => 'Lived in',
                'inverse_predicate' => 'was home to',
                'inverse_description' => 'Was home to',
                'constraint_type' => 'non_overlapping',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'attendance',
                'forward_predicate' => 'attended',
                'forward_description' => 'Attended',
                'inverse_predicate' => 'was attended by',
                'inverse_description' => 'Was attended by',
                'constraint_type' => 'non_overlapping',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'ownership',
                'forward_predicate' => 'owned',
                'forward_description' => 'Owned',
                'inverse_predicate' => 'was owned by',
                'inverse_description' => 'Was owned by',
                'constraint_type' => 'non_overlapping',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'membership',
                'forward_predicate' => 'was member of',
                'forward_description' => 'Was member of',
                'inverse_predicate' => 'had member',
                'inverse_description' => 'Had member',
                'constraint_type' => 'non_overlapping',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'family',
                'forward_predicate' => 'is family of',
                'forward_description' => 'Is a family member of',
                'inverse_predicate' => 'is family of',
                'inverse_description' => 'Is a family member of',
                'constraint_type' => 'single',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'relationship',
                'forward_predicate' => 'has relationship with',
                'forward_description' => 'Has a relationship with',
                'inverse_predicate' => 'has relationship with',
                'inverse_description' => 'Has a relationship with',
                'constraint_type' => 'single',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'travel',
                'forward_predicate' => 'traveled to',
                'forward_description' => 'Traveled to',
                'inverse_predicate' => 'was visited by',
                'inverse_description' => 'Was visited by',
                'constraint_type' => 'non_overlapping',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'participation',
                'forward_predicate' => 'participated in',
                'forward_description' => 'Participated in',
                'inverse_predicate' => 'had participant',
                'inverse_description' => 'Had as a participant',
                'constraint_type' => 'non_overlapping',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'education',
                'forward_predicate' => 'studied at',
                'forward_description' => 'Studied at',
                'inverse_predicate' => 'educated',
                'inverse_description' => 'Educated',
                'constraint_type' => 'non_overlapping',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order of creation
        Schema::dropIfExists('user_spans');
        Schema::dropIfExists('connections');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['personal_span_id']);
            $table->dropColumn(['personal_span_id', 'is_admin', 'deleted_at']);
        });
        Schema::dropIfExists('spans');
        Schema::dropIfExists('connection_types');
        Schema::dropIfExists('span_types');
    }
}; 