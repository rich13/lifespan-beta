const fs = require('fs');
const path = require('path');

// Source and destination directories
const sourceDir = path.resolve(__dirname, '../node_modules/bootstrap-icons/font/fonts');
const destDir = path.resolve(__dirname, '../public/fonts');

// Create destination directory if it doesn't exist
if (!fs.existsSync(destDir)) {
    fs.mkdirSync(destDir, { recursive: true });
    console.log(`Created directory ${destDir}`);
}

// Copy font files
try {
    if (fs.existsSync(sourceDir)) {
        const files = fs.readdirSync(sourceDir);
        
        files.forEach(file => {
            const sourcePath = path.join(sourceDir, file);
            const destPath = path.join(destDir, file);
            
            fs.copyFileSync(sourcePath, destPath);
            console.log(`Copied ${file} to ${destDir}`);
        });
        
        console.log('Font copying completed successfully!');
    } else {
        console.error(`Source directory ${sourceDir} does not exist`);
    }
} catch (error) {
    console.error('Error copying fonts:', error);
} 