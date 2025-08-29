# Railway Upload Limits Configuration

## Overview

Railway has default request size limits that can cause 413 "Payload Too Large" errors when uploading files. This document explains how we've configured the application to handle these limits.

## Problem

When uploading photos in production on Railway, users may encounter:
- `[Error] Failed to load resource: the server responded with a status of 413 ()`
- `[Error] Failed to load resource: The network connection was lost.`

This happens because Railway's proxy layer has a default request size limit that's smaller than what we need for photo uploads.

## Solution

We've implemented several layers of configuration to handle Railway's upload limits:

### 1. Nginx Configuration

The production nginx configuration (`docker/prod/nginx.conf`) includes:
```nginx
client_max_body_size 100M;
```

This allows uploads up to 100MB at the nginx level.

### 2. PHP Configuration

The PHP configuration (`docker/prod/php.ini`) includes:
```ini
upload_max_filesize = 64M
post_max_size = 64M
```

This allows uploads up to 64MB at the PHP level.

### 3. Railway Configuration

We've added Railway-specific configuration:

#### railway.toml
```toml
[deploy.env]
RAILWAY_UPLOAD_SIZE_LIMIT = "100M"
RAILWAY_REQUEST_TIMEOUT = "300"
```

#### railway.json
```json
{
  "variables": {
    "RAILWAY_UPLOAD_SIZE_LIMIT": "100M",
    "RAILWAY_REQUEST_TIMEOUT": "300"
  }
}
```

### 4. Application-Level Handling

#### Middleware
The `RailwayUploadLimits` middleware logs upload attempts and provides debugging information.

#### Frontend Error Handling
The photo upload form now provides better error messages for 413 errors, including:
- Specific error message for 413 status codes
- Helpful guidance about Railway's upload limits
- Console logging for debugging

## Testing Upload Limits

To test if the configuration is working:

1. Try uploading a photo larger than 5MB but smaller than 50MB
2. Check the browser console for any error messages
3. Check the application logs for upload attempts
4. Verify that the upload completes successfully

## Troubleshooting

If uploads are still failing with 413 errors:

1. **Check Railway Dashboard**: Verify that the environment variables are set correctly
2. **Check Application Logs**: Look for upload attempts in the Laravel logs
3. **Check Browser Console**: Look for detailed error information
4. **Contact Railway Support**: If the issue persists, Railway may need to adjust their proxy settings

## Monitoring

The application now includes:
- Logging of all upload attempts
- Warning logs for large uploads (>50MB)
- Detailed error information in the browser console
- User-friendly error messages for 413 errors

## Future Improvements

If Railway continues to have issues with upload limits, consider:
1. Implementing chunked uploads for large files
2. Using direct-to-R2 uploads (bypassing the application server)
3. Implementing client-side image compression before upload
