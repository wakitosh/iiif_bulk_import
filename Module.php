<?php

namespace IiifBulkImport;

use Omeka\Module\AbstractModule;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Mvc\Controller\AbstractController;
use IiifBulkImport\Controller\Admin\ImportController;
use IiifBulkImport\Form\ConfigForm;

/**
 * Module entry class for IIIF Bulk Import.
 */
class Module extends AbstractModule {

  /**
   * Return module configuration.
   *
   * @return array
   *   Module config.
   */
  public function getConfig(): array {
    return include __DIR__ . '/config/module.config.php';
  }

  /**
   * Bootstrap: register ACL for admin controller.
   */
  public function onBootstrap(MvcEvent $event): void {
    $services = $event->getApplication()->getServiceManager();
    $acl = $services->get('Omeka\\Acl');
    $resource = ImportController::class;
    if (!$acl->hasResource($resource)) {
      $acl->addResource($resource);
    }
    // Allow global administrators to access this controller.
    $acl->allow('global_admin', $resource);
  }

  /**
   * Render module configuration form.
   *
   * @param \Laminas\View\Renderer\PhpRenderer $renderer
   *   View renderer.
   *
   * @return string
   *   HTML form markup.
   */
  public function getConfigForm(PhpRenderer $renderer) {
    $services = $renderer->getHelperPluginManager()->getServiceLocator();
    $settings = $services->get('Omeka\\Settings');
    // Use FormElementManager to instantiate the form (Omeka core pattern).
    $form = $services->get('FormElementManager')->get(ConfigForm::class);

    // Populate defaults from settings.
    // Note: field names use underscores; settings keys keep dots.
    $form->setData([
      'iiif_bulk_image_size_fallbacks' => (string) ($settings->get('iiif_bulk.image_size_fallbacks') ?? '4000,2400,1600,1200,800'),
      'iiif_bulk_http_timeout' => (int) ($settings->get('iiif_bulk.http_timeout') ?? 20),
      'iiif_bulk_http_retries' => (int) ($settings->get('iiif_bulk.http_retries') ?? 3),
      'iiif_bulk_max_image_width' => (int) ($settings->get('iiif_bulk.max_image_width') ?? 20000),
      'iiif_bulk_max_image_height' => (int) ($settings->get('iiif_bulk.max_image_height') ?? 20000),
    ]);

    $form->prepare();
    return $renderer->formCollection($form);
  }

  /**
   * Handle module configuration form submission.
   *
   * @param \Laminas\Mvc\Controller\AbstractController $controller
   *   Admin controller.
   *
   * @return bool
   *   TRUE on success.
   */
  public function handleConfigForm(AbstractController $controller) {
    $services = $controller->getEvent()->getApplication()->getServiceManager();
    $settings = $services->get('Omeka\\Settings');
    $post = $controller->params()->fromPost();

    // Normalize list of sizes.
    $sizes = trim((string) ($post['iiif_bulk_image_size_fallbacks'] ?? ''));
    // Normalize Japanese comma and spaces to ASCII comma; collapse repeats.
    $sizes = (string) preg_replace('/[\x{3001}\x{FF0C}\s]+/u', ',', $sizes);

    $timeout = (int) ($post['iiif_bulk_http_timeout'] ?? 20);
    $retries = (int) ($post['iiif_bulk_http_retries'] ?? 3);
    $maxW = (int) ($post['iiif_bulk_max_image_width'] ?? 20000);
    $maxH = (int) ($post['iiif_bulk_max_image_height'] ?? 20000);

    // Persist settings.
    $settings->set('iiif_bulk.image_size_fallbacks', $sizes !== '' ? $sizes : '4000,2400,1600,1200,800');
    $settings->set('iiif_bulk.http_timeout', max(1, $timeout));
    $settings->set('iiif_bulk.http_retries', max(1, $retries));
    $settings->set('iiif_bulk.max_image_width', max(1, $maxW));
    $settings->set('iiif_bulk.max_image_height', max(1, $maxH));

    $controller->messenger()->addSuccess('IiifBulkImport settings were saved.');
    return TRUE;
  }

}
