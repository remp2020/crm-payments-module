<?php

use Phinx\Migration\AbstractMigration;

class RemoveUnusedGoPayConfigs extends AbstractMigration
{
    public function change()
    {
        // Old unused GoPay configs in PaymentsModule (seeded before GoPay functionality was moved to a separate module)
        $this->execute("DELETE from configs WHERE name IN ('gopay_mode', 'gopay_recurrence_date_to')");
    }
}
