<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\PreviousNextPaginator;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\ApplicationMountManager;
use League\Flysystem\FileNotFoundException;
use Nette\Application\Responses\CallbackResponse;
use Nette\Application\UI\Form;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class ExportsAdminPresenter extends AdminPresenter
{
    /** @var ApplicationMountManager @inject */
    public $applicationMountManager;

    /** @var DataProviderManager @inject */
    public $dataProviderManager;

    /** @persistent */
    public $file_system;

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $exports = $this->getExports();
        $fileCount = count($exports);

        $pnp = new PreviousNextPaginator();
        $this->addComponent($pnp, 'paginator');
        $paginator = $pnp->getPaginator();
        $paginator->setItemsPerPage($this->onPage);

        $exports = array_slice($exports, $paginator->getOffset(), $paginator->getLength());
        $pnp->setActualItemCount(count($exports));

        $this->template->fileCount = $fileCount;
        $this->template->exports = $exports;
    }

    private function getExports(): array
    {
        if (isset($this->file_system)) {
            return $this->applicationMountManager->getListContents($this->file_system);
        }

        return $this->applicationMountManager->getContentsForGroup('payments');
    }

    public function createComponentExportsAdminForm()
    {
        $form = new Form;
        $form->setRenderer(new BootstrapInlineRenderer());
        $form->setTranslator($this->translator);

        $buckets = $this->applicationMountManager->getBucketsForGroup('payments');
        $form->addSelect(
            'file_system',
            'payments.admin.component.exports_admin_form.file_system.label',
            array_combine($buckets, $buckets)
        )
            ->setPrompt('--')
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addSubmit('send', 'payments.admin.component.exports_admin_form.filter.send')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('payments.admin.component.exports_admin_form.filter.send'));
        $presenter = $this;

        $form->addSubmit('cancel', 'payments.admin.component.exports_admin_form.filter.cancel')->onClick[] = function () use ($presenter, $form) {
            $emptyDefaults = array_fill_keys(array_keys((array) $form->getComponents()), null);
            $presenter->redirect('ExportsAdmin:Default', $emptyDefaults);
        };

        $form->onSuccess[] = [$this, 'adminFilterSubmitted'];
        $form->setDefaults([
            'file_system' => $this->file_system
        ]);

        return $form;
    }

    /**
     * @admin-access-level read
     */
    public function handleDownloadExport($bucket, $fileName)
    {
        $path = $this->applicationMountManager->getFilePath($bucket, $fileName);
        $exists = $this->applicationMountManager->has($path);
        if (!$exists) {
            throw new \Exception('Missing payments export with path ' . $path);
        }

        $this->getHttpResponse()->addHeader('Content-Encoding', 'windows-1250');
        $this->getHttpResponse()->addHeader('Content-Type', 'application/octet-stream; charset=windows-1250');
        $this->getHttpResponse()->addHeader('Content-Disposition', "attachment; filename=" . $fileName);

        $response = new CallbackResponse(function () use ($path) {
            echo $this->applicationMountManager->read($path);
        });
        $this->sendResponse($response);
    }

    /**
     * @admin-access-level write
     */
    public function handleDeleteExport($bucket, $fileName)
    {
        $path = $this->applicationMountManager->getFilePath($bucket, $fileName);
        $exists = $this->applicationMountManager->has($path);
        if (!$exists) {
            throw new \Exception('Missing payments export with path ' . $path);
        }

        try {
            $isDeleted = $this->applicationMountManager->delete($path);
            if ($isDeleted) {
                $this->flashMessage($this->translator->translate('payments.admin.exports.deleted'));
            } else {
                $this->flashMessage($this->translator->translate('payments.admin.exports.delete_error'), 'error');
            }
        } catch (FileNotFoundException $e) {
            $this->flashMessage($this->translator->translate('payments.admin.exports.delete_error'), 'error');
        }

        $this->redirect('default');
    }
}
