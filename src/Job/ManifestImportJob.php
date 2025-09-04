<?php

namespace IiifBulkImport\Job;

use Omeka\Job\AbstractJob;

/**
 * Import one IIIF manifest URL as an Item with a IIIF Presentation media.
 */
class ManifestImportJob extends AbstractJob {

  /**
   * Cache of property term => id.
   *
   * @var array<string,int>
   */
  protected $propertyIdCache = [];

  /**
   * Execute the job.
   */
  public function perform() {
    $logger = $this->getServiceLocator()->get('Omeka\Logger');
    $raw = (string) $this->getArg('manifest_urls', '');
    $urls = array_values(array_filter(array_map('trim', preg_split("/(\r?\n)+/", $raw)), function ($u) {
      return $u !== '';
    }));
    if (!$urls) {
      $logger->err('[IiifBulkImport] No manifest URLs provided.');
      return;
    }

    $api = $this->getServiceLocator()->get('Omeka\\ApiManager');
    $baseTemplateId = $this->getBaseResourceTemplateId();

    foreach ($urls as $url) {
      $logger->info(sprintf('[IiifBulkImport] Start import. URL: %s', $url));
      try {
        $manifest = $this->fetchJson($url);
      }
      catch (\Exception $e) {
        $logger->err(sprintf('[IiifBulkImport] Fetch failed: %s', $e->getMessage()));
        continue;
      }

      $label = $this->extractLabel($manifest) ?: $url;
      $media = [];
      $canvasImageInfoUrls = $this->extractIiifCanvasImageUrls($manifest);
      foreach ($canvasImageInfoUrls as $imgInfoUrl) {
        $media[] = [
          'o:ingester' => 'iiif',
          'o:source' => $imgInfoUrl,
        ];
      }
      if (!$media) {
        // Fallback: attach the manifest itself as iiif_presentation.
        $media[] = [
          'o:ingester' => 'iiif_presentation',
          'o:source' => $url,
        ];
      }

      $props = $this->mapDcterms($manifest, $url, $this->countCanvases($manifest));
      // Ensure title present/preferred.
      $props['dcterms:title'] = [
        [
          '@value' => $label,
        ],
      ];

      // If there are image canvases, set a sensible dcterms:type
      // (DCMI Still Image).
      if ($canvasImageInfoUrls) {
        $typeUri = 'http://purl.org/dc/dcmitype/StillImage';
        $already = FALSE;
        if (!empty($props['dcterms:type'])) {
          foreach ($props['dcterms:type'] as $tv) {
            if (isset($tv['@id']) && $tv['@id'] === $typeUri) {
              $already = TRUE;
              break;
            }
          }
        }
        if (!$already) {
          $props['dcterms:type'][] = [
            '@id' => $typeUri,
            'type' => 'uri',
          ];
        }
      }

      // Ensure property ids are present for all values (improves reliability).
      $props = $this->injectPropertyIds($props);

      $itemData = $props + [
        'o:media' => $media,
      ];
      if ($baseTemplateId) {
        $itemData['o:resource_template'] = ['o:id' => $baseTemplateId];
      }

      try {
        $response = $api->create('items', $itemData);
        $item = $response->getContent();
        $itemId = method_exists($item, 'id') ? $item->id() : NULL;
        $logger->info(sprintf('[IiifBulkImport] Created Item #%s with %d media.', (string) $itemId, count($media)));
      }
      catch (\Exception $e) {
        $logger->err(sprintf('[IiifBulkImport] API create failed: %s', $e->getMessage()));
        continue;
      }
    }

    $logger->info('[IiifBulkImport] Job completed.');
  }

  /**
   * Fetch JSON from a URL using Omeka's HttpClient.
   */
  protected function fetchJson(string $url): array {
    /** @var \Laminas\Http\Client $client */
    $client = $this->getServiceLocator()->get('Omeka\\HttpClient');
    $client->reset();
    $client->setUri($url);
    $response = $client->send();
    if (!$response->isOk()) {
      throw new \RuntimeException(sprintf('HTTP %s %s', $response->getStatusCode(), $response->getReasonPhrase()));
    }
    $data = json_decode($response->getBody(), TRUE);
    if (!is_array($data)) {
      throw new \RuntimeException('Invalid JSON');
    }
    return $data;
  }

