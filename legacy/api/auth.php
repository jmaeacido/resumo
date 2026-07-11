<?php

use Resumo\Auth;

require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($action !== 'status') {
            sendAuthJson(['error' => 'Unknown auth action.'], 404);
            exit;
        }

        sendAuthJson(['user' => Auth::publicUser(Auth::user())]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendAuthJson(['error' => 'Method not allowed.'], 405);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($payload)) {
        throw new RuntimeException('Invalid auth request payload.');
    }

    if ($action === 'signup') {
        $user = Auth::signup(
            (string)($payload['name'] ?? ''),
            (string)($payload['email'] ?? ''),
            (string)($payload['password'] ?? '')
        );
        sendAuthJson(['user' => $user, 'message' => 'Account created.']);
        exit;
    }

    if ($action === 'login') {
        $user = Auth::login(
            (string)($payload['email'] ?? ''),
            (string)($payload['password'] ?? '')
        );
        sendAuthJson(['user' => $user, 'message' => 'Signed in.']);
        exit;
    }

    if ($action === 'logout') {
        Auth::logout();
        sendAuthJson(['user' => null, 'message' => 'Signed out.']);
        exit;
    }

    sendAuthJson(['error' => 'Unknown auth action.'], 404);
} catch (Throwable $error) {
    sendAuthJson(['error' => $error->getMessage()], 422);
}

function sendAuthJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}
