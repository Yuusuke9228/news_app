<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>キュレーションメディアアプリ</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* リセットCSS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Noto Sans JP', sans-serif;
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* ヘッダー */
        header {
            background-color: #1a73e8;
            color: white;
            padding: 10px 15px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            font-size: 20px;
            font-weight: 700;
        }

        .user-menu {
            display: flex;
            align-items: center;
        }

        .user-menu a {
            margin-left: 15px;
            font-size: 14px;
        }

        /* カテゴリータブ */
        .tabs-container {
            position: fixed;
            top: 50px;
            left: 0;
            right: 0;
            background-color: white;
            overflow-x: auto;
            white-space: nowrap;
            z-index: 999;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .tabs {
            display: inline-flex;
            padding: 0 15px;
        }

        .tab {
            padding: 12px 15px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            position: relative;
        }

        .tab.active {
            color: #1a73e8;
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: #1a73e8;
        }

        /* メインコンテンツ */
        .main-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 110px 15px 20px;
        }

        /* 記事カードのスタイル */
        .article-container {
            margin-bottom: 20px;
        }

        .article-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 15px;
        }

        .article-link {
            display: flex;
            padding: 15px;
        }

        .article-thumbnail {
            width: 100px;
            height: 70px;
            background-size: cover;
            background-position: center;
            background-color: #eee;
            margin-right: 15px;
            flex-shrink: 0;
            border-radius: 4px;
        }

        .article-content {
            flex: 1;
        }

        .article-title {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 5px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .article-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #666;
        }

        .article-source {
            max-width: 50%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .article-bookmark-count {
            color: #1a73e8;
        }

        .article-category {
            display: inline-block;
            font-size: 11px;
            background-color: #f1f8ff;
            color: #1a73e8;
            padding: 2px 5px;
            border-radius: 3px;
            margin-right: 5px;
        }

        /* モーダル */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            width: 90%;
            max-width: 400px;
            border-radius: 8px;
            overflow: hidden;
        }

        .modal-header {
            background-color: #1a73e8;
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 500;
        }

        .modal-close {
            cursor: pointer;
            font-size: 20px;
        }

        .modal-body {
            padding: 20px;
        }

        /* フォーム */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #1a73e8;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-block {
            display: block;
            width: 100%;
        }

        /* 設定メニュー */
        .settings-container {
            margin-top: 20px;
        }

        .settings-title {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 10px;
        }

        .settings-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background-color: white;
            border-bottom: 1px solid #eee;
        }

        .settings-item-name {
            font-size: 14px;
        }

        .settings-toggle {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 20px;
        }

        .settings-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .settings-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 20px;
        }

        .settings-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.settings-slider {
            background-color: #1a73e8;
        }

        input:checked+.settings-slider:before {
            transform: translateX(20px);
        }

        /* 読み込みアニメーション */
        .loader {
            text-align: center;
            padding: 20px;
        }

        .spinner {
            display: inline-block;
            width: 30px;
            height: 30px;
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top-color: #1a73e8;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* レスポンシブデザイン */
        @media (max-width: 480px) {
            .article-thumbnail {
                width: 80px;
                height: 60px;
            }

            .article-title {
                font-size: 14px;
            }

            .tab {
                padding: 10px 12px;
                font-size: 13px;
            }
        }
    </style>
</head>

<body>
    <!-- ヘッダー -->
    <header>
        <div class="header-container">
            <div class="logo">ニュースアプリ</div>
            <div class="user-menu">
                <a href="#" id="login-btn">ログイン</a>
                <a href="#" id="register-btn">新規登録</a>
                <a href="#" id="settings-btn" style="display: none;"><i class="fas fa-cog"></i></a>
                <a href="#" id="logout-btn" style="display: none;">ログアウト</a>
            </div>
        </div>
    </header>

    <!-- カテゴリータブ -->
    <div class="tabs-container">
        <div class="tabs" id="category-tabs">
            <!-- タブはJavaScriptで動的に生成される -->
            <div class="tab active" data-category="top">トップ</div>
        </div>
    </div>

    <!-- メインコンテンツ -->
    <div class="main-container">
        <div class="article-container" id="article-container">
            <!-- 記事はJavaScriptで動的に生成される -->
        </div>
        <div class="loader" id="loader">
            <div class="spinner"></div>
        </div>
    </div>

    <!-- ログインモーダル -->
    <div class="modal" id="login-modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">ログイン</div>
                <div class="modal-close">&times;</div>
            </div>
            <div class="modal-body">
                <form id="login-form">
                    <div class="form-group">
                        <label for="login-username">ユーザー名またはメールアドレス</label>
                        <input type="text" id="login-username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="login-password">パスワード</label>
                        <input type="password" id="login-password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-block">ログイン</button>
                    <p class="error-message" id="login-error" style="color: red; margin-top: 10px; display: none;"></p>
                </form>
            </div>
        </div>
    </div>

    <!-- 新規登録モーダル -->
    <div class="modal" id="register-modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">新規登録</div>
                <div class="modal-close">&times;</div>
            </div>
            <div class="modal-body">
                <form id="register-form">
                    <div class="form-group">
                        <label for="register-username">ユーザー名</label>
                        <input type="text" id="register-username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="register-email">メールアドレス</label>
                        <input type="email" id="register-email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="register-password">パスワード（8文字以上）</label>
                        <input type="password" id="register-password" class="form-control" required minlength="8">
                    </div>
                    <button type="submit" class="btn btn-block">登録</button>
                    <p class="error-message" id="register-error" style="color: red; margin-top: 10px; display: none;"></p>
                </form>
            </div>
        </div>
    </div>

    <!-- 設定モーダル -->
    <div class="modal" id="settings-modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">設定</div>
                <div class="modal-close">&times;</div>
            </div>
            <div class="modal-body">
                <div class="settings-title">カテゴリー表示設定</div>
                <div id="category-settings">
                    <!-- カテゴリー設定はJavaScriptで動的に生成される -->
                </div>

                <div class="settings-title" style="margin-top: 20px;">カスタムカテゴリー追加</div>
                <form id="add-category-form">
                    <div class="form-group">
                        <input type="text" id="new-category-name" class="form-control" placeholder="カテゴリー名" required>
                    </div>
                    <button type="submit" class="btn btn-block">追加</button>
                </form>
                <p class="error-message" id="category-error" style="color: red; margin-top: 10px; display: none;"></p>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>

</html>