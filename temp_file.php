<?php
// Код, который нужно вставить вместо отображения ORIGINAL_NAME
$displayName = $arFile["ORIGINAL_NAME"];
// Проверка на наличие тега <name> в PARENT_NAME
if(preg_match('/<name>(.*?)<\/name>/i', $arFile["PARENT_NAME"], $matches)) {
    $displayName = $matches[1];
}
?>
<a href="<?=$arFile["SRC"]?>" class="name" target="_blank"><span><?=$displayName?></span></a>
