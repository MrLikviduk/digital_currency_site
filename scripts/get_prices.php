<?php
/**
 * Created by PhpStorm.
 * User: Виталий
 * Date: 16.10.2018
 * Time: 19:57
 */

set_include_path(dirname(__DIR__));

require_once 'config.php';
require_once 'scripts/functions.php';

if (!user_is_logged()) {
    echo json_encode([
        'status' => 'error',
        'error_text' => 'Action is unauthorized'
    ]);
    exit;
}

$current_user = current_user();
$mysqli = connect_to_database();
$symbols = $mysqli->query("SELECT * FROM symbols WHERE user_id = {$current_user['id']} ORDER BY base_currency");
$timestamps = $mysqli->all('timestamps');

switch ($_GET['action']) {
    case 'get_timestamps':
        echo json_encode($timestamps);
        break;
    case 'get_template':
        $arr = [];
        foreach ($symbols as $symbol) {
            array_push($arr, [
                'base_currency' => $symbol['base_currency']
            ]);
        }
        echo json_encode($arr);
        break;
    case 'get_prices':
        $r = api_method('price');
        $arr = [];
        foreach ($symbols as $symbol) {
            $price = -1;
            foreach ($r as $ticker) {
                if ($ticker->symbol === $symbol['base_currency'] . 'USDT') {
                    array_push($arr, [
                        'base_currency' => $symbol['base_currency'],
                        'price' => $ticker->price,
                        'timestamps' => []
                    ]);
                    $price = $ticker->price;
                    break;
                }
            }
            foreach ($timestamps as $timestamp) {
                $time = time() - $timestamp['minutes'] * 60;
                $query = "SELECT price FROM prices WHERE base_currency = '{$symbol['base_currency']}' AND `time` <= {$time} ORDER BY id DESC LIMIT 1";
                $result = $mysqli->query($query);
                $timestamp_price = ($result->num_rows == 0 ? '-' : (float)$result->fetch_all(MYSQLI_ASSOC)[0]['price']);
                $timestamp['price'] = $timestamp_price;
                array_push($arr[count($arr) - 1]['timestamps'], $timestamp);
            }
        }
        echo json_encode($arr);
        break;
}

$mysqli->close();