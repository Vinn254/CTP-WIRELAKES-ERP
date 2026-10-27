<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Writes production.sql: every table's structure, plus only the rows the programme itself owns.
 *
 * Registers, projects, cohorts, ledgers and all member and staff accounts are deliberately left out — a fresh
 * import must open on an empty office with no demo login anywhere. Re-run this before go-live to carry the latest
 * catalogue and website copy across.
 */
class ExportProductionDatabase extends Command
{
    protected $signature = 'db:export-production {--out=production.sql : File to write, relative to the web folder}';

    protected $description = 'Export the schema plus real programme data as production.sql, without any demo accounts';

    /** Tables whose rows are the programme's own reference content, not test records. */
    private const KEEP_DATA = [
        'migrations',
        'counties',
        'settings',
        'trainings',
        'training_modules',
        'content_sections',
        'content_fields',
        'content_entries',
        'content_values',
    ];

    public function handle(): int
    {
        $path = $this->option('out');
        $full = str_starts_with($path, '/') || str_contains($path, ':\\')
            ? $path
            : base_path($path);

        $tables = array_map(
            fn ($row) => array_values((array) $row)[0],
            DB::select("SELECT TABLE_NAME FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")
        );

        $pdo = DB::connection()->getPdo();
        $out = fopen($full, 'w');
        fwrite($out, $this->header());

        foreach ($tables as $table) {
            fwrite($out, $this->createBlock($table));
            if (in_array($table, self::KEEP_DATA, true)) {
                fwrite($out, $this->insertBlock($table, $pdo));
            }
        }

        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($out);

        $this->info('Wrote ' . $full);
        $this->line('Tables: ' . count($tables) . ' · rows carried for: ' . implode(', ', self::KEEP_DATA));
        $this->line('Accounts in the export: 0 (the users table is created empty)');

        return self::SUCCESS;
    }

    private function header(): string
    {
        return "-- WireLakes Technologies production database\n"
            . "-- Generated " . now() . " by `php artisan db:export-production`\n"
            . "-- Structure for every module, plus the counties, fees, training catalogue and website content.\n"
            . "-- No members, no staff accounts, no test records. Import into an empty database.\n\n"
            . "SET NAMES utf8mb4;\n"
            . "SET FOREIGN_KEY_CHECKS=0;\n"
            . "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";
    }

    private function createBlock(string $table): string
    {
        $row = (array) DB::selectOne("SHOW CREATE TABLE `$table`");
        $sql = preg_replace('/\s+AUTO_INCREMENT=\d+/', '', $row['Create Table']);

        return "-- ---- $table ----\nDROP TABLE IF EXISTS `$table`;\n$sql;\n\n";
    }

    private function insertBlock(string $table, \PDO $pdo): string
    {
        $columns = array_column(DB::select("SHOW COLUMNS FROM `$table`"), 'Field');
        $head = '-- rows: ' . DB::table($table)->count() . "\nINSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";

        $rows = DB::table($table)->orderBy(DB::raw('`' . $columns[0] . '`'))->get();
        $lines = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $values = array_map(fn ($c) => $pdo->quote($row[$c] ?? null), $columns);
            $lines[] = '  (' . implode(', ', $values) . ')';
        }

        return $rows->isEmpty() ? '' : $head . implode(",\n", $lines) . ";\n\n";
    }
}
