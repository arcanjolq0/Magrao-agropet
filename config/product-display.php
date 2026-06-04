<?php
declare(strict_types=1);

/*
 * Campos finais de exibicao dos produtos.
 * Mantem compatibilidade com bancos antigos: se as colunas ainda nao existem,
 * o sistema tenta criar automaticamente sem apagar dados cadastrados.
 */
function ensure_product_display_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo = db();
        $columns = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM produtos');
        foreach ($stmt->fetchAll() as $column) {
            $columns[strtolower((string) $column['Field'])] = strtolower((string) ($column['Type'] ?? ''));
        }

        $isIntegerColumn = static function (?string $type): bool {
            return preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/', (string) $type) === 1;
        };

        $normalizeIntegerColumn = static function (string $column) use ($pdo, &$columns, $isIntegerColumn): void {
            if (!isset($columns[$column]) || $isIntegerColumn($columns[$column])) {
                return;
            }

            $temporaryColumn = $column . '_int_tmp';
            if (isset($columns[$temporaryColumn])) {
                $pdo->exec('ALTER TABLE produtos DROP COLUMN ' . $temporaryColumn);
                unset($columns[$temporaryColumn]);
            }

            $pdo->exec('ALTER TABLE produtos ADD COLUMN ' . $temporaryColumn . ' INT NOT NULL DEFAULT 0 AFTER ' . $column);
            $pdo->exec(
                'UPDATE produtos
                    SET ' . $temporaryColumn . " = CASE
                      WHEN TRIM(CAST(" . $column . " AS CHAR)) REGEXP '^[0-9]+$'
                        THEN CAST(TRIM(CAST(" . $column . ' AS CHAR)) AS UNSIGNED)
                      ELSE 0
                    END'
            );
            $pdo->exec('ALTER TABLE produtos DROP COLUMN ' . $column);
            $pdo->exec('ALTER TABLE produtos CHANGE ' . $temporaryColumn . ' ' . $column . ' INT NOT NULL DEFAULT 0');
            $columns[$column] = 'int';
        };

        $normalizeIntegerColumn('estoque');
        $normalizeIntegerColumn('estoque_minimo');

        $adds = [];
        $addedStockAvailability = false;
        if (!isset($columns['estoque'])) {
            $adds[] = 'ADD COLUMN estoque INT NOT NULL DEFAULT 0 AFTER icone';
        }
        if (!isset($columns['estoque_minimo'])) {
            $adds[] = 'ADD COLUMN estoque_minimo INT NOT NULL DEFAULT 0 AFTER estoque';
        }
        if (!isset($columns['mostrar_home'])) {
            $adds[] = 'ADD COLUMN mostrar_home TINYINT(1) NOT NULL DEFAULT 1 AFTER oferta';
        }
        if (!isset($columns['compra_mensal'])) {
            $adds[] = 'ADD COLUMN compra_mensal TINYINT(1) NOT NULL DEFAULT 0 AFTER mostrar_home';
        }
        if (!isset($columns['em_estoque'])) {
            $adds[] = 'ADD COLUMN em_estoque TINYINT(1) NOT NULL DEFAULT 1 AFTER compra_mensal';
            $addedStockAvailability = true;
        }
        if (!isset($columns['mostrar_quantidade_estoque'])) {
            $afterColumn = isset($columns['em_estoque']) ? 'em_estoque' : 'compra_mensal';
            $adds[] = 'ADD COLUMN mostrar_quantidade_estoque TINYINT(1) NOT NULL DEFAULT 0 AFTER ' . $afterColumn;
        }

        if ($adds) {
            $pdo->exec('ALTER TABLE produtos ' . implode(', ', $adds));
            if ($addedStockAvailability) {
                $pdo->exec('UPDATE produtos SET em_estoque = CASE WHEN estoque > 0 THEN 1 ELSE 0 END');
            }
        }
    } catch (Throwable $exception) {
        // Nao bloqueia instalacao/setup caso o banco ainda esteja vazio.
    }
}
