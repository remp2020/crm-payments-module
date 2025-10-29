<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Models\RecurrentPayment;

use Nette\Database\Table\ActiveRow;
use Nette\Utils\Random;
use Tracy\Debugger;

final readonly class ChainData
{
    public function __construct(
        public ?string $chainId,
        public int $cycle,
    ) {
    }

    public static function fromParentPayment(?ActiveRow $parentRecurrentPayment): self
    {
        if ($parentRecurrentPayment === null) {
            return new self(
                chainId: Random::generate(12),
                cycle: 1,
            );
        }

        if ($parentRecurrentPayment->chain_id === null) {
            Debugger::log(
                "Recurrent payment parent [{$parentRecurrentPayment->id}] has no chain_id. " .
                "Run command [payments:fill_recurrent_chain_tracking] to backfill chain tracking data.",
                Debugger::WARNING,
            );

            return new self(
                chainId: null,
                cycle: 1,
            );
        }

        $shouldIncrementCycle = in_array(
            $parentRecurrentPayment->state,
            [
                RecurrentPaymentStateEnum::Charged->value,
                RecurrentPaymentStateEnum::Active->value,
                RecurrentPaymentStateEnum::Pending->value,
            ],
            true,
        );

        $cycle = $shouldIncrementCycle ? $parentRecurrentPayment->cycle + 1 : $parentRecurrentPayment->cycle;

        return new self(
            chainId: $parentRecurrentPayment->chain_id,
            cycle: $cycle,
        );
    }
}
