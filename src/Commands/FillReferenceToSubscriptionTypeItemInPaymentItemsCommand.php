<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\Repositories\PaymentItemsRepository;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemsRepository;
use Nette\Database\Table\ActiveRow;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FillReferenceToSubscriptionTypeItemInPaymentItemsCommand extends Command
{
    public function __construct(
        private PaymentItemsRepository $paymentItemsRepository,
        private SubscriptionTypeItemsRepository $subscriptionTypeItemsRepository
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('payments:fill_reference_to_subscription_type_item')
            ->setDescription('Fill reference to subscription type item in `payment_items` table');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $limit = 1000;

        while (true) {
            $paymentItems = $this->paymentItemsRepository->getTable()
                ->where('subscription_type_id IS NOT NULL')
                ->where('subscription_type_item_id', null)
                ->where('type', SubscriptionTypePaymentItem::TYPE)
                ->where('id > ?', $paymentItem->id ?? 0)
                ->order('id')
                ->limit($limit)
                ->fetchAll();

            if (!count($paymentItems)) {
                $output->writeln(' OK!');
                break;
            }

            foreach ($paymentItems as $paymentItem) {
                $subscriptionTypeItem = $this->getRelatedSubscriptionTypeItem($paymentItem);

                if ($subscriptionTypeItem) {
                    $this->paymentItemsRepository->update($paymentItem, [
                        'subscription_type_item_id' => $subscriptionTypeItem->id
                    ], true);
                }
            }

            $output->write('.');
        }

        return Command::SUCCESS;
    }

    private function getRelatedSubscriptionTypeItem(ActiveRow $paymentItem): ?ActiveRow
    {
        // check reference from `payment_item_meta`
        $subscriptionTypeItemId = $paymentItem->related('payment_item_meta')->where('key', 'subscription_type_item_id')->fetch();
        if ($subscriptionTypeItemId) {
            $subscriptionTypeItem = $this->subscriptionTypeItemsRepository->find($subscriptionTypeItemId);
            if ($subscriptionTypeItem) {
                return $subscriptionTypeItem;
            }
        }

        // try to fetch subscription_type_item from subscription_type
        $subscriptionType = $paymentItem->subscription_type;
        $subscriptionTypeItems = $subscriptionType->related('subscription_type_items');

        if ($subscriptionTypeItems->count('*') === 1) {
            return $subscriptionTypeItems->fetch();
        }

        // anomaly fix: subscription type has more items than payment
        $allPaymentItems = $paymentItem->payment->related('payment_items')
            ->where('type', SubscriptionTypePaymentItem::TYPE);
        if ($allPaymentItems->count('*') === 1) {
            // 1. prefer subscription type item with higher VAT
            // 2. prefer subscription type item with higher price
            // 3. prefer subscription type item with lower id
            $subscriptionTypeItems->order('vat DESC, amount DESC, id ASC');

            return $subscriptionTypeItems->fetch();
        }

        // match by exact name
        foreach ($subscriptionTypeItems as $subscriptionTypeItem) {
            if ($paymentItem->name === $subscriptionTypeItem->name) {
                return $subscriptionTypeItem;
            }
        }

        // match by most similar name
        $subscriptionTypeItemPairs = $subscriptionTypeItems->fetchPairs('id', 'name');
        $paymentItemName = $paymentItem->name;
        uasort($subscriptionTypeItemPairs, function ($a, $b) use ($paymentItemName) {
            return levenshtein($paymentItemName, $a) < levenshtein($paymentItemName, $b) ? -1 : 1;
        });

        return $this->subscriptionTypeItemsRepository->find(array_key_first($subscriptionTypeItemPairs));
    }
}
