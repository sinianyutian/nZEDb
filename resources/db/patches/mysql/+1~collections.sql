ALTER TABLE collections ADD column added TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE binaries MODIFY partcheck TINYINT(1) UNSIGNED NOT NULL DEFAULT 0;

DROP PROCEDURE IF EXISTS tpg_change;

DELIMITER $$
CREATE PROCEDURE tpg_change(IN databaseName varchar(255))
  BEGIN
    DECLARE done INT DEFAULT false;
    DECLARE _table CHAR(255);
    DECLARE _stmt VARCHAR(1000);
    DECLARE cur1 CURSOR FOR SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = databaseName AND TABLE_NAME LIKE "collections\_%";
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    OPEN cur1;
      myloop: loop FETCH cur1 INTO _table;
        IF done THEN LEAVE myloop; END IF;
        SET @sql1 := CONCAT("ALTER TABLE ", _table," ADD COLUMN added TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
        PREPARE _stmt FROM @sql1;
        EXECUTE _stmt;
        DROP PREPARE _stmt;
      END loop;
    CLOSE cur1;
  END $$
DELIMITER ;

CALL tpg_change( DATABASE() );
DROP PROCEDURE tpg_change;

DELIMITER $$
CREATE PROCEDURE tpg_change(IN databaseName varchar(255))
  BEGIN
    DECLARE done INT DEFAULT false;
    DECLARE _table CHAR(255);
    DECLARE _stmt VARCHAR(1000);
    DECLARE cur1 CURSOR FOR SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = databaseName AND TABLE_NAME LIKE "binaries\_%";
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    OPEN cur1;
      myloop: loop FETCH cur1 INTO _table;
        IF done THEN LEAVE myloop; END IF;
        SET @sql1 := CONCAT("ALTER TABLE ", _table," MODIFY partcheck TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");
        PREPARE _stmt FROM @sql1;
        EXECUTE _stmt;
        DROP PREPARE _stmt;
      END loop;
    CLOSE cur1;
  END $$
DELIMITER ;

CALL tpg_change( DATABASE() );
DROP PROCEDURE tpg_change;
