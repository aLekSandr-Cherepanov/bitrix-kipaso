<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
$this->setFrameMode(true);
?>

<?php if (!empty($arResult["SECTIONS"])): ?>
    <div class="accordion-catalog">
        <?php foreach ($arResult["SECTIONS"] as $section): ?>
            <div class="accordion-item">
                <div class="accordion-header">
                    <div class="accordion-toggle">
                        <span class="accordion-icon"></span>
                        <?= $section["NAME"] ?>
                    </div>
                </div>
                <div class="accordion-content">
                    <div class="catalog-categories-container">
                        <!-- Бренд слева -->
                        <div class="brands-container">
                            <?php if (!empty($section["BRANDS"])): ?>
                                <div class="brand-logo">
                                    <?php 
                                    // Получаем первый бренд из списка
                                    $brand = reset($section["BRANDS"]);
                                    $brandName = is_array($brand) ? $brand["NAME"] : $brand;
                                    $brandPicture = is_array($brand) && !empty($brand["PICTURE"]) ? $brand["PICTURE"]["SRC"] : false;
                                    
                                    // Проверяем наличие изображения
                                    if ($brandPicture): ?>
                                        <!-- Используем изображение из инфоблока брендов -->
                                        <img src="<?= $brandPicture ?>" alt="<?= htmlspecialchars($brandName) ?>">
                                    <?php elseif (file_exists($_SERVER["DOCUMENT_ROOT"].$templateFolder."/images/elhart-logo.png")): ?>
                                        <!-- Используем локальное изображение -->
                                        <img src="<?= $templateFolder ?>/images/elhart-logo.png" alt="<?= htmlspecialchars($brandName) ?>">
                                    <?php else: ?>
                                        <!-- Используем текстовое оформление -->
                                        <div class="brand-title brand-title-large"><?= htmlspecialchars($brandName) ?></div>
                                    <?php endif; ?>
                                    <div class="brand-title">Сделано в России</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Подкатегории справа -->
                        <div class="subcategories-container">
                            <?php if (!empty($section["SUBSECTIONS"])): ?>
                                <div class="subcategories-grid">
                                    <?php foreach ($section["SUBSECTIONS"] as $subsection): ?>
                                        <div class="subcategory-item">
                                            <a href="<?= $subsection["SECTION_PAGE_URL"] ?>" class="subcategory-link">
                                                <div class="subcategory-image">
                                                    <?php if (!empty($subsection["PICTURE"])): ?>
                                                        <img src="<?= $subsection["PICTURE"]["SRC"] ?>" alt="<?= $subsection["NAME"] ?>">
                                                    <?php else: ?>
                                                        <div class="no-image"></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="subcategory-name"><?= $subsection["NAME"] ?></div>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
