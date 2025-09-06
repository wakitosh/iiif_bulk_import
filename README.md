# IiifBulkImport (Omeka S Module)

A robust bulk importer for IIIF manifests (Presentation v2/v3) for Omeka S. This module allows you to import multiple IIIF manifests at once by simply providing their URLs. It is designed to be both powerful and resilient, handling common import issues gracefully.

## Key Features

- **Bulk Import**: Paste multiple manifest URLs (one per line) to create corresponding Omeka S items in a single batch job.
- **Configurable Settings**: A new settings page allows administrators to configure:
  - Fallback image sizes (height-based).
  - HTTP timeout and retry counts.
  - Maximum image dimensions (width/height) to prevent processing errors.
- **Enhanced Robustness**:
  - **Height-Based Fallbacks**: If the default IIIF import fails, the module now attempts to create media using specific image heights (e.g., 4000px, 2400px), improving compatibility with various IIIF servers.
  - **Job Summary**: Job logs now include a summary with a sample of failed media URLs, making it easier to diagnose issues.
- **Intelligent Media Creation**:
  - Prefers the `iiif` ingester first for best quality.
  - If that fails, it tries a series of height-constrained image URLs (`/full/,{height}/0/default.jpg`).
  - As a last resort, it attaches the manifest itself using the `iiif_presentation` ingester.
- **Automatic Metadata Mapping**:
  - Maps descriptive metadata from the manifest (v2/v3) to Dublin Core Terms fields.
  - Supports common English and Japanese labels for fields like `title`, `creator`, `description`, `publisher`, `rights`, etc.
  - Automatically populates `dcterms:type` with "DCMI Still Image" for items with image media.
  - Adds the manifest URL to `dcterms:identifier` and canvas count to `dcterms:extent`.
- **Resilient and Robust**:
  - **Network Retries**: Automatically retries fetching manifests and `info.json` with exponential backoff if a network error occurs (configurable).
  - **Oversize Image Filtering**: To prevent common ImageMagick memory/dimension errors, it reads image `width` and `height` from `info.json` and skips images larger than the configured dimensions.
  - **API Fallback**: If creating an item with many media fails (e.g., due to a problematic derivative), it will fall back to creating the item with just the manifest attached, ensuring no data is lost.
- **Resource Template Integration**: If a resource template named "Base Resource" exists, it is automatically applied to newly created items.

> **Note**: Manifests with many canvases may take time to import due to thumbnail generation and multiple external HTTP requests. The job runs in the background, so you can continue using Omeka S while it processes.

## Requirements
- Omeka S (v4.x or later)
- PHP 8.1+

## Installation
1. Download the latest release and place the repository at `omeka-s/modules/IiifBulkImport`.
2. Log in as an administrator, go to the "Modules" section, and enable "IiifBulkImport".
3. (Optional) Configure the module settings via the "Configure" button to adjust timeouts, retries, and image size limits.

## Usage
1. After installation, a new "IIIF Bulk Import" link will appear in the admin navigation menu.
2. Click the link to open the import form.
3. Paste your IIIF manifest URLs into the text area, with each URL on a new line.
4. Click "Import" to start the background job.
5. You will be redirected to the job status page, where you can monitor the import progress.

## Detailed Metadata Mapping
The module attempts to map the following fields from the IIIF manifest to Dublin Core Terms:

| Dublin Core Term        | IIIF Manifest Source(s)                                                              |
| ----------------------- | ------------------------------------------------------------------------------------ |
| `dcterms:title`         | `label` (v2/v3)                                                                      |
| `dcterms:description`   | `summary` (v3), `description` (v2), or metadata with "description" label             |
| `dcterms:rights`        | `rights` (v3), `requiredStatement` (v3), `license` (v2), `attribution` (v2)          |
| `dcterms:identifier`    | Manifest URL, plus any metadata fields labeled "identifier" or "id"                  |
| `dcterms:publisher`     | `provider.label` (v3) or metadata labeled "publisher"                                |
| `dcterms:relation`      | `homepage` and `seeAlso` URIs                                                        |
| `dcterms:extent`        | Number of canvases in the manifest                                                   |
| `dcterms:type`          | "DCMI Still Image" (if image media is created)                                       |
| Other Mapped Fields     | `creator`, `contributor`, `date`, `language`, `subject`, `spatial`, `temporal`, `format`, `rightsHolder`, `alternative` (with English/Japanese label support) |

## License
GPL-3.0-or-later

Copyright (C) 2025

---

# IiifBulkImport（日本語）

Omeka S 向けの堅牢な IIIF マニフェスト一括インポートモジュールです。複数の IIIF マニフェスト URL を一度に指定して、アイテムを一括で作成できます。一般的なインポート時の問題を自動で回避する、安定性の高い設計になっています。

## 主な機能