  /**
   * Extract a readable label from IIIF v2/v3 manifest.
   */
  protected function extractLabel(array $manifest): string {
    // IIIF v3: label is a language map.
    if (isset($manifest['label']) && is_array($manifest['label'])) {
      $langs = ['ja', 'en', 'none'];
      foreach ($langs as $lg) {
        if (!empty($manifest['label'][$lg][0])) {
          return (string) $manifest['label'][$lg][0];
        }
        // Sometimes keys include an at-sign in dumps.
        if (!empty($manifest['label']['@' . $lg][0])) {
          return (string) $manifest['label']['@' . $lg][0];
        }
      }
      // Fallback: first scalar value we can find.
      foreach ($manifest['label'] as $vals) {
        if (is_array($vals) && !empty($vals[0])) {
          return (string) $vals[0];
        }
      }
    }
    // IIIF v2: label is commonly a string.
    if (isset($manifest['label']) && is_string($manifest['label'])) {
      return $manifest['label'];
    }
    return '';
  }

  /**
   * Count canvases in a v2/v3 manifest.
   */
  protected function countCanvases(array $manifest): int {
    if (!empty($manifest['items']) && is_array($manifest['items'])) {
      return count($manifest['items']);
    }
    if (!empty($manifest['sequences'][0]['canvases'])
      && is_array($manifest['sequences'][0]['canvases'])
    ) {
      return count($manifest['sequences'][0]['canvases']);
    }
    return 0;
  }

  /**
   * Extract IIIF image info.json URLs from v2/v3 canvases.
   *
   * Non-IIIF images are ignored.
   */
  protected function extractIiifCanvasImageUrls(array $manifest): array {
    $urls = [];
    // v3: items[] -> canvases, each canvas.items[] -> annotations.
    // Annotation body typically has a service/id pointing to an Image API.
    if (!empty($manifest['items']) && is_array($manifest['items'])) {
      foreach ($manifest['items'] as $canvas) {
        $urls = array_merge($urls, $this->extractFromCanvasV3($canvas));
      }
    }
    // v2: sequences[0].canvases[]
    // Each canvas.images[0].resource.service['@id'].
    if (!empty($manifest['sequences'][0]['canvases']) && is_array($manifest['sequences'][0]['canvases'])) {
      foreach ($manifest['sequences'][0]['canvases'] as $canvas) {
        $urls = array_merge($urls, $this->extractFromCanvasV2($canvas));
      }
    }
    return array_values(array_unique($urls));
  }

  /**
   * Extract IIIF Image API service URLs from a v3 canvas.
   */
  protected function extractFromCanvasV3(array $canvas): array {
    $urls = [];
    if (empty($canvas['items']) || !is_array($canvas['items'])) {
      return $urls;
    }
    foreach ($canvas['items'] as $annopage) {
      if (empty($annopage['items']) || !is_array($annopage['items'])) {
        continue;
      }
      foreach ($annopage['items'] as $anno) {
        $body = $anno['body'] ?? NULL;
        if (!$body) {
          continue;
        }
        // Body may be array or object-like array. Normalize to array list.
        $bodies = isset($body[0]) ? $body : [$body];
        foreach ($bodies as $b) {
          // Service may be array or single.
          if (isset($b['service'])) {
            $svc = $b['service'];
            $svcs = isset($svc[0]) ? $svc : [$svc];
            foreach ($svcs as $s) {
              $id = $s['id'] ?? ($s['@id'] ?? NULL);
              if ($id) {
                $urls[] = rtrim($id, '/') . '/info.json';
              }
            }
          }
        }
      }
    }
    return $urls;
  }

  /**
   * Extract IIIF Image API service URLs from a v2 canvas.
   */
  protected function extractFromCanvasV2(array $canvas): array {
    $urls = [];
    if (empty($canvas['images']) || !is_array($canvas['images'])) {
      return $urls;
    }
    foreach ($canvas['images'] as $img) {
      $res = $img['resource'] ?? NULL;
      if (!$res) {
        continue;
      }
      $svc = $res['service'] ?? NULL;
      if (!$svc) {
        continue;
      }
      $id = $svc['@id'] ?? ($svc['id'] ?? NULL);
      if ($id) {
        $urls[] = rtrim($id, '/') . '/info.json';
      }
    }
    return $urls;
  }

