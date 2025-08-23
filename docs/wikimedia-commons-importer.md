# Wikimedia Commons Importer

This importer allows you to import images from Wikimedia Commons into Lifespan and connect them to existing spans via subject_of connections.

## Overview

Wikimedia Commons is a repository of free media files, including millions of images that are freely licensed. This importer provides a user-friendly interface to:

1. **Search** for images in Wikimedia Commons by keywords or person names
2. **Search by year** to find images from specific time periods
3. **Preview** what will be imported before committing
4. **Import** images as photo spans in Lifespan
5. **Connect** images to existing spans via subject_of connections

## How It Works

### 1. Search for Images
- Use the search interface to find images by person name, keywords, or specific years
- Results are paginated and show image titles and snippets
- Click on any result to view detailed information

### 2. Preview Import
- Before importing, the system shows you exactly what will be created:
  - **Image span** → imported as `thing` span with `photo` subtype
  - **Subject_of connection** → connects the image to the target span
- The system checks for existing images to avoid duplicates
- You can see what will be created vs. what will be connected

### 3. Import Image
- The importer creates image spans with rich metadata from Wikimedia Commons
- Automatically creates subject_of connections between the image and target span
- All imported data is marked as public and includes source attribution

## Data Mapping

### Images (Photo Spans)
- **Name**: Cleaned image title from Wikimedia Commons
- **Description**: Image description and author information
- **Dates**: Extracted from image metadata (if available)
- **Metadata**: 
  - Wikimedia Commons ID and title
  - Image URLs (thumbnail, medium, large, original)
  - Image dimensions and file size
  - Author, license, and source information
  - Categories and tags
- **Sources**: Link back to original Wikimedia Commons page

### Connections
- **Type**: `subject_of` connection
- **Direction**: Image → Target Span (Image features Target Span)
- **Timeless**: No temporal constraints

## Usage

### Accessing the Importer
1. Go to the Admin Dashboard
2. Click on "Wikimedia Commons" in the import tools section
3. Or navigate directly to `/admin/import/wikimedia-commons`

### Step-by-Step Process
1. **Search**: Enter keywords to find images (e.g., "Paul McCartney", "Beatles", "London")
2. **Optional Year**: Add a specific year to find images from that time period
3. **Select**: Click on an image from the search results
4. **Review**: Examine the image details and metadata
5. **Choose Target**: Select the span you want to connect this image to
6. **Preview**: See what will be created or connected
7. **Import**: Click "Import Image" to create the span and connection

### Search Options
- **General Search**: Find images by keywords or names
- **Year Search**: Find images from specific years (useful for historical photos)
- **Pagination**: Browse through large result sets

## Examples

### Finding Photos of Paul McCartney
1. Search for "Paul McCartney"
2. Optionally add year "1965" to find photos from that era
3. Browse through results showing Paul McCartney at different times
4. Select an image and connect it to the Paul McCartney person span

### Finding Historical Photos
1. Search for "London 1960s" to find photos of London from the 1960s
2. Browse through results showing various London scenes
3. Select an image and connect it to a relevant place or event span

## Technical Details

### API Integration
- Uses the Wikimedia Commons MediaWiki API
- Implements caching to improve performance
- Handles rate limiting and error conditions gracefully

### Duplicate Detection
- Checks for existing images by Wikimedia Commons ID
- Connects to existing images rather than creating duplicates
- Provides clear feedback about what will be created vs. connected

### Data Quality
- Preserves all original Wikimedia Commons metadata
- Maintains source attribution and links
- Handles missing or incomplete data gracefully
- Extracts dates from various formats in image descriptions

### Image URLs
- Wikimedia Commons provides direct image URLs
- All image sizes use the same URL (Wikimedia serves optimized versions)
- Images are served directly from Wikimedia's CDN

## License and Attribution

All images imported from Wikimedia Commons maintain their original licensing information. The importer:

- Preserves the original license information in the span metadata
- Includes author attribution when available
- Links back to the original Wikimedia Commons page
- Respects Creative Commons and other free licenses

## Limitations

- Images are served directly from Wikimedia Commons (not downloaded)
- Some images may have limited metadata
- Date extraction depends on the quality of image descriptions
- Search results depend on Wikimedia Commons indexing

## Troubleshooting

### Common Issues
- **No search results**: Try different keywords or check spelling
- **Image not loading**: Check if the Wikimedia Commons image is still available
- **Import fails**: Verify the target span exists and you have permission to edit it

### Performance
- Search results are cached for 1 hour
- Image details are cached for 24 hours
- Use the "Clear Cache" button if you encounter stale data

## Future Enhancements

Potential improvements to the importer:

- **Category-based search**: Search within specific Wikimedia Commons categories
- **Batch import**: Import multiple images at once
- **Advanced filtering**: Filter by license type, image size, etc.
- **Automatic date detection**: Better parsing of dates from image descriptions
- **Image download**: Option to download and store images locally
