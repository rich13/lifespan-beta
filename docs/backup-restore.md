# Database Backup and Restore Process

This document outlines the process for backing up and restoring the database.

## Backup Process

1. Create a backup using Laravel's backup command:
   ```bash
   php artisan backup:run --only-db --disable-notifications
   ```
   This will create a backup file in `storage/app/Laravel/` with a name like `backup-YYYY-MM-DD-HH-mm-ss.zip`

2. Copy the backup to a safe location:
   ```bash
   mkdir -p backups
   cp storage/app/Laravel/backup-*.zip backups/
   ```

## Restore Process

1. First, drop and recreate the public schema to ensure a clean slate:
   ```bash
   psql lifespan_beta -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
   ```

2. Extract the SQL file from the backup (replace YYYY-MM-DD-HH-mm-ss with your backup timestamp):
   ```bash
   unzip -p backups/backup-YYYY-MM-DD-HH-mm-ss.zip db-dumps/postgresql-lifespan_beta-YYYY-MM-DD-HH-mm-ss.sql > restore.sql
   ```

3. Restore the database:
   ```bash
   psql lifespan_beta < restore.sql
   ```

4. Verify the restore by checking the counts:
   ```bash
   php artisan tinker --execute="echo 'Spans: ' . \App\Models\Span::count() . PHP_EOL . 'Connections: ' . \App\Models\Connection::count();"
   ```

5. Clean up the temporary SQL file:
   ```bash
   rm restore.sql
   ```

## Important Notes

- Always keep backups in a safe location outside the project directory
- The restore process will completely replace the current database state
- Make sure you have the correct database name in your `.env` file
- The backup file contains only the database dump, not files or other data
- You can check the contents of a backup file using `unzip -l backups/backup-*.zip` 