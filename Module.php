<?php

namespace IiifBulkImport;

use Omeka\Module\AbstractModule;
use Laminas\Mvc\MvcEvent;
use IiifBulkImport\Controller\Admin\ImportController;

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

}
