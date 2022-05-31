<?php

use Phinx\Migration\AbstractMigration;

class ChangeDynamicRowFormatAndCollationOfPaymentMeta extends AbstractMigration
{
    public function up()
    {
        $result = $this->query(<<<SQL
            SELECT table_name as "table_name", row_format as "row_format"
            FROM `information_schema`.`tables`
            WHERE
               `table_schema` = DATABASE()
               AND `table_name` = "payment_meta"
SQL
        )->fetch(PDO::FETCH_ASSOC);

        if ($result['row_format'] !== 'Dynamic') {
            $this->execute("ALTER TABLE `payment_meta` ROW_FORMAT=DYNAMIC");
        }

        $this->execute("ALTER TABLE `payment_meta` CONVERT TO CHARACTER SET utf8mb4 collate utf8mb4_unicode_ci");
    }

    public function down()
    {
        $this->output->writeln('Down migration is risky. See migration class for details. Nothing done.');
        return;

        // WARNING: This character set & collation & row format could be previous state but also didn't have to be.
        // Migrate only if you know what you are doing.

        $this->execute("ALTER TABLE `payment_meta` CONVERT TO CHARACTER SET utf8 collate utf8_unicode_ci");

        $result = $this->query(<<<SQL
            SELECT table_name as "table_name", row_format as "row_format"
            FROM `information_schema`.`tables`
            WHERE
               `table_schema` = DATABASE()
               AND `table_name` = "payment_meta"
SQL
        )->fetch(PDO::FETCH_ASSOC);

        if ($result['row_format'] !== 'Compact') {
            $this->execute("ALTER TABLE `payment_meta` ROW_FORMAT=COMPACT");
        }
    }
}
