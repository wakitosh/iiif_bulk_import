<?php

/**
 * @file
 * IiifBulkImport module configuration.
 */

use IiifBulkImport\Controller\Admin\ImportController;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use IiifBulkImport\Form\ManifestImportForm;
use IiifBulkImport\Form\ConfigForm;
use Laminas\ServiceManager\Factory\InvokableFactory;

return [
  'router' => [
    'routes' => [
      'iiif-bulk-import-admin' => [
        'type' => Literal::class,
        'options' => [
          'route' => '/admin/iiif-bulk-import',
          'defaults' => [
            'controller' => ImportController::class,
            'action' => 'index',
            '__ADMIN__' => TRUE,
            '__NAMESPACE__' => 'IiifBulkImport\\Controller\\Admin',
          ],
        ],
        'may_terminate' => TRUE,
      ],
      'admin' => [
        'child_routes' => [
          'iiif-bulk-import' => [
            'type' => Segment::class,
            'options' => [
              'route' => '/iiif-bulk-import[/:action]'
              . '',
              'defaults' => [
                '__NAMESPACE__' => 'IiifBulkImport\\Controller\\Admin',
                'controller' => ImportController::class,
                'action' => 'index',
                '__ADMIN__' => TRUE,
              ],
            ],
            'may_terminate' => TRUE,
          ],
        ],
      ],
    ],
  ],
  'controllers' => [
    'factories' => [
      ImportController::class => InvokableFactory::class,
    ],
    'aliases' => [
      // Use admin/default route ":controller" segment as "iiif-bulk-import".
      'iiif-bulk-import' => ImportController::class,
      // When __NAMESPACE__ is applied by the admin route, this name is used.
      'Omeka\\Controller\\Admin\\iiif-bulk-import' => ImportController::class,
      // Some Laminas setups normalize dashed to CamelCase.
      'Omeka\\Controller\\Admin\\IiifBulkImport' => ImportController::class,
      // And sometimes include the Controller suffix.
      'Omeka\\Controller\\Admin\\IiifBulkImportController' => ImportController::class,
    ],
  ],

  'form_elements' => [
    'factories' => [
      ManifestImportForm::class => InvokableFactory::class,
      ConfigForm::class => InvokableFactory::class,
    ],
  ],

  'view_manager' => [
    'template_path_stack' => [
      __DIR__ . '/../view',
    ],
  ],
  'navigation' => [
    'AdminGlobal' => [
      [
        'label' => 'IIIF一括インポート',
        'route' => 'iiif-bulk-import-admin',
        'visible' => TRUE,
      ],
    ],
  ],
];
