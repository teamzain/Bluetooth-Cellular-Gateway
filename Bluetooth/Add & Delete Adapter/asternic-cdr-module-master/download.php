<?php
try {
    // Connect to the database
    $db = FreePBX::Database();

    // Query to fetch new calls from cdr table
    $fetchCallsSql = "SELECT SUM(duration) AS total_duration, COUNT(*) AS total_calls, src AS trunk_name FROM asteriskcdrdb.cdr WHERE calldate >= NOW() - INTERVAL 1 MINUTE GROUP BY src";
    $stmt = $db->query($fetchCallsSql);

    // Update trunk_balance table based on fetched calls
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $trunkName = $row['trunk_name'];
        $totalMinutes = isset($row['total_duration']) ? $row['total_duration'] : 0;
        $totalCalls = isset($row['total_calls']) ? $row['total_calls'] : 0;

        // Update trunk_balance table
        $updateDataSql = "UPDATE asteriskcdrdb.trunk_balance SET remaining_trunk_minutes = remaining_trunk_minutes - ?, remaining_calls = remaining_calls - ? WHERE trunk_name = ?";
        $updateStmt = $db->prepare($updateDataSql);
        $updateStmt->execute([$totalMinutes, $totalCalls, $trunkName]);
    }

    // Commit transactions
    $db->commit();
    
    // Close database connection
    $db = null;
} catch (PDOException $e) {
    // Handle PDO exceptions
    echo "Error: " . $e->getMessage();
}
?>
