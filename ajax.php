<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Illuminate\Database\Capsule\Manager as Capsule;

$action = $_GET['action'];
switch($action) {
    case 'payer_auth':
        $post_data = $_POST;
        if(isset($post_data['data']) && !empty($post_data['data'])){
            $payload = decrypt($post_data['data']);
        }
        break;
    case 'requery':

        break;

        default;

        break;
}

function decrypt($encrypted) {
    $method = 'aes-256-cbc';
    $password = '3sc3RLrpd17';
    $iv = chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0);


    // My secret message 1234
    $decrypted = openssl_decrypt(base64_decode($encrypted), $method, $password, OPENSSL_RAW_DATA, $iv);
    return $decrypted;
}