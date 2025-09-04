<?php

namespace IiifBulkImport\Form;

use Laminas\Form\Element\Csrf;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Textarea;
use Laminas\Form\Form;

/**
 * Form to accept one or more IIIF manifest URLs (one per line).
 */
class ManifestImportForm extends Form {

  /**
   * Initialize elements.
   */
  public function init(): void {
    $this->add([
      'name' => 'manifest_urls',
      'type' => Textarea::class,
      'options' => [
        // @translate
        'label' => 'IIIF Manifest URLs (one per line)',
      ],
      'attributes' => [
        'required' => TRUE,
        'rows' => 6,
        'placeholder' => "https://example.org/iiif/manifest1.json\nhttps://example.org/iiif/manifest2.json",
      ],
    ]);

    $this->add([
      'name' => 'csrf',
      'type' => Csrf::class,
    ]);

    $this->add([
      'name' => 'submit',
      'type' => Submit::class,
      'attributes' => [
        // @translate
        'value' => 'Import',
        'class' => 'o-button',
      ],
    ]);
  }

}
