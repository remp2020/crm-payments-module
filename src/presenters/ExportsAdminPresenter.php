<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\VisualPaginator;
use Crm\ApplicationModule\Models\ApplicationMountManager;
use Crm\PaymentsModule\Models\FileSystem;
use League\Flysystem\FileNotFoundException;
use Nette\Application\Responses\CallbackResponse;

class ExportsAdminPresenter extends AdminPresenter
{
    /** @var ApplicationMountManager @inject */
    public $adminMountManager;

    public function renderDefault()
    {
        $exports = $this->adminMountManager->getListContents(FileSystem::EXPORTS_BUCKET_NAME);
        $fileCount = count($exports);

        $vp = new VisualPaginator();
        $this->addComponent($vp, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->setItemCount($fileCount);
        $paginator->setItemsPerPage($this->onPage);
        $this->template->vp = $vp;
        $this->template->fileCount = $fileCount;
        $this->template->exports = array_slice($exports, $paginator->getOffset(), $paginator->getLength());
    }

    public function handleDownloadExport($bucket, $fileName)
    {
        $path = $this->adminMountManager->getFilePath($bucket, $fileName);
        $exists = $this->adminMountManager->has($path);
        if (!$exists) {
            throw new \Exception('Missing payments export with path ' . $path);
        }

        $this->getHttpResponse()->addHeader('Content-Encoding', 'windows-1250');
        $this->getHttpResponse()->addHeader('Content-Type', 'application/octet-stream; charset=windows-1250');
        $this->getHttpResponse()->addHeader('Content-Disposition', "attachment; filename=" . $fileName);

        $response = new CallbackResponse(function () use ($path) {
            echo $this->adminMountManager->read($path);
        });
        $this->sendResponse($response);
    }

    public function handleDeleteExport($bucket, $fileName)
    {
        $path = $this->adminMountManager->getFilePath($bucket, $fileName);
        $exists = $this->adminMountManager->has($path);
        if (!$exists) {
            throw new \Exception('Missing payments export with path ' . $path);
        }

        try {
            $isDeleted = $this->adminMountManager->delete($path);
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
