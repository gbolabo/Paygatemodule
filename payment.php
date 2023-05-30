<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Illuminate\Database\Capsule\Manager as Capsule;

function encryptData($data, $secretKey)
{
    // Generate a random initialization vector (IV)
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length("AES-128-ECB"));

    // Encrypt the data using AES-128-ECB with PKCS5Padding
    $encrypted = openssl_encrypt(json_encode($data), "AES-128-ECB", $secretKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);

    // Encode the encrypted data and IV as base64
    $encryptedData = base64_encode($encrypted);
    $encodedIV = base64_encode($iv);

    // Combine the encrypted data and IV into a single encrypted string
    $encryptedString = $encryptedData . ":" . $encodedIV;

    return $encryptedString;
}

function paygate_MetaData()
{
    return array(
        'DisplayName' => 'PayGate',
        'APIVersion' => '1.1',
    );
}

function paygate_config()
{
    $configarray = array(
        "FriendlyName" => array(
            "Type" => "System",
            "Value" => "PayGate",
        ),
        "SecretKey" => array(
            "FriendlyName" => "Secret Key",
            "Type" => "password",
            "Size" => "50",
            "Default" => "",
            "Description" => "Enter your PayGate secret key here",
        ),
        "Username" => array(
            "FriendlyName" => "Username",
            "Type" => "text",
            "Size" => "100",
            "Default" => "",
            "Description" => "Enter your PayGate secret key here",
        ),
        "Password" => array(
            "FriendlyName" => "Password",
            "Type" => "password",
            "Size" => "50",
            "Default" => "",
            "Description" => "Enter Api password",
            
        )
    ;

    return $configarray;
}

function paygate_link($params)
{
    // Retrieve the necessary parameters from the WHMCS order
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $currency = $params['currency'];

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $action = $_GET['action'];
        switch($action) {
            case 'init': 
                $cardDetailsForm = <<<'HTML'
                    <div class="container">
      <div class="card-form">
        <h2 class="text-center">Pay Gate</h2>
        <form id="paymentForm" method="POST">
          <div class="form-group">
            <label for="cardNumber">Card Number:</label>
            <input
              type="text"
              class="form-control"
              id="cardNumber"
              name="cardNumber"
              placeholder="Enter card number"
            />
          </div>
          <div class="form-group">
            <label for="cvv">CVV:</label>
            <input
              type="text"
              class="form-control"
              id="cvv"
              name="cvv"
              placeholder="Enter CVV"
            />
          </div>
          <div class="form-group">
            <label for="expirationDate">Expiration Date:</label>
            <div class="input-group date">
              <input
                type="text"
                class="form-control"
                id="expirationDate"
                name="expirationDate"
                placeholder="Enter expiration date (MM/YYYY)"
              />
              <span class="input-group-addon">
                <span class="glyphicon glyphicon-calendar"></span>
              </span>
            </div>
          </div>
          <div class="form-group">
            <label for="name">Cardholder's Name:</label>
            <input
              type="text"
              class="form-control"
              id="name"
              name="name"
              placeholder="Enter cardholder's name"
            />
          </div>
          <div class="text-center">
            <button type="submit" class="btn btn-submit">Submit</button>
          </div>
        </form>
      </div>
    </div>
                HTML;

                return $cardDetailsForm;
                break;
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
        $post_data = $_POST;
        if(!isset($post_data['data']) || empty($post_data['data'])){
            return 
        }
        $payload = decrypt($post_data['data']);
        // Define the JSON object to be encrypted
        $data = array(
            "payGateRef" => "WHM" . PayGateGenerateTransactionNumber();,
            "cardNumber" => $_POST['cardNumber'],
            "expirationMonth" => $_POST['expirationMonth'],
            "expirationYear" => $_POST['expirationYear'],
            "currency" => $currency,
            "securityCode" => $_POST['securityCode'],
            "Amount" => $amount,
            "countryCode" => $_POST['countryCode'],
            "email" => $_POST['email'],
        );

        // Retrieve the secret key from the module configuration
        $secretKey = $params['SecretKey'];

        // Encrypt the JSON object
        $encryptedJSON = encryptData($data, $secretKey);

        // Define the API endpoint
        $apiEndpoint = "https://paygate.upperlink.ng/api/payerAuth";

        // Define the authorization credentials
        $username = $params['Username'];
        $password = $params['Password'];
        
        // Set the request payload
        $requestPayload = $encryptedJSON;

        // Set the headers for authorization and content type
        $headers = array(
            "Authorization: Basic " . base64_encode($username . ":" . $password),
            "Content-Type: application/json",
        );

        // Make a POST request to the API endpoint
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiEndpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestPayload);
        // curl_setopt($ch
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);

        // Process the API response
        if ($response) {
            $responseData = json_decode($response, true);

            // Extract the authUrl from the response
            $authUrl = $responseData['authUrl'];

            // Redirect the user to the authUrl
            return '<form method="post" action="' . $authUrl . '">
                <input type="submit" value="Pay Now">
            </form>';
        } else {
            // Handle the error scenario
            $errorMessage = "Failed to connect to the payment gateway";
            return $errorMessage;
        }
    } else {
        return '<form method="post" action="' . $authUrl . '?action=init&invoiceid='.$params['invoiceid'].'">
                <input type="submit" value="Pay Now">
            </form>';
    }

}


function PayGateGenerateTransactionNumber($randStringLength = 12) {
    $timestring = microtime();
    $secondsSinceEpoch=(integer) substr($timestring, strrpos($timestring, " "), 100);
    $microseconds=(double) $timestring;
    $seed = mt_rand(0,1000000000) + 10000000 * $microseconds + $secondsSinceEpoch;
    mt_srand($seed);
    $randstring = "";

    for($i=0; $i < $randStringLength; $i++){
    $randstring .= mt_rand(0, 9);
    }
    
    //return code
    return (string) $randstring;
}

// function encryptData($data, $secretKey)
// {
//     // Generate a random initialization vector (IV)
//     $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length("AES-128-ECB"));

//     // Encrypt the data using AES-128-ECB with PKCS5Padding
//     $encrypted = openssl_encrypt(json_encode($data), "AES-128-ECB", $secretKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);

//     // Encode the encrypted data and IV as base64
//     $encryptedData = base64_encode($encrypted);
//     $encodedIV = base64_encode($iv);

//     // Combine the encrypted data and IV into a single encrypted string
//     $encryptedString = $encryptedData . ":" . $encodedIV;

//     return $encryptedString;
// }

function decrypt($encrypted) {
    $method = 'aes-256-cbc';
    $password = '3sc3RLrpd17';
    $iv = chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0);


    // My secret message 1234
    $decrypted = openssl_decrypt(base64_decode($encrypted), $method, $password, OPENSSL_RAW_DATA, $iv);
    return $decrypted;
}