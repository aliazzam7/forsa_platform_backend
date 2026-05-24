<?php
require_once __DIR__ . '/config/core.php';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT u.name AS student_name, u.email AS student_email, j.title AS job_title, c.company_name, c.email AS company_email FROM applications a JOIN users u ON u.id = a.user_id JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id WHERE a.id = ?");
    $stmt->execute([18]);
    $info = $stmt->fetch();
    echo json_encode($info);
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}