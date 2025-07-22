<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddBooleanParamToRecurrentPaymentCardExpiredCriteria extends AbstractMigration
{
    public function up(): void
    {
        $this->getAdapter()->beginTransaction();

        try {
            $sql = <<<SQL
                SELECT * FROM scenarios_elements
                WHERE type = 'condition'
                AND options LIKE '%recurrent_payment_card_expired%'
            SQL;

            $rows = $this->fetchAll($sql);

            foreach ($rows as $row) {
                $options = json_decode($row['options'], true);
                if (!isset($options['conditions']['nodes'])) {
                    continue;
                }

                foreach ($options['conditions']['nodes'] as &$node) {
                    if ($node['key'] === 'recurrent_payment_card_expired' && empty($node['params'])) {
                        $node['params'][] = [
                            'key' => 'recurrent_payment_card_expired',
                            'values' => [
                                'selection' => true,
                            ],
                        ];
                    }
                }

                $this->execute(
                    "UPDATE scenarios_elements SET options = :options WHERE id = :id",
                    [
                        'options' => json_encode($options),
                        'id' => $row['id'],
                    ]
                );
            }
            $this->getAdapter()->commitTransaction();
        } catch (\Throwable $e) {
            $this->getAdapter()->rollbackTransaction();
            throw $e;
        }
    }

    public function down(): void
    {
        $this->output->writeln('This is data migration. Down migration is not available.');
    }
}
