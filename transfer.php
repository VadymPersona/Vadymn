<?php
header('Content-Type: application/json');

$json_file = 'transactions.json';
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'No data']); exit;
}

$sender = strtolower(str_replace('@', '', $input['sender']));
$recipient = strtolower(str_replace('@', '', $input['recipient']));
$amount = floatval($input['amount']);
$fee = $amount * 0.05;
$total_needed = $amount + $fee;

if ($amount <= 0 || $sender === $recipient) {
    echo json_encode(['success' => false, 'error' => 'Invalid amount or recipient']); exit;
}

// Читаем базу
$history = json_decode(file_get_contents($json_file), true) ?: [];

// Считаем баланс отправителя
$balance = 0;
foreach ($history as $tx) {
    if (strtolower(str_replace('@', '', $tx['to_user'])) === $sender) {
        $balance += floatval($tx['amount_mj']);
    }
}

if ($balance < $total_needed) {
    echo json_encode(['success' => false, 'error' => 'Insufficient funds']); exit;
}

$date = date('d.m.Y H:i');

// 1. Запись отправителю (минус)
$history[] = [
    "to_user" => $sender,
    "amount_mj" => "-" . number_format($total_needed, 2, '.', ''),
    "amount_ton_eq" => "-" . number_format($total_needed * 0.0002, 4, '.', ''),
    "type" => "transfer_out",
    "description" => "Перевод @$recipient",
    "date" => $date
];

// 2. Запись получателю (плюс)
$history[] = [
    "to_user" => $recipient,
    "amount_mj" => "+" . number_format($amount, 2, '.', ''),
    "amount_ton_eq" => number_format($amount * 0.0002, 4, '.', ''),
    "type" => "transfer_in",
    "description" => "От @$sender",
    "date" => $date
];

if (file_put_contents($json_file, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Write error']);
}