- **一括インポート**: 複数のマニフェスト URL を改行区切りで貼り付けるだけで、対応する Omeka S アイテムを一つのバッチジョブで作成します。
- **設定画面**: 管理者は以下の項目を設定できます。
  - フォールバックで試行する画像の高さ
  - HTTP通信のタイムアウトとリトライ回数
  - 処理エラーを防ぐための最大画像サイズ（幅・高さ）
- **堅牢性の強化**:
  - **高さベースのフォールバック**: `iiif` インジェスタでのインポートに失敗した場合、特定の画像の高さ（例: 4000px, 2400px）を指定してメディア作成を再試行します。これにより、様々なIIIFサーバとの互換性が向上します。
  - **ジョブ要約**: ジョブログに、失敗したメディアURLのサンプルを含む要約が追加され、問題の診断が容易になりました。
- **インテリジェントなメディア作成**:
  - 最高品質のため、まず `iiif` インジェスタを試します。
  - 失敗した場合、高さを指定した画像URL（`/full/,{height}/0/default.jpg`）を順に試します。
  - 最終手段として、`iiif_presentation` インジェスタを使い、マニフェスト自体をメディアとして添付します。
- **メタデータの自動マッピング**:
  - マニフェスト（v2/v3）のメタデータを Dublin Core Terms の各フィールドにマッピングします。
  - `title`, `creator`, `description`, `publisher`, `rights` などのフィールドは、一般的な英語・日本語ラベルに対応しています。
  - 画像メディアを持つアイテムには、`dcterms:type` に「DCMI Still Image」を自動で設定します。
  - `dcterms:identifier` にマニフェスト URL を、`dcterms:extent` に Canvas 数を自動で入力します。
- **安定性と堅牢性**:
  - **ネットワークリトライ**: マニフェストや `info.json` の取得時にネットワークエラーが発生した場合、設定された回数まで自動でリトライします。
  - **巨大画像フィルタリング**: ImageMagick のメモリや画像サイズ上限によるエラーを防ぐため、`info.json` から画像の `width` と `height` を読み取り、設定された上限を超える画像はスキップします。
  - **APIフォールバック**: 多数のメディアを持つアイテムの作成に失敗した場合（例：問題のあるメディア生成）、マニフェストのみを添付した状態でアイテムを作成し、データの損失を防ぎます。
- **リソーステンプレート連携**: 「Base Resource」という名前のリソーステンプレートが存在する場合、新規作成されたアイテムに自動で適用されます。

> **注意**: 多数の Canvas を持つマニフェストは、サムネイル生成や外部への複数回の HTTP リクエストのため、インポートに時間がかかることがあります。ジョブはバックグラウンドで実行されるため、処理中も Omeka S の他の操作を続けられます。

## 要件
- Omeka S (v4.x 以降)
- PHP 8.1+

## インストール
1. 最新のリリースをダウンロードし、リポジトリを `omeka-s/modules/IiifBulkImport` に配置します。
2. 管理者としてログインし、「モジュール」セクションから「IiifBulkImport」を有効化します。
3. （任意）「設定」ボタンから、タイムアウト、リトライ回数、画像サイズ上限などを調整します。

## 使い方
1. インストール後、管理画面のナビゲーションメニューに「IIIF Bulk Import」が追加されます。
2. リンクをクリックしてインポートフォームを開きます。
3. テキストエリアに、IIIF マニフェストの URL を1行に1つずつ貼り付けます。
4. 「Import」ボタンをクリックすると、バックグラウンドジョブが開始されます。
5. ジョブのステータスページにリダイレクトされ、インポートの進行状況を確認できます。

## 詳細なメタデータマッピング
このモジュールは、IIIF マニフェストの以下のフィールドを Dublin Core Terms にマッピングします。

| Dublin Core 用語      | IIIF マニフェストのソース                                                            |
| ----------------------- | ------------------------------------------------------------------------------------ |
| `dcterms:title`         | `label` (v2/v3)                                                                      |
| `dcterms:description`   | `summary` (v3), `description` (v2), または "description" ラベルを持つメタデータ      |
| `dcterms:rights`        | `rights` (v3), `requiredStatement` (v3), `license` (v2), `attribution` (v2)          |
| `dcterms:identifier`    | マニフェスト URL、および "identifier" や "id" ラベルを持つメタデータフィールド       |
| `dcterms:publisher`     | `provider.label` (v3) または "publisher" ラベルを持つメタデータ                      |
| `dcterms:relation`      | `homepage` および `seeAlso` の URI                                                   |
| `dcterms:extent`        | マニフェスト内の Canvas 数                                                           |
| `dcterms:type`          | "DCMI Still Image"（画像メディアが作成された場合）                                   |
| その他マッピング対象    | `creator`, `contributor`, `date`, `language`, `subject`, `spatial`, `temporal`, `format`, `rightsHolder`, `alternative`（英語・日本語ラベルに対応） |

## ライセンス
GPL-3.0-or-later

Copyright (C) 2025