  /**
   * Get the ID of the "Base Resource" resource template, if available.
   */
  protected function getBaseResourceTemplateId(): ?int {
    try {
      $api = $this->getServiceLocator()->get('Omeka\\ApiManager');
      // Try common label variants and a case-insensitive search.
      foreach (['Base resource', 'Base Resource'] as $label) {
        $templates = $api->search('resource_templates', ['label' => $label])->getContent();
        if ($templates && isset($templates[0])) {
          return (int) $templates[0]->id();
        }
      }
      // Fallback: list and pick first template labeled like "base resource".
      $templates = $api->search('resource_templates', [])->getContent();
      foreach ($templates as $tpl) {
        if (method_exists($tpl, 'label')) {
          $lab = (string) $tpl->label();
          if (mb_strtolower($lab) === 'base resource') {
            return (int) $tpl->id();
          }
        }
      }
    }
    catch (\Exception $e) {
      // Ignore; template may not exist.
    }
    return NULL;
  }

  /**
   * Map IIIF manifest fields to Dublin Core Terms properties.
   *
   * @return array
   *   JSON-LD property array keyed by dcterms local names.
   */
  protected function mapDcterms(array $manifest, string $manifestUrl, int $canvasCount): array {
    $props = [];

    // Description: v3 summary or v2 description or metadata fallback.
    $summary = $this->extractLangValue($manifest['summary'] ?? NULL);
    if (!$summary && isset($manifest['description']) && is_string($manifest['description'])) {
      $summary = $this->stripTags($manifest['description']);
    }
    if (!$summary) {
      $meta = $this->extractMetadataMap($manifest);
      foreach (['description', '概要', '説明'] as $k) {
        if (!empty($meta[$k])) {
          $summary = $meta[$k][0];
          break;
        }
      }
    }
    if ($summary) {
      $props['dcterms:description'][] = [
        '@value' => $summary,
      ];
    }

    // Rights: v3 rights (URI/string), requiredStatement.value,
    // v2 attribution/license, metadata 'rights'.
    foreach ($this->extractRightsValues($manifest) as $r) {
      if ($this->looksLikeUrl($r)) {
        $props['dcterms:rights'][] = [
          '@id' => $r,
        ];
      }
      else {
        $props['dcterms:rights'][] = [
          '@value' => $r,
        ];
      }
    }

    // Identifier: manifest URL + metadata identifiers.
    $props['dcterms:identifier'][] = [
      '@id' => $manifestUrl,
    ];

    $meta = $meta ?? $this->extractMetadataMap($manifest);

    // Provider (v3): map to publisher if present.
    foreach ($this->extractProviderLabels($manifest) as $prov) {
      $props['dcterms:publisher'][] = [
        '@value' => $prov,
      ];
    }

    $this->copyMeta($meta, $props, [
      // Label => dcterms.
      'creator' => 'dcterms:creator',
      'author' => 'dcterms:creator',
      'artist' => 'dcterms:creator',
      'photographer' => 'dcterms:creator',
      '作成者' => 'dcterms:creator',
      '著者' => 'dcterms:creator',
      '作者' => 'dcterms:creator',
      '写真家' => 'dcterms:creator',

      'publisher' => 'dcterms:publisher',
      '発行者' => 'dcterms:publisher',
      '出版者' => 'dcterms:publisher',

      'contributor' => 'dcterms:contributor',
      '貢献者' => 'dcterms:contributor',

      'date' => 'dcterms:date',
      'issued' => 'dcterms:date',
      'created' => 'dcterms:date',
      '日付' => 'dcterms:date',
      '作成日' => 'dcterms:date',
      '制作年' => 'dcterms:date',
      '発行年' => 'dcterms:date',

      'language' => 'dcterms:language',
      '言語' => 'dcterms:language',

      'identifier' => 'dcterms:identifier',
      'id' => 'dcterms:identifier',
      '識別子' => 'dcterms:identifier',

      'subject' => 'dcterms:subject',
      'keywords' => 'dcterms:subject',
      '主題' => 'dcterms:subject',
      '件名' => 'dcterms:subject',
      'キーワード' => 'dcterms:subject',

      'coverage' => 'dcterms:spatial',
      'spatial' => 'dcterms:spatial',
      'place' => 'dcterms:spatial',
      '場所' => 'dcterms:spatial',
      '地域' => 'dcterms:spatial',

      'temporal' => 'dcterms:temporal',
      '期間' => 'dcterms:temporal',
      '時期' => 'dcterms:temporal',

      'format' => 'dcterms:format',
      '形式' => 'dcterms:format',
      'フォーマット' => 'dcterms:format',

      'type' => 'dcterms:type',
      'タイプ' => 'dcterms:type',

      'rights' => 'dcterms:rights',
      'ライセンス' => 'dcterms:rights',
      '権利' => 'dcterms:rights',

      'rights holder' => 'dcterms:rightsHolder',
      'rights holder(s)' => 'dcterms:rightsHolder',
      '権利者' => 'dcterms:rightsHolder',

      'alternative title' => 'dcterms:alternative',
      'title alternative' => 'dcterms:alternative',
      '代替タイトル' => 'dcterms:alternative',
      '別タイトル' => 'dcterms:alternative',
    ]);

    // Extent: number of canvases.
    if ($canvasCount > 0) {
      $props['dcterms:extent'][] = [
        '@value' => sprintf('%d canvases', $canvasCount),
      ];
    }

    // Related links: homepage and seeAlso.
    foreach ($this->extractHomepageUris($manifest) as $uri) {
      $props['dcterms:relation'][] = [
        '@id' => $uri,
      ];
    }
    foreach ($this->extractSeeAlsoUris($manifest) as $uri) {
      $props['dcterms:relation'][] = [
        '@id' => $uri,
      ];
    }

    return $props;
  }

