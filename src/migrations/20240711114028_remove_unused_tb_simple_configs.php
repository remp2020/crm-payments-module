<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveUnusedTbSimpleConfigs extends AbstractMigration
{
    public function change(): void
    {
        $this->execute("DELETE from configs WHERE name IN ('tb_simple_confirmation_host', 'tb_simple_confirmation_port', 'tb_simple_confirmation_username', 'tb_simple_confirmation_password', 'tb_simple_confirmation_processed_folder')");
    }
}
