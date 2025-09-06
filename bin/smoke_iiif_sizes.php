#!/usr/bin/env php
<?php
/**
 * @file
 * Quick smoke test: fetch a manifest, extract IIIF info.jsons similarly to the job,
 */

// Then probe descending sized URLs to see the first that returns 200 OK.
// This does not require Omeka runtime; it is a standalone CLI.
if (PHP_SAPI !== 'cli') {
  fwrite(STDERR, "Run from CLI.\n");
  exit(1);
}

$manifests = array_slice($argv, 1);
if (!$manifests) {
  fwrite(STDERR, "Usage: smoke_iiif_sizes.php <manifest_url> [<manifest_url> ...]\n");
  exit(2);
}

$sizes = [4000, 2400, 1600, 1200, 800];
$timeout = 15;

/**
 *
 */
function http_get_json($url, $timeout) {
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'timeout' => $timeout,
      'follow_location' => 1,
      'header' => ['Accept: application/json'],
    ],
  ]);
  $body = @file_get_contents($url, FALSE, $ctx);
  if ($body === FALSE) {
    throw new RuntimeException("GET failed: $url");
  }
  $data = json_decode($body, TRUE);
  if (!is_array($data)) {
    throw new RuntimeException("Invalid JSON: $url");
  }
  return $data;
}

/**
 *
 */
function build_sized_url($infoJsonUrl, $edge) {
  $base = rtrim(preg_replace('~/(info\.json)$~', '', rtrim($infoJsonUrl, '/')), '/');
  return $base . '/full/,' . $edge . '/0/default.jpg';
}

/**
 *
 */
function extract_info_urls($manifest) {
  $urls = [];
  if (!empty($manifest['items']) && is_array($manifest['items'])) {
    foreach ($manifest['items'] as $canvas) {
      if (empty($canvas['items'])) {
        continue;
      }
      foreach ($canvas['items'] as $ap) {
        if (empty($ap['items'])) {
          continue;
        }
        foreach ($ap['items'] as $anno) {
          $body = $anno['body'] ?? NULL;
          if (!$body) {
            continue;
          }
          $bodies = isset($body[0]) ? $body : [$body];
          foreach ($bodies as $b) {
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
    }
  }
  if (!empty($manifest['sequences'][0]['canvases'])) {
    foreach ($manifest['sequences'][0]['canvases'] as $canvas) {
      $images = $canvas['images'] ?? [];
      foreach ($images as $img) {
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
    }
  }
  return array_values(array_unique($urls));
}

/**
 *
 */
function http_head($url, $timeout) {
  $ctx = stream_context_create([
    'http' => [
      'method' => 'HEAD',
      'timeout' => $timeout,
      'follow_location' => 1,
    ],
  ]);
  $fp = @fopen($url, 'r', FALSE, $ctx);
  if (!$fp) {
    return FALSE;
  }
  $meta = stream_get_meta_data($fp);
  fclose($fp);
  foreach ($meta['wrapper_data'] as $h) {
    if (preg_match('~^HTTP/[^ ]+\s+(\d{3})~', $h, $m)) {
      $code = (int) $m[1];
      return $code >= 200 && $code < 400;
    }
  }
  return FALSE;
}

foreach ($manifests as $mu) {
  echo "Manifest: $mu\n";
  try {
    $m = http_get_json($mu, $timeout);
  }
  catch (Throwable $e) {
    echo "  ERROR manifest fetch: " . $e->getMessage() . "\n";
    continue;
  }
  $infos = extract_info_urls($m);
  echo "  info.json count: " . count($infos) . "\n";
  $ok = 0;
  $fail = 0;
  foreach ($infos as $info) {
    $okForThis = FALSE;
    foreach ($sizes as $edge) {
      $url = build_sized_url($info, $edge);
      $okHead = http_head($url, $timeout);
      if ($okHead) {
        $okForThis = TRUE;
        break;
      }
    }
    if ($okForThis) {
      $ok++;
    }
    else {
      $fail++;
      echo "    FAIL: $info\n";
    }
  }
  echo "  reachable (some size): $ok, failures: $fail\n";
}
