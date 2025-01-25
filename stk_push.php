<?php
class MpesaDaraja {
    private $consumerKey;
    private $consumerSecret;
    private $accessToken;
    private $environment;

    public function __construct($consumerKey, $consumerSecret, $environment = 'sandbox') {
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->environment = $environment;
        $this->generateAccessToken();
    }

    private function generateAccessToken() {
        $url = ($this->environment === 'sandbox') 
            ? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($this->consumerKey . ':' . $this->consumerSecret)],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new Exception("cURL Error: " . $err);
        }

        $result = json_decode($response);
        $this->accessToken = $result->access_token;
    }

    public function initiateSTKPush($phoneNumber, $amount, $accountReference, $transactionDesc) {
        $url = ($this->environment === 'sandbox') 
            ? 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            : 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        // Prepare the request parameters
        $timestamp = date('YmdHis');
        $shortCode = 'YOUR_PAYBILL_NUMBER'; // Replace with your actual paybill number
        $passkey = 'YOUR_PASSKEY'; // Replace with your actual passkey
        $password = base64_encode($shortCode . $passkey . $timestamp);

        $curl_post_data = [
            'BusinessShortCode' => $shortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phoneNumber,
            'PartyB' => $shortCode,
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => 'callback.php', // Replace with your callback URL
            'AccountReference' => $accountReference,
            'TransactionDesc' => $transactionDesc
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($curl_post_data),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new Exception("cURL Error: " . $err);
        }

        return json_decode($response);
    }

    public function processCallback() {
        // Receive the raw POST data
        $callbackJSONData = file_get_contents('php://input');
        $jsonData = json_decode($callbackJSONData);

        // Extract relevant information
        $resultCode = $jsonData->Body->stkCallback->ResultCode;
        $resultDesc = $jsonData->Body->stkCallback->ResultDesc;
        $merchantRequestID = $jsonData->Body->stkCallback->MerchantRequestID;
        $checkoutRequestID = $jsonData->Body->stkCallback->CheckoutRequestID;

        // Log or process the callback data
        // Implement your specific logic here (e.g., update database, send notifications)

        // Respond to Daraja to confirm receipt
        $response = [
            'ResultCode' => 0,
            'ResultDesc' => 'Success'
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}

// Example usage
try {
    $mpesa = new MpesaDaraja('YOUR_CONSUMER_KEY', 'YOUR_CONSUMER_SECRET');
    
    // Initiate STK Push
    $result = $mpesa->initiateSTKPush(
        '254XXXXXXXXX',  // Phone number
        10, // Amount
        'Invoice123',    // Account Reference
        'Payment Test'   // Transaction Description
    );

    print_r($result);
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}

// Callback handler
// Create a separate file (callback.php) with this method
$mpesa = new MpesaDaraja('YOUR_CONSUMER_KEY', 'YOUR_CONSUMER_SECRET');
$mpesa->processCallback();
?>
