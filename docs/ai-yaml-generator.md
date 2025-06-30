# AI YAML Generator

The AI YAML Generator is a powerful feature that uses ChatGPT to automatically create biographical YAML records for people based on publicly available information.

## Overview

This feature allows users to input a person's name (and optional disambiguation hint) and receive a structured YAML record that includes:

- Basic biographical information (name, birth date, occupation, nationality)
- Family connections (parents, children, relationships)
- Education history
- Employment history
- Notable residences
- Professional roles and positions

## How to Use

### 1. Access the Generator

Navigate to the AI Generator in the main navigation menu, or visit `/ai-yaml-generator`.

### 2. Input Person Details

- **Person's Name**: Enter the full name of the person (required)
- **Disambiguation Hint**: Optional hint to distinguish from others with the same name (e.g., "the naturalist and broadcaster" for David Attenborough)

### 3. Generate YAML

Click the "Generate YAML" button. The system will:

1. Research the person using publicly available sources
2. Generate structured YAML following our schema
3. Validate the YAML syntax
4. Display the results with usage statistics

### 4. Use the Generated YAML

Once generated, you can:

- **Copy YAML**: Copy the generated YAML to your clipboard
- **Use in Editor**: Open the YAML in the span editor for further editing and application to the database

## Features

### Caching
Results are cached for 24 hours to avoid duplicate API calls and reduce costs.

### Validation
The generated YAML is automatically validated for syntax correctness.

### Usage Tracking
Token usage is displayed to help monitor API costs.

### Error Handling
Comprehensive error handling for API failures, invalid responses, and network issues.

## Technical Details

### API Integration
- Uses OpenAI's GPT-4 model
- Configured with `OPENAI_API_KEY` environment variable
- Temperature set to 0.3 for consistent output

### YAML Schema
The AI follows a strict YAML schema that includes:

```yaml
name: Full Name
type: person
state: placeholder
start: YYYY-MM-DD (birth date)
end: null (if alive) or YYYY-MM-DD (if deceased)
metadata:
  gender: male/female/other
  birth_name: Name at birth (if different)
  occupation: Primary occupation
  nationality: Nationality
access_level: public
connections:
  # Family connections, education, employment, etc.
has_role:
  # Professional roles with nested organisation connections
```

### Prompt Engineering
The system uses a carefully crafted prompt that:

1. Instructs the AI to use only publicly verifiable information
2. Provides detailed schema requirements
3. Emphasizes accuracy over completeness
4. Ensures proper YAML formatting

## Configuration

### Environment Variables

Add to your `.env` file:

```env
OPENAI_API_KEY=your_openai_api_key_here
```

### Service Configuration

The service is configured in `config/services.php`:

```php
'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
],
```

## Security and Privacy

- Only uses publicly available information
- No personal or private data is sent to OpenAI
- Results are cached but not stored permanently
- API keys are kept secure in environment variables

## Cost Considerations

- Uses GPT-4 model (more expensive but higher quality)
- Results are cached for 24 hours to reduce duplicate calls
- Token usage is displayed for monitoring
- Consider implementing rate limiting for production use

## Troubleshooting

### Common Issues

1. **"OpenAI API key not configured"**
   - Ensure `OPENAI_API_KEY` is set in your environment

2. **"Failed to generate YAML"**
   - Check your internet connection
   - Verify your OpenAI API key is valid
   - Check OpenAI service status

3. **Invalid YAML generated**
   - The system will show validation errors
   - Try regenerating with a more specific disambiguation hint

### Error Logging

Errors are logged to Laravel's log system with context including:
- Person name and disambiguation
- API response details
- Token usage information

## Future Enhancements

Potential improvements could include:

- Support for other entity types (organisations, places)
- Multiple language support
- Enhanced disambiguation options
- Integration with existing span search
- Batch processing capabilities
- Custom prompt templates 