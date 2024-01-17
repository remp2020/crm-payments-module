<?php

namespace Crm\PaymentsModule\Segment;

use Crm\ApplicationModule\Criteria\CriteriaInterface;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SegmentModule\Models\Criteria\Fields;
use Crm\SegmentModule\Models\Params\BooleanParam;
use Crm\SegmentModule\Models\Params\DateTimeParam;
use Crm\SegmentModule\Models\Params\NumberArrayParam;
use Crm\SegmentModule\Models\Params\ParamsBag;
use Crm\SegmentModule\Models\Params\StringArrayParam;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;

class PaymentCriteria implements CriteriaInterface
{
    private $paymentsRepository;

    private $paymentItemsRepository;

    private $subscriptionTypesRepository;

    public function __construct(
        PaymentsRepository $paymentsRepository,
        PaymentItemsRepository $paymentItemsRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository
    ) {
        $this->paymentsRepository = $paymentsRepository;
        $this->paymentItemsRepository = $paymentItemsRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
    }

    public function label(): string
    {
        return "Payment";
    }

    public function category(): string
    {
        return "Payment";
    }

    public function params(): array
    {
        return [
            new BooleanParam(
                "additional_amount",
                "Donation",
                "Filters users with payments with / without donation"
            ),
            new DateTimeParam(
                "created",
                "Created",
                "Filters users with payments created within selected period"
            ),
            new StringArrayParam(
                "status",
                "Status",
                "Filters users with payments with specific status",
                false,
                [PaymentsRepository::STATUS_PAID],
                null,
                array_keys($this->paymentsRepository->getStatusPairs())
            ),
            new StringArrayParam(
                "item_types",
                "Payment item types",
                "Filters users with payments with specific types of payment items",
                false,
                [],
                null,
                array_keys($this->paymentItemsRepository->getTypes())
            ),
            new NumberArrayParam(
                "subscription_type",
                "Subscription type",
                "Filters users with payments with specific subscription types",
                false,
                null,
                null,
                $this->subscriptionTypesRepository->getAllActive()->fetchPairs('id', 'name')
            ),
        ];
    }

    public function join(ParamsBag $params): string
    {
        $where = [];

        if ($params->has('additional_amount')) {
            if ($params->boolean('additional_amount')->isTrue()) {
                $where[] = ' payments.additional_amount > 0 AND payments.additional_type IS NOT NULL ';
            } elseif ($params->boolean('additional_amount')->isFalse()) {
                $where[] = ' payments.additional_amount = 0 AND payments.additional_type IS NULL ';
            }
        }

        if ($params->has('status')) {
            $where[] = " payments.status IN ({$params->stringArray('status')->escapedString()}) ";
        }

        if ($params->has('item_types')) {
            $where[] = " payment_items.type IN ({$params->stringArray('item_types')->escapedString()}) ";
        }

        if ($params->has('created')) {
            $where = array_merge($where, $params->datetime('created')->escapedConditions('payments.created_at'));
        }

        if ($params->has('subscription_type')) {
            $values = $params->numberArray('subscription_type')->escapedString();
            $where[] = " payment_items.subscription_type_id IN ({$values}) ";
        }

        return "SELECT DISTINCT(payments.user_id) AS id, " . Fields::formatSql($this->fields()) . "
          FROM payments
          LEFT JOIN payment_items ON payment_items.payment_id = payments.id
          WHERE " . implode(" AND ", $where);
    }

    public function title(ParamsBag $paramBag): string
    {
        $result = '';
        if ($paramBag->has('additional_amount') || $paramBag->has('status') || $paramBag->has('created')) {
            if ($paramBag->has('status')) {
                $result .= " with {$paramBag->stringArray('status')->escapedString()} payment";
            } else {
                $result .= ' with payment';
            }

            if ($paramBag->has('item_types')) {
                $result .= " with {$paramBag->stringArray('item_types')->escapedString()} items";
            } else {
                $result .= ' with all item types';
            }

            if ($paramBag->has('additional_amount')) {
                if ($paramBag->boolean('additional_amount')->isTrue()) {
                    $result .= ' with additional amount';
                } elseif ($paramBag->boolean('additional_amount')->isFalse()) {
                    $result .= ' without additional amount';
                }
            }

            if ($paramBag->has('created')) {
                $result .= " created{$paramBag->datetime('created')->title('payments.created_at')}";
            }
        }
        return $result;
    }

    public function fields(): array
    {
        return [
            'payments.id' => 'payment_id',
            'payments.status' => 'payment_status',
            'payments.variable_symbol' => 'variable_symbol',
            'payments.amount' => 'amount',
            'payments.additional_amount' => 'additional_amount',
            'payments.additional_type' => 'additional_type',
            'payments.created_at' => 'created_at',
            'payments.paid_at' => 'paid_at',
            'payments.recurrent_charge' => 'recurrent_charge',
        ];
    }
}
