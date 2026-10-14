<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\DB;

/**
 * Dump SQL în PHP pur (fără mysqldump/exec — adesea indisponibile pe cPanel), comprimat gzip, în streaming.
 * Suportă MySQL/MariaDB și SQLite.
 */
class DatabaseDumper
{
    public function dump(string $targetGz): int
    {
        $conn = DB::connection();
        $driver = $conn->getDriverName();
        $pdo = $conn->getPdo();
        $gz = gzopen($targetGz, 'wb6');
        if ($gz === false) {
            throw new UpdateException('Nu pot crea fișierul de backup al bazei de date.');
        }

        $write = fn (string $s) => gzwrite($gz, $s);
        $write("-- Autentik Market — backup bază de date\n-- Data: ".now()->toIso8601String()."\n-- Driver: {$driver}\n\n");

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            $tables = array_map(fn ($r) => array_values((array) $r)[0], $conn->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"));
            foreach ($tables as $table) {
                $create = (array) $conn->selectOne("SHOW CREATE TABLE `{$table}`");
                $write("DROP TABLE IF EXISTS `{$table}`;\n".array_values($create)[1].";\n\n");
                $this->dumpRows($table, $write, $pdo, '`');
            }
            $write("SET FOREIGN_KEY_CHECKS=1;\n");
        } elseif ($driver === 'sqlite') {
            $write("PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n");
            $rows = $conn->select("SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
            foreach ($rows as $row) {
                $write("DROP TABLE IF EXISTS \"{$row->name}\";\n{$row->sql};\n");
                $this->dumpRows($row->name, $write, $pdo, '"');
            }
            $write("COMMIT;\nPRAGMA foreign_keys=ON;\n");
        } else {
            gzclose($gz);
            throw new UpdateException("Driver DB nesuportat pentru backup: {$driver}");
        }

        gzclose($gz);

        return (int) filesize($targetGz);
    }

    protected function dumpRows(string $table, callable $write, \PDO $pdo, string $q): void
    {
        $columns = DB::getSchemaBuilder()->getColumnListing($table);
        if ($columns === []) {
            return;
        }
        $colList = implode(',', array_map(fn ($c) => $q.$c.$q, $columns));
        $hasId = in_array('id', $columns, true);
        $offset = 0;
        $chunk = 500;

        while (true) {
            $query = DB::table($table)->limit($chunk);
            $hasId ? $query->orderBy('id')->offset($offset) : $query->offset($offset);
            $rows = $query->get();
            if ($rows->isEmpty()) {
                break;
            }
            $values = [];
            foreach ($rows as $row) {
                $vals = [];
                foreach ($columns as $c) {
                    $v = $row->{$c} ?? null;
                    $vals[] = $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v));
                }
                $values[] = '('.implode(',', $vals).')';
            }
            $write("INSERT INTO {$q}{$table}{$q} ({$colList}) VALUES\n".implode(",\n", $values).";\n");
            $offset += $chunk;
        }
        $write("\n");
    }
}
