<?php

namespace IiifBulkImport\Form;

use Laminas\Form\Element\Number;
use Laminas\Form\Element\Text;
use Laminas\Form\Form;

/**
 * Module settings form for IiifBulkImport.
 */
class ConfigForm extends Form {

  /**
   * Build module configuration form elements.
   */
  public function init(): void {
    $this->add([
      'name' => 'iiif_bulk_image_size_fallbacks',
      'type' => Text::class,
      'options' => [
        'label' => 'Image size fallbacks (px, high→low, comma-separated)',
      ],
      'attributes' => [
        'placeholder' => '4000,2400,1600,1200,800',
      ],
    ]);

    $this->add([
      'name' => 'iiif_bulk_http_timeout',
      'type' => Number::class,
      'options' => [
        'label' => 'HTTP timeout (seconds)',
      ],
      'attributes' => [
        'min' => 1,
        'step' => 1,
      ],
    ]);

    $this->add([
      'name' => 'iiif_bulk_http_retries',
      'type' => Number::class,
      'options' => [
        'label' => 'HTTP retries (count)',
      ],
      'attributes' => [
        'min' => 1,
        'step' => 1,
      ],
    ]);

    $this->add([
      'name' => 'iiif_bulk_max_image_width',
      'type' => Number::class,
      'options' => [
        'label' => 'Max original width (px)',
      ],
      'attributes' => [
        'min' => 1,
        'step' => 1,
      ],
    ]);

    $this->add([
      'name' => 'iiif_bulk_max_image_height',
      'type' => Number::class,
      'options' => [
        'label' => 'Max original height (px)',
      ],
      'attributes' => [
        'min' => 1,
        'step' => 1,
      ],
    ]);

    // CSRF is provided by Omeka core on the settings page; no need to add here.
  }

}
