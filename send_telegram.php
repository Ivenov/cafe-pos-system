<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['message'])) {
        $message = $input['message'];
        sendOrderToTelegram($message);
        echo 'Order sent to Telegram';
    } else {
        echo 'No message provided';
    }
}

function sendOrderToTelegram($message) {
    $token = '631176858:AAHvNUcp0zcDOEmCIxGbHUefJLP3L4IZoV0';
    $chat_id = '561371743';
    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];

    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}
?>
