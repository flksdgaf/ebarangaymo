<?php
session_start();
require_once 'dbconn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedInUserRole']) || !in_array($_SESSION['loggedInUserRole'], ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$purok = $_POST['purok'] ?? '';
if (!in_array($purok, ['1', '2', '3', '4', '5', '6'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid purok selected']);
    exit;
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$tableName = "purok{$purok}_rbi";
$filePath = $_FILES['csv_file']['tmp_name'];

// Clean function to remove null bytes and weird characters
function cleanString($str) {
    $str = str_replace("\0", "", $str); // Remove null bytes
    $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8'); // Fix encoding
    return trim($str);
}

try {
    $file = fopen($filePath, 'r');
    if (!$file) {
        throw new Exception('Could not open file');
    }

    // Skip header row
    $header = fgetcsv($file);

    $imported = 0;
    $skipped = 0;
    $errors = [];

    $conn->begin_transaction();

    $rowNumber = 0; // Changed from 1 to 0
    while (($row = fgetcsv($file)) !== false) {
        $rowNumber++;
        
        // Skip if this looks like a header row (contains "No#" or "Fullname")
        if ($rowNumber === 1 && (
            stripos($row[0], 'no') !== false || 
            stripos($row[2], 'fullname') !== false ||
            stripos($row[2], 'name') !== false
        )) {
            continue;
        }
        
        // Skip empty rows
        if (empty(array_filter($row))) {
            $skipped++;
            continue;
        }
        
        // Clean all fields
        $row = array_map('cleanString', $row);

        // Map CSV columns
        $relationship_to_head = isset($row[1]) ? $row[1] : '';
        $full_name_raw = isset($row[2]) ? $row[2] : '';

        // Fix name format: "Lastname, Firstname, Middlename" -> "Lastname, Firstname Middlename"
        $full_name = $full_name_raw;
        if (!empty($full_name_raw) && strpos($full_name_raw, ',') !== false) {
            $nameParts = array_map('trim', explode(',', $full_name_raw));
            if (count($nameParts) === 3) {
                // Format: Lastname, Firstname, Middlename -> Lastname, Firstname Middlename
                $full_name = $nameParts[0] . ', ' . $nameParts[1] . ' ' . $nameParts[2];
            } elseif (count($nameParts) === 2) {
                // Already correct: Lastname, Firstname
                $full_name = $nameParts[0] . ', ' . $nameParts[1];
            }
        }
        $birthdate = isset($row[3]) ? $row[3] : '';
        $sex = isset($row[4]) ? $row[4] : '';
        $civil_status = isset($row[5]) ? $row[5] : '';
        $blood_type = isset($row[6]) ? trim($row[6]) : '';

        // Validate blood type - set to Unknown if empty or invalid
        $validBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        if (empty($blood_type) || !in_array($blood_type, $validBloodTypes)) {
            $blood_type = 'Unknown';
        }
        $birth_registration_number = isset($row[7]) ? $row[7] : '';
        $highest_educational_attainment = isset($row[8]) ? $row[8] : '';
        $occupation = isset($row[9]) ? $row[9] : '';
        $registry_number = isset($row[10]) ? $row[10] : '';
        $total_population = isset($row[11]) ? $row[11] : '';
        
        // Validate required field
        if (empty($full_name)) {
            $errors[] = "Row {$rowNumber}: Missing full name";
            $skipped++;
            continue;
        }
        
        // Check for duplicates
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM `{$tableName}` WHERE full_name = ?");
        $checkStmt->bind_param('s', $full_name);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc()['count'];
        $checkStmt->close();
        
        if ($exists > 0) {
            $errors[] = "Row {$rowNumber}: '{$full_name}' already exists";
            $skipped++;
            continue;
        }
        
        // Convert date - with default fallback
        $birthdateFormatted = '1900-01-01';
        
        if (!empty($birthdate)) {
            $formats = ['m/d/Y', 'd/m/Y', 'Y-m-d', 'n/j/Y', 'j/n/Y', 'Y/m/d'];
            foreach ($formats as $format) {
                $date = @DateTime::createFromFormat($format, $birthdate);
                if ($date && $date->format($format) === $birthdate) {
                    $birthdateFormatted = $date->format('Y-m-d');
                    break;
                }
            }
            
            if ($birthdateFormatted === '1900-01-01') {
                $errors[] = "Row {$rowNumber}: Invalid date '{$birthdate}' for {$full_name} - using 1900-01-01";
            }
        }
        
        // Normalize sex
        $sexLower = strtolower($sex);
        if (in_array($sexLower, ['m', 'male', 'lalaki'])) {
            $sex = 'Male';
        } elseif (in_array($sexLower, ['f', 'female', 'babae'])) {
            $sex = 'Female';
        } else {
            $sex = 'Unknown';
        }
        
        // Normalize civil status
        $civilMap = [
            'single' => 'Single',
            'married' => 'Married',
            'widowed' => 'Widowed',
            'widow' => 'Widowed',
            'separated' => 'Separated',
            'divorced' => 'Divorced'
        ];
        $civilLower = strtolower($civil_status);
        $civil_status = $civilMap[$civilLower] ?? 'Unknown';
        
        // Set defaults
        $account_ID = 0;
        $profile_picture = 'default_profile_pic.png';
        $house_number = null;
        $registry_number = is_numeric($registry_number) ? (int)$registry_number : null;
        $total_population = is_numeric($total_population) ? (int)$total_population : null;
        
        // Insert
        $stmt = $conn->prepare("
            INSERT INTO `{$tableName}` 
            (account_ID, full_name, birthdate, sex, civil_status, blood_type, 
             birth_registration_number, highest_educational_attainment, occupation, 
             house_number, relationship_to_head, registry_number, total_population, profile_picture) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            'issssssssssiis',
            $account_ID,
            $full_name,
            $birthdateFormatted,
            $sex,
            $civil_status,
            $blood_type,
            $birth_registration_number,
            $highest_educational_attainment,
            $occupation,
            $house_number,
            $relationship_to_head,
            $registry_number,
            $total_population,
            $profile_picture
        );
        
        if ($stmt->execute()) {
            $imported++;
        } else {
            $errors[] = "Row {$rowNumber} ({$full_name}): " . $stmt->error;
            $skipped++;
        }
        
        $stmt->close();
    }
    
    fclose($file);
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'imported_count' => $imported,
        'skipped_count' => $skipped,
        'total_rows' => $rowNumber - 1,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>