  /**
   * Normalize IIIF metadata to a map: lowercase label => array of strings.
   */
  protected function extractMetadataMap(array $manifest): array {
    $out = [];
    $metadata = $manifest['metadata'] ?? [];
    if (!is_array($metadata)) {
      return $out;
    }
    foreach ($metadata as $entry) {
      $label = $this->extractLangValue($entry['label'] ?? NULL);
      if ('' === $label) {
        continue;
      }
      $labelKey = mb_strtolower(trim($label));
      $value = $this->extractLangValue($entry['value'] ?? NULL);
      if ('' !== $value) {
        $out[$labelKey][] = $value;
      }
    }
    return $out;
  }

  /**
   * Extract rights-related values (URIs and/or text).
   *
   * Returns de-duplicated string list.
   */
  protected function extractRightsValues(array $manifest): array {
    $vals = [];
    // v3 rights: string or array of URIs.
    if (isset($manifest['rights'])) {
      if (is_array($manifest['rights'])) {
        foreach ($manifest['rights'] as $r) {
          if (is_string($r)) {
            $vals[] = $r;
          }
        }
      }
      elseif (is_string($manifest['rights'])) {
        $vals[] = $manifest['rights'];
      }
    }
    // v3 requiredStatement.value: text attribution.
    if (!empty($manifest['requiredStatement']['value'])) {
      $txt = $this->extractLangValue($manifest['requiredStatement']['value']);
      if ($txt !== '') {
        $vals[] = $txt;
      }
    }
    // v2 attribution.
    if (isset($manifest['attribution']) && is_string($manifest['attribution'])) {
      $vals[] = $this->stripTags($manifest['attribution']);
    }
    // v2 license (URI or array).
    if (isset($manifest['license'])) {
      if (is_array($manifest['license'])) {
        foreach ($manifest['license'] as $r) {
          if (is_string($r)) {
            $vals[] = $r;
          }
        }
      }
      elseif (is_string($manifest['license'])) {
        $vals[] = $manifest['license'];
      }
    }
    // metadata: rights.
    $meta = $this->extractMetadataMap($manifest);
    foreach (['rights', '権利', 'ライセンス'] as $k) {
      if (!empty($meta[$k])) {
        foreach ($meta[$k] as $v) {
          $vals[] = $v;
        }
      }
    }
    // De-duplicate while preserving order.
    $vals = array_values(array_unique(array_filter(array_map('trim', $vals), function ($v) {
      return $v !== '';
    })));
    return $vals;
  }

  /**
   * Extract provider labels (v3) as strings.
   */
  protected function extractProviderLabels(array $manifest): array {
    $out = [];
    $prov = $manifest['provider'] ?? NULL;
    if (!$prov) {
      return $out;
    }
    $providers = isset($prov[0]) ? $prov : [$prov];
    foreach ($providers as $p) {
      $lab = $this->extractLangValue($p['label'] ?? NULL);
      if ($lab !== '') {
        $out[] = $lab;
      }
    }
    return array_values(array_unique($out));
  }

  /**
   * Extract homepage URIs (v3).
   */
  protected function extractHomepageUris(array $manifest): array {
    $uris = [];
    $hp = $manifest['homepage'] ?? NULL;
    if (!$hp) {
      return $uris;
    }
    $homes = isset($hp[0]) ? $hp : [$hp];
    foreach ($homes as $h) {
      $id = $h['id'] ?? ($h['@id'] ?? NULL);
      if ($id) {
        $uris[] = $id;
      }
    }
    return array_values(array_unique($uris));
  }

