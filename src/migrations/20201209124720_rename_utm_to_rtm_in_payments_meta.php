<?php

use Phinx\Migration\AbstractMigration;

class RenameUtmToRtmInPaymentsMeta extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("update payment_meta set `key`= 'rtm_campaign' where `key` = 'utm_campaign'");
        $this->execute("update payment_meta set `key`= 'rtm_content' where `key` = 'utm_content'");
        $this->execute("update payment_meta set `key`= 'rtm_source' where `key` = 'utm_source'");
        $this->execute("update payment_meta set `key`= 'rtm_medium' where `key` = 'utm_medium'");
        $this->execute("update payment_meta set `key`= 'rtm_variant' where `key` = 'banner_variant'");
    }

    public function down(): void
    {
        $this->execute("update payment_meta set `key`= 'utm_campaign' where `key` = 'rtm_campaign'");
        $this->execute("update payment_meta set `key`= 'utm_content' where `key` = 'rtm_content'");
        $this->execute("update payment_meta set `key`= 'utm_source' where `key` = 'rtm_source'");
        $this->execute("update payment_meta set `key`= 'utm_medium' where `key` = 'rtm_medium'");
        $this->execute("update payment_meta set `key`= 'banner_variant' where `key` = 'rtm_variant'");
    }
}
