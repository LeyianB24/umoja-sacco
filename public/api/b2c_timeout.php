<?php
// Log timeout
file_put_contents(__DIR__ . '/../member/mpesa_error.log', "B2C TIMEOUT RECEIVED\n", FILE_APPEND);
echo json_encode(["ResultCode" => 0, "ResultDesc" => "Timeout Received"]);