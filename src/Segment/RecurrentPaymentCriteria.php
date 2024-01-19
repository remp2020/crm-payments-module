<?php

namespace Crm\PaymentsModule\Segment;

use Crm\ApplicationModule\Models\Criteria\CriteriaInterface;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\SegmentModule\Models\Criteria\Fields;
use Crm\SegmentModule\Models\Params\BooleanParam;
use Crm\SegmentModule\Models\Params\ParamsBag;

class RecurrentPaymentCriteria implements CriteriaInterface
{
    private $recurrentPaymentsRepository;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository
    ) {
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
    }

    public function label(): string
    {
        return "Recurrent payment";
    }

    public function category(): string
    {
        return "Users";
    }

    public function params(): array
    {
        return [
            new BooleanParam('active_recurrent', "Active recurrent", "Filters users with / without active recurrent payment", true, true),
        ];
    }

    public function join(ParamsBag $params): string
    {
        $where = [];

        if ($params->has('active_recurrent')) {
            if ($params->boolean('active_recurrent')->isTrue()) {
                $where[] = " recurrent_payments.id IS NOT NULL AND recurrent_payments.state = 'active' ";
            } elseif ($params->boolean('active_recurrent')->isFalse()) {
                $where[] = " recurrent_payments.id IS NULL OR users.id NOT IN (SELECT DISTINCT(recurrent_payments.user_id) FROM recurrent_payments WHERE recurrent_payments.state = 'active') ";
            }
        }

        return "SELECT DISTINCT(users.id) AS id, " . Fields::formatSql($this->fields()) . "
          FROM users
          LEFT JOIN recurrent_payments ON users.id = recurrent_payments.user_id
          WHERE " . implode(" AND ", $where);
    }

    public function title(ParamsBag $paramBag): string
    {
        $result = '';
        if ($paramBag->has('active_recurrent')) {
            if ($paramBag->has('active_recurrent')) {
                if ($paramBag->boolean('active_recurrent')->isTrue()) {
                    $result .= ' with active recurrent';
                } elseif ($paramBag->boolean('active_recurrent')->isFalse()) {
                    $result .= ' without active recurrent';
                }
            }
        }
        return $result;
    }

    public function fields(): array
    {
        return [
            'recurrent_payments.id' => 'recurrent_payments',
        ];
    }
}
