# LinkedIn Import Feature

## Overview

The LinkedIn import feature allows users to import their work history from LinkedIn's data export into the Lifespan system. This creates a structured representation of their professional experience with proper connections between people, roles, and organisations.

## How It Works

### Data Structure

The import creates the following structure:

1. **Person Span** - The individual whose work history is being imported
2. **Role Spans** - Each job title/position becomes a role span
3. **Organisation Spans** - Each company becomes an organisation span
4. **Connections**:
   - `has_role` connection between person and role (with dates)
   - `at_organisation` connection between role connection and organisation (with dates)

### Import Process

1. **Upload CSV File** - Users upload their LinkedIn `Positions.csv` file
2. **Preview Import** - The system analyzes the data and shows what will be created vs connected to existing spans
3. **Confirm Import** - Users review the preview and confirm the import
4. **Import Execution** - The system creates the spans and connections

## Preview Functionality

The preview feature provides detailed information about what the import will do:

### Person Analysis
- Shows whether the person span exists or will be created
- Indicates if "Create Person if Not Found" is required

### Summary Statistics
- Total positions in the CSV
- Number of valid vs invalid positions
- Number of new organisations that will be created
- Number of new roles that will be created

### Detailed Breakdown

#### Organisations
- **Will Create**: List of new organisation spans that will be created
- **Will Connect**: List of existing organisation spans that will be connected to

#### Roles
- **Will Create**: List of new role spans that will be created
- **Will Connect**: List of existing role spans that will be connected to

#### Position Details
- Table showing each position with:
  - Company name and title
  - Whether organisation/role will be created or connected
  - Date ranges
  - Validation status

### Validation
- Checks for required fields (Company Name, Title)
- Shows validation errors for invalid rows
- Prevents import if person doesn't exist and "Create Person" is disabled

## CSV Format

The import expects a LinkedIn `Positions.csv` file with the following columns:

- `Company Name` (required)
- `Title` (required)
- `Description` (optional)
- `Location` (optional)
- `Started On` (optional)
- `Finished On` (optional)

### Date Format Support

The importer handles LinkedIn's various date formats and precision levels:

#### Supported Formats:
- **Year only**: `2020`, `2022`
- **Month/Year**: `Jul 2023`, `May 2014`, `Jan 2000`
- **Full date**: `2020-01-01`, `14 May 2020`, `May 14, 2020`
- **Ongoing positions**: Empty `Finished On` field

#### Precision Mapping:
- **Year-only dates**: Only `start_year`/`end_year` are set (month/day are null)
- **Month/Year dates**: `start_year`/`start_month` and `end_year`/`end_month` are set (day is null)
- **Full dates**: All fields (`start_year`/`start_month`/`start_day` and `end_year`/`end_month`/`end_day`) are set

#### Ongoing Positions:
- When `Finished On` is empty, the position is treated as ongoing
- Only start date fields are populated, end date fields remain null
- This correctly represents current/ongoing employment relationships

## Usage

### Step 1: Export LinkedIn Data
1. Go to LinkedIn account settings
2. Request a copy of your data (Data Export)
3. Download the ZIP file and extract it
4. Find the `Positions.csv` file

### Step 2: Import
1. Navigate to Settings > Import Settings > LinkedIn Import
2. Upload the `Positions.csv` file
3. Enter the person name
4. Choose options:
   - **Create Person if Not Found**: Creates a new person span if one doesn't exist
   - **Update Existing Positions**: Updates existing connections if they exist
5. Click "Preview Import" to see what will be created
6. Review the preview and click "Confirm Import" to proceed

## Technical Implementation

### Files
- `app/Http/Controllers/LinkedInImportController.php` - Controller handling import requests
- `app/Services/LinkedInImportService.php` - Service handling CSV parsing and import logic
- `resources/views/settings/import/linkedin/index.blade.php` - Import UI
- `tests/Feature/LinkedInImportTest.php` - Feature tests

### Key Methods
- `previewCsv()` - Analyzes CSV and generates preview data
- `importCsv()` - Performs the actual import
- `generateImportPreview()` - Creates detailed preview information
- `processPosition()` - Processes individual position rows

### Database Impact
- Creates new spans for organisations and roles that don't exist
- Creates connections between existing spans
- Preserves existing data - only creates new spans and connections
- Uses transactions to ensure data consistency

## Error Handling

- Validates CSV format and required headers
- Shows validation errors for individual rows
- Prevents import if person doesn't exist (unless "Create Person" is enabled)
- Uses database transactions to rollback on errors
- Logs import activities for debugging

## Testing

The feature includes comprehensive tests covering:
- Successful imports with new and existing data
- Preview functionality
- Error handling for invalid data
- Edge cases like missing persons and invalid CSV files

Run tests with:
```bash
./scripts/run-pest.sh --filter=LinkedInImportTest
``` 