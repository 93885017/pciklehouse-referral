<?php
// Prevent any hidden server warnings from breaking the JSON format
ob_start();
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

// The path to your secured file (hidden behind the .htaccess wall)
$file_path = 'secure_data/ID.csv';

// 1. Grab the phone number securely from the JSON POST request
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

$phone = '';
if (isset($input['phone'])) {
    $phone = trim($input['phone']);
} elseif (isset($_GET['phone'])) {
    $phone = trim($_GET['phone']); // Fallback just in case
}

// Check if a phone number was actually sent
if (empty($phone)) {
    ob_end_clean();
    echo json_encode(['error' => 'Phone number missing. Please try again.']);
    exit;
}

$referralCode = null;
$memberTier = 'Valued'; // Default fallback

// 2. Open the CSV file securely on the server
if (($handle = fopen($file_path, "r")) !== FALSE) {
    
    // Read the first row (the headers) to find which columns have the ID, Code, and Member Tier
    $header = fgetcsv($handle, 1000, ",");
    if ($header) {
        $idIndex = -1;
        $codeIndex = -1;
        $memberIndex = -1;

        // Find the correct columns even if the capitalization is slightly off
        foreach ($header as $index => $col) {
            $colLower = strtolower(trim($col));
            if (strpos($colLower, 'user id') !== false || $colLower === 'id') {
                $idIndex = $index;
            }
            if (strpos($colLower, 'referral code') !== false || strpos($colLower, 'password') !== false) {
                $codeIndex = $index;
            }
            // Look for the member/tier column
            if (strpos($colLower, 'member') !== false || strpos($colLower, 'tier') !== false || strpos($colLower, 'membership') !== false) {
                $memberIndex = $index;
            }
        }

        // If we found the mandatory ID and Code columns, start searching the rows
        if ($idIndex !== -1 && $codeIndex !== -1) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // If the ID column matches the requested phone number
                if (trim($data[$idIndex]) === $phone) {
                    $referralCode = trim($data[$codeIndex]);
                    
                    // If we also found the member column, grab the tier
                    if ($memberIndex !== -1 && isset($data[$memberIndex]) && trim($data[$memberIndex]) !== '') {
                        $memberTier = trim($data[$memberIndex]);
                    }
                    
                    break; // Stop searching, we found it!
                }
            }
        }
    }
    fclose($handle); // Close the file
} else {
    // If the file is missing or misspelled
    ob_end_clean();
    echo json_encode(['error' => 'Secure database file not found. Ensure ID.csv is in the secure_data folder.']);
    exit;
}

ob_end_clean(); // Clear any invisible server output

// 3. Send the result securely back to the frontend HTML
if ($referralCode) {
    // Note: Variable names must match what index.html expects exactly
    echo json_encode([
        'success' => true, 
        'referralCode' => $referralCode,
        'memberTier' => $memberTier
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'error' => 'No matching User ID found. Please verify your number.'
    ]);
}
?>