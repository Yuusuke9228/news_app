// グローバル変数
const API_URL = '/news_app/api.php';
let currentCategory = 'top';
let isLoading = false;
let page = 0;
let hasMoreArticles = true;

// APIリクエストとレスポンスをデバッグするための改良版fetchAPI関数
async function fetchAPI(params) {
    const queryString = new URLSearchParams(params).toString();
    const url = `${API_URL}?${queryString}`;

    console.log('APIリクエスト:', url);

    try {
        const response = await fetch(url);

        // レスポンスのステータスコードを確認
        console.log('APIレスポンスステータス:', response.status);

        // レスポンスのテキスト内容を取得
        const responseText = await response.text();
        console.log('APIレスポンステキスト:', responseText);

        // レスポンスが空でないことを確認
        if (!responseText) {
            console.error('空のレスポンス');
            return { success: false, error: '空のレスポンス' };
        }

        // JSONとしてパースを試みる
        try {
            const data = JSON.parse(responseText);
            return data;
        } catch (parseError) {
            console.error('JSONパースエラー:', parseError);
            return { success: false, error: 'JSONパースエラー: ' + parseError.message };
        }
    } catch (error) {
        console.error('ネットワークエラー:', error);
        throw error;
    }
}

// ユーザー設定を取得
async function fetchUserPreferences() {
    try {
        const response = await fetchAPI({
            action: 'get_user_preferences'
        });

        if (response.success) {
            // ユーザー情報の更新
            updateUserUI(response.user);

            // カテゴリータブの更新
            renderCategoryTabs(response.categories, response.custom_categories);
        } else {
            console.error('ユーザー設定の取得に失敗しました:', response.error);
        }
    } catch (error) {
        console.error('APIエラー:', error);
    }
}

// カテゴリー設定を取得
async function fetchCategorySettings() {
    try {
        const response = await fetchAPI({
            action: 'get_categories'
        });

        if (response.success) {
            renderCategorySettings(response.categories, response.custom_categories);
        } else {
            console.error('カテゴリー設定の取得に失敗しました:', response.error);
        }
    } catch (error) {
        console.error('APIエラー:', error);
    }
}

// DOM要素
const articleContainer = document.getElementById('article-container');
const loader = document.getElementById('loader');
const categoryTabs = document.getElementById('category-tabs');
const loginBtn = document.getElementById('login-btn');
const registerBtn = document.getElementById('register-btn');
const settingsBtn = document.getElementById('settings-btn');
const logoutBtn = document.getElementById('logout-btn');
const scrollSensor = document.getElementById('scroll-sensor'); 
const historyBtn = document.createElement('a');
historyBtn.href = '#';
historyBtn.id = 'history-btn';
historyBtn.innerHTML = '<i class="fas fa-history"></i>';
historyBtn.style.display = 'none';
document.querySelector('.user-menu').appendChild(historyBtn);

// モーダル要素
const loginModal = document.getElementById('login-modal');
const registerModal = document.getElementById('register-modal');
const settingsModal = document.getElementById('settings-modal');
const loginForm = document.getElementById('login-form');
const registerForm = document.getElementById('register-form');
const addCategoryForm = document.getElementById('add-category-form');
const loginError = document.getElementById('login-error');
const registerError = document.getElementById('register-error');
const categoryError = document.getElementById('category-error');
const categorySettings = document.getElementById('category-settings');
const historyModal = document.getElementById('history-modal');
const historyContainer = document.getElementById('history-container');

