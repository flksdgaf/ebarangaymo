<?php
session_start();
require 'dbconn.php';

// Only allow Lupon Tagapamayapa role
if (!isset($_SESSION['loggedInUserRole']) || $_SESSION['loggedInUserRole'] !== 'Lupon Tagapamayapa') {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate
if ($month < 1 || $month > 12) $month = date('n');
if ($year < 2020 || $year > 2030) $year = date('Y');

$meetings = [];

$meetingsQuery = "
    SELECT 
        bc.transaction_id,
        bc.case_no,
        bc.schedule_pb_first,
        bc.schedule_pb_second,
        bc.schedule_pb_third,
        bc.schedule_unang_patawag,
        bc.schedule_ikalawang_patawag,
        bc.schedule_ikatlong_patawag,
        bc.complaint_stage,
        bc.complainant_name,
        bc.respondent_name
    FROM barangay_complaints bc
    WHERE (
        (YEAR(bc.schedule_pb_first) = {$year} AND MONTH(bc.schedule_pb_first) = {$month})
        OR (YEAR(bc.schedule_pb_second) = {$year} AND MONTH(bc.schedule_pb_second) = {$month})
        OR (YEAR(bc.schedule_pb_third) = {$year} AND MONTH(bc.schedule_pb_third) = {$month})
        OR (YEAR(bc.schedule_unang_patawag) = {$year} AND MONTH(bc.schedule_unang_patawag) = {$month})
        OR (YEAR(bc.schedule_ikalawang_patawag) = {$year} AND MONTH(bc.schedule_ikalawang_patawag) = {$month})
        OR (YEAR(bc.schedule_ikatlong_patawag) = {$year} AND MONTH(bc.schedule_ikatlong_patawag) = {$month})
    )
    ORDER BY bc.created_at DESC
";

$meetingsResult = $conn->query($meetingsQuery);

if ($meetingsResult && $meetingsResult->num_rows > 0) {
    while ($row = $meetingsResult->fetch_assoc()) {
        $complainantParts = explode(',', $row['complainant_name']);
        $complainantLastName = trim($complainantParts[0]);
        
        $respondentParts = explode(',', $row['respondent_name']);
        $respondentLastName = trim($respondentParts[0]);
        
        $caseLabel = $row['case_no'] ? $row['case_no'] : $row['transaction_id'];
        $title = $caseLabel . ': ' . $complainantLastName . ' vs ' . $respondentLastName;
        
        $schedules = [
            'pb_first' => $row['schedule_pb_first'],
            'pb_second' => $row['schedule_pb_second'],
            'pb_third' => $row['schedule_pb_third'],
            'unang_patawag' => $row['schedule_unang_patawag'],
            'ikalawang_patawag' => $row['schedule_ikalawang_patawag'],
            'ikatlong_patawag' => $row['schedule_ikatlong_patawag']
        ];
        
        foreach ($schedules as $type => $schedule) {
            if ($schedule) {
                $date = date('Y-m-d', strtotime($schedule));
                $time = date('g:i A', strtotime($schedule));
                
                if (!isset($meetings[$date])) {
                    $meetings[$date] = [];
                }
                
                $meetings[$date][] = [
                    'time' => $time,
                    'title' => $title,
                    'type' => $type,
                    'transaction_id' => $row['transaction_id']
                ];
            }
        }
    }
    
    // Sort meetings by time for each date
    foreach ($meetings as $date => &$dayMeetings) {
        usort($dayMeetings, function($a, $b) {
            return strtotime($a['time']) - strtotime($b['time']);
        });
    }
}

echo json_encode([
    'success' => true,
    'meetings' => $meetings,
    'month' => $month,
    'year' => $year
]);

$conn->close();
exit;
?>
