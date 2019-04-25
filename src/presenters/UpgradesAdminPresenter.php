<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Components\VisualPaginator;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Upgrade\Expander;

class UpgradesAdminPresenter extends AdminPresenter
{
    /** @var PaymentsRepository @inject */
    public $paymentsRepository;

    public function renderDefault($type = null)
    {
        $where = ['upgrade_type IS NOT NULL'];
        $totalCount = $this->paymentsRepository->all()->where($where)->count('*');
        if ($type) {
            $where['upgrade_type'] = $type;
        }

        $typesCounts = [
            Expander::UPGRADE_RECURRENT_FREE => $this->paymentsRepository->all()->where(['upgrade_type' => Expander::UPGRADE_RECURRENT_FREE])->count('*'),
            Expander::UPGRADE_RECURRENT => $this->paymentsRepository->all()->where(['upgrade_type' => Expander::UPGRADE_RECURRENT])->count('*'),
            Expander::UPGRADE_SHORT => $this->paymentsRepository->all()->where(['upgrade_type' => Expander::UPGRADE_SHORT])->count('*'),
            Expander::UPGRADE_PAID_EXTEND => $this->paymentsRepository->all()->where(['upgrade_type' => Expander::UPGRADE_PAID_EXTEND])->count('*'),
        ];
        $this->template->typesCounts = $typesCounts;

        $payments = $this->paymentsRepository->all()->where($where)->order('modified_at DESC');
        $filteredCount = $payments->count('*');

        $vp = new VisualPaginator();
        $this->addComponent($vp, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->setItemCount($filteredCount);
        $paginator->setItemsPerPage($this->onPage);
        $this->template->vp = $vp;
        $this->template->payments = $payments->limit($paginator->getLength(), $paginator->getOffset());
        $this->template->totalPayments = $totalCount;
        $this->template->filteredCount = $filteredCount;
        $this->template->type = $type;
    }

    protected function createComponentUpgradesGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $graphDataItem1 = new GraphDataItem();
        $graphDataItem2 = new GraphDataItem();
        $upgradeTypeWhere = 'AND upgrade_type IS NOT NULL';
        if (isset($this->params['type'])) {
            $upgradeTypeWhere = "AND upgrade_type = '" . addslashes($this->params['type']) . "'";
        }
        $graphDataItem1->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setTimeField('modified_at')
            ->setWhere($upgradeTypeWhere)
            ->setValueField('COUNT(*)')
            ->setStart('-1 month'))
            ->setName($this->translator->translate('payments.admin.upgrades.all_upgrades'));

        $graphDataItem2->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setTimeField('modified_at')
            ->setWhere($upgradeTypeWhere . ' AND payments.status = \'paid\'')
            ->setValueField('COUNT(*)')
            ->setStart('-1 month'))
            ->setName($this->translator->translate('payments.admin.upgrades.paid_upgrades'));

        $control = $factory->create()
            ->setGraphTitle($this->translator->translate('payments.admin.upgrades.title'))
            ->setGraphHelp($this->translator->translate('payments.admin.upgrades.upgrades_in_time'))
            ->addGraphDataItem($graphDataItem1)
            ->addGraphDataItem($graphDataItem2);

        return $control;
    }
}