// 記事を読み込む
async function loadArticles(reset = false) {
    // リセットされていない場合で、すでに読み込み中か追加記事がない場合は中断
    if (isLoading || (!hasMoreArticles && !reset)) return;

    isLoading = true;

    if (reset) {
        page = 0;
        articleContainer.innerHTML = '';
        hasMoreArticles = true; // リセット時に記事取得可能フラグをリセット
    }

    // ローダーを表示
    loader.classList.add('visible');

    try {
        // API呼び出し
        const params = {
            action: 'get_articles',
            offset: page * 60,
            limit: 60
        };

        if (currentCategory === 'top') {
            params.for_top_page = true;
        } else {
            params.category_id = currentCategory;
        }

        const response = await fetchAPI(params);

        if (response.success) {
            // articlesプロパティがあることを確認
            if (response.articles && Array.isArray(response.articles)) {
                // 記事が0件の場合または少ない場合のみ追加記事なしとする
                if (response.articles.length === 0) {
                    hasMoreArticles = false;
                    console.log('これ以上の記事はありません');
                } else {
                    renderArticles(response.articles);
                    page++; // 次のページのために増加
                    console.log(`記事を読み込みました: ${response.articles.length}件, 次のページ: ${page}`);

                    // 少ない場合も追加記事があるかもしれないため、フラグは下げない
                    hasMoreArticles = true;
                }
            } else {
                console.error('APIレスポンスに articles 配列がありません:', response);
                hasMoreArticles = false;
            }
        } else {
            console.error('記事の取得に失敗しました:', response.error || '不明なエラー');
            hasMoreArticles = false;
        }
    } catch (error) {
        console.error('APIエラー:', error);
        hasMoreArticles = false;
    } finally {
        isLoading = false;
        // ローダーを非表示
        setTimeout(() => {
            loader.classList.remove('visible');
        }, 300);
    }
}

// 記事をDOMに追加
function renderArticles(articles) {
    articles.forEach(article => {
        const articleElement = document.createElement('div');
        articleElement.className = 'article-card';

        // サムネイル画像URL
        const thumbnailUrl = article.thumbnail_url || '/news_app/images/no-image.png';

        // カテゴリー表示
        let categoryHTML = '';
        if (article.categories && article.categories.length > 0) {
            article.categories.slice(0, 2).forEach(category => {
                categoryHTML += `<span class="article-category">${category.name}</span>`;
            });
        }

        // 日付フォーマット
        const publishDate = new Date(article.published_at);
        const formattedDate = `${publishDate.getMonth() + 1}/${publishDate.getDate()} ${publishDate.getHours()}:${String(publishDate.getMinutes()).padStart(2, '0')}`;

        articleElement.innerHTML = `
            <a href="${article.url}" class="article-link" target="_blank" data-article-id="${article.id}">
                <div class="article-thumbnail" style="background-image: url('${thumbnailUrl}')"></div>
                <div class="article-content">
                    <div class="article-title">${article.title}</div>
                    <div class="article-meta">
                        <div class="article-source">${article.source_site}</div>
                        <div class="article-bookmark-count">${article.bookmark_count} users</div>
                    </div>
                    <div>${categoryHTML}</div>
                </div>
            </a>
        `;

        articleContainer.appendChild(articleElement);

        // 記事クリック時の履歴保存
        articleElement.querySelector('.article-link').addEventListener('click', (e) => {
            // 記事IDを取得
            const articleId = e.currentTarget.dataset.articleId;
            if (articleId) {
                // 履歴を記録
                saveArticleHistory(articleId);
            }
        });
    });
}

// 記事の閲覧履歴を保存する関数
async function saveArticleHistory(articleId) {
    try {
        const response = await fetchAPI({
            action: 'save_article_history',
            article_id: articleId
        });

        if (!response.success) {
            console.error('履歴保存エラー:', response.error || '不明なエラー');
        }
    } catch (error) {
        console.error('履歴保存APIエラー:', error);
    }
}

// カテゴリー切り替え
function switchCategory(category) {
    if (currentCategory === category) return;

    // アクティブタブの変更
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
        if (tab.dataset.category === category) {
            tab.classList.add('active');
        }
    });

    currentCategory = category;

    // 記事をリセットして読み込み直す
    loadArticles(true);
}

