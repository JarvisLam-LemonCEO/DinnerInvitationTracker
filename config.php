<?php
// config.php (InfinityFree)
// IMPORTANT: Use the exact MySQL details shown in your InfinityFree Control Panel -> MySQL Databases

$db_host = "sql112.infinityfree.com";      // e.g. sql112.infinityfree.com
$db_user = "if0_40691153";                // e.g. if0_40691153 (from panel)
$db_pass = "ReplacePasswordHere";         // set/reset in panel
$db_name = "if0_40691153_dinner";         // exact database name from panel

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset('utf8mb4');
