<?php

namespace Crm\PaymentsModule\Models\RecurrentPayment;

use Nette\Database\Table\ActiveRow;
use Nette\Utils\Random;

final readonly class ChainData
{
    public function __construct(
        public string $chainId,
        public int $cycle,
    ) {
    }

    public static function fromParentPayment(?ActiveRow $parentRecurrent): self
    {
        if ($parentRecurrent === null) {
            return new self(
                chainId: Random::generate(12),
                cycle: 1,
            );
        }

        $shouldIncrementCycle = in_array(
            $parentRecurrent->state,
            [RecurrentPaymentStateEnum::Charged->value, RecurrentPaymentStateEnum::Active->value],
            true,
        );
        $cycle = $shouldIncrementCycle ? $parentRecurrent->cycle + 1 : $parentRecurrent->cycle;

        return new self(
            chainId: $parentRecurrent->chain_id,
            cycle: $cycle,
        );
    }
}