// Intersection Observerを使用した無限スクロール
function setupInfiniteScroll() {
    if (!scrollSensor) {
        console.error('スクロールセンサー要素が見つかりません');
        return;
    }

    // 以前のIntersectionObserverを破棄（存在する場合）
    if (window.scrollObserver) {
        window.scrollObserver.disconnect();
    }

    // 新しいIntersectionObserverを作成
    window.scrollObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            // スクロールセンサーが表示領域に入ったら
            if (entry.isIntersecting) {
                console.log('スクロールセンサーが表示領域に入りました');

                // ロード中でなく、まだ記事がある場合のみ読み込み
                if (!isLoading && hasMoreArticles) {
                    console.log('追加記事を読み込みます...');
                    loadArticles();
                } else {
                    console.log('ロード状態:', isLoading ? 'ロード中' : 'ロード可能', ', 追加記事:', hasMoreArticles ? 'あり' : 'なし');
                }
            }
        });
    }, {
        rootMargin: '300px', // より早く検知するために余白を増やす
        threshold: 0.1       // 10%表示されたら発火
    });

    // スクロールセンサーを監視
    window.scrollObserver.observe(scrollSensor);
    console.log('無限スクロールを設定しました');
}

// DIV要素追加のための修正
function ensureScrollSensor() {
    // すでに存在する場合は何もしない
    if (document.getElementById('scroll-sensor')) return;

    // 存在しない場合は作成して追加
    const sensor = document.createElement('div');
    sensor.id = 'scroll-sensor';
    sensor.style.height = '10px';
    sensor.style.width = '100%';
    sensor.style.marginTop = '10px';
    sensor.style.position = 'relative';

    // コンテナの最後に追加
    const container = document.querySelector('.main-container');
    if (container) {
        container.appendChild(sensor);
        console.log('スクロールセンサー要素を追加しました');
    }
}


// カテゴリータブを描画
function renderCategoryTabs(categories, customCategories) {
    categoryTabs.innerHTML = `<div class="tab active" data-category="top">トップ</div>`;

    // デフォルトカテゴリー
    categories.forEach(category => {
        if (category.is_visible !== 0) {
            categoryTabs.innerHTML += `
                <div class="tab" data-category="${category.id}">${category.name}</div>
            `;
        }
    });

    // カスタムカテゴリー
    if (customCategories && customCategories.length > 0) {
        customCategories.forEach(category => {
            categoryTabs.innerHTML += `
                <div class="tab" data-category="custom_${category.id}">${category.name}</div>
            `;
        });
    }
}

// カテゴリー設定を描画
function renderCategorySettings(categories, customCategories) {
    categorySettings.innerHTML = '';

    // デフォルトカテゴリー
    categories.forEach(category => {
        const isChecked = category.is_visible !== 0 ? 'checked' : '';

        categorySettings.innerHTML += `
            <div class="settings-item">
                <div class="settings-item-name">${category.name}</div>
                <label class="settings-toggle">
                    <input type="checkbox" data-category="${category.id}" ${isChecked}>
                    <span class="settings-slider"></span>
                </label>
            </div>
        `;
    });

    // カスタムカテゴリー
    if (customCategories && customCategories.length > 0) {
        categorySettings.innerHTML += `<div class="settings-title" style="margin-top: 15px;">カスタムカテゴリー</div>`;

        customCategories.forEach(category => {
            categorySettings.innerHTML += `
                <div class="settings-item">
                    <div class="settings-item-name">${category.name}</div>
                    <button class="btn" data-custom-category="${category.id}" style="padding: 5px 10px; font-size: 12px;">削除</button>
                </div>
            `;
        });
    }

    // トグルスイッチのイベントリスナー
    document.querySelectorAll('.settings-toggle input').forEach(toggle => {
        toggle.addEventListener('change', async (e) => {
            const categoryId = e.target.dataset.category;
            const isVisible = e.target.checked ? 1 : 0;

            try {
                const response = await fetchAPI({
                    action: 'update_category_preferences',
                    category_id: categoryId,
                    is_visible: isVisible
                });

                if (response.success) {
                    // カテゴリータブを更新
                    fetchUserPreferences();
                } else {
                    console.error('カテゴリー設定の更新に失敗しました:', response.error);
                    // 失敗したら元に戻す
                    e.target.checked = !e.target.checked;
                }
            } catch (error) {
                console.error('APIエラー:', error);
                e.target.checked = !e.target.checked;
            }
        });
    });
}

