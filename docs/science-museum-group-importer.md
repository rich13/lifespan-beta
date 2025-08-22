# Science Museum Group Importer

This importer allows you to import objects, people, and places from the Science Museum Group's open data collection into Lifespan.

## Overview

The Science Museum Group (SMG) maintains a vast collection of scientific and technological objects, along with information about their creators and places of origin. This importer provides a user-friendly interface to:

1. **Search** for objects in the SMG collection
2. **Preview** what will be imported (objects, creators, places, images)
3. **Import** the data as spans in Lifespan
4. **Connect** related spans with appropriate relationships

## How It Works

### 1. Search for Objects
- Use the search interface to find objects by name, description, or keywords
- Results are paginated and show thumbnails when available
- Click on any result to view detailed information

### 2. Preview Import
- Before importing, the system shows you exactly what will be created:
  - **Objects** → imported as `thing` spans
  - **Creators/Makers** → imported as `person` spans
  - **Places** → imported as `place` spans
  - **Images** → imported as `photo` spans
- The system checks for existing spans to avoid duplicates
- You can choose which elements to import

### 3. Import Data
- The importer creates spans with rich metadata from SMG
- Automatically creates connections between related spans:
  - Object → Creator (creator relationship)
  - Object → Place (location relationship)
  - Object → Image (depicts relationship)
- All imported data is marked as public and includes source attribution

## Data Mapping

### Objects (Thing Spans)
- **Name**: Object title from SMG
- **Description**: Catalogue description
- **Dates**: Creation dates (start/end years)
- **Metadata**: SMG ID, identifiers, categories, source info
- **Sources**: Link back to original SMG record

### People (Person Spans)
- **Name**: Preferred name from SMG
- **Description**: Biography
- **Dates**: Birth and death dates
- **Metadata**: Nationality, occupation, gender, birth/death places
- **Sources**: Link back to original SMG record

### Places (Place Spans)
- **Name**: Place name from SMG
- **Description**: Place description
- **Metadata**: SMG ID, source info
- **Sources**: Link back to original SMG record

### Images (Photo Spans)
- **Name**: Generic "Image from Science Museum Group"
- **Description**: Image credit information
- **Metadata**: Image URLs (thumbnail, medium, full size)
- **Sources**: Image URL and credit information

## Usage

### Accessing the Importer
1. Go to the Admin Dashboard
2. Click on "Science Museum Group" in the import tools section
3. Or navigate directly to `/admin/import/science-museum-group`

### Step-by-Step Process
1. **Search**: Enter keywords to find objects (e.g., "televisor", "computer", "engine")
2. **Select**: Click on an object from the search results
3. **Review**: Examine the object details and related data
4. **Preview**: Click "Preview Import" to see what will be created
5. **Configure**: Choose which elements to import (object, makers, places, images)
6. **Import**: Click "Import Object" to create the spans and connections

### Import Options
- **Import object**: Create the main object as a thing span
- **Import makers**: Create person spans for creators and connect them
- **Import places**: Create place spans for locations and connect them
- **Import images**: Create photo spans for images and connect them

## Technical Details

### API Integration
- Uses the Science Museum Group's public JSON API
- Implements caching to improve performance
- Handles rate limiting and error conditions gracefully

### Duplicate Detection
- Checks for existing spans by name and type
- Connects to existing spans rather than creating duplicates
- Provides clear feedback about what will be created vs. connected

### Data Quality
- Preserves all original SMG metadata
- Maintains source attribution and links
- Handles missing or incomplete data gracefully

## Examples

### Example: Baird Televisor
Searching for "televisor" might find the "Baird televisor" object:

**Object**: Baird televisor (thing span)
- Created by: John Logie Baird (person span)
- Made in: United Kingdom (place span)
- Images: Museum photographs (photo spans)

**Connections**:
- Baird televisor → John Logie Baird (creator)
- Baird televisor → United Kingdom (location)
- Baird televisor → Images (depicts)

### Example: Computer
Searching for "computer" might find various computing devices:

**Object**: Early computer (thing span)
- Created by: Various inventors (person spans)
- Made in: Various locations (place spans)
- Images: Technical diagrams and photos (photo spans)

## Troubleshooting

### Common Issues
1. **Search not working**: Check your internet connection and try clearing the cache
2. **No results found**: Try different search terms or broader keywords
3. **Import fails**: Check that you have admin permissions and the database is accessible

### Cache Management
- Use the "Clear Cache" button to refresh data from SMG
- Cache is automatically managed for performance

### Error Handling
- The system logs errors for debugging
- Failed imports are rolled back to maintain data integrity
- User-friendly error messages are displayed

## Future Enhancements

Potential improvements to consider:
- Bulk import functionality
- Advanced search filters
- Import history and statistics
- Custom mapping of SMG categories to Lifespan types
- Integration with other museum APIs

## License and Attribution

This importer respects the Science Museum Group's data usage policies and includes proper attribution for all imported content. All imported data maintains links back to the original SMG records.

