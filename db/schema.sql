-- ユーザーテーブル
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255),
    -- ハッシュ化されたパスワード
    email VARCHAR(100) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_guest BOOLEAN DEFAULT FALSE
);

-- カテゴリーテーブル
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 記事テーブル
CREATE TABLE articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    url VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    thumbnail_url VARCHAR(255),
    source_site VARCHAR(100),
    bookmark_count INT DEFAULT 0,
    published_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 記事とカテゴリーの関連付けテーブル
CREATE TABLE article_categories (
    article_id INT,
    category_id INT,
    PRIMARY KEY (article_id, category_id),
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- ユーザーのカテゴリー設定テーブル
CREATE TABLE user_category_preferences (
    user_id INT,
    category_id INT,
    is_visible BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    PRIMARY KEY (user_id, category_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- ユーザーの記事閲覧履歴テーブル
CREATE TABLE user_article_history (
    user_id INT,
    article_id INT,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, article_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
);

-- ユーザーのカスタムカテゴリーテーブル
CREATE TABLE user_custom_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    name VARCHAR(50) NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- デフォルトカテゴリーの作成
INSERT INTO
    categories (name, slug, is_default)
VALUES
    ('総合', 'general', TRUE),
    ('テクノロジー', 'technology', TRUE),
    ('エンタメ', 'entertainment', TRUE),
    ('ビジネス', 'business', TRUE),
    ('スポーツ', 'sports', TRUE),
    ('科学', 'science', TRUE),
    ('健康', 'health', TRUE),
    ('ライフスタイル', 'lifestyle', TRUE);