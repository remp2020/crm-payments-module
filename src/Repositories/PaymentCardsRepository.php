<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class PaymentCardsRepository extends Repository
{
    protected $tableName = 'payment_cards';

    /**
     * @param ActiveRow $paymentMethod
     * @param DateTime|null $expiration card expiration date
     * @param string|null $maskedCardNumber
     * @param string|null $description card description if provided by the payment provider
     * @param string|null $cardHolderName
     * @return int|bool|ActiveRow
     * @throws \Exception
     */
    final public function upsert(
        ActiveRow $paymentMethod,
        DateTime $expiration = null,
        string $maskedCardNumber = null,
        string $description = null,
        string $cardHolderName = null,
    ): int|ActiveRow|bool {
        $newData = array_filter([
            'expiration' => $expiration,
            'masked_card_number' => $maskedCardNumber,
            'description' => $description,
            'card_holder_name' => $cardHolderName,
        ]);

        $card = $this->getTable()->where('payment_method_id', $paymentMethod->id)->fetch();
        if ($card) {
            $cardData = array_filter([
                'expiration' => $card->expiration,
                'masked_card_number' => $card->masked_card_number,
                'description' => $card->description,
                'card_holder_name' => $card->card_holder_name,
            ]);
            if ($newData == $cardData) {
                return $card;
            }

            $updated = $this->update($card, $newData + [
                'updated_at' => new \DateTime(),
            ]);
            if ($updated) {
                return $card;
            }
            return false;
        }

        return $this->insert($newData + [
            'payment_method_id' => $paymentMethod->id,
            'created_at' => new \DateTime(),
            'updated_at' => new \DateTime(),
        ]);
    }

    final public function getByPaymentMethod(ActiveRow $paymentMethod): ?ActiveRow
    {
        return $this->getTable()
            ->where('payment_method_id', $paymentMethod->id)
            ->fetch();
    }
}
