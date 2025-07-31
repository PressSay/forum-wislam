<?php
$files = ['auth.php', 'pagination.php', 'passwords.php', 'validation.php'];
foreach ($files as $file) {
    $array = include $file;
    file_put_contents(str_replace('.php', '.json', $file), json_encode($array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
