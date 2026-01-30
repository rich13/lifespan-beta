#!/usr/bin/env php
<?php
/**
 * Create per-process test databases for parallel test runs.
 * Creates lifespan_beta_testing_test_1 through _N so each Pest/Paratest worker has its own DB.
 *
 * Usage: php scripts/create-parallel-test-databases.php [N]
 * Default N=8. Run from repo root (or /var/www in Docker).
 */

$numDbs = isset($argv[1]) ? (int) $argv[1] : 8;
if ($numDbs < 1 || $numDbs > 32) {
    fwrite(STDERR, "Usage: php create-parallel-test-databases.php [N]\nN must be 1-32 (default 8).\n");
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$config = config('database.connections.testing');
$host = $config['host'];
$port = $config['port'] ?? 5432;
$user = $config['username'];
$pass = $config['password'];

$dsn = "pgsql:host={$host};port={$port};dbname=postgres";
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    fwrite(STDERR, "Could not connect to PostgreSQL as postgres: " . $e->getMessage() . "\n");
    exit(1);
}

$base = 'lifespan_beta_testing_test_';
for ($i = 1; $i <= $numDbs; $i++) {
    $db = $base . $i;
    $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = " . $pdo->quote($db));
    if ($stmt && $stmt->fetch()) {
        echo "Database {$db} already exists, skipping.\n";
        continue;
    }
    try {
        $pdo->exec('CREATE DATABASE "' . $db . '"');
        echo "Created database: {$db}\n";
    } catch (PDOException $e) {
        fwrite(STDERR, "Failed to create {$db}: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "Done. Created/verified {$numDbs} parallel test databases.\n";
