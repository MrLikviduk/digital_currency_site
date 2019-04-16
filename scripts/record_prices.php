<?php // Поставить в cron выполняться каждую минуту!!!

set_include_path(dirname(__DIR__));

require_once 'config.php';
require_once 'scripts/functions.php';

if (!user_is_logged($_SERVER['argv'][1], $_SERVER['argv'][2])) {
    echo 'Action is unauthorized';
    exit;
}

$mysqli = connect_to_database();
$timestamps = $mysqli->all('timestamps');

$r = api_method('price');

foreach ($r as $ticker) {
    if (substr($ticker->symbol, -4) !== 'USDT')
        continue;
    $base_currency = substr($ticker->symbol, 0, -4);
    $price = (float)$ticker->price;
    $mysqli->add('prices', [
        'id' => 0,
        'base_currency' => $base_currency,
        'price' => $price,
        'time' => time()
    ]);

    // Проверяет, у кого добавлена эта валюта, и отправляет сообщение в телегу, если был превышен лимит на скачок цены в процентах
    $query = "SELECT symbols.id AS id, symbols.user_id AS user_id, timestamps.minutes AS minutes, symbols.percentage AS percentage, symbols.base_currency AS base_currency 
              FROM symbols 
              INNER JOIN timestamps ON symbols.timestamp_id = timestamps.id
              WHERE base_currency = '{$base_currency}'";
    $symbols = $mysqli->query($query)->fetch_all(MYSQLI_ASSOC);
    foreach ($symbols as $symbol) {
        $time = time() - $symbol['minutes'] * 60;
        $query = "SELECT * FROM prices WHERE base_currency = '{$base_currency}' AND `time` <= {$time} ORDER BY id DESC LIMIT 1";
        $timestamp_price = round(floatval($mysqli->query($query)->fetch_assoc()['price']), 3);
        $current_price = round($price, 3);
        $percentage = round(abs($timestamp_price / $current_price * 100 - 100), 2);
        if ($percentage >= $symbol['percentage'] && $timestamp_price != 0) {
            $telegram_chat_id = $mysqli->query("SELECT telegram_chat_id FROM users WHERE id = {$symbol['user_id']}")->fetch_assoc()['telegram_chat_id'];
            telegram_api_method('sendMessage', [
                'chat_id' => $telegram_chat_id,
                'text' => "There was a ".($current_price > $timestamp_price ? 'increasing' : 'decreasing')." of price\nBase currency: {$symbol['base_currency']}\nPercentage: {$percentage}%\nInterval: {$symbol['minutes']}m\nCurrent price: \${$current_price}\nPrevious price: \${$timestamp_price}"
            ]);
        }
    }
}

$mysqli->query("DELETE FROM prices WHERE `time` < UNIX_TIMESTAMP() - 2 * 24 * 60 * 60");
