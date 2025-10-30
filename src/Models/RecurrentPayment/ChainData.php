<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Models\RecurrentPayment;

use Nette\Database\Table\ActiveRow;
use Nette\Utils\Random;
use Tracy\Debugger;
use Tracy\ILogger;

final readonly class ChainData
{
    public function __construct(
        public ?string $chainId,
        public ?int $cycle,
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
                ILogger::WARNING,
            );

            return new self(
                chainId: null,
                cycle: null,
            );
        }

        $shouldIncrementCycle = in_array(
            $parentRecurrentPayment->state,
            [
                RecurrentPaymentStateEnum::Active->value,
                RecurrentPaymentStateEnum::Charged->value,
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