// ユーザーUI更新
function updateUserUI(user) {
    if (user.is_guest) {
        loginBtn.style.display = 'inline-block';
        registerBtn.style.display = 'inline-block';
        settingsBtn.style.display = 'none';
        logoutBtn.style.display = 'none';
        historyBtn.style.display = 'none';
    } else {
        loginBtn.style.display = 'none';
        registerBtn.style.display = 'none';
        settingsBtn.style.display = 'inline-block';
        logoutBtn.style.display = 'inline-block';
        historyBtn.style.display = 'inline-block';
    }
}

// ログイン処理
async function handleLogin(e) {
    e.preventDefault();

    const username = document.getElementById('login-username').value;
    const password = document.getElementById('login-password').value;

    loginError.style.display = 'none';

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'login',
                username: username,
                password: password
            })
        });

        const data = await response.json();

        if (data.success) {
            closeAllModals();
            fetchUserPreferences();
            loadArticles(true);
        } else {
            loginError.textContent = data.error;
            loginError.style.display = 'block';
        }
    } catch (error) {
        console.error('APIエラー:', error);
        loginError.textContent = 'サーバーエラーが発生しました';
        loginError.style.display = 'block';
    }
}

// 新規登録処理
async function handleRegister(e) {
    e.preventDefault();

    const username = document.getElementById('register-username').value;
    const email = document.getElementById('register-email').value;
    const password = document.getElementById('register-password').value;

    registerError.style.display = 'none';

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'register',
                username: username,
                email: email,
                password: password
            })
        });

        const data = await response.json();

        if (data.success) {
            closeAllModals();
            fetchUserPreferences();
            loadArticles(true);
        } else {
            registerError.textContent = data.error;
            registerError.style.display = 'block';
        }
    } catch (error) {
        console.error('APIエラー:', error);
        registerError.textContent = 'サーバーエラーが発生しました';
        registerError.style.display = 'block';
    }
}

// ログアウト処理
async function handleLogout(e) {
    e.preventDefault();

    try {
        const response = await fetchAPI({
            action: 'logout'
        });

        if (response.success) {
            fetchUserPreferences();
            loadArticles(true);
        } else {
            console.error('ログアウトに失敗しました:', response.error);
        }
    } catch (error) {
        console.error('APIエラー:', error);
    }
}

// カスタムカテゴリー追加
async function handleAddCategory(e) {
    e.preventDefault();

    const categoryName = document.getElementById('new-category-name').value;

    categoryError.style.display = 'none';

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'add_custom_category',
                name: categoryName
            })
        });

        const data = await response.json();

        if (data.success) {
            document.getElementById('new-category-name').value = '';
            fetchCategorySettings();
            fetchUserPreferences();
        } else {
            categoryError.textContent = data.error;
            categoryError.style.display = 'block';
        }
    } catch (error) {
        console.error('APIエラー:', error);
        categoryError.textContent = 'サーバーエラーが発生しました';
        categoryError.style.display = 'block';
    }
}

// モーダルを開く
function openModal(modal) {
    closeAllModals();
    modal.style.display = 'flex';
}

// すべてのモーダルを閉じる
function closeAllModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.style.display = 'none';
    });

    // エラーメッセージをクリア
    loginError.style.display = 'none';
    registerError.style.display = 'none';
    categoryError.style.display = 'none';

    // フォームをリセット
    loginForm.reset();
    registerForm.reset();
    addCategoryForm.reset();
}

