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

        // 3. Create connection_types table (uses string primary key)
        Schema::create('connection_types', function (Blueprint $table) {
            $table->string('type')->primary();
            $table->string('forward_predicate');
            $table->string('forward_description');
            $table->string('inverse_predicate');
            $table->string('inverse_description');
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
            $table->index(['start_year', 'start_month', 'start_day']);
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

            // Unique constraint to prevent duplicate connections
            $table->unique(['parent_id', 'child_id', 'type_id']);
        });

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
                'description' => 'An organization, institution, or company',
                'metadata' => json_encode([
                    'schema' => [
                        'type' => [
                            'type' => 'select',
                            'label' => 'Organisation Type',
                            'component' => 'select',
                            'options' => [
                                'business', 'educational', 'government', 
                                'non-profit', 'religious', 'other'
                            ],
                            'help' => 'Type of organisation',
                            'required' => true
                        ],
                        'industry' => [
                            'type' => 'text',
                            'label' => 'Industry',
                            'component' => 'text-input',
                            'help' => 'Primary industry or sector',
                            'required' => false
                        ],
                        'headquarters' => [
                            'type' => 'text',
                            'label' => 'Headquarters',
                            'component' => 'text-input',
                            'help' => 'Location of main headquarters',
                            'required' => false
                        ],
                        'website' => [
                            'type' => 'url',
                            'label' => 'Website',
                            'component' => 'url-input',
                            'help' => 'Official website',
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
                        'type' => [
                            'type' => 'select',
                            'label' => 'Place Type',
                            'component' => 'select',
                            'options' => [
                                'city', 'country', 'region', 'building',
                                'landmark', 'natural-feature', 'other'
                            ],
                            'help' => 'Type of place',
                            'required' => true
                        ],
                        'coordinates' => [
                            'type' => 'text',
                            'label' => 'Coordinates',
                            'component' => 'text-input',
                            'help' => 'Geographic coordinates (latitude, longitude)',
                            'required' => false
                        ],
                        'country' => [
                            'type' => 'text',
                            'label' => 'Country',
                            'component' => 'text-input',
                            'help' => 'Country where this place is located',
                            'required' => false
                        ],
                        'current_name' => [
                            'type' => 'text',
                            'label' => 'Current Name',
                            'component' => 'text-input',
                            'help' => 'Current name if different from historical name',
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
                'description' => 'A specific event or occurrence',
                'metadata' => json_encode([
                    'schema' => [
                        'type' => [
                            'type' => 'select',
                            'label' => 'Event Type',
                            'component' => 'select',
                            'options' => [
                                'historical', 'personal', 'cultural',
                                'political', 'natural', 'other'
                            ],
                            'help' => 'Type of event',
                            'required' => true
                        ],
                        'location' => [
                            'type' => 'text',
                            'label' => 'Location',
                            'component' => 'text-input',
                            'help' => 'Where the event took place',
                            'required' => false
                        ],
                        'significance' => [
                            'type' => 'text',
                            'label' => 'Significance',
                            'component' => 'textarea',
                            'help' => 'Historical or personal significance of this event',
                            'required' => false
                        ],
                        'participants' => [
                            'type' => 'array',
                            'label' => 'Key Participants',
                            'component' => 'array-input',
                            'help' => 'Key people or organizations involved',
                            'required' => false,
                            'array_item_schema' => [
                                'type' => 'span',
                                'label' => 'Participant'
                            ]
                        ]
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type_id' => 'period',
                'name' => 'Time Period',
                'description' => 'A defined period of time',
                'metadata' => json_encode([
                    'schema' => [
                        'type' => [
                            'type' => 'select',
                            'label' => 'Period Type',
                            'component' => 'select',
                            'options' => [
                                'historical', 'cultural', 'geological',
                                'personal', 'other'
                            ],
                            'help' => 'Type of time period',
                            'required' => true
                        ],
                        'era' => [
                            'type' => 'text',
                            'label' => 'Era',
                            'component' => 'text-input',
                            'help' => 'Historical era or epoch',
                            'required' => false
                        ],
                        'significance' => [
                            'type' => 'text',
                            'label' => 'Significance',
                            'component' => 'textarea',
                            'help' => 'Historical or cultural significance of this period',
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
                        'role' => [
                            'type' => 'text',
                            'label' => 'Role',
                            'component' => 'text-input',
                            'help' => 'Role or nature of the connection',
                            'required' => false
                        ],
                        'details' => [
                            'type' => 'text',
                            'label' => 'Details',
                            'component' => 'textarea',
                            'help' => 'Additional details about the connection',
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
                'type' => 'family',
                'forward_predicate' => 'is parent of',
                'forward_description' => 'Indicates that one span is the parent of another span',
                'inverse_predicate' => 'is child of',
                'inverse_description' => 'Indicates that one span is the child of another span',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'membership',
                'forward_predicate' => 'is member of',
                'forward_description' => 'Indicates that one span is a member of a group or organization',
                'inverse_predicate' => 'has member',
                'inverse_description' => 'Indicates that a group or organization has a member',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'travel',
                'forward_predicate' => 'traveled to',
                'forward_description' => 'Indicates places someone visited or traveled to',
                'inverse_predicate' => 'was visited by',
                'inverse_description' => 'Indicates who traveled to this location',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'participation',
                'forward_predicate' => 'participated in',
                'forward_description' => 'Indicates that one span participated in an event',
                'inverse_predicate' => 'had participant',
                'inverse_description' => 'Indicates that an event had a participant',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'employment',
                'forward_predicate' => 'worked at',
                'forward_description' => 'Indicates that one span worked at an organization',
                'inverse_predicate' => 'employed',
                'inverse_description' => 'Indicates that an organization employed a span',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'education',
                'forward_predicate' => 'studied at',
                'forward_description' => 'Indicates that one span studied at an educational institution',
                'inverse_predicate' => 'educated',
                'inverse_description' => 'Indicates that an educational institution educated a span',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'residence',
                'forward_predicate' => 'resided at',
                'forward_description' => 'Indicates where someone lived',
                'inverse_predicate' => 'was residence of',
                'inverse_description' => 'Indicates who lived at this location',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'relationship',
                'forward_predicate' => 'is related to',
                'forward_description' => 'Indicates a relationship between people',
                'inverse_predicate' => 'is related to',
                'inverse_description' => 'Indicates a relationship between people',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Create system user
        $systemUserId = DB::table('users')->insertGetId([
            'id' => Str::uuid(),
            'email' => 'system@example.com',
            'password' => bcrypt(Str::random(32)),
            'email_verified_at' => now(),
            'is_admin' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create system user's personal span
        $systemSpanId = Str::uuid();
        DB::table('spans')->insert([
            'id' => $systemSpanId,
            'name' => 'System',
            'slug' => 'system',
            'type_id' => 'person',
            'is_personal_span' => true,
            'start_year' => 2024,
            'owner_id' => $systemUserId,
            'updater_id' => $systemUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Link system user to personal span
        DB::table('users')->where('id', $systemUserId)->update([
            'personal_span_id' => $systemSpanId
        ]);

        // Create user-span connection for system user
        DB::table('user_spans')->insert([
            'id' => Str::uuid(),
            'user_id' => $systemUserId,
            'span_id' => $systemSpanId,
            'access_level' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
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