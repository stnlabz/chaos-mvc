<?php
if (class_exists('theme') && theme::render('foot', get_defined_vars())) {
    return;
}

require __DIR__ . '/classic_foot.php';
