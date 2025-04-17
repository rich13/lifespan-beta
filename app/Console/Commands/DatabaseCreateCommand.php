<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class DatabaseCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:create 
                           {--database=testing : The database connection to use}
                           {--name= : The name of the database to create}
                           {--force : Force the operation to run without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new database for testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = $this->option('database');
        $dbName = $this->option('name');
        $force = $this->option('force');

        if (empty($dbName)) {
            $dbName = Config::get("database.connections.$connection.database");
        }

        if (!$force && !$this->confirm("Are you sure you want to create database '$dbName'?")) {
            return 1;
        }

        try {
            // Get the default connection
            $defaultConnection = Config::get('database.default');
            $dbConfig = Config::get("database.connections.$connection");
            
            // Create a temporary connection to PostgreSQL without specifying a database
            Config::set('database.connections.temp_postgres', [
                'driver' => 'pgsql',
                'host' => $dbConfig['host'],
                'port' => $dbConfig['port'],
                'database' => 'postgres', // Connect to the default postgres database
                'username' => $dbConfig['username'],
                'password' => $dbConfig['password'],
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
            ]);

            // Connect to the temporary connection
            $tempConnection = DB::connection('temp_postgres');

            // Check if the database already exists
            $databaseExists = $tempConnection->select(
                "SELECT 1 FROM pg_database WHERE datname = ?",
                [$dbName]
            );

            if (empty($databaseExists)) {
                // Create the database
                $tempConnection->statement("CREATE DATABASE $dbName");
                $this->info("Database '$dbName' created successfully");
            } else {
                $this->info("Database '$dbName' already exists");
            }

            // Close the temporary connection
            $tempConnection->disconnect();
            
            // Remove the temporary connection
            Config::set('database.connections.temp_postgres', null);

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to create database: " . $e->getMessage());
            return 1;
        }
    }
} 