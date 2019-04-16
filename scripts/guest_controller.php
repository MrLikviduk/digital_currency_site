<?php
/**
 * Created by PhpStorm.
 * User: Виталий
 * Date: 29.10.2018
 * Time: 11:04
 */
require_once $_SERVER['DOCUMENT_ROOT'].'/config.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/scripts/functions.php';
switch ($_GET['action']) {
    case 'log_in':
        if (user_is_logged($_POST['username'], $_POST['password'])) {
            $expire_time = (isset($_POST['remember_me']) && $_POST['remember_me'] == '1' ? time() + 60 * 60 * 24 * 30 : 0);
            setcookie('username', $_POST['username'], $expire_time, '/');
            setcookie('password', $_POST['password'], $expire_time, '/');
            echo json_encode([
                'status' => 'success'
            ]);
        }
        else {
            echo json_encode([
                'status' => 'error',
                'error_text' => 'Username or password is incorrect'
            ]);
        }
        break;
    case 'log_out':
        setcookie('username', '', time() - 1, '/');
        setcookie('password', '', time() - 1, '/');
        echo json_encode([
            'status' => 'success'
        ]);
        break;
    case 'check_user':
        if ((!isset($_COOKIE['username']) || !isset($_COOKIE['password'])) || !user_is_logged()) {
            echo json_encode([
                'is_logged' => 0
            ]);
            break;
        }
        echo json_encode([
            'is_logged' => 1
        ]);
        break;
}