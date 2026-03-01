<?php

function validateField($value, $type, $length) {
    switch($type) {
        case 'N': // Number
            if (!is_numeric($value) || strlen($value) > $length) {
                return false;
            }
            break;
        case 'A': // Alphanumeric
            if (!is_string($value) || strlen($value) > $length) {
                return false;
            }
            break;
        default:
            return false;
    }
    return true;
}

function validateInvoiceData($data) {
    $requiredFields = [
        'invoice_id' => ['type' => 'A', 'length' => 50],
        'invoice_type' => ['type' => 'N', 'length' => 4],
        'vat_number' => ['type' => 'N', 'length' => 9],
        'invoice_date' => ['type' => 'A', 'length' => 10],
        'invoice_issuance_date' => ['type' => 'A', 'length' => 10],
        'payment_amount' => ['type' => 'N', 'length' => 12],
        'vat_amount' => ['type' => 'N', 'length' => 12],
        'payment_amount_including_vat' => ['type' => 'N', 'length' => 12],
    ];

    foreach ($requiredFields as $field => $rules) {
        if (!isset($data[$field])) {
            return [
                'status' => 400,
                'message' => [
                    'errors' => [[
                        'code' => 446,
                        'message' => "Missing required field: $field",
                        'param' => $field,
                        'location' => 'request'
                    ]]
                ],
                'confirmation_number' => '0',
                'approved' => false
            ];
        }

        if (!validateField($data[$field], $rules['type'], $rules['length'])) {
            return [
                'status' => 400,
                'message' => [
                    'errors' => [[
                        'code' => 431,
                        'message' => "Invalid format for field: $field",
                        'param' => $field,
                        'location' => 'request'
                    ]]
                ],
                'confirmation_number' => '0',
                'approved' => false
            ];
        }
    }

    // // Validate invoice date
    // $invoiceDate = strtotime($data['Invoice_Date']);
    // $oneMonthAhead = strtotime('+1 month');
    // $twoYearsAgo = strtotime('-2 years');

    // if ($invoiceDate > $oneMonthAhead) {
    //     return [
    //         'status' => 400,
    //         'message' => [
    //             'errors' => [[
    //                 'code' => 435,
    //                 'message' => 'Invoice date is more than a month ahead',
    //                 'param' => 'invoice_date',
    //                 'location' => 'request'
    //             ]]
    //         ],
    //         'confirmation_number' => '0',
    //         'approved' => false
    //     ];
    // }

    // if ($invoiceDate < $twoYearsAgo) {
    //     return [
    //         'status' => 400,
    //         'message' => [
    //             'errors' => [[
    //                 'code' => 434,
    //                 'message' => 'Invoice date is too old for approval',
    //                 'param' => 'invoice_date',
    //                 'location' => 'request'
    //             ]]
    //         ],
    //         'confirmation_number' => '0',
    //         'approved' => false
    //     ];
    // }


    if (!empty($data['vat_amount']) && !empty($data['payment_amount']) && !empty($data['payment_amount_including_vat'])) {
        $calculatedTotal = $data['payment_amount'] + $data['vat_amount'];
        if (abs($calculatedTotal - $data['payment_amount_including_vat']) > 0.01) {
            return [
                'status' => 400,
                'message' => [
                    'errors' => [[
                        'code' => 431,
                        'message' => 'Payment amount including VAT does not match calculation',
                        'param' => 'payment_amount_including_vat',
                        'location' => 'request'
                    ]]
                ],
                'confirmation_number' => '0',
                'approved' => false
            ];
        }
    }

    return true;
}


function handleTokenRequest($postData) {
    $required = ['client_id', 'client_secret', 'refresh_token', 'scope', 'grant_type'];
    foreach ($required as $field) {
        if (empty($postData[$field])) {
            http_response_code(401);
            return [
                'error' => 'invalid_request',
                'error_description' => "Missing required parameter: $field"
            ];
        }
    }
    
    if ($postData['grant_type'] !== 'refresh_token') {
        http_response_code(401);
        return [
            'error' => 'invalid_grant_type',
            'error_description' => 'grant_type must be refresh_token'
        ];
    }

    return [
        'access_token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsIng1dCI6...',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'refresh_token' => 'new_refresh_token_' . time()
    ];
}


if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $json = file_get_contents('php://input');
    $requestData = json_decode($json, true);
} else {
    $requestData = $_POST;
}
// Log incoming requests for debugging
// file_put_contents("CRM_DATA", print_r($_SERVER, 1).$json, );

// Set content type header
header('Content-Type: application/json');

if (preg_match('/\/Approval/', $_SERVER["REQUEST_URI"])) {
    $validationResult = validateInvoiceData($requestData);

    if ($validationResult === true) {
        // If validation passes, always approve
        echo json_encode([
            'status' => 200,
			'Status' => 200,//BC to V1
            'message' => 'Invoice approved',
            'confirmation_number' => date('YmdHisu', time()),
			'Confirmation_Number' => date('YmdHisu', time()),//BC to V1
            'approved' => true
        ]);
    } else {
        echo json_encode($validationResult);
    }
} elseif (preg_match('/\/openapi/', $_SERVER["REQUEST_URI"]) || 
          preg_match('/\/token/', $_SERVER["REQUEST_URI"])) {
    // Handle token requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(handleTokenRequest($requestData));
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'method_not_allowed']);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'endpoint_not_found']);
}



?>


