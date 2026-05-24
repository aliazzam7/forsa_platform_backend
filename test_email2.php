<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/EmailHelper.php';

EmailHelper::sendApplicationStatusEmail(
    'alidevloper76@gmail.com',
    'Ali Dev',
    'Software Engineer',
    'Ali Tech',
    'alitech@gmail.com',
    'accepted'
);

echo 'Done!';