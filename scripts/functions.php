<?php
set_include_path(dirname(__DIR__));
spl_autoload_register(function ($class_name) {
    require_once 'classes/'.$class_name.'.php';
});
function get_include_contents($filename) {
    if (is_file($filename)) {
        ob_start();
        include $filename;
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }
    return false;
}
function api_method($method, $params = [], $http_method = 'GET') {
    switch (strtoupper($http_method)) {
        case 'GET':
            $url = 'https://api.binance.com/api/v3/ticker/'.$method.'?'.http_build_query($params);
            return json_decode(file_get_contents($url));
        case 'POST':
            $url = 'https://api.binance.com/api/v3/ticker/'.$method;
            $myCurl = curl_init();
            curl_setopt_array($myCurl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($params)
            ]);
            $response = curl_exec($myCurl);
            curl_close($myCurl);
            return $response;
    }
}
function telegram_api_method($method, $params = []) {
    $url = 'https://api.telegram.org/bot'.TELEGRAM_ACCESS_TOKEN.'/'.$method.'?'.http_build_query($params);
    return json_decode(file_get_contents($url));
}
function connect_to_database() {
    return new MyMySQLi(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
}
function filter_quantity($quantity, $filter) {
    if ($filter['filterType'] !== 'LOT_SIZE')
        return 0;
    if ($quantity < $filter['minQty']) {
        $quantity = $filter['minQty'];
    }
    else if ($quantity > $filter['maxQty']) {
        $quantity = $filter['maxQty'];
    }
    for ($i = $filter['stepSize']; TRUE; $i += $filter['stepSize']) {
        if ($i + $filter['stepSize'] > $quantity) {
            $quantity = $i;
            break;
        }
    }
    return $quantity;
}
function user_is_logged($input_username = '', $input_password = '') {
    if ((!isset($_COOKIE['username']) || !isset($_COOKIE['password'])) && (strlen($input_username) == 0 || strlen($input_password) == 0))
        return FALSE;
    $input_username = (strlen($input_username) == 0 ? $_COOKIE['username'] : $input_username);
    $input_password = (strlen($input_password) == 0 ? $_COOKIE['password'] : $input_password);
    $mysqli = connect_to_database();
    $result = $mysqli->find('users', $input_username, 'name');
    $mysqli->close();
    if ($result === FALSE || count($result) == 0)
        return FALSE;
    $salt = md5($result['id']);
    if ($result['password'] === md5($input_password . substr($salt, 0, 6))) {
        return TRUE;
    }
    return FALSE;
}
function current_user() {
    if ((!isset($_COOKIE['username']) || !isset($_COOKIE['password'])))
        return FALSE;
    $username = $_COOKIE['username'];
    $password = $_COOKIE['password'];
    if (!user_is_logged($username, $password))
        return FALSE;
    $mysqli = connect_to_database();
    $user = $mysqli->find('users', $username, 'name');
    $mysqli->close();
    return $user;
}

