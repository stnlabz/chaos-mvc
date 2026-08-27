<?php
if (class_exists('theme') && theme::render('head', get_defined_vars())) {
    return;
}

require __DIR__ . '/classic_head.php';
