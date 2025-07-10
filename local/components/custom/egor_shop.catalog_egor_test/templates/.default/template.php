<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>

<link rel="stylesheet" href="<?= $templateFolder ?>/style.css">
<script src="<?= $templateFolder ?>/script.js"></script>

<form id="category-form" method="get">
    <select name="SECTION_ID" onchange="this.form.submit()">
        <option value="">-- Выберите категорию --</option>
        <?php foreach ($arResult['SECTIONS'] as $section): ?>
            <option value="<?= $section['ID'] ?>" <?php if ($_GET['SECTION_ID'] == $section['ID']) echo 'selected'; ?>>
                <?= str_repeat('— ', $section['DEPTH_LEVEL'] - 1) . $section['NAME'] ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if (!empty($arResult['PRODUCTS'])): ?>
    <div class="product-list">
        <?php foreach ($arResult['PRODUCTS'] as $product): ?>
            <div class="product-card">
                <img src="<?= $product['PREVIEW_PICTURE_SRC'] ?>" alt="<?= $product['NAME'] ?>">
                <h3><?= $product['NAME'] ?></h3>
                <p><?= $product['PREVIEW_TEXT'] ?></p>
                <strong>Цена: <?= $product['PROPERTY_PRICE_VALUE'] ?> ₽</strong>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
