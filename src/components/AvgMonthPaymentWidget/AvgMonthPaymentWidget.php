<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Nette\Database\Connection;

class AvgMonthPaymentWidget extends BaseWidget
{
    private $templateName = 'avg_month_payment_widget.latte';

    private $database;

    public function __construct(WidgetManager $widgetManager, Connection $database)
    {
        parent::__construct($widgetManager);
        $this->database = $database;
    }

    public function identifier()
    {
        return 'avgmonthpaymentwidget';
    }

    public function render(array $params)
    {
        $segment = $params['segment'];
        $data = $params['data'];
        $usersIds = [];
        $tableData = [];
        $displayFields = false;

        $segment->process(function ($row) use (&$usersIds, $data, &$tableData, &$displayFields) {
            $usersIds[] = $row->id;

            if ($data) {
                if (!$displayFields) {
                    $displayFields = array_keys((array) $row);
                }
                $tableData[] = array_values((array) $row);
            }
        }, 100000);

        $this->template->fields = $displayFields;
        $this->template->data = $tableData;

        if (count($usersIds)) {
            $usersIds = implode(',', $usersIds);
            $query = "SELECT AVG(value) AS avg_month_payment FROM user_meta WHERE `key`='avg_month_payment' AND user_id IN (".$usersIds.")";
            $average = $this->database->query($query)->fetch();
            $this->template->avgMonthPayment = $average->avg_month_payment;
        }

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
