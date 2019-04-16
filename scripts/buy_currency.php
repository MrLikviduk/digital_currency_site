<?php

set_include_path(dirname(__DIR__));

spl_autoload_register(function ($class_name) {
    require_once 'classes/'.$class_name.'.php';
});

require_once 'config.php';
require_once 'scripts/functions.php';

if ((!isset($_SERVER['argv'][1]) || !isset($_SERVER['argv'][2])) || !user_is_logged($_SERVER['argv'][1], $_SERVER['argv'][2])) {
    echo 'Action is unauthorized';
    exit;
}

$mysqli = connect_to_database();

$symbols = $mysqli->all('symbols');
$prices = api_method('price');

foreach ($symbols as $symbol) {
    $amount_to_buy_in_dollars = (float)$symbol['amount_to_buy'];
    if ($amount_to_buy_in_dollars <= 0)
        continue;
    $user = $mysqli->query("SELECT * FROM users WHERE id = {$symbol['user_id']}")->fetch_assoc();
    $base_currency = $symbol['base_currency'];
    $price = 0;
    foreach ($prices as $price) {
        if ($price->symbol == $base_currency . 'USDT') {
            $price = (float)$price->price;
            break;
        }
    }
    $amount_to_buy = $amount_to_buy_in_dollars / $price;
    $binance = new Binance($user['binance_api_key'], $user['binance_api_secret']);
    $exchange_info = $binance->exchangeInfo();
    foreach ($exchange_info['symbols'] as $tmp_symbol) {
        if ($tmp_symbol['baseAsset'] === $base_currency && $tmp_symbol['quoteAsset'] === 'USDT') {
            $filter = $tmp_symbol['filters'][1];
            break;
        }
    }
    $amount_to_buy = round(filter_quantity($amount_to_buy, $filter), 4);
    $result = $binance->buy(
        $base_currency . 'USDT',
        $amount_to_buy,
        0,
        'MARKET'
    );
    telegram_api_method('sendMessage', [
        'chat_id' => $user['telegram_chat_id'],
        'text' => 'Было куплено '.$amount_to_buy.' '.$base_currency.' за ~$'.$amount_to_buy_in_dollars.' по цене $'.round($price, 2)
    ]);
}