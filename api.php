<?php
// config.php - 設定ファイル
require_once 'config.php';

// エラー表示設定（デバッグ時のみ有効にする）
ini_set('display_errors', 0); // 本番環境では0に
error_reporting(E_ALL);

// レスポンスヘッダーの設定
header('Content-Type: application/json');

// データベース接続クラス
class Database
{
    private $conn;

    public function __construct()
    {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS,
                [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'データベース接続エラー: ' . $e->getMessage()]);
            exit;
        }
    }

    public function getConnection()
    {
        return $this->conn;
    }
}

// APIリクエスト処理クラス
class API
{
    private $db;
    private $conn;
    private $request;
    private $user_id;

    public function __construct()
    {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();

        // リクエストパラメータの取得
        $this->request = $this->getRequestParams();

        // セッション開始
        session_start();

        // ユーザーIDの取得（ログイン済みの場合）
        $this->user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $this->getOrCreateGuestUser();
    }

    // リクエストパラメータを取得
    private function getRequestParams()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return json_decode(file_get_contents('php://input'), true);
        } else {
            return $_GET;
        }
    }

    // ゲストユーザーの作成/取得
    private function getOrCreateGuestUser()
    {
        // ゲストIDがクッキーにある場合
        if (isset($_COOKIE['guest_id'])) {
            $guest_id = $_COOKIE['guest_id'];

            // 存在確認
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE id = ? AND is_guest = 1");
            $stmt->execute([$guest_id]);

            if ($stmt->rowCount() > 0) {
                return $guest_id;
            }
        }

        // 新しいゲストユーザーを作成
        $stmt = $this->conn->prepare("INSERT INTO users (username, is_guest) VALUES (?, 1)");
        $guest_username = 'guest_' . uniqid();
        $stmt->execute([$guest_username]);

        $guest_id = $this->conn->lastInsertId();

        // デフォルトカテゴリー設定を追加
        $this->setupDefaultCategories($guest_id);

        // クッキーにゲストIDを保存（30日間）
        setcookie('guest_id', $guest_id, time() + (86400 * 30), '/');

        return $guest_id;
    }

    // デフォルトカテゴリー設定をユーザーに追加
    private function setupDefaultCategories($user_id)
    {
        try {
            $stmt = $this->conn->prepare("SELECT id FROM categories WHERE is_default = 1");
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($categories)) {
                // データベースにカテゴリーがない場合、ダミーのレスポンスを返す
                return;
            }

            $insert = $this->conn->prepare("INSERT INTO user_category_preferences (user_id, category_id, display_order) VALUES (?, ?, ?)");

            $order = 1;
            foreach ($categories as $category) {
                $insert->execute([$user_id, $category['id'], $order]);
                $order++;
            }
        } catch (PDOException $e) {
            // エラーをログに記録するなどの処理
            error_log('setupDefaultCategories error: ' . $e->getMessage());
        }
    }


    // APIのエントリーポイント
    public function handleRequest()
    {
        if (!isset($this->request['action'])) {
            echo json_encode(['success' => false, 'error' => 'アクションが指定されていません']);
            return;
        }

        switch ($this->request['action']) {
            case 'get_articles':
                $this->getArticles();
                break;
            case 'get_categories':
                $this->getCategories();
                break;
            case 'update_category_preferences':
                $this->updateCategoryPreferences();
                break;
            case 'add_custom_category':
                $this->addCustomCategory();
                break;
            case 'register':
                $this->registerUser();
                break;
            case 'login':
                $this->loginUser();
                break;
            case 'logout':
                $this->logoutUser();
                break;
            case 'get_user_preferences':
                $this->getUserPreferences();
                break;
            case 'save_article_history': 
                $this->saveArticleHistory();
                break;
            case 'get_article_history':
                $this->getArticleHistory();
                break;
            default:
                echo json_encode(['success' => false, 'error' => '不明なアクションです']);
                break;
        }
    }

    // 記事一覧を取得
    private function getArticles()
    {
        try {
            // テーブルが存在しない場合のテスト対応
            $check = $this->conn->query("SHOW TABLES LIKE 'articles'");
            if ($check->rowCount() === 0) {
                // テーブルがない場合はダミーデータを返す（テスト用）
                echo json_encode([
                    'success' => true,
                    'articles' => $this->generateDummyArticles(20), // 常に20件のダミーデータを生成
                    'total_count' => 100, // 合計件数情報も追加
                    'has_more' => true    // まだ続きがあることを示す
                ]);
                return;
            }

            $category_id = isset($this->request['category_id']) ? (int)$this->request['category_id'] : null;
            $limit = isset($this->request['limit']) ? (int)$this->request['limit'] : 60;
            $offset = isset($this->request['offset']) ? (int)$this->request['offset'] : 0;

            // 1. まず合計件数を取得する
            $countSql = "SELECT COUNT(*) AS total FROM articles a ";
            $whereClause = "";
            $countParams = [];

            if ($category_id) {
                $whereClause = "WHERE a.id IN (SELECT article_id FROM article_categories WHERE category_id = ?)";
                $countParams[] = $category_id;
            }

            // トップページ向けに、ユーザーの閲覧履歴から興味を予測
            if (isset($this->request['for_top_page']) && $this->request['for_top_page']) {
                // 直近で閲覧した記事のカテゴリーを取得
                $stmt = $this->conn->prepare("
                SELECT DISTINCT ac.category_id 
                FROM user_article_history uah 
                JOIN article_categories ac ON uah.article_id = ac.article_id 
                WHERE uah.user_id = ? 
                ORDER BY uah.viewed_at DESC 
                LIMIT 5
            ");
                $stmt->execute([$this->user_id]);
                $recent_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($recent_categories)) {
                    if (!empty($whereClause)) {
                        $whereClause .= " AND (";
                    } else {
                        $whereClause = "WHERE (";
                    }

                    $category_conditions = [];
                    foreach ($recent_categories as $cat_id) {
                        $category_conditions[] = "a.id IN (SELECT article_id FROM article_categories WHERE category_id = ?)";
                        $countParams[] = $cat_id;
                    }

                    $whereClause .= implode(' OR ', $category_conditions) . ")";
                }
            }

            // 合計件数の取得
            $countSql .= $whereClause;
            $countStmt = $this->conn->prepare($countSql);
            $countStmt->execute($countParams);
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // 2. 次に実際の記事データを取得
            $params = $countParams; // 同じパラメータを再利用
            $sql = "SELECT a.* FROM articles a ";

            if (!empty($whereClause)) {
                $sql .= $whereClause;
            }

            // 注意: LIMITとOFFSETは値として明示的に埋め込み
            $sql .= " ORDER BY a.bookmark_count DESC, a.published_at DESC LIMIT " . $limit . " OFFSET " . $offset;

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 各記事のカテゴリーを取得
            foreach ($articles as &$article) {
                $stmt = $this->conn->prepare("
                SELECT c.id, c.name 
                FROM categories c 
                JOIN article_categories ac ON c.id = ac.category_id 
                WHERE ac.article_id = ?
            ");
                $stmt->execute([$article['id']]);
                $article['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // 続きがあるかどうかを計算
            $hasMore = ($offset + count($articles)) < $totalCount;

            // 結果を返す
            echo json_encode([
                'success' => true,
                'articles' => $articles,
                'total_count' => $totalCount,
                'has_more' => $hasMore
            ]);
        } catch (PDOException $e) {
            // エラーを記録
            error_log('SQL Error in getArticles: ' . $e->getMessage() . ', SQL: ' . (isset($sql) ? $sql : ''));

            echo json_encode([
                'success' => false,
                'error' => '記事取得エラー: ' . $e->getMessage()
            ]);
        }
    }

    // ダミーデータ生成関数（テスト用）
    private function generateDummyArticles($count = 20)
    {
        $articles = [];
        $categories = [
            ['id' => 1, 'name' => '総合'],
            ['id' => 2, 'name' => 'テクノロジー'],
            ['id' => 3, 'name' => 'エンタメ'],
            ['id' => 4, 'name' => 'ビジネス'],
            ['id' => 5, 'name' => 'スポーツ']
        ];

        $sources = ['Yahoo', 'CNN', 'BBC', '日経新聞', 'TechCrunch', 'Wired'];

        for ($i = 1; $i <= $count; $i++) {
            $offset = isset($this->request['offset']) ? (int)$this->request['offset'] : 0;
            $id = $offset + $i;

            // ランダムなカテゴリーを1～2個選択
            $catCount = mt_rand(1, 2);
            $selectedCats = [];
            for ($j = 0; $j < $catCount; $j++) {
                $selectedCats[] = $categories[mt_rand(0, count($categories) - 1)];
            }

            $articles[] = [
                'id' => $id,
                'title' => "サンプル記事 #{$id} - " . substr(md5(mt_rand()), 0, 8),
                'url' => "https://example.com/article/{$id}",
                'description' => "これはサンプル記事 #{$id} の説明文です。実際のAPIからのデータではありません。",
                'thumbnail_url' => '',
                'source_site' => $sources[mt_rand(0, count($sources) - 1)],
                'bookmark_count' => mt_rand(5, 200),
                'published_at' => date('Y-m-d H:i:s', time() - mt_rand(0, 86400 * 30)),
                'categories' => $selectedCats
            ];
        }

        return $articles;
    }

    // 記事閲覧履歴保存
    private function saveArticleHistory()
    {
        try {
            if (!isset($this->request['article_id'])) {
                echo json_encode(['success' => false, 'error' => '記事IDが指定されていません']);
                return;
            }

            $article_id = (int)$this->request['article_id'];

            // テーブルが存在しない場合の対応
            $check = $this->conn->query("SHOW TABLES LIKE 'user_article_history'");
            if ($check->rowCount() === 0) {
                // テーブルがない場合は成功したふりをする
                echo json_encode(['success' => true]);
                return;
            }

            // 既存の履歴を確認
            $stmt = $this->conn->prepare("
            SELECT viewed_at FROM user_article_history
            WHERE user_id = ? AND article_id = ?
        ");
            $stmt->execute([$this->user_id, $article_id]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($exists) {
                // 既に履歴がある場合は更新
                $stmt = $this->conn->prepare("
                UPDATE user_article_history 
                SET viewed_at = CURRENT_TIMESTAMP
                WHERE user_id = ? AND article_id = ?
            ");
                $stmt->execute([$this->user_id, $article_id]);
            } else {
                // 新規履歴の追加
                $stmt = $this->conn->prepare("
                INSERT INTO user_article_history 
                (user_id, article_id)
                VALUES (?, ?)
            ");
                $stmt->execute([$this->user_id, $article_id]);
            }

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log('履歴保存エラー: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => '履歴保存エラー: ' . $e->getMessage()]);
        }
    }

    // 履歴取得メソッド
    private function getArticleHistory()
    {
        try {
            // テーブルが存在しない場合の対応
            $check = $this->conn->query("SHOW TABLES LIKE 'user_article_history'");
            if ($check->rowCount() === 0) {
                echo json_encode(['success' => true, 'history' => []]);
                return;
            }

            $limit = isset($this->request['limit']) ? (int)$this->request['limit'] : 10;

            $stmt = $this->conn->prepare(
                "
                    SELECT a.id, a.title, a.url, a.source_site, a.thumbnail_url, 
                        uah.viewed_at 
                    FROM user_article_history uah
                    JOIN articles a ON uah.article_id = a.id
                    WHERE uah.user_id = ?
                    ORDER BY uah.viewed_at DESC
                    LIMIT " . $limit
            );
            $stmt->execute([$this->user_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'history' => $history]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => '閲覧履歴取得エラー: ' . $e->getMessage()]);
        }
    }

    // カテゴリー一覧を取得
    private function getCategories()
    {
        try {
            // テーブルが存在しない場合のテスト対応
            $check = $this->conn->query("SHOW TABLES LIKE 'categories'");
            if ($check->rowCount() === 0) {
                // テーブルがない場合はダミーデータを返す
                echo json_encode([
                    'success' => true,
                    'categories' => [
                        ['id' => 1, 'name' => '総合', 'slug' => 'general', 'is_visible' => 1, 'display_order' => 1],
                        ['id' => 2, 'name' => 'テクノロジー', 'slug' => 'technology', 'is_visible' => 1, 'display_order' => 2]
                    ],
                    'custom_categories' => []
                ]);
                return;
            }

            $stmt = $this->conn->prepare("
                SELECT c.*, ucp.is_visible, ucp.display_order
                FROM categories c
                LEFT JOIN user_category_preferences ucp ON c.id = ucp.category_id AND ucp.user_id = ?
                ORDER BY ucp.display_order ASC, c.name ASC
            ");
            $stmt->execute([$this->user_id]);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // カスタムカテゴリーの取得
            $stmt = $this->conn->prepare("
                SELECT id, name, display_order
                FROM user_custom_categories
                WHERE user_id = ?
                ORDER BY display_order ASC
            ");
            $stmt->execute([$this->user_id]);
            $custom_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'categories' => $categories,
                'custom_categories' => $custom_categories
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'カテゴリー取得エラー: ' . $e->getMessage()]);
        }
    }

    // カテゴリー表示設定の更新
    private function updateCategoryPreferences()
    {
        try {
            if (!isset($this->request['category_id']) || !isset($this->request['is_visible'])) {
                echo json_encode(['error' => 'パラメータが不足しています']);
                return;
            }

            $category_id = (int)$this->request['category_id'];
            $is_visible = (int)$this->request['is_visible'];
            $display_order = isset($this->request['display_order']) ? (int)$this->request['display_order'] : null;

            // 既存設定の確認
            $stmt = $this->conn->prepare("
                SELECT * FROM user_category_preferences
                WHERE user_id = ? AND category_id = ?
            ");
            $stmt->execute([$this->user_id, $category_id]);

            if ($stmt->rowCount() > 0) {
                // 既存設定の更新
                $sql = "UPDATE user_category_preferences SET is_visible = ?";
                $params = [$is_visible];

                if ($display_order !== null) {
                    $sql .= ", display_order = ?";
                    $params[] = $display_order;
                }

                $sql .= " WHERE user_id = ? AND category_id = ?";
                $params[] = $this->user_id;
                $params[] = $category_id;

                $stmt = $this->conn->prepare($sql);
                $stmt->execute($params);
            } else {
                // 新規設定の追加
                $stmt = $this->conn->prepare("
                    INSERT INTO user_category_preferences
                    (user_id, category_id, is_visible, display_order)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $this->user_id,
                    $category_id,
                    $is_visible,
                    $display_order !== null ? $display_order : 0
                ]);
            }

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'カテゴリー設定更新エラー: ' . $e->getMessage()]);
        }
    }

    // カスタムカテゴリーの追加
    private function addCustomCategory()
    {
        try {
            if (!isset($this->request['name'])) {
                echo json_encode(['error' => 'カテゴリー名が指定されていません']);
                return;
            }

            $name = trim($this->request['name']);

            if (empty($name)) {
                echo json_encode(['error' => 'カテゴリー名が空です']);
                return;
            }

            // 既存のカスタムカテゴリーの数を確認
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) FROM user_custom_categories
                WHERE user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $count = $stmt->fetchColumn();

            if ($count >= 10) {
                echo json_encode(['error' => 'カスタムカテゴリーは最大10個までです']);
                return;
            }

            // 同名のカテゴリーの確認
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) FROM user_custom_categories
                WHERE user_id = ? AND name = ?
            ");
            $stmt->execute([$this->user_id, $name]);

            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['error' => '同じ名前のカテゴリーが既に存在します']);
                return;
            }

            // 新規カテゴリーの追加
            $stmt = $this->conn->prepare("
                INSERT INTO user_custom_categories
                (user_id, name, display_order)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$this->user_id, $name, $count + 1]);

            $category_id = $this->conn->lastInsertId();

            echo json_encode([
                'success' => true,
                'category' => [
                    'id' => $category_id,
                    'name' => $name,
                    'display_order' => $count + 1
                ]
            ]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'カスタムカテゴリー追加エラー: ' . $e->getMessage()]);
        }
    }

    // ユーザー登録
    private function registerUser()
    {
        try {
            if (!isset($this->request['username']) || !isset($this->request['password']) || !isset($this->request['email'])) {
                echo json_encode(['error' => 'ユーザー名、パスワード、メールアドレスが必要です']);
                return;
            }

            $username = trim($this->request['username']);
            $password = $this->request['password'];
            $email = trim($this->request['email']);

            // 入力検証
            if (empty($username) || empty($password) || empty($email)) {
                echo json_encode(['error' => '全ての項目を入力してください']);
                return;
            }

            if (strlen($password) < 8) {
                echo json_encode(['error' => 'パスワードは8文字以上必要です']);
                return;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['error' => '有効なメールアドレスを入力してください']);
                return;
            }

            // ユーザー名とメールアドレスの重複チェック
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['error' => 'ユーザー名またはメールアドレスが既に使用されています']);
                return;
            }

            // パスワードのハッシュ化
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // トランザクション開始
            $this->conn->beginTransaction();

            // ゲストユーザーの場合、アップグレード
            if (isset($_COOKIE['guest_id'])) {
                $guest_id = $_COOKIE['guest_id'];

                $stmt = $this->conn->prepare("
                    UPDATE users
                    SET username = ?, password = ?, email = ?, is_guest = 0
                    WHERE id = ? AND is_guest = 1
                ");
                $stmt->execute([$username, $hashed_password, $email, $guest_id]);

                if ($stmt->rowCount() > 0) {
                    // セッションにユーザー情報を保存
                    $_SESSION['user_id'] = $guest_id;
                    $_SESSION['username'] = $username;

                    // クッキーを削除
                    setcookie('guest_id', '', time() - 3600, '/');

                    $this->conn->commit();
                    echo json_encode(['success' => true, 'message' => 'ゲストアカウントをアップグレードしました']);
                    return;
                }

                $this->conn->rollBack();
                echo json_encode(['error' => 'ゲストアカウントのアップグレードに失敗しました']);
                return;
            }

            // 新規ユーザー登録
            $stmt = $this->conn->prepare("
                INSERT INTO users (username, password, email, is_guest)
                VALUES (?, ?, ?, 0)
            ");
            $stmt->execute([$username, $hashed_password, $email]);

            $user_id = $this->conn->lastInsertId();

            // デフォルトカテゴリー設定を追加
            $this->setupDefaultCategories($user_id);

            // セッションにユーザー情報を保存
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;

            // トランザクション終了
            $this->conn->commit();

            echo json_encode(['success' => true, 'message' => '登録が完了しました']);
        } catch (PDOException $e) {
            $this->conn->rollBack();
            echo json_encode(['error' => '登録エラー: ' . $e->getMessage()]);
        }
    }

    // ユーザーログイン
    private function loginUser()
    {
        try {
            if (!isset($this->request['username']) || !isset($this->request['password'])) {
                echo json_encode(['error' => 'ユーザー名とパスワードが必要です']);
                return;
            }

            $username = trim($this->request['username']);
            $password = $this->request['password'];

            // ユーザー情報の取得
            $stmt = $this->conn->prepare("
                SELECT id, username, password
                FROM users
                WHERE (username = ? OR email = ?) AND is_guest = 0
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password'])) {
                echo json_encode(['error' => 'ユーザー名またはパスワードが正しくありません']);
                return;
            }

            // セッションにユーザー情報を保存
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // ゲストクッキーを削除
            if (isset($_COOKIE['guest_id'])) {
                setcookie('guest_id', '', time() - 3600, '/');
            }

            // 最終ログイン時間の更新
            $stmt = $this->conn->prepare("
                UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?
            ");
            $stmt->execute([$user['id']]);

            echo json_encode(['success' => true, 'message' => 'ログインしました']);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'ログインエラー: ' . $e->getMessage()]);
        }
    }

    // ユーザーログアウト
    private function logoutUser()
    {
        // セッションを破棄
        session_destroy();

        // ゲストIDの再生成
        $this->getOrCreateGuestUser();

        echo json_encode(['success' => true, 'message' => 'ログアウトしました']);
    }

    // ユーザー設定の取得
    private function getUserPreferences()
    {
        try {
            // ユーザー情報
            $is_guest = true;
            $username = "";

            if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
                $stmt = $this->conn->prepare("
                    SELECT is_guest FROM users WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result && $result['is_guest'] == 0) {
                    $is_guest = false;
                    $username = $_SESSION['username'];
                }
            }

            // テーブルが存在しない場合のテスト対応
            $check = $this->conn->query("SHOW TABLES LIKE 'categories'");
            if ($check->rowCount() === 0) {
                // テーブルがない場合はダミーデータを返す
                echo json_encode([
                    'success' => true,
                    'user' => [
                        'is_guest' => $is_guest,
                        'username' => $username
                    ],
                    'categories' => [
                        ['id' => 1, 'name' => '総合', 'slug' => 'general', 'is_visible' => 1, 'display_order' => 1],
                        ['id' => 2, 'name' => 'テクノロジー', 'slug' => 'technology', 'is_visible' => 1, 'display_order' => 2]
                    ],
                    'custom_categories' => []
                ]);
                return;
            }

            // カテゴリー設定
            $stmt = $this->conn->prepare("
                SELECT c.id, c.name, c.slug, ucp.is_visible, ucp.display_order
                FROM categories c
                LEFT JOIN user_category_preferences ucp ON c.id = ucp.category_id AND ucp.user_id = ?
                ORDER BY ucp.display_order ASC, c.name ASC
            ");
            $stmt->execute([$this->user_id]);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // カスタムカテゴリー
            $stmt = $this->conn->prepare("
                SELECT id, name, display_order
                FROM user_custom_categories
                WHERE user_id = ?
                ORDER BY display_order ASC
            ");
            $stmt->execute([$this->user_id]);
            $custom_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'user' => [
                    'is_guest' => $is_guest,
                    'username' => $username
                ],
                'categories' => $categories,
                'custom_categories' => $custom_categories
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'ユーザー設定取得エラー: ' . $e->getMessage()]);
        }
    }
}

// APIの実行
$api = new API();
$api->handleRequest();