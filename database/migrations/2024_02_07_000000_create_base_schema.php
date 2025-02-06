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
            $table->string('name');
            $table->string('description');
            $table->string('inverse_name');
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
            $table->integer('start_year');
            $table->integer('start_month')->nullable();
            $table->integer('start_day')->nullable();
            $table->integer('end_year')->nullable();
            $table->integer('end_month')->nullable();
            $table->integer('end_day')->nullable();
            $table->string('start_precision_level')->default('year');
            $table->string('end_precision_level')->default('year');
            
            // State and metadata
            $table->string('state')->default('draft');
            $table->jsonb('metadata')->default('{}');
            
            // Ownership and tracking
            $table->uuid('creator_id');
            $table->uuid('updater_id');
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys (except self-referential ones)
            $table->foreign('type_id')->references('type_id')->on('span_types');
            $table->foreign('creator_id')->references('id')->on('users');
            $table->foreign('updater_id')->references('id')->on('users');

            // Indexes
            $table->index('type_id');
            $table->index('parent_id');
            $table->index('root_id');
            $table->index('creator_id');
            $table->index('updater_id');
            $table->index('start_year');
            $table->index(['start_year', 'start_month', 'start_day']);
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
            $table->uuid('connection_span_id')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            // Foreign keys
            $table->foreign('parent_id')->references('id')->on('spans')->onDelete('cascade');
            $table->foreign('child_id')->references('id')->on('spans')->onDelete('cascade');
            $table->foreign('type_id')->references('type')->on('connection_types')->onDelete('cascade');
            $table->foreign('connection_span_id')->references('id')->on('spans')->onDelete('set null');

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

        // Insert default span types
        DB::table('span_types')->insert([
            [
                'type_id' => 'person',
                'name' => 'Person',
                'description' => 'A person or individual',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type_id' => 'event',
                'name' => 'Event',
                'description' => 'A specific event or occurrence',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type_id' => 'period',
                'name' => 'Time Period',
                'description' => 'A defined period of time',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type_id' => 'organization',
                'name' => 'Organization',
                'description' => 'An organization or institution',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type_id' => 'place',
                'name' => 'Place',
                'description' => 'A physical location or place',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type_id' => 'connection',
                'name' => 'Connection',
                'description' => 'A connection between spans',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Insert default connection types
        DB::table('connection_types')->insert([
            [
                'type' => 'parent',
                'name' => 'Parent',
                'description' => 'A parent-child connection',
                'inverse_name' => 'Child',
                'inverse_description' => 'A child-parent connection',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'member_of',
                'name' => 'Member',
                'description' => 'A membership connection',
                'inverse_name' => 'Group',
                'inverse_description' => 'A group membership connection',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'located_at',
                'name' => 'Location',
                'description' => 'A location connection',
                'inverse_name' => 'Place',
                'inverse_description' => 'A place connection',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'participated_in',
                'name' => 'Participant',
                'description' => 'A participation connection',
                'inverse_name' => 'Event',
                'inverse_description' => 'An event participation connection',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'at_work',
                'name' => 'Work',
                'description' => 'An employment connection',
                'inverse_name' => 'Employer',
                'inverse_description' => 'An employer connection',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'at_education',
                'name' => 'Education',
                'description' => 'An educational connection',
                'inverse_name' => 'Institution',
                'inverse_description' => 'An educational institution connection',
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
            'creator_id' => $systemUserId,
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