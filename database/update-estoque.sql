-- Atualizacao segura para controle de estoque.
-- Execute uma vez no banco MySQL da hospedagem/XAMPP depois de fazer backup.
-- Nao recria tabelas e nao apaga produtos existentes.

DROP PROCEDURE IF EXISTS magrao_atualizar_estoque_produtos;

DELIMITER $$

CREATE PROCEDURE magrao_atualizar_estoque_produtos()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'estoque'
  ) THEN
    ALTER TABLE produtos
      ADD COLUMN estoque INT NOT NULL DEFAULT 0 AFTER icone;
  ELSEIF EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'estoque'
      AND DATA_TYPE NOT IN ('int', 'bigint', 'mediumint', 'smallint', 'tinyint')
  ) THEN
    ALTER TABLE produtos
      ADD COLUMN estoque_novo INT NOT NULL DEFAULT 0 AFTER estoque;

    UPDATE produtos
       SET estoque_novo = CASE
         WHEN TRIM(CAST(estoque AS CHAR)) REGEXP '^[0-9]+$'
           THEN CAST(TRIM(CAST(estoque AS CHAR)) AS UNSIGNED)
         ELSE 0
       END;

    ALTER TABLE produtos DROP COLUMN estoque;
    ALTER TABLE produtos CHANGE estoque_novo estoque INT NOT NULL DEFAULT 0;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'estoque_minimo'
  ) THEN
    ALTER TABLE produtos
      ADD COLUMN estoque_minimo INT NOT NULL DEFAULT 0 AFTER estoque;
  ELSEIF EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'estoque_minimo'
      AND DATA_TYPE NOT IN ('int', 'bigint', 'mediumint', 'smallint', 'tinyint')
  ) THEN
    ALTER TABLE produtos
      ADD COLUMN estoque_minimo_novo INT NOT NULL DEFAULT 0 AFTER estoque_minimo;

    UPDATE produtos
       SET estoque_minimo_novo = CASE
         WHEN TRIM(CAST(estoque_minimo AS CHAR)) REGEXP '^[0-9]+$'
           THEN CAST(TRIM(CAST(estoque_minimo AS CHAR)) AS UNSIGNED)
         ELSE 0
       END;

    ALTER TABLE produtos DROP COLUMN estoque_minimo;
    ALTER TABLE produtos CHANGE estoque_minimo_novo estoque_minimo INT NOT NULL DEFAULT 0;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'em_estoque'
  ) THEN
    ALTER TABLE produtos
      ADD COLUMN em_estoque TINYINT(1) NOT NULL DEFAULT 1 AFTER estoque_minimo;

    UPDATE produtos
       SET em_estoque = CASE WHEN estoque > 0 THEN 1 ELSE 0 END;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'mostrar_quantidade_estoque'
  ) THEN
    ALTER TABLE produtos
      ADD COLUMN mostrar_quantidade_estoque TINYINT(1) NOT NULL DEFAULT 0 AFTER em_estoque;
  END IF;
END$$

DELIMITER ;

CALL magrao_atualizar_estoque_produtos();
DROP PROCEDURE magrao_atualizar_estoque_produtos;
