<?php
/**
 * Created by PhpStorm.
 * User: Виталий
 * Date: 24.10.2018
 * Time: 21:24
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/scripts/functions.php';

if (!user_is_logged()) {
    echo json_encode([
        'status' => 'error',
        'error_text' => 'Action is unauthorized'
    ]);
    exit;
}

$current_user = current_user();
$mysqli = connect_to_database();
$binance = new Binance($current_user['binance_api_key'], $current_user['binance_api_secret']);

switch ($_GET['action']) {
    case 'create_order':
        $method = '';
        $side = $_POST['side'];
        $type = $_POST['type'];
        $base_currency = $_POST['base_currency'];
        $symbol = $base_currency . 'USDT';
        $quantity = floatval($_POST['quantity']);
        $price = floatval($_POST['price']);
        if ($quantity < 0.0001) {
            echo json_encode([
                'status' => 'error',
                'error_text' => 'Quantity is too small!'
            ]);
            break;
        }
        if (!in_array($side, ['BUY', 'SELL'], TRUE)) {
            echo json_encode([
                'status' => 'error',
                'error_text' => 'Unknown side!'
            ]);
            break;
        }
        $method .= strtolower($side);
        if (!in_array($type, ['LIMIT', 'MARKET'], TRUE)) {
            echo json_encode([
                'status' => 'error',
                'error_text' => 'Unknown type!'
            ]);
            break;
        }
        $method .= ($type === 'MARKET' ? 'Market' : '');
        switch ($method) {
            case 'buy':
                $r = $binance->buy($symbol, $quantity, $price);
                break;
            case 'sell':
                $r = $binance->sell($symbol, $quantity, $price);
                break;
            case 'buyMarket':
                $r = $binance->buy($symbol, $quantity, $price, 'MARKET');
                break;
            case 'sellMarket':
                $r = $binance->sell($symbol, $quantity, $price, 'MARKET');
                break;
        }
        if (isset($r['code']) && $r['code'] < 0) {
            echo json_encode([
                'status' => 'error',
                'error_text' => $r['msg']
            ]);
        } else {
            echo json_encode([
                'status' => 'success',
                'success_text' => "Order was created successfully!<br>Status: {$r['status']}"
            ]);
        }
        break;
    case 'get_prices':
        echo json_encode(api_method('price'));
        break;
    case 'get_template':
        $currencies = $mysqli->query("SELECT symbols.id AS id, symbols.base_currency AS base_currency, timestamps.minutes AS minutes, symbols.percentage AS percentage, symbols.amount_to_buy AS amount_to_buy FROM symbols INNER JOIN timestamps ON symbols.timestamp_id = timestamps.id WHERE symbols.user_id = {$current_user['id']} ORDER BY symbols.id DESC")->fetch_all(MYSQLI_ASSOC);
        echo json_encode([
            'currencies' => $currencies
        ]);
        break;
    case 'delete_currency':
        $id = (int)$_POST['id'];
        $symbol = $mysqli->find('symbols', $id);
        if ($symbol['user_id'] != $current_user['id']) {
            echo json_encode([
                'status' => 'error',
                'error_text' => 'You do not have permission to do this!'
            ]);
            break;
        }
        $result = $mysqli->delete('symbols', $id);
        echo json_encode([
            'status' => ($result === FALSE ? 'error' : 'success')
        ]);
        break;
    case 'get_timestamps':
        $timestamps = $mysqli->all('timestamps');
        echo json_encode([
            'timestamps' => $timestamps
        ]);
        break;
    case 'add_currency':
        $base_currency = $mysqli->real_escape_string(strtoupper($_POST['base_currency']));
        $percentage = (int)$_POST['percentage'];
        $amount_to_buy = (int)$_POST['amount_to_buy'];
        if ($mysqli->query("SELECT * FROM symbols WHERE base_currency = '{$base_currency}' AND user_id = {$current_user['id']}")->num_rows == 1) {
            echo json_encode([
                'status' => 'error',
                'error_text' => 'This currency is already exists!'
            ]);
            break;
        }
        $r = api_method('price');
        $v = FALSE;
        foreach ($r as $ticker) {
            if ($base_currency . 'USDT' === $ticker->symbol) {
                $v = TRUE;
                break;
            }
        }
        if (!$v) {
            echo json_encode([
                'status' => 'error',
                'error_text' => 'This currency does not exists!'
            ]);
            break;
        }
        if ($percentage < 1) {
            echo json_encode([
                'status' => 'error',
                'error_text' => 'Percentage must be an integer number, biggers than 1 or equals 1'
            ]);
            break;
        }
        if ($amount_to_buy < 0) {
            echo json_encode([
                'status' => 'error',
                'error_text' => 'Amount to buy must be an integer number, bigger than 0 or equals 0'
            ]);
            break;
        }
        if (!isset($_POST['interval'])) {
            echo json_encode([
                'status' => 'error',
                'error_text' => 'You should choose an interval'
            ]);
            break;
        }
        $res = $mysqli->add('symbols', [
            'id' => 0,
            'user_id' => $current_user['id'],
            'base_currency' => $base_currency,
            'timestamp_id' => $_POST['interval'],
            'percentage' => $percentage,
            'amount_to_buy' => $amount_to_buy
        ]);
        if ($res === FALSE) {
            echo json_encode([
                'status' => 'error',
                'error_text' => 'Произошел троллинг'
            ]);
            break;
        }
        echo json_encode([
            'currency' => $mysqli->query("SELECT symbols.id AS id, symbols.base_currency AS base_currency, timestamps.minutes AS minutes, symbols.percentage AS percentage, symbols.amount_to_buy AS amount_to_buy FROM symbols LEFT JOIN timestamps ON symbols.timestamp_id = timestamps.id WHERE symbols.base_currency = '{$base_currency}' AND symbols.user_id = {$current_user['id']} ORDER BY symbols.id DESC")->fetch_assoc(),
            'status' => 'success'
        ]);
        break;
    case 'get_balance':
        $balances = $binance->account()['balances'];
        $response = [];
        foreach ($balances as $currency) {
            if ($currency['free'] > 0) {
                array_push($response, [
                    'base_currency' => $currency['asset'],
                    'total' => $currency['free'] + $currency['locked'],
                    'in_order' => $currency['locked']
                ]);
            }
        }
        usort($response, function($a, $b) {
            return strcmp($a['base_currency'], $b['base_currency']);
        });
        echo json_encode($response);
        break;
}

$mysqli->close();