  /**
   * Extract seeAlso URIs (v2/v3).
   */
  protected function extractSeeAlsoUris(array $manifest): array {
    $uris = [];
    $sa = $manifest['seeAlso'] ?? NULL;
    if (!$sa) {
      return $uris;
    }
    $list = isset($sa[0]) ? $sa : [$sa];
    foreach ($list as $s) {
      $id = $s['id'] ?? ($s['@id'] ?? NULL);
      if ($id) {
        $uris[] = $id;
      }
    }
    return array_values(array_unique($uris));
  }

  /**
   * Copy selected metadata labels to DCTERMS properties.
   *
   * @param array $meta
   *   Map: lowercased label => [values].
   * @param array &$props
   *   Output properties.
   * @param array $labelMap
   *   Map: label => dcterms:local.
   */
  protected function copyMeta(array $meta, array &$props, array $labelMap): void {
    foreach ($labelMap as $label => $term) {
      $key = mb_strtolower($label);
      if (empty($meta[$key])) {
        continue;
      }
      foreach ($meta[$key] as $v) {
        $v = trim($this->stripTags($v));
        if ($v === '') {
          continue;
        }
        if ($term === 'dcterms:identifier' && $this->looksLikeUrl($v)) {
          $props[$term][] = [
            '@id' => $v,
          ];
        }
        else {
          $props[$term][] = [
            '@value' => $v,
          ];
        }
      }
    }
  }

  /**
   * Extract a string from a IIIF language map or raw string.
   */
  protected function extractLangValue($val): string {
    if (is_string($val)) {
      return trim($this->stripTags($val));
    }
    if (is_array($val)) {
      // Language map: prefer ja, en, none, then first available.
      foreach (['ja', 'en', 'none'] as $lg) {
        $k = isset($val[$lg]) ? $lg : ('@' . $lg);
        if (!empty($val[$k][0]) && is_string($val[$k][0])) {
          return trim($this->stripTags($val[$k][0]));
        }
      }
      foreach ($val as $v) {
        if (is_array($v) && !empty($v[0]) && is_string($v[0])) {
          return trim($this->stripTags($v[0]));
        }
        if (is_string($v)) {
          return trim($this->stripTags($v));
        }
      }
    }
    return '';
  }

  /**
   * Strip tags and decode basic entities.
   */
  protected function stripTags(string $s): string {
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(strip_tags($s));
  }

  /**
   * Heuristic: does the string look like a URL?
   */
  protected function looksLikeUrl(string $s): bool {
    return (bool) preg_match('~^https?://~i', $s);
  }

  /**
   * Add property_id to each value entry based on the property term.
   */
  protected function injectPropertyIds(array $props): array {
    if (!$props) {
      return $props;
    }
    foreach ($props as $term => &$values) {
      $propId = $this->getPropertyId($term);
      if (!$propId || !is_array($values)) {
        continue;
      }
      foreach ($values as &$v) {
        if (!is_array($v)) {
          continue;
        }
        // Don't override if already set.
        if (!isset($v['property_id'])) {
          $v['property_id'] = $propId;
        }
        // Ensure a valid data type is set to satisfy the ValueHydrator.
        if (!isset($v['type']) || !is_string($v['type']) || $v['type'] === '') {
          $v['type'] = isset($v['@id']) ? 'uri' : 'literal';
        }
      }
      unset($v);
    }
    unset($values);
    return $props;
  }

  /**
   * Get a property id from a term like "dcterms:title".
   */
  protected function getPropertyId(string $term): ?int {
    if (isset($this->propertyIdCache[$term])) {
      return $this->propertyIdCache[$term] ?: NULL;
    }
    try {
      $api = $this->getServiceLocator()->get('Omeka\\ApiManager');
      $response = $api->search('properties', ['term' => $term]);
      $list = $response->getContent();
      $prop = is_array($list) && count($list) ? reset($list) : NULL;
      $id = $prop && method_exists($prop, 'id') ? (int) $prop->id() : NULL;
      $this->propertyIdCache[$term] = $id ?: 0;
      return $id;
    }
    catch (\Exception $e) {
      $this->propertyIdCache[$term] = 0;
      return NULL;
    }
  }

}