// イベントリスナー設定
function setupEventListeners() {
    // カテゴリークリック
    categoryTabs.addEventListener('click', (e) => {
        if (e.target.classList.contains('tab')) {
            const category = e.target.dataset.category;
            switchCategory(category);
        }
    });

    // モーダル開閉
    loginBtn.addEventListener('click', () => openModal(loginModal));
    registerBtn.addEventListener('click', () => openModal(registerModal));
    settingsBtn.addEventListener('click', () => {
        fetchCategorySettings();
        openModal(settingsModal);
    });
    historyBtn.addEventListener('click', () => {
        fetchArticleHistory();
        openModal(historyModal);
    });

    // モーダルを閉じる
    document.querySelectorAll('.modal-close').forEach(closeBtn => {
        closeBtn.addEventListener('click', () => {
            closeAllModals();
        });
    });

    // モーダル外クリックで閉じる
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal')) {
            closeAllModals();
        }
    });

    // フォーム送信
    loginForm.addEventListener('submit', handleLogin);
    registerForm.addEventListener('submit', handleRegister);
    addCategoryForm.addEventListener('submit', handleAddCategory);

    // ログアウト
    logoutBtn.addEventListener('click', handleLogout);
}

// 閲覧履歴取得
async function fetchArticleHistory() {
    historyContainer.innerHTML = '<div class="loader"><div class="spinner"></div></div>';

    try {
        const response = await fetchAPI({
            action: 'get_article_history',
            limit: 20
        });

        if (response.success) {
            renderArticleHistory(response.history);
        } else {
            historyContainer.innerHTML = `<p class="error-message">履歴の取得に失敗しました: ${response.error || '不明なエラー'}</p>`;
        }
    } catch (error) {
        console.error('履歴取得APIエラー:', error);
        historyContainer.innerHTML = '<p class="error-message">履歴の取得中にエラーが発生しました</p>';
    }
}

// 履歴を描画
function renderArticleHistory(history) {
    if (!history || history.length === 0) {
        historyContainer.innerHTML = '<p>閲覧履歴はありません</p>';
        return;
    }

    let html = '';

    history.forEach(item => {
        const viewedDate = new Date(item.viewed_at);
        const formattedDate = `${viewedDate.getFullYear()}/${viewedDate.getMonth() + 1}/${viewedDate.getDate()} ${viewedDate.getHours()}:${String(viewedDate.getMinutes()).padStart(2, '0')}`;

        html += `
            <div class="article-card">
                <a href="${item.url}" class="article-link" target="_blank">
                    <div class="article-thumbnail" style="background-image: url('${item.thumbnail_url || '/news_app/images/no-image.png'}')"></div>
                    <div class="article-content">
                        <div class="article-title">${item.title}</div>
                        <div class="article-meta">
                            <div class="article-source">${item.source_site}</div>
                            <div class="article-date">閲覧: ${formattedDate}</div>
                        </div>
                    </div>
                </a>
            </div>
        `;
    });

    historyContainer.innerHTML = html;
}

// 初期化
document.addEventListener('DOMContentLoaded', () => {
    ensureScrollSensor();

    // グローバル参照を更新（追加した場合のため）
    if (!scrollSensor) {
        scrollSensor = document.getElementById('scroll-sensor');
    }

    // イベントリスナーを設定
    setupEventListeners();

    // ページ読み込み時にユーザー設定を取得
    fetchUserPreferences();

    // 記事を読み込む
    loadArticles();

    // 無限スクロールの設定
    setupInfiniteScroll();

    console.log('アプリケーションを初期化しました');
});

// デバッグモードの追加（コンソールからの操作用）
window.debugNewsApp = {
    loadMore: () => loadArticles(),
    reset: () => loadArticles(true),
    getState: () => ({
        currentCategory,
        isLoading,
        page,
        hasMoreArticles
    }),
    setHasMoreArticles: (value) => {
        hasMoreArticles = !!value;
        return `hasMoreArticles を ${hasMoreArticles} に設定しました`;
    }
};