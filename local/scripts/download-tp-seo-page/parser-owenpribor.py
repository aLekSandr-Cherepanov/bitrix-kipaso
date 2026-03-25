#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import re
import time
import warnings
from urllib.parse import urljoin, urlparse, parse_qs

import requests
from bs4 import BeautifulSoup
from lxml import etree

# Убираем warning urllib3/OpenSSL (не критично)
try:
    from urllib3.exceptions import NotOpenSSLWarning
    warnings.filterwarnings("ignore", category=NotOpenSSLWarning)
except Exception:
    pass

from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry


BASE_DOMAIN = "https://www.owenkomplekt.ru"
CATEGORY_URL = "https://www.owenkomplekt.ru/catalog/izmeriteli-regulyatory/"

REQUEST_TIMEOUT = 25
SLEEP_BETWEEN_REQUESTS = 0.8


class OwenCategoryParser:
    def __init__(self):
        self.session = requests.Session()

        retry = Retry(
            total=4,
            connect=4,
            read=4,
            backoff_factor=1.2,
            status_forcelist=[429, 500, 502, 503, 504],
            allowed_methods=["GET", "HEAD"],
        )
        adapter = HTTPAdapter(max_retries=retry)
        self.session.mount("https://", adapter)
        self.session.mount("http://", adapter)

        self.session.headers.update({
            "User-Agent": (
                "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
                "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36"
            ),
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
            "Accept-Language": "ru-RU,ru;q=0.9,en;q=0.8",
            "Cache-Control": "no-cache",
            "Pragma": "no-cache",
            "Connection": "keep-alive",
            "Upgrade-Insecure-Requests": "1",
        })

    # ------------------------
    # Базовые helpers
    # ------------------------
    def normalize_url(self, url: str) -> str:
        if not url:
            return ""
        # иногда ссылки вида /catalog/../product/...
        full = urljoin(BASE_DOMAIN, url)
        full = full.split("#")[0]
        return full

    def clean_text(self, text: str) -> str:
        if not text:
            return ""
        return re.sub(r"\s+", " ", text).strip()

    def fetch_html(self, url: str, referer: str = None) -> str:
        headers = {}
        if referer:
            headers["Referer"] = referer

        resp = self.session.get(url, headers=headers, timeout=REQUEST_TIMEOUT)
        resp.raise_for_status()
        resp.encoding = resp.apparent_encoding or "utf-8"

        time.sleep(SLEEP_BETWEEN_REQUESTS)
        return resp.text

    def soup(self, html: str) -> BeautifulSoup:
        return BeautifulSoup(html, "html.parser")

    def is_product_url(self, url: str) -> bool:
        p = urlparse(url)
        return p.netloc.endswith("owenkomplekt.ru") and "/product/" in p.path

    # ------------------------
    # Пагинация (только страницы категории)
    # ------------------------
    def collect_category_pages(self) -> list[str]:
        """
        Получаем ВСЕ страницы категории:
        - первая страница CATEGORY_URL
        - страницы из блока .pagination со ссылками ?PAGEN_1=N
        """
        html = self.fetch_html(CATEGORY_URL, referer=BASE_DOMAIN + "/")
        s = self.soup(html)

        pages = [CATEGORY_URL]
        seen = {CATEGORY_URL}

        pagination = s.select_one(".pagination")
        if pagination:
            for a in pagination.select("a[href]"):
                href = self.normalize_url(a.get("href"))

                if not href:
                    continue
                if "/catalog/izmeriteli-regulyatory/" not in href:
                    continue

                # Только реальные страницы категории
                if href not in seen:
                    seen.add(href)
                    pages.append(href)

        # Важно: на первой странице в pagination обычно нет всех 107 ссылок,
        # поэтому добавим "догрузку" по максимуму найденной страницы.
        max_page = 1
        for p in pages:
            n = self.extract_page_num(p)
            if n > max_page:
                max_page = n

        # Иногда на странице видно только часть (например 1,2,3, ... 107)
        # Если max_page > 1, строим полный список 1..max_page
        if max_page > 1:
            generated = [CATEGORY_URL]
            for n in range(2, max_page + 1):
                generated.append(f"{CATEGORY_URL}?PAGEN_1={n}")
            pages = generated

        return pages

    def extract_page_num(self, url: str) -> int:
        if "?PAGEN_1=" in url:
            try:
                qs = parse_qs(urlparse(url).query)
                return int(qs.get("PAGEN_1", ["1"])[0])
            except Exception:
                return 1
        return 1

    # ------------------------
    # Парсинг карточек на странице категории (строго по блоку)
    # ------------------------
    def parse_category_page_products(self, page_url: str) -> list[dict]:
        html = self.fetch_html(page_url, referer=CATEGORY_URL)
        s = self.soup(html)

        items = []
        cards = s.select("div.catalog-products__list-item-view-2")

        for card in cards:
            item = self.parse_category_card(card)
            if item and item.get("url"):
                items.append(item)

        return items

    def parse_category_card(self, card):
        """
        Из карточки категории берем:
        - url товара
        - название
        - артикул (из param-title / param-value)
        - краткое описание (из .catalog-view-2__content-text)
        """
        # 1) Ссылка + название
        product_link = ""
        product_name = ""

        # Берем ссылку с title класса (она стабильнее)
        a_title = card.select_one("a.catalog-products__list-item-view-2_title[href]")
        if a_title:
            product_link = self.normalize_url(a_title.get("href"))

            # Внутри ссылки есть img + div с текстом, берем последний div с текстом
            text_divs = a_title.find_all("div")
            # Обычно последний div — это название
            for d in reversed(text_divs):
                txt = self.clean_text(d.get_text(" ", strip=True))
                if txt:
                    product_name = txt
                    break

        # fallback: если не нашли название
        if not product_name:
            txt = self.clean_text(card.get_text(" ", strip=True))
            product_name = txt[:200] if txt else ""

        # 2) Артикул и параметры внутри карточки
        card_article = ""
        params = {}

        for row in card.select(".catalog-view-2__content-params_left-item"):
            title_el = row.select_one(".param-title")
            value_el = row.select_one(".param-value")
            if not title_el or not value_el:
                continue

            key = self.clean_text(title_el.get_text(" ", strip=True))
            val = self.clean_text(value_el.get_text(" ", strip=True))
            if not key:
                continue

            # Если одинаковые ключи повторяются, можно оставить первое значение
            if key not in params:
                params[key] = val

            if key.lower() == "артикул" and val:
                card_article = val

        # 3) Краткое описание в карточке
        short_desc = ""
        desc_el = card.select_one(".catalog-view-2__content-text")
        if desc_el:
            short_desc = self.clean_text(desc_el.get_text(" ", strip=True))

        if not product_link or not self.is_product_url(product_link):
            return None

        return {
            "url": product_link,
            "category_name": "Измерители-регуляторы",
            "category_url": CATEGORY_URL,
            "card_name": product_name,
            "card_article": card_article,
            "card_short_description": short_desc,
            "card_params": params,
        }

    # ------------------------
    # Парсинг страницы товара (точечно)
    # ------------------------
    def parse_product_page(self, product_url: str) -> dict:
        html = self.fetch_html(product_url, referer=CATEGORY_URL)
        s = self.soup(html)

        # Название h1
        h1 = s.find("h1")
        page_name = self.clean_text(h1.get_text(" ", strip=True)) if h1 else ""

        # Артикул
        page_article = ""
        art_block = s.select_one(".articul")
        if art_block:
            span = art_block.find("span")
            if span:
                page_article = self.clean_text(span.get_text(" ", strip=True))
            else:
                m = re.search(r"Артикул\s*[:\-]?\s*([A-Za-zА-Яа-я0-9._-]+)", art_block.get_text(" ", strip=True))
                if m:
                    page_article = m.group(1)

        # fallback по всей странице
        if not page_article:
            all_text = s.get_text("\n", strip=True)
            m = re.search(r"Артикул\s*[:\-]?\s*([A-Za-zА-Яа-я0-9._-]+)", all_text)
            if m:
                page_article = m.group(1)

        # Краткое описание
        page_short_description = ""
        preview = s.select_one(".catalog-detail__body-preview")
        if preview:
            page_short_description = self.clean_text(preview.get_text(" ", strip=True))

        # Только ПЕРВОЕ фото из productpage__picture-block/productpage__picture-main
        first_image = ""
        img = s.select_one(".productpage__picture-block .productpage__picture-main img")
        if img:
            src = (
                img.get("src")
                or img.get("data-src")
                or img.get("data-lazy")
                or ""
            ).strip()
            first_image = self.normalize_url(src)

        return {
            "page_name": page_name,
            "page_article": page_article,
            "page_short_description": page_short_description,
            "first_image": first_image,
        }

    # ------------------------
    # Основной цикл: по страницам категории и товарам по порядку
    # ------------------------
    def scrape_all(self) -> list[dict]:
        all_products = []
        seen_urls = set()

        pages = self.collect_category_pages()
        print(f"[INFO] Страниц категории найдено: {len(pages)}")

        for page_idx, page_url in enumerate(pages, start=1):
            print(f"[INFO] Страница категории {page_idx}/{len(pages)}: {page_url}")

            try:
                category_items = self.parse_category_page_products(page_url)
            except Exception as e:
                print(f"[WARN] Ошибка чтения страницы категории: {e}")
                continue

            print(f"[INFO]  Карточек на странице: {len(category_items)}")

            for item_idx, item in enumerate(category_items, start=1):
                product_url = item["url"]

                if product_url in seen_urls:
                    continue
                seen_urls.add(product_url)

                print(f"[INFO]   Товар {item_idx}/{len(category_items)} -> {product_url}")

                try:
                    page_data = self.parse_product_page(product_url)
                except Exception as e:
                    print(f"[WARN]   Ошибка парсинга товара: {e}")
                    page_data = {}

                # Склеиваем данные карточки категории + страницы товара
                merged = {
                    "url": product_url,
                    "category_name": item.get("category_name", ""),
                    "category_url": item.get("category_url", ""),

                    # приоритет странице товара, fallback = карточка категории
                    "name": page_data.get("page_name") or item.get("card_name", ""),
                    "article": page_data.get("page_article") or item.get("card_article", ""),
                    "short_description": page_data.get("page_short_description") or item.get("card_short_description", ""),
                    "image": page_data.get("first_image", ""),

                    # Доп.поля из карточки категории (опционально)
                    "card_params": item.get("card_params", {}),
                }

                all_products.append(merged)

        return all_products

    # ------------------------
    # XML
    # ------------------------
    def save_xml(self, products: list[dict], filename: str = "owenkomplekt_izmeriteli_regulyatory.xml"):
        root = etree.Element("products")

        for i, p in enumerate(products, start=1):
            pe = etree.SubElement(root, "product", id=str(i))

            etree.SubElement(pe, "url").text = p.get("url", "")
            etree.SubElement(pe, "name").text = p.get("name", "")
            etree.SubElement(pe, "article").text = p.get("article", "")
            etree.SubElement(pe, "short_description").text = p.get("short_description", "")

            # категория (одна, нужная)
            cats = etree.SubElement(pe, "categories")
            c = etree.SubElement(cats, "category")
            etree.SubElement(c, "name").text = p.get("category_name", "Измерители-регуляторы")
            etree.SubElement(c, "url").text = p.get("category_url", CATEGORY_URL)

            # только одно фото
            images = etree.SubElement(pe, "images")
            if p.get("image"):
                etree.SubElement(images, "image").text = p["image"]

            # параметры из карточки категории
            params_el = etree.SubElement(pe, "card_params")
            for key, val in p.get("card_params", {}).items():
                param_el = etree.SubElement(params_el, "param")
                etree.SubElement(param_el, "name").text = str(key)
                etree.SubElement(param_el, "value").text = str(val)

        tree = etree.ElementTree(root)
        tree.write(filename, encoding="utf-8", xml_declaration=True, pretty_print=True)
        print(f"[OK] XML сохранен: {filename}")


if __name__ == "__main__":
    parser = OwenCategoryParser()
    products = parser.scrape_all()
    parser.save_xml(products)