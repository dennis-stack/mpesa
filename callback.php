<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection (recommended for logging)
$servername = "localhost";
$username = "your_db_username";
$password = "your_db_password";
$dbname = "mpesa_transactions";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Receive raw POST data
$callbackJSONData = file_get_contents('php://input');

// Log raw callback data (for debugging and auditing)
$logFile = 'mpesa_callback_log.txt';
file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $callbackJSONData . "\n", FILE_APPEND);

// Decode JSON data
$jsonData = json_decode($callbackJSONData, true);

// Check if data is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die("Invalid JSON");
}

// Extract transaction details
$resultCode = $jsonData['Body']['stkCallback']['ResultCode'] ?? null;
$resultDesc = $jsonData['Body']['stkCallback']['ResultDesc'] ?? 'No description';
$merchantRequestID = $jsonData['Body']['stkCallback']['MerchantRequestID'] ?? null;
$checkoutRequestID = $jsonData['Body']['stkCallback']['CheckoutRequestID'] ?? null;

// Prepare SQL to insert callback data
$stmt = $conn->prepare("INSERT INTO mpesa_callbacks 
    (result_code, result_desc, merchant_request_id, checkout_request_id, raw_data) 
    VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param(
    "issss", 
    $resultCode, 
    $resultDesc, 
    $merchantRequestID, 
    $checkoutRequestID, 
    $callbackJSONData
);

// Execute SQL
$stmt->execute();

// Process transaction based on result code
if ($resultCode === 0) {
    // Successful transaction
    $itemData = $jsonData['Body']['stkCallback']['CallbackMetadata']['Item'];
    
    // Extract transaction details
    $transactionDetails = [];
    foreach ($itemData as $item) {
        $transactionDetails[$item['Name']] = $item['Value'];
    }

    // Update transaction status in your system
    $updateStmt = $conn->prepare("UPDATE transactions SET status = 'completed', mpesa_receipt_number = ?, amount = ?, transaction_date = NOW() WHERE checkout_request_id = ?");
    $updateStmt->bind_param(
        "sds", 
        $transactionDetails['MpesaReceiptNumber'],
        $transactionDetails['Amount'],
        $checkoutRequestID
    );
    $updateStmt->execute();

    // Optional: Send confirmation email/SMS to customer
} else {
    // Failed transaction
    $updateStmt = $conn->prepare("UPDATE transactions SET status = 'failed', failure_reason = ? WHERE checkout_request_id = ?");
    $updateStmt->bind_param(
        "ss", 
        $resultDesc, 
        $checkoutRequestID
    );
    $updateStmt->execute();
}

// Close statements and connection
$stmt->close();
$updateStmt->close();
$conn->close();

// Respond to Daraja
$response = [
    'ResultCode' => 0,
    'ResultDesc' => 'Success'
];

header('Content-Type: application/json');
echo json_encode($response);
exit();
?>
