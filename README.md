# IiifBulkImport (Omeka S Module)

Omeka S 用の簡易 IIIF マニフェスト一括インポートモジュールです。複数の IIIF Presentation (v2/v3) マニフェスト URL を入力し、各マニフェストからアイテムとメディアを作成します。

- アイテム作成時にリソーステンプレート未指定の場合は Base Resource を自動適用
- IIIF のラベル/メタデータを Dublin Core Terms にマッピング（英語/日本語の代表的ラベルに対応）
- Canvas の Image API info.json があれば `iiif` ingester で画像メディアとして追加。無ければ `iiif_presentation` ingester でマニフェストを添付
- 権利、識別子、出版者、関連、枚数（extent）などを自動設定

注意: 多数のキャンバスを持つマニフェストはメディア作成に時間がかかります（サムネイル生成や外部HTTPアクセスのため）。必要に応じて最初の数枚のみに絞るなどの最適化オプションを今後提供予定です。

## 要件
- Omeka S (develop または 4.x 系想定)
- PHP 8.1+

## インストール
1. リポジトリを `modules/IiifBulkImport` として配置
2. 管理画面 > モジュール から有効化
3. 管理画面メニュー「IIIF Bulk Import」へアクセス

## 使い方
1. 管理画面「IIIF Bulk Import」で、IIIF マニフェスト URL を改行区切りで入力
2. 送信するとバックグラウンドジョブが起動し、各マニフェストからアイテム/メディアを作成
3. 処理の進行はジョブ詳細画面で確認可能

## マッピング概要
- タイトル: マニフェストの `label`（v2/v3対応）
- dcterms:description: `summary`/`description`/`metadata`
- dcterms:rights: `rights`/`requiredStatement`/`license`/`metadata`
- dcterms:identifier: マニフェストURL + `metadata` 内の ID 系
- dcterms:publisher: v3 `provider.label`
- dcterms:relation: `homepage`/`seeAlso` の URI
- dcterms:extent: Canvas 数
- その他、creator/publisher/contributor/date/language/identifier/subject/spatial/temporal/format/type/rights/rightsHolder/alternative に英/日ラベルで対応
- 画像キャンバスがある場合は dcterms:type に DCMI Still Image を自動付与

## ライセンス
GPL-3.0-or-later

Copyright (C) 2025 
