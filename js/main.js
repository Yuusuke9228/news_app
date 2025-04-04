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

// 記事を読み込む
async function loadArticles(reset = false) {
    if (isLoading || (!hasMoreArticles && !reset)) return;

    isLoading = true;

    if (reset) {
        page = 0;
        articleContainer.innerHTML = '';
        hasMoreArticles = true;
    }

    loader.style.display = 'block';

    try {
        // API呼び出し
        const params = {
            action: 'get_articles',
            offset: page * 20,
            limit: 20
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
                if (response.articles.length === 0) {
                    hasMoreArticles = false;
                } else {
                    renderArticles(response.articles);
                    page++;
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
        loader.style.display = hasMoreArticles ? 'block' : 'none';
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
            <a href="${article.url}" class="article-link" target="_blank">
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
            // 履歴を記録する処理（未実装）
        });
    });
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

// スクロールハンドラ
function handleScroll() {
    if (isLoading || !hasMoreArticles) return;
    
    const scrollPosition = window.innerHeight + window.scrollY;
    const docHeight = document.body.offsetHeight;
    
    // 下部に近づいたら記事を追加読み込み
    if (scrollPosition >= docHeight - 500) {
        loadArticles();
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
    } else {
        loginBtn.style.display = 'none';
        registerBtn.style.display = 'none';
        settingsBtn.style.display = 'inline-block';
        logoutBtn.style.display = 'inline-block';
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
    
    // 無限スクロール
    window.addEventListener('scroll', handleScroll);
}

// 初期化
document.addEventListener('DOMContentLoaded', () => {
    // イベントリスナーを設定
    setupEventListeners();
    
    // ページ読み込み時にユーザー設定を取得
    fetchUserPreferences();
    
    // 記事を読み込む
    loadArticles();
});