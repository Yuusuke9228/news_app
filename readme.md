# ニュースキュレーションアプリ

人気記事を収集し、カテゴリー別に表示するSmartNews風のキュレーションアプリケーションです。

## 機能

- 人気記事を自動収集
- カテゴリー別の記事表示
- ユーザーごとのカスタマイズ機能
  - カテゴリー表示/非表示の設定
  - カスタムカテゴリーの追加
- ユーザー登録/ログイン機能
- ゲストモードでの利用
- レスポンシブデザイン

## 技術スタック

- **フロントエンド**: HTML, CSS, JavaScript (Vanilla JS)
- **バックエンド**: PHP
- **データ収集**: Python
- **データベース**: MariaDB

## セットアップ

### 必要条件

- PHP 7.4以上
- Python 3.6以上
- MariaDB 10.3以上
- Webサーバー (Apache/Nginx)

### インストール手順

1. リポジトリをクローンまたはダウンロードします。

```bash
git clone https://github.com/Yuusuke9228/news_app.git
cd news-app
```

2. 必要なディレクトリとパーミッションを設定します。

```bash
mkdir -p logs
chmod -R 777 logs
```

3. データベースを作成し、スキーマを適用します。

```bash
mysql -u username -p < db/schema.sql
```

4. 設定ファイルを編集します。

```bash
cp config_sample.php config.php
cp config_sample.ini config.ini
# config.phpを編集して接続情報を設定
```

5. 記事取得スクリプトの定期実行を設定します（例：1時間ごと）。

```bash
crontab -e
# 以下を追加:
0 * * * * cd /path/to/news_app && python3 scripts/fetch_articles.py >> logs/cron.log 2>&1
```

## 使い方

1. ブラウザで `http://yourserver.com/news_app/` にアクセスします。
2. ゲストとして利用するか、アカウントを作成してログインします。
3. カテゴリータブから興味のあるジャンルの記事を閲覧できます。
4. 設定からカテゴリー表示のカスタマイズやカスタムカテゴリーの追加が可能です。

## システム構成

- **index.php**: メインページ（HTML/CSSを含む）
- **api.php**: バックエンドAPI
- **scripts/fetch_articles.py**: はてなブックマーク記事取得スクリプト
- **js/main.js**: フロントエンドの動作を制御
- **config.php**: 設定ファイル

## ライセンス

このプロジェクトはMITライセンスの下で公開されています。

## 連絡先

- 作者: Yuusuke9228
- GitHub: github.com/Yuusuke9228
