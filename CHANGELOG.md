# Changelog

## v0.2.0 - 2025-09-06

### English

- **Added Settings Page**: Module behavior can now be configured from the admin interface.
  - Height-based fallback image sizes.
  - HTTP timeout and retry count.
  - Maximum image dimensions (width/height) to prevent processing errors.
- **Enhanced Robustness**:
  - Implemented a fallback to retry with specific image sizes (e.g., 4000px, 2400px height) if the default IIIF import fails.
  - Added a summary to job logs, including a sample of failed media URLs.
- **Bug Fixes**:
  - Resolved CSRF error and form saving issues on the settings page.
  - Improved fallback logic to fix an issue where media was not created for some manifests.

### 日本語

- **設定画面の追加**: モジュールの動作を管理画面から設定できるようになりました。
  - 高さベースのフォールバック画像サイズ
  - HTTPタイムアウトとリトライ回数
  - 処理エラーを防ぐための最大画像サイズ（幅・高さ）
- **堅牢性の強化**:
  - デフォルトのIIIFインポートに失敗した場合、特定の画像サイズ（例：高さ4000px, 2400px）でリトライするフォールバックを実装。
  - ジョブログに、失敗したメディアURLのサンプルを含む要約を追加。
- **バグ修正**:
  - 設定画面でのCSRFエラーとフォーム保存の問題を解決。
  - フォールバックロジックを改善し、一部のマニフェストでメディアが作成されない問題を修正。

---

## v0.1.0 - 2025-09-04

### English

- Initial public release.
- Accepts multiple manifest URLs to create items/media from each.
- Automatic application of "Base Resource" template and DCTERMS mapping (with EN/JA label support).
- Automatically assigns `dcterms:type=StillImage` if images are present.

### 日本語

- 初回公開リリース
- 複数マニフェストURLを受け付け、各マニフェストからアイテム/メディア作成
- Base Resource 自動適用、DCTERMSマッピング（英/日ラベル対応）
- 画像がある場合は dcterms:type=StillImage を自動付与

