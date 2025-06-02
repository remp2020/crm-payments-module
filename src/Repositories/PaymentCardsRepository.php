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
     * @param string|null $description card description if provided by payment provider
     * @return int|bool|ActiveRow
     * @throws \Exception
     */
    final public function upsert(
        ActiveRow $paymentMethod,
        DateTime $expiration = null,
        string $maskedCardNumber = null,
        string $description = null,
    ): int|ActiveRow|bool {
        $newData = [
            'expiration' => $expiration,
            'masked_card_number' => $maskedCardNumber,
            'description' => $description,
        ];

        $card = $this->getTable()->where('payment_method_id', $paymentMethod->id)->fetch();
        if ($card) {
            $cardData = [
                'expiration' => $card->expiration,
                'masked_card_number' => $card->masked_card_number,
                'description' => $card->description,
            ];
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
}
