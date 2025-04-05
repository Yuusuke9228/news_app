#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import requests
import json
import pymysql
import time
import re
import os
from bs4 import BeautifulSoup
from bs4 import XMLParsedAsHTMLWarning
import warnings
from datetime import datetime
import configparser

# XMLをHTMLとして解析する警告を無視
warnings.filterwarnings("ignore", category=XMLParsedAsHTMLWarning)

# 設定ファイルの読み込み
config = configparser.ConfigParser()
script_dir = os.path.dirname(os.path.abspath(__file__))
app_root = os.path.dirname(script_dir)
config.read(os.path.join(app_root, 'config.ini'))

# データベース接続設定
db_config = {
    'host': config['DATABASE']['HOST'],
    'user': config['DATABASE']['USER'],
    'password': config['DATABASE']['PASSWORD'],
    'db': config['DATABASE']['DB_NAME'],
    'charset': 'utf8mb4',
    'cursorclass': pymysql.cursors.DictCursor
}

# 人気記事を取得するカテゴリのリスト
categories = [
    {"name": "総合", "url": "https://b.hatena.ne.jp/hotentry.rss"},
    {"name": "テクノロジー", "url": "https://b.hatena.ne.jp/hotentry/it.rss"},
    {"name": "エンタメ", "url": "https://b.hatena.ne.jp/hotentry/entertainment.rss"},
    {"name": "ビジネス", "url": "https://b.hatena.ne.jp/hotentry/economics.rss"},
    {"name": "スポーツ", "url": "https://b.hatena.ne.jp/hotentry/game.rss"},
    {"name": "科学", "url": "https://b.hatena.ne.jp/hotentry/knowledge.rss"},
    {"name": "健康", "url": "https://b.hatena.ne.jp/hotentry/life.rss"},
    {"name": "ライフスタイル", "url": "https://b.hatena.ne.jp/hotentry/guide.rss"}  # guide.rssに変更
]

# User-Agent設定
headers = {
    'User-Agent': 'Mozilla/5.0 (compatible; MyNewsAggregator/1.0; +https://mynewsaggregator.example.com)'
}

def get_bookmark_count(url):
    """はてなブックマーク数を取得する関数"""
    try:
        # URLをエンコード
        encoded_url = url.replace(":", "%3A").replace("/", "%2F")
        api_url = f"https://b.hatena.ne.jp/entry/json/{encoded_url}"
        
        response = requests.get(api_url, headers=headers)
        if response.status_code == 200 and response.text and response.text != "null":
            data = json.loads(response.text)
            return data.get('count', 0)
        return 0
    except Exception as e:
        print(f"Error getting bookmark count for {url}: {e}")
        return 0

def get_thumbnail(url):
    """OGP画像またはページ内の画像を取得する関数"""
    try:
        response = requests.get(url, headers=headers, timeout=10)
        if response.status_code == 200:
            soup = BeautifulSoup(response.text, 'html.parser')
            
            # OGP画像を探す
            og_image = soup.find('meta', property='og:image')
            if og_image and og_image.get('content'):
                return og_image['content']
            
            # Twitter Cardの画像を探す
            twitter_image = soup.find('meta', attrs={'name': 'twitter:image'})
            if twitter_image and twitter_image.get('content'):
                return twitter_image['content']
            
            # OGP画像がなければ最初の大きな画像を使用
            images = soup.find_all('img')
            for img in images:
                # サイズ属性があれば確認
                if img.get('width') and img.get('height'):
                    if int(img.get('width', 0)) >= 200 and int(img.get('height', 0)) >= 200:
                        src = img.get('src', '')
                        if src.startswith('//'):  # プロトコル相対URLを絶対URLに変換
                            return 'https:' + src
                        elif src.startswith('/'):  # 相対パスを絶対URLに変換
                            parsed_url = re.match(r'https?://[^/]+', url)
                            if parsed_url:
                                return parsed_url.group(0) + src
                        return src
                    
            # サイズ属性がない場合は最初の画像を返す
            if images and images[0].get('src'):
                src = images[0].get('src', '')
                if src.startswith('//'):
                    return 'https:' + src
                elif src.startswith('/'):
                    parsed_url = re.match(r'https?://[^/]+', url)
                    if parsed_url:
                        return parsed_url.group(0) + src
                return src
        
        return None
    except Exception as e:
        print(f"Error getting thumbnail for {url}: {e}")
        return None

def get_site_name(url):
    """URLからサイト名を抽出する関数"""
    try:
        domain = re.search(r'https?://(?:www\.)?([^/]+)', url).group(1)
        parts = domain.split('.')
        return parts[-2] if len(parts) >= 2 else domain
    except:
        return "unknown"

