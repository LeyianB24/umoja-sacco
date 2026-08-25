<?php
$data = json_decode(file_get_contents(__DIR__ . '/schema_dump.json'), true);

function toPascalCase($string) {
    return str_replace('_', '', ucwords(ltrim($string, '_'), '_'));
}

function mapType($mysqlType, $nullable, &$dbAttr, $defaultVal = null) {
    $dbAttr = '';
    $typeLower = strtolower($mysqlType);

    if (strpos($typeLower, 'tinyint(1)') !== false || $typeLower === 'boolean' || $typeLower === 'bool') {
        return 'Boolean';
    }
    if (strpos($typeLower, 'enum') !== false) {
        $dbAttr = "@db.VarChar(50)";
        return 'String';
    }
    if ($defaultVal !== null && !is_numeric($defaultVal) && $defaultVal !== 'CURRENT_TIMESTAMP' && strpos($defaultVal, 'current_timestamp') === false) {
        // If default value is a string like "active", it must be String
        if (strpos($typeLower, 'int') !== false) {
            $dbAttr = "@db.VarChar(50)";
            return 'String';
        }
    }
    if (strpos($typeLower, 'int') !== false) {
        if (strpos($typeLower, 'bigint') !== false) {
            return 'BigInt';
        }
        return 'Int';
    }
    if (strpos($typeLower, 'decimal') !== false || strpos($typeLower, 'numeric') !== false || strpos($typeLower, 'float') !== false || strpos($typeLower, 'double') !== false) {
        if (preg_match('/decimal\((\d+),(\d+)\)/', $typeLower, $m)) {
            $dbAttr = "@db.Decimal({$m[1]}, {$m[2]})";
        } else {
            $dbAttr = "@db.Decimal(15, 2)";
        }
        return 'Decimal';
    }
    if (strpos($typeLower, 'datetime') !== false || strpos($typeLower, 'timestamp') !== false) {
        return 'DateTime';
    }
    if (strpos($typeLower, 'date') !== false) {
        $dbAttr = "@db.Date";
        return 'DateTime';
    }
    if (strpos($typeLower, 'blob') !== false || strpos($typeLower, 'binary') !== false || strpos($typeLower, 'bytea') !== false) {
        return 'Bytes';
    }
    if (strpos($typeLower, 'json') !== false) {
        return 'Json';
    }
    if (preg_match('/varchar\((\d+)\)/', $typeLower, $m)) {
        $dbAttr = "@db.VarChar({$m[1]})";
        return 'String';
    }
    if (preg_match('/char\((\d+)\)/', $typeLower, $m)) {
        $dbAttr = "@db.Char({$m[1]})";
        return 'String';
    }
    if (strpos($typeLower, 'text') !== false) {
        $dbAttr = "@db.Text";
        return 'String';
    }

    return 'String';
}

$output = "// prisma/schema.prisma\n";
$output .= "// Generated for Umoja SACCO Management System (USMS)\n";
$output .= "// Target: PostgreSQL (Neon) with Connection Pooling\n\n";
$output .= "generator client {\n";
$output .= "  provider = \"prisma-client-js\"\n";
$output .= "}\n\n";
$output .= "datasource db {\n";
$output .= "  provider = \"postgresql\"\n";
$output .= "  url      = env(\"DATABASE_URL\")\n";
$output .= "}\n\n";

foreach ($data as $tableName => $tableInfo) {
    $modelName = toPascalCase($tableName);
    $output .= "// ==========================================\n";
    $output .= "// Model: {$modelName} (Table: {$tableName})\n";
    $output .= "// ==========================================\n";
    $output .= "model {$modelName} {\n";

    $cols = $tableInfo['columns'];
    $primaryKeys = [];

    foreach ($cols as $c) {
        $field = $c['field'];
        $nullable = ($c['null'] === 'YES');
        $dbAttr = '';
        $type = mapType($c['type'], $nullable, $dbAttr, $c['default']);

        $isPk = ($c['key'] === 'PRI');
        $isUnique = ($c['key'] === 'UNI');
        $isAutoInc = (strpos($c['extra'], 'auto_increment') !== false);

        if ($isPk) {
            $primaryKeys[] = $field;
        }

        $typeStr = $type . ($nullable ? '?' : '');
        $attributes = [];

        if ($isPk && count(array_filter($cols, fn($x) => $x['key'] === 'PRI')) === 1) {
            $attributes[] = '@id';
            if ($isAutoInc) {
                $attributes[] = '@default(autoincrement())';
            }
        } elseif ($isUnique) {
            $attributes[] = '@unique';
        }

        if (!empty($dbAttr)) {
            $attributes[] = $dbAttr;
        }

        if ($c['default'] !== null && !$isAutoInc && !$isPk) {
            $defLower = strtolower(trim($c['default']));
            if ($defLower === 'current_timestamp' || strpos($defLower, 'current_timestamp') !== false || strpos($defLower, 'curdate') !== false) {
                $attributes[] = '@default(now())';
            } elseif ($type === 'Boolean') {
                $boolVal = ($c['default'] == '1' || strtolower($c['default']) === 'true') ? 'true' : 'false';
                $attributes[] = "@default({$boolVal})";
            } elseif ($type === 'Int' || $type === 'BigInt') {
                if (is_numeric($c['default'])) {
                    $attributes[] = "@default({$c['default']})";
                }
            } elseif ($type === 'Decimal') {
                if (is_numeric($c['default'])) {
                    $attributes[] = "@default({$c['default']})";
                }
            } elseif (is_string($c['default'])) {
                $escaped = addslashes($c['default']);
                $attributes[] = "@default(\"{$escaped}\")";
            }
        }

        $prismaField = $field;
        if (strpos($field, '_') === 0) {
            $prismaField = ltrim($field, '_');
            $attributes[] = "@map(\"{$field}\")";
        }

        $attrStr = count($attributes) > 0 ? ' ' . implode(' ', $attributes) : '';
        $output .= sprintf("  %-24s %-12s%s\n", $prismaField, $typeStr, $attrStr);
    }

    if (count($primaryKeys) > 1) {
        $pkList = implode(', ', $primaryKeys);
        $output .= "  @@id([{$pkList}])\n";
    }

    $output .= "  @@map(\"{$tableName}\")\n";
    $output .= "}\n\n";
}

file_put_contents(__DIR__ . '/../../frontend/prisma/schema.prisma', $output);
echo "Generated clean schema.prisma successfully at frontend/prisma/schema.prisma (" . strlen($output) . " bytes)\n";
