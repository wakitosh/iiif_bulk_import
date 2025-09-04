<?php

namespace IiifBulkImport\Controller\Admin;

use IiifBulkImport\Form\ManifestImportForm;
use IiifBulkImport\Job\ManifestImportJob;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

/**
 * Admin controller for IIIF bulk import.
 */
class ImportController extends AbstractActionController {

  /**
   * Default browse action maps to index.
   */
  public function browseAction() {
    return $this->indexAction();
  }

  /**
   * Form page and job dispatch.
   */
  public function indexAction() {
    $form = $this->getForm(ManifestImportForm::class);

    $post = $this->params()->fromPost();
    if (!empty($post)) {
      $form->setData($post);
      if ($form->isValid()) {
        $data = $form->getData();
        $raw = (string) ($data['manifest_urls'] ?? '');
        $urls = array_values(array_filter(array_map('trim', preg_split("/(\r?\n)+/", $raw)), function ($u) {
          return $u !== '';
        }));
        if (!$urls) {
          $this->messenger()->addError('No manifest URLs provided.');
          return $this->redirect()->toUrl($this->url()->fromRoute('iiif-bulk-import-admin'));
        }
        // Dispatch a single job containing all manifest URLs.
        $job = $this->jobDispatcher()->dispatch(ManifestImportJob::class, [
          'manifest_urls' => implode("\n", $urls),
        ]);
        $this->messenger()->addSuccess(sprintf('Started import job for %d manifest(s).', count($urls)));
        return $this->redirect()->toRoute('admin/id', [
          'controller' => 'job',
          'id' => $job->getId(),
        ], [], FALSE);
      }
      $this->messenger()->addFormErrors($form);
    }

    $view = new ViewModel();
    $view->setVariable('form', $form);
    return $view;
  }

}
