<?php

use Resumo\GroqButler;
use Resumo\Auth;

require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($payload)) {
        throw new RuntimeException('Invalid Butler request payload.');
    }

    $message = trim((string)($payload['message'] ?? ''));
    if ($message === '') {
        throw new RuntimeException('Ask Resumo Butler a question first.');
    }

    $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
    $context['authenticated'] = Auth::user() !== null;
    echo json_encode([
        'butler' => GroqButler::reply($message, $context),
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(400);
    echo json_encode(['error' => $error->getMessage()]);
}
