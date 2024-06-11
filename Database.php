<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $query = $this->processQuery($query, $args);
        return $query;
    }

    public function skip()
    {
        return '__SKIP__';
    }

    private function processQuery(string $query, array $args): string
    {
        $query = preg_replace_callback('/\?([dfas#]?)/', function ($matches) use (&$args) {
            $specifier = $matches[1];
            if (empty($args)) {
                throw new Exception('Not enough arguments for query.');
            }
            $arg = array_shift($args);
            switch ($specifier) {
                case 'd':
                    return is_null($arg) ? 'NULL' : (int)$arg;
                case 'f':
                    return is_null($arg) ? 'NULL' : (float)$arg;
                case 'a':
                    if (!is_array($arg)) {
                        throw new Exception('Array expected for ?a specifier.');
                    }
                    if (empty($arg)) {
                        throw new Exception('Empty array for ?a specifier.');
                    }
                    if (array_keys($arg) === range(0, count($arg) - 1)) {
                        return implode(', ', array_map(function ($val) {
                            return $this->formatValue($val);
                        }, $arg));
                    } else {
                        $pairs = [];
                        foreach ($arg as $key => $val) {
                            $pairs[] = $this->formatValue($key) . ' = ' . $this->formatValue($val);
                        }
                        return implode(', ', $pairs);
                    }
                case '#':
                    if (!is_scalar($arg) && !is_array($arg)) {
                        throw new Exception('Scalar or array expected for ?# specifier.');
                    }
                    return $this->formatValue($arg);
                default:
                    return $this->formatValue($arg);
            }
        }, $query);
        $query = preg_replace('/\{.*?\}/', '', $query);
        return $query;
    }

    private function formatValue($value)
    {
        if (is_null($value)) {
            return 'NULL';
        } elseif (is_bool($value)) {
            return $value ? '1' : '0';
        } elseif (is_string($value)) {
            return "'" . $this->mysqli->real_escape_string($value) . "'";
        } else {
            return $value;
        }
    }
}
