#!/bin/bash

# Script to copy YAML files from storage/app/imports to yaml-samples directory
# This script should be run locally to update the repository with the latest YAML files

# Create directory if it doesn't exist
mkdir -p yaml-samples

# Count existing files
yaml_count=$(find yaml-samples -name "*.yaml" | wc -l)
echo "Found $yaml_count existing YAML files in yaml-samples directory"

# Clear directory if it has files
if [ $yaml_count -gt 0 ]; then
    read -p "Do you want to replace existing YAML files? (y/n): " confirm
    if [[ $confirm =~ ^[Yy]$ ]]; then
        rm -f yaml-samples/*.yaml
        echo "Cleared existing YAML files"
    else
        echo "Operation cancelled"
        exit 0
    fi
fi

# Check if storage/app/imports directory exists and has YAML files
if [ ! -d "storage/app/imports" ]; then
    echo "Error: storage/app/imports directory not found"
    exit 1
fi

# Count YAML files in imports directory
import_count=$(find storage/app/imports -name "*.yaml" | wc -l)
if [ $import_count -eq 0 ]; then
    echo "Error: No YAML files found in storage/app/imports"
    exit 1
fi

# Copy files
echo "Copying $import_count YAML files to yaml-samples directory..."
cp storage/app/imports/*.yaml yaml-samples/

# Verify copy
new_count=$(find yaml-samples -name "*.yaml" | wc -l)
echo "Successfully copied $new_count YAML files to yaml-samples directory"

echo "Done! Remember to commit and push these changes to your repository." 