<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\PaymentsModule\Repository\PaymentsRepository;

class ExportsPresenter extends AdminPresenter
{
    private $paymentsRepository;

    public function __construct(PaymentsRepository $paymentsRepository)
    {
        parent::__construct();
        $this->paymentsRepository = $paymentsRepository;
    }

    public function renderUserSum()
    {
        $total = $this->paymentsRepository->totalUserAmounts();
        echo "user_id,email,total\n";
        foreach ($total as $row) {
            echo "{$row->user_id},{$row->email},{$row->total}\n";
        }
        $this->terminate();
    }
}
