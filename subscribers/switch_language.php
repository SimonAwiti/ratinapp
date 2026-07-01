<?php
// switch_language.php
if (session_status() == PHP_SESSION_NONE) session_start();
require_once '../admin/includes/config.php';
require_once 'includes/TranslationManager.php';

header('Content-Type: application/json');
$response = ['success' => false];

if (isset($_POST['lang'])) {
    $translator = TranslationManager::getInstance($con);
    if ($translator->setLanguage(trim($_POST['lang']))) {
        $response['success'] = true;
    }
}
echo json_encode($response);