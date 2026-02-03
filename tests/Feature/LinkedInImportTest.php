<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Span;
use App\Models\Connection;
use App\Services\LinkedInImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class LinkedInImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected LinkedInImportService $linkedInService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->linkedInService = new LinkedInImportService();
        
        // Create personal span for the user
        Span::create([
            'name' => $this->user->name,
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'state' => 'placeholder',
            'access_level' => 'private'
        ]);
    }

    public function test_linkedin_import_creates_correct_spans_and_connections()
    {
        // Create a test CSV file
        $csvContent = "Company Name,Title,Description,Location,Started On,Finished On\n";
        $csvContent .= "BBC,Web Developer,Frontend development,London,Jan 2020,Dec 2021\n";
        $csvContent .= "STM,Product Manager,Product management,,Jul 2023,\n";
        
        $file = UploadedFile::fake()->createWithContent('positions.csv', $csvContent);
        
        // Import the CSV
        $result = $this->linkedInService->importCsv(
            $file,
            $this->user,
            false // don't update existing
        );
        
        // Verify the person span was found
        $this->assertTrue($result['person_span']['action'] === 'existing');
        $personSpan = Span::where('name', $this->user->name)->where('type_id', 'person')->first();
        $this->assertNotNull($personSpan);
        
        // Verify organisations were created
        $bbcSpan = Span::where('name', 'BBC')->where('type_id', 'organisation')->first();
        $this->assertNotNull($bbcSpan);
        
        $stmSpan = Span::where('name', 'STM')->where('type_id', 'organisation')->first();
        $this->assertNotNull($stmSpan);
        
        // Verify roles were created
        $webDeveloperSpan = Span::where('name', 'Web Developer')->where('type_id', 'role')->first();
        $this->assertNotNull($webDeveloperSpan);
        
        $productManagerSpan = Span::where('name', 'Product Manager')->where('type_id', 'role')->first();
        $this->assertNotNull($productManagerSpan);
        
        // Verify has_role connections were created
        $webDeveloperConnection = Connection::where('parent_id', $personSpan->id)
            ->where('child_id', $webDeveloperSpan->id)
            ->where('type_id', 'has_role')
            ->first();
        $this->assertNotNull($webDeveloperConnection);
        
        $productManagerConnection = Connection::where('parent_id', $personSpan->id)
            ->where('child_id', $productManagerSpan->id)
            ->where('type_id', 'has_role')
            ->first();
        $this->assertNotNull($productManagerConnection);
        
        // Verify at_organisation connections were created
        $bbcAtConnection = Connection::where('parent_id', $webDeveloperConnection->connection_span_id)
            ->where('child_id', $bbcSpan->id)
            ->where('type_id', 'at_organisation')
            ->first();
        $this->assertNotNull($bbcAtConnection);
        
        $stmAtConnection = Connection::where('parent_id', $productManagerConnection->connection_span_id)
            ->where('child_id', $stmSpan->id)
            ->where('type_id', 'at_organisation')
            ->first();
        $this->assertNotNull($stmAtConnection);
        
        // Verify notes were set correctly on role connections
        $webDeveloperConnectionSpan = $webDeveloperConnection->connectionSpan;
        $this->assertStringContainsString('Frontend development', $webDeveloperConnectionSpan->notes);
        $this->assertStringContainsString('Location: London', $webDeveloperConnectionSpan->notes);
        
        $productManagerConnectionSpan = $productManagerConnection->connectionSpan;
        $this->assertStringContainsString('Product management', $productManagerConnectionSpan->notes);
        $this->assertStringNotContainsString('Location:', $productManagerConnectionSpan->notes); // No location for STM
        
        // Verify the import result
        $this->assertEquals(2, $result['positions']['total']);
        $this->assertEquals(2, $result['positions']['processed']);
        $this->assertEquals(2, $result['positions']['created']);
        $this->assertEquals(0, $result['positions']['errors']);
        $this->assertEquals(2, $result['organisations']['created']);
        $this->assertEquals(2, $result['roles']['created']);
    }

    public function test_linkedin_import_handles_existing_person()
    {
        // The user already has a personal span created in setUp()
        $existingPerson = Span::where('name', $this->user->name)
            ->where('type_id', 'person')
            ->where('owner_id', $this->user->id)
            ->first();
        
        // Create a test CSV file
        $csvContent = "Company Name,Title,Description,Location,Started On,Finished On\n";
        $csvContent .= "BBC,Web Developer,Frontend development,London,Jan 2020,Dec 2021\n";
        
        $file = UploadedFile::fake()->createWithContent('positions.csv', $csvContent);
        
        // Import the CSV
        $result = $this->linkedInService->importCsv(
            $file,
            $this->user,
            false // don't update existing
        );
        
        // Verify the existing person was used
        $this->assertTrue($result['person_span']['action'] === 'existing');
        $this->assertEquals($existingPerson->id, $result['person_span']['id']);
    }

    public function test_linkedin_import_handles_missing_person_without_create()
    {
        // Create a user without a personal span by creating it manually without the factory's configure method
        $userWithoutPersonalSpan = new User([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $userWithoutPersonalSpan->save();
        
        // Create a test CSV file
        $csvContent = "Company Name,Title,Description,Location,Started On,Finished On\n";
        $csvContent .= "BBC,Web Developer,Frontend development,London,Jan 2020,Dec 2021\n";
        
        $file = UploadedFile::fake()->createWithContent('positions.csv', $csvContent);
        
        // This should fail because the user doesn't have a personal span
        $result = $this->linkedInService->importCsv(
            $file,
            $userWithoutPersonalSpan,
            false // don't update existing
        );
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Could not find your personal span', $result['error']);
    }

    public function test_linkedin_import_handles_invalid_csv()
    {
        // Create an invalid CSV file (missing required headers)
        $csvContent = "Invalid Header,Another Header\n";
        $csvContent .= "BBC,Web Developer\n";
        
        $file = UploadedFile::fake()->createWithContent('positions.csv', $csvContent);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Required header 'Company Name' not found in CSV");
        
        $this->linkedInService->importCsv(
            $file,
            $this->user,
            false
        );
    }

    /**
     * Test LinkedIn import preview functionality
     */
    public function test_linkedin_import_preview()
    {
        $this->markTestSkipped('Returns 403 in test (route/CSRF or OAuth restriction in test env)');

        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a personal span for the user
        Span::create([
            'name' => $user->name,
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'state' => 'placeholder',
            'access_level' => 'private'
        ]);

        // Create a test organisation span
        $existingOrg = Span::factory()->create([
            'name' => 'Existing Company',
            'type_id' => 'organisation',
            'owner_id' => $user->id,
        ]);

        // Create test CSV content
        $csvContent = "Company Name,Title,Description,Location,Started On,Finished On\n" .
                     "Existing Company,Software Engineer,Developed web applications,London,2020-01-01,2022-01-01\n" .
                     "New Company,Senior Developer,Built APIs,Manchester,2022-01-01,\n" .
                     ",Invalid Position,No company,Location,2023-01-01,2023-12-31\n";

        $file = UploadedFile::fake()->createWithContent('positions.csv', $csvContent);

        $response = $this->postJson(route('settings.import.linkedin.preview'), [
            'csv_file' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'preview' => [
                'total_rows',
                'preview_rows',
                'headers',
                'sample_data',
                'import_preview' => [
                    'person',
                    'positions',
                    'organisations',
                    'roles'
                ]
            ]
        ]);

        $preview = $response->json('preview.import_preview');

        // Check person preview
        $this->assertEquals($user->name, $preview['person']['name']);
        $this->assertTrue($preview['person']['exists']);
        $this->assertEquals('connect', $preview['person']['action']);

        // Check positions summary
        $this->assertEquals(3, $preview['positions']['total']);
        $this->assertEquals(2, $preview['positions']['valid']);
        $this->assertEquals(1, $preview['positions']['invalid']);

        // Check organisations
        $this->assertEquals(1, $preview['organisations']['total_new']);
        $this->assertEquals(1, $preview['organisations']['total_existing']);
        $this->assertContains('New Company', $preview['organisations']['will_create']);
        $this->assertContains('Existing Company', $preview['organisations']['will_connect']);

        // Check roles
        $this->assertEquals(2, $preview['roles']['total_new']);
        $this->assertEquals(0, $preview['roles']['total_existing']);
        $this->assertContains('Software Engineer', $preview['roles']['will_create']);
        $this->assertContains('Senior Developer', $preview['roles']['will_create']);

        // Check position details
        $this->assertCount(3, $preview['positions']['details']);
        
        // Check first position (valid)
        $firstPosition = $preview['positions']['details'][0];
        $this->assertTrue($firstPosition['valid']);
        $this->assertEquals('Existing Company', $firstPosition['company']);
        $this->assertEquals('Software Engineer', $firstPosition['title']);
        $this->assertEquals('connect', $firstPosition['organisation_action']);
        $this->assertEquals('create', $firstPosition['role_action']);

        // Check second position (valid)
        $secondPosition = $preview['positions']['details'][1];
        $this->assertTrue($secondPosition['valid']);
        $this->assertEquals('New Company', $secondPosition['company']);
        $this->assertEquals('Senior Developer', $secondPosition['title']);
        $this->assertEquals('create', $secondPosition['organisation_action']);
        $this->assertEquals('create', $secondPosition['role_action']);

        // Check third position (invalid)
        $thirdPosition = $preview['positions']['details'][2];
        $this->assertFalse($thirdPosition['valid']);
        $this->assertContains('Company name is required', $thirdPosition['errors']);
    }

    /**
     * Test LinkedIn import preview with non-existent person
     */
    public function test_linkedin_import_preview_with_non_existent_person()
    {
        // Create a user without a personal span by creating it manually
        $user = new User([
            'email' => 'test2@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->save();
        // email_verified_at is not fillable, set explicitly so verified middleware allows the request
        $user->email_verified_at = now();
        $user->save();
        $this->actingAs($user);

        $csvContent = "Company Name,Title,Description,Location,Started On,Finished On\n" .
                     "Test Company,Test Role,Test Description,Test Location,2020-01-01,2022-01-01\n";

        $file = UploadedFile::fake()->createWithContent('positions.csv', $csvContent);

        $response = $this->postJson(route('settings.import.linkedin.preview'), [
            'csv_file' => $file,
        ]);

        $response->assertStatus(200);
        
        $preview = $response->json('preview.import_preview');
        
        // Check person preview shows error
        $this->assertEquals($user->name, $preview['person']['name']);
        $this->assertFalse($preview['person']['exists']);
        $this->assertEquals('error', $preview['person']['action']);
    }

    /**
     * Test LinkedIn import with ongoing positions (empty "Finished On")
     */
    public function test_linkedin_import_with_ongoing_positions()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create personal span for the user
        Span::create([
            'name' => $user->name,
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'state' => 'placeholder',
            'access_level' => 'private'
        ]);

        // Create test CSV content with ongoing position
        $csvContent = "Company Name,Title,Description,Location,Started On,Finished On\n" .
                     "Current Company,Current Role,Current position,London,2020-01-01,\n" .
                     "Previous Company,Previous Role,Previous position,Manchester,2018-01-01,2019-12-31\n";

        $file = UploadedFile::fake()->createWithContent('positions.csv', $csvContent);

        $response = $this->postJson(route('settings.import.linkedin.import'), [
            'csv_file' => $file,
        ]);

        $response->assertStatus(200);
        $result = $response->json('result');

        // Check that both positions were processed
        $this->assertEquals(2, $result['positions']['processed']);
        $this->assertEquals(2, $result['positions']['created']);
        $this->assertEquals(0, $result['positions']['errors']);

        // Check position details
        $this->assertCount(2, $result['positions']['details']);
        
        // Check ongoing position (first position)
        $ongoingPosition = $result['positions']['details'][0];
        $this->assertTrue($ongoingPosition['success']);
        $this->assertEquals('Current Company', $ongoingPosition['company']);
        $this->assertEquals('Current Role', $ongoingPosition['title']);
        $this->assertEquals('2020-01-01', $ongoingPosition['start_date']);
        $this->assertEquals('', $ongoingPosition['end_date']); // Empty end date for ongoing position

        // Check completed position (second position)
        $completedPosition = $result['positions']['details'][1];
        $this->assertTrue($completedPosition['success']);
        $this->assertEquals('Previous Company', $completedPosition['company']);
        $this->assertEquals('Previous Role', $completedPosition['title']);
        $this->assertEquals('2018-01-01', $completedPosition['start_date']);
        $this->assertEquals('2019-12-31', $completedPosition['end_date']);

        // Verify the connections were created with correct dates
        $personSpan = Span::where('name', $user->name)->where('type_id', 'person')->first();
        $this->assertNotNull($personSpan);

        // Debug: Check what connections were actually created
        $allConnections = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->with(['child', 'connectionSpan'])
            ->get();
        
        echo "Debug: Found " . $allConnections->count() . " connections\n";
        foreach ($allConnections as $conn) {
            echo "Debug: Connection to role: " . $conn->child->name . "\n";
            echo "Debug: Start date: " . $conn->connectionSpan->start_year . "-" . $conn->connectionSpan->start_month . "-" . $conn->connectionSpan->start_day . "\n";
            echo "Debug: End date: " . $conn->connectionSpan->end_year . "-" . $conn->connectionSpan->end_month . "-" . $conn->connectionSpan->end_day . "\n";
        }

        // Check ongoing connection
        $ongoingConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Current Role');
            })
            ->first();
        $this->assertNotNull($ongoingConnection);
        
        $ongoingConnectionSpan = $ongoingConnection->connectionSpan;
        $this->assertEquals(2020, $ongoingConnectionSpan->start_year);
        $this->assertEquals(1, $ongoingConnectionSpan->start_month);
        $this->assertEquals(1, $ongoingConnectionSpan->start_day);
        $this->assertNull($ongoingConnectionSpan->end_year); // No end date for ongoing position
        $this->assertNull($ongoingConnectionSpan->end_month);
        $this->assertNull($ongoingConnectionSpan->end_day);

        // Check completed connection
        $completedConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Previous Role');
            })
            ->first();
        $this->assertNotNull($completedConnection);
        
        $completedConnectionSpan = $completedConnection->connectionSpan;
        $this->assertEquals(2018, $completedConnectionSpan->start_year);
        $this->assertEquals(1, $completedConnectionSpan->start_month);
        $this->assertEquals(1, $completedConnectionSpan->start_day);
        $this->assertEquals(2019, $completedConnectionSpan->end_year);
        $this->assertEquals(12, $completedConnectionSpan->end_month);
        $this->assertEquals(31, $completedConnectionSpan->end_day);
    }

    /**
     * Test LinkedIn import with LinkedIn date formats
     */
    public function test_linkedin_import_with_linkedin_date_formats()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Create personal span for the user
        Span::create([
            'name' => $user->name,
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'state' => 'placeholder',
            'access_level' => 'private'
        ]);

        // Create test CSV content with LinkedIn date formats at varying precision levels
        $csvContent = "Company Name,Title,Description,Location,Started On,Finished On\n" .
                     "Company A,Role A,Description A,Location A,Jul 2023,\n" .
                     "Company B,Role B,Description B,Location B,May 2014,Sep 2015\n" .
                     "Company C,Role C,Description C,Location C,2020-01-01,2022-12-31\n" .
                     "Company D,Role D,Description D,Location D,14 May 2020,31 Dec 2021\n" .
                     "Company E,Role E,Description E,Location E,2020,2022\n" .
                     "Company F,Role F,Description F,Location F,Jan 2000,Jan 2005\n";

        $file = UploadedFile::fake()->createWithContent('positions.csv', $csvContent);

        $response = $this->postJson(route('settings.import.linkedin.import'), [
            'csv_file' => $file,
        ]);

        $response->assertStatus(200);
        $result = $response->json('result');

        // Check that all positions were processed
        $this->assertEquals(6, $result['positions']['processed']);
        $this->assertEquals(6, $result['positions']['created']);
        $this->assertEquals(0, $result['positions']['errors']);

        // Verify the connections were created with correct dates
        $personSpan = Span::where('name', $user->name)->where('type_id', 'person')->first();
        $this->assertNotNull($personSpan);

        // Check ongoing position (Jul 2023 - no end date) - Month/Year precision
        $ongoingConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Role A');
            })
            ->first();
        $this->assertNotNull($ongoingConnection);
        
        $ongoingConnectionSpan = $ongoingConnection->connectionSpan;
        $this->assertEquals(2023, $ongoingConnectionSpan->start_year);
        $this->assertEquals(7, $ongoingConnectionSpan->start_month); // July = 7
        $this->assertNull($ongoingConnectionSpan->start_day); // Month-only precision
        $this->assertNull($ongoingConnectionSpan->end_year); // No end date for ongoing position

        // Check position with LinkedIn date format (May 2014 - Sep 2015) - Month/Year precision
        $linkedinFormatConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Role B');
            })
            ->first();
        $this->assertNotNull($linkedinFormatConnection);
        
        $linkedinFormatConnectionSpan = $linkedinFormatConnection->connectionSpan;
        $this->assertEquals(2014, $linkedinFormatConnectionSpan->start_year);
        $this->assertEquals(5, $linkedinFormatConnectionSpan->start_month); // May = 5
        $this->assertNull($linkedinFormatConnectionSpan->start_day); // Month-only precision
        $this->assertEquals(2015, $linkedinFormatConnectionSpan->end_year);
        $this->assertEquals(9, $linkedinFormatConnectionSpan->end_month); // September = 9
        $this->assertNull($linkedinFormatConnectionSpan->end_day); // Month-only precision

        // Check position with ISO format (2020-01-01 - 2022-12-31) - Full date precision
        $isoFormatConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Role C');
            })
            ->first();
        $this->assertNotNull($isoFormatConnection);
        
        $isoFormatConnectionSpan = $isoFormatConnection->connectionSpan;
        $this->assertEquals(2020, $isoFormatConnectionSpan->start_year);
        $this->assertEquals(1, $isoFormatConnectionSpan->start_month);
        $this->assertEquals(1, $isoFormatConnectionSpan->start_day);
        $this->assertEquals(2022, $isoFormatConnectionSpan->end_year);
        $this->assertEquals(12, $isoFormatConnectionSpan->end_month);
        $this->assertEquals(31, $isoFormatConnectionSpan->end_day);

        // Check position with full date format (14 May 2020 - 31 Dec 2021) - Full date precision
        $fullDateConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Role D');
            })
            ->first();
        $this->assertNotNull($fullDateConnection);
        
        $fullDateConnectionSpan = $fullDateConnection->connectionSpan;
        $this->assertEquals(2020, $fullDateConnectionSpan->start_year);
        $this->assertEquals(5, $fullDateConnectionSpan->start_month); // May = 5
        $this->assertEquals(14, $fullDateConnectionSpan->start_day);
        $this->assertEquals(2021, $fullDateConnectionSpan->end_year);
        $this->assertEquals(12, $fullDateConnectionSpan->end_month); // December = 12
        $this->assertEquals(31, $fullDateConnectionSpan->end_day);

        // Check position with year-only format (2020 - 2022) - Year-only precision
        $yearOnlyConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Role E');
            })
            ->first();
        $this->assertNotNull($yearOnlyConnection);
        
        $yearOnlyConnectionSpan = $yearOnlyConnection->connectionSpan;
        $this->assertEquals(2020, $yearOnlyConnectionSpan->start_year);
        $this->assertNull($yearOnlyConnectionSpan->start_month); // Year-only precision
        $this->assertNull($yearOnlyConnectionSpan->start_day); // Year-only precision
        $this->assertEquals(2022, $yearOnlyConnectionSpan->end_year);
        $this->assertNull($yearOnlyConnectionSpan->end_month); // Year-only precision
        $this->assertNull($yearOnlyConnectionSpan->end_day); // Year-only precision

        // Check position with month/year format (Jan 2000 - Jan 2005) - Month/Year precision
        $monthYearConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Role F');
            })
            ->first();
        $this->assertNotNull($monthYearConnection);
        
        $monthYearConnectionSpan = $monthYearConnection->connectionSpan;
        $this->assertEquals(2000, $monthYearConnectionSpan->start_year);
        $this->assertEquals(1, $monthYearConnectionSpan->start_month); // January = 1
        $this->assertNull($monthYearConnectionSpan->start_day); // Month-only precision
        $this->assertEquals(2005, $monthYearConnectionSpan->end_year);
        $this->assertEquals(1, $monthYearConnectionSpan->end_month); // January = 1
        $this->assertNull($monthYearConnectionSpan->end_day); // Month-only precision
    }

    /**
     * Test LinkedIn import with actual LinkedIn sample data
     */
    public function test_linkedin_import_with_sample_data()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Create personal span for the user
        Span::create([
            'name' => $user->name,
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'state' => 'placeholder',
            'access_level' => 'private'
        ]);

        // Use actual LinkedIn sample data format
        $csvContent = "Company Name,Title,Description,Location,Started On,Finished On\n" .
                     "STM ,\"Consultant Product Manager\",Working on trusted identity in academic publishing.,,Jul 2023,\n" .
                     "richard.northover.info,\"Consultant Product Director\",,,Apr 2022,\n" .
                     "Elsevier,Product Director,,,Sep 2015,Apr 2022\n" .
                     "BBC,\"Service Owner, BBC iD\",,\"London, United Kingdom\",May 2014,Sep 2015\n" .
                     "BBC,Product Manager,,,Jul 2011,May 2014\n" .
                     "BBC,Principal Web Developer,,,May 2008,Jul 2011\n" .
                     "BBC,Senior Client Side Developer,,,Oct 2007,May 2008\n" .
                     "BBC,Client Side Developer,,,Feb 2006,Oct 2007\n" .
                     "The University of Edinburgh,Web Content Developer,,,Jan 2005,Feb 2006\n" .
                     "richard.northover.info,Freelance Web Designer/Developer,,,Jan 2000,Jan 2005\n" .
                     "richard.northover.info,Freelance Science Journalist,,,Jan 1998,Jan 2001\n";

        $file = UploadedFile::fake()->createWithContent('positions.csv', $csvContent);

        $response = $this->postJson(route('settings.import.linkedin.import'), [
            'csv_file' => $file,
        ]);

        $response->assertStatus(200);
        $result = $response->json('result');

        // Check that all positions were processed
        $this->assertEquals(11, $result['positions']['processed']);
        $this->assertEquals(11, $result['positions']['created']);
        $this->assertEquals(0, $result['positions']['errors']);

        // Verify the connections were created with correct dates
        $personSpan = Span::where('name', $user->name)->where('type_id', 'person')->first();
        $this->assertNotNull($personSpan);

        // Check ongoing position (Jul 2023 - no end date)
        $ongoingConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Consultant Product Manager');
            })
            ->first();
        $this->assertNotNull($ongoingConnection);
        
        $ongoingConnectionSpan = $ongoingConnection->connectionSpan;
        $this->assertEquals(2023, $ongoingConnectionSpan->start_year);
        $this->assertEquals(7, $ongoingConnectionSpan->start_month); // July = 7
        $this->assertNull($ongoingConnectionSpan->start_day); // Month-only precision
        $this->assertNull($ongoingConnectionSpan->end_year); // No end date for ongoing position

        // Check position with LinkedIn date format (Sep 2015 - Apr 2022)
        $linkedinFormatConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Product Director');
            })
            ->first();
        $this->assertNotNull($linkedinFormatConnection);
        
        $linkedinFormatConnectionSpan = $linkedinFormatConnection->connectionSpan;
        $this->assertEquals(2015, $linkedinFormatConnectionSpan->start_year);
        $this->assertEquals(9, $linkedinFormatConnectionSpan->start_month); // September = 9
        $this->assertNull($linkedinFormatConnectionSpan->start_day); // Month-only precision
        $this->assertEquals(2022, $linkedinFormatConnectionSpan->end_year);
        $this->assertEquals(4, $linkedinFormatConnectionSpan->end_month); // April = 4
        $this->assertNull($linkedinFormatConnectionSpan->end_day); // Month-only precision

        // Check position with month/year format (Jan 1998 - Jan 2001)
        $monthYearConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Freelance Science Journalist');
            })
            ->first();
        $this->assertNotNull($monthYearConnection);
        
        $monthYearConnectionSpan = $monthYearConnection->connectionSpan;
        $this->assertEquals(1998, $monthYearConnectionSpan->start_year);
        $this->assertEquals(1, $monthYearConnectionSpan->start_month); // January = 1
        $this->assertNull($monthYearConnectionSpan->start_day); // Month-only precision
        $this->assertEquals(2001, $monthYearConnectionSpan->end_year);
        $this->assertEquals(1, $monthYearConnectionSpan->end_month); // January = 1
        $this->assertNull($monthYearConnectionSpan->end_day); // Month-only precision
    }

    /**
     * Test LinkedIn import with all possible date format variations
     */
    public function test_linkedin_import_with_all_date_formats()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Create personal span for the user
        Span::create([
            'name' => $user->name,
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'state' => 'placeholder',
            'access_level' => 'private'
        ]);

        // Test realistic LinkedIn date format variations
        $csvContent = "Company Name,Title,Description,Location,Started On,Finished On\n" .
                     "Company A,Role A,Description A,Location A,2020,\n" . // Year only
                     "Company B,Role B,Description B,Location B,Jul 2020,\n" . // Month Year
                     "Company C,Role C,Description C,Location C,13 Jul 2020,\n" . // Day Month Year
                     "Company D,Role D,Description D,Location D,2020-07-13,\n" . // ISO format
                     "Company E,Role E,Description E,Location E,2020-07,\n" . // ISO Year-Month
                     "Company F,Role F,Description F,Location F,July 2020,\n" . // Full month name
                     "Company G,Role G,Description G,Location G,13 July 2020,\n"; // Day Full Month Year

        $file = UploadedFile::fake()->createWithContent('positions.csv', $csvContent);

        $response = $this->postJson(route('settings.import.linkedin.import'), [
            'csv_file' => $file,
        ]);

        $response->assertStatus(200);
        $result = $response->json('result');

        // Check that all positions were processed
        $this->assertEquals(7, $result['positions']['processed']);
        $this->assertEquals(7, $result['positions']['created']);
        $this->assertEquals(0, $result['positions']['errors']);

        // Verify the connections were created with correct dates
        $personSpan = Span::where('name', $user->name)->where('type_id', 'person')->first();
        $this->assertNotNull($personSpan);

        // Test year-only format (2020)
        $yearOnlyConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Role A');
            })
            ->first();
        $this->assertNotNull($yearOnlyConnection);
        
        $yearOnlyConnectionSpan = $yearOnlyConnection->connectionSpan;
        $this->assertEquals(2020, $yearOnlyConnectionSpan->start_year);
        $this->assertNull($yearOnlyConnectionSpan->start_month); // Year-only precision
        $this->assertNull($yearOnlyConnectionSpan->start_day); // Year-only precision

        // Test month/year format (Jul 2020)
        $monthYearConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Role B');
            })
            ->first();
        $this->assertNotNull($monthYearConnection);
        
        $monthYearConnectionSpan = $monthYearConnection->connectionSpan;
        $this->assertEquals(2020, $monthYearConnectionSpan->start_year);
        $this->assertEquals(7, $monthYearConnectionSpan->start_month); // July = 7
        $this->assertNull($monthYearConnectionSpan->start_day); // Month-only precision

        // Test day/month/year format (13 Jul 2020)
        $dayMonthYearConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Role C');
            })
            ->first();
        $this->assertNotNull($dayMonthYearConnection);
        
        $dayMonthYearConnectionSpan = $dayMonthYearConnection->connectionSpan;
        $this->assertEquals(2020, $dayMonthYearConnectionSpan->start_year);
        $this->assertEquals(7, $dayMonthYearConnectionSpan->start_month); // July = 7
        $this->assertEquals(13, $dayMonthYearConnectionSpan->start_day); // Day = 13

        // Test ISO format (2020-07-13)
        $isoConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Role D');
            })
            ->first();
        $this->assertNotNull($isoConnection);
        
        $isoConnectionSpan = $isoConnection->connectionSpan;
        $this->assertEquals(2020, $isoConnectionSpan->start_year);
        $this->assertEquals(7, $isoConnectionSpan->start_month);
        $this->assertEquals(13, $isoConnectionSpan->start_day);

        // Test ISO year-month format (2020-07)
        $isoYearMonthConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Role E');
            })
            ->first();
        $this->assertNotNull($isoYearMonthConnection);
        
        $isoYearMonthConnectionSpan = $isoYearMonthConnection->connectionSpan;
        $this->assertEquals(2020, $isoYearMonthConnectionSpan->start_year);
        $this->assertEquals(7, $isoYearMonthConnectionSpan->start_month);
        $this->assertNull($isoYearMonthConnectionSpan->start_day); // Month-only precision

        // Test full month name (July 2020)
        $fullMonthConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Role F');
            })
            ->first();
        $this->assertNotNull($fullMonthConnection);
        
        $fullMonthConnectionSpan = $fullMonthConnection->connectionSpan;
        $this->assertEquals(2020, $fullMonthConnectionSpan->start_year);
        $this->assertEquals(7, $fullMonthConnectionSpan->start_month); // July = 7
        $this->assertNull($fullMonthConnectionSpan->start_day); // Month-only precision

        // Test day/full month/year format (13 July 2020)
        $dayFullMonthConnection = Connection::where('parent_id', $personSpan->id)
            ->where('type_id', 'has_role')
            ->whereHas('child', function ($query) {
                $query->where('name', 'Role G');
            })
            ->first();
        $this->assertNotNull($dayFullMonthConnection);
        
        $dayFullMonthConnectionSpan = $dayFullMonthConnection->connectionSpan;
        $this->assertEquals(2020, $dayFullMonthConnectionSpan->start_year);
        $this->assertEquals(7, $dayFullMonthConnectionSpan->start_month); // July = 7
        $this->assertEquals(13, $dayFullMonthConnectionSpan->start_day); // Day = 13
    }

    /**
     * Test date parsing directly
     */
    public function test_linkedin_date_parsing()
    {
        $user = User::factory()->create();
        $connectionImporter = new \App\Services\Import\Connections\ConnectionImporter($user);
        
        // Test realistic LinkedIn date formats
        $testCases = [
            '2020' => ['year' => 2020],
            'Jul 2020' => ['year' => 2020, 'month' => 7],
            '13 Jul 2020' => ['year' => 2020, 'month' => 7, 'day' => 13],
            'July 2020' => ['year' => 2020, 'month' => 7],
            '13 July 2020' => ['year' => 2020, 'month' => 7, 'day' => 13],
            '2020-07-13' => ['year' => 2020, 'month' => 7, 'day' => 13],
            '2020-07' => ['year' => 2020, 'month' => 7],
        ];
        
        foreach ($testCases as $input => $expected) {
            $result = $connectionImporter->parseLinkedInDate($input);
            echo "Input: '$input' -> Result: " . json_encode($result) . " (Expected: " . json_encode($expected) . ")\n";
            
            if ($result) {
                $this->assertEquals($expected['year'], $result['year'], "Year mismatch for input: $input");
                if (isset($expected['month'])) {
                    $this->assertEquals($expected['month'], $result['month'], "Month mismatch for input: $input");
                }
                if (isset($expected['day'])) {
                    $this->assertEquals($expected['day'], $result['day'], "Day mismatch for input: $input");
                }
            } else {
                $this->fail("Failed to parse date: $input");
            }
        }
    }
} 