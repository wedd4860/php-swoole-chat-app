<?php
spl_autoload_register(function ($pathClass) {
    $pathClass = str_replace('\\', '/', $pathClass);
    $pathFile = '/masang/websocket/' . $pathClass . '.php';
    if (file_exists($pathFile)) {
        include_once $pathFile;
    }
});