def parse_rss_items(response_content, category_name):
    """RSSフィードから記事アイテムを解析する関数"""
    try:
        # まずXML形式として解析を試みる
        soup = BeautifulSoup(response_content, features='xml')
        
        # RSSフィード内のアイテムを検索
        items = soup.find_all('item')
        
        if items:
            print(f"Found {len(items)} items for {category_name} (XML format)")
            return items
            
        # XMLとして解析できない場合はHTMLとして解析
        soup = BeautifulSoup(response_content, 'html.parser')
        
        # RSSフィードの場合
        channel = soup.find('channel')
        if channel:
            items = channel.find_all('item')
            if items:
                print(f"Found {len(items)} items for {category_name} (RSS format)")
                return items
        
        # Atom形式の場合
        feed = soup.find('feed')
        if feed:
            entries = feed.find_all('entry')
            if entries:
                print(f"Found {len(entries)} entries for {category_name} (Atom format)")
                return entries
                
        # HTML内のリンクを取得（最終手段）
        links = soup.find_all('a', class_='entry-link')
        if links:
            print(f"Found {len(links)} links for {category_name} (HTML format)")
            # 擬似的なアイテムを作成
            items = []
            for link in links:
                title_elem = link.find('h3', class_='entry-title')
                if title_elem:
                    item = soup.new_tag('item')
                    title = soup.new_tag('title')
                    title.string = title_elem.text.strip()
                    item.append(title)
                    
                    link_tag = soup.new_tag('link')
                    link_tag.string = link.get('href', '')
                    item.append(link_tag)
                    
                    desc = soup.new_tag('description')
                    desc_elem = link.find('p', class_='entry-description')
                    if desc_elem:
                        desc.string = desc_elem.text.strip()
                    item.append(desc)
                    
                    items.append(item)
            
            if items:
                return items
                
        print(f"No items found for {category_name} in any format")
        # Debug: print the first 500 characters of the content
        print(f"Response content sample: {response_content[:500]}...")
        return []
        
    except Exception as e:
        print(f"Error parsing RSS items for {category_name}: {e}")
        return []

def fetch_and_store_articles():
    """はてなブックマークから記事を取得してデータベースに保存する関数"""
    connection = None
    try:
        connection = pymysql.connect(**db_config)
        
        with connection.cursor() as cursor:
            # カテゴリIDを取得
            category_map = {}
            cursor.execute("SELECT id, name FROM categories")
            for row in cursor.fetchall():
                category_map[row['name']] = row['id']
            
            for category in categories:
                print(f"Fetching articles for category: {category['name']}")
                
                try:
                    response = requests.get(category['url'], headers=headers, timeout=10)
                    if response.status_code != 200:
                        print(f"Failed to fetch RSS for {category['name']}, status code: {response.status_code}")
                        continue
                    
                    # 記事アイテムを解析
                    items = parse_rss_items(response.content, category['name'])
                    if not items:
                        continue
                    
                    for item in items:
                        # 必要な要素を取得
                        title_elem = item.find('title')
                        
                        # RSS形式とAtom形式でリンクの取得方法が異なる
                        link_elem = item.find('link')
                        if link_elem and not link_elem.string and link_elem.get('href'):  # Atom形式
                            link = link_elem.get('href')
                        elif link_elem and link_elem.string:  # RSS形式
                            link = link_elem.string.strip()
                        else:
                            link = None
                        
                        # 要素が見つからない場合はスキップ
                        if not title_elem or not link:
                            print("Skipping item: Missing title or link")
                            continue
                        
                        title = title_elem.text.strip()
                        
                        # 説明文
                        description = ""
                        description_elem = item.find('description') or item.find('summary') or item.find('content')
                        if description_elem:
                            description = description_elem.text.strip()
                            
                        # 公開日
                        published_at = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                        pubdate_elem = item.find('pubdate') or item.find('dc:date') or item.find('published') or item.find('updated')
                        if pubdate_elem:
                            try:
                                date_str = pubdate_elem.text.strip()
                                # 複数の日付形式に対応
                                date_formats = [
                                    '%a, %d %b %Y %H:%M:%S %z',  # RFC 822
                                    '%Y-%m-%dT%H:%M:%S%z',       # ISO 8601
                                    '%Y-%m-%dT%H:%M:%SZ',        # ISO 8601 (UTC)
                                    '%Y-%m-%d %H:%M:%S',         # シンプルな形式
                                ]
                                
                                for fmt in date_formats:
                                    try:
                                        parsed_date = datetime.strptime(date_str, fmt)
                                        published_at = parsed_date.strftime('%Y-%m-%d %H:%M:%S')
                                        break
                                    except ValueError:
                                        continue
                            except Exception as e:
                                print(f"Date parsing error for {title}: {e}")
                        
                        # すでに存在するか確認
                        cursor.execute("SELECT id FROM articles WHERE url = %s", (link,))
                        result = cursor.fetchone()
                        
                        if result:
                            # 更新する場合はここで処理
                            article_id = result['id']
                            bookmark_count = get_bookmark_count(link)
                            cursor.execute(
                                "UPDATE articles SET bookmark_count = %s WHERE id = %s",
                                (bookmark_count, article_id)
                            )
                            print(f"Updated article: {title}")
                        else:
                            # サムネイル画像を取得
                            thumbnail_url = get_thumbnail(link)
                            
                            # サイト名を取得
                            source_site = get_site_name(link)
                            
                            # ブックマーク数を取得
                            bookmark_count = get_bookmark_count(link)
                            
                            # 記事をデータベースに挿入
                            cursor.execute(
                                """INSERT INTO articles 
                                   (title, url, description, thumbnail_url, source_site, bookmark_count, published_at) 
                                   VALUES (%s, %s, %s, %s, %s, %s, %s)""",
                                (title, link, description, thumbnail_url, source_site, bookmark_count, published_at)
                            )
                            
                            article_id = cursor.lastrowid
                            print(f"Inserted new article: {title}")
                            
                            # カテゴリと記事を関連付け
                            if category['name'] in category_map:
                                cursor.execute(
                                    "INSERT INTO article_categories (article_id, category_id) VALUES (%s, %s)",
                                    (article_id, category_map[category['name']])
                                )
                        
                        connection.commit()
                        
                        # APIの過負荷を避けるため短い待機
                        time.sleep(0.5)
                
                except Exception as e:
                    print(f"Error processing category {category['name']}: {e}")
                    continue
                
                # カテゴリごとに少し待機
                time.sleep(2)
    
    except Exception as e:
        print(f"Database error: {e}")
    
    finally:
        if connection:
            connection.close()

if __name__ == "__main__":
    print("Starting article collection...")
    fetch_and_store_articles()
    print("Article collection completed.")
