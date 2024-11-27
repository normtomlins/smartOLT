<?php

$token = getenv('SMARTOLT_TOKEN');
$domain = getenv('SMARTOLT_SUB_DOMAIN');
$nfty_server = getenv('NTFY_SERVER_URL');
$twillo_server = getenv('TWILLO_SERVER');

// OLT ID to friendly name mapping
$oltFriendlyNames = [
    '3' => 'BlackStock',
    '4' => 'Caesarea',
    '5' => 'Island Cabinet',
];

// Initialize a cURL session
$curl = curl_init();

// Set cURL options for getting ONUs statuses
curl_setopt_array($curl, [
    CURLOPT_URL => "https://$domain.smartolt.com/api/onu/get_onus_statuses",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => ["X-Token: $token"],
//    CURLOPT_SSL_VERIFYPEER => false,
//    CURLOPT_SSL_VERIFYHOST => false,
]);

// Execute the cURL request for getting ONUs statuses
$response = curl_exec($curl);
$err = curl_error($curl);

// Close the cURL session for getting ONUs statuses
curl_close($curl);

if ($err) {
    echo "cURL Error #:" . $err;
} else {
    $dataArray = json_decode($response, true);
//    var_dump($response);

    if (!isset($dataArray['response']) || !is_array($dataArray['response'])) {
        echo "Unexpected data format or no data received.";
        exit;
    }

    // Initialize a structure to hold counts per olt_id
    $countsByOltId = [];

    // Iterate through each item and accumulate counts per olt_id
    foreach ($dataArray['response'] as $item) {
        $oltId = $item['olt_id'];
        $status = $item['status'];

        // Initialize sub-array for this olt_id if not already set
        if (!isset($countsByOltId[$oltId])) {
            $countsByOltId[$oltId] = ['Online' => 0, 'Offline' => 0, 'Power fail' => 0, 'LOS' => 0, 'Total' => 0];
        }

        // Update status count for this olt_id
        if (isset($countsByOltId[$oltId][$status])) {
            $countsByOltId[$oltId][$status]++;
        }

        // Always increment the total count
        $countsByOltId[$oltId]['Total']++;
    }

    $sendAlert = false;
    $postData = "";
    // For demonstration, print counts by olt_id with friendly names
    echo "\n".date("Y-m-d H:i:s")."\n";

    foreach ($countsByOltId as $oltId => $counts) {
	$allowed_offline = round($counts['Total'] * 0.08,0);

	$friendlyName = isset($oltFriendlyNames[$oltId]) ? $oltFriendlyNames[$oltId] : "OLT ID $oltId";
        echo "   $friendlyName - Total: {$counts['Total']}, Allowed: {$allowed_offline}, Online: {$counts['Online']}, Offline: {$counts['Offline']}, Power Fail: {$counts['Power fail']}, LOS: {$counts['LOS']}\n";

        $postData .= "\n$friendlyName - Total: {$counts['Total']}, Online: {$counts['Online']}, Offline: {$counts['Offline']}, Power Fail: {$counts['Power fail']}, LOS: {$counts['LOS']}\n";



	// Send Alert
	if (($counts['Total'] - $allowed_offline) > $counts['Online']) {
		echo "Setting Alert to True\n";
		$sendAlert = True;
		$checkOLT = $friendlyName;
    		$postData .= "\nCheck OLT: ".$checkOLT ."\n";
		if ($counts['Power fail'] > $counts['LOS']) {
                        $reason = "Power Failure";
                } elseif ($counts['LOS'] > $counts['Power fail']) {
                        $reason = "Potential Fibre cut";
                } else {
                        $reason = "Unknown Reason";
                }
                break;
	}

    }


    // Add your notification logic here, if needed...
    // File to track last notification time
    $timeTrackerFile = 'last_notification_time.txt';
    $canNotify = false;

    if (file_exists($timeTrackerFile)) {
        $lastNotificationTime = file_get_contents($timeTrackerFile);
        if (time() - $lastNotificationTime >= 600) { // 3600 seconds = 1 hour
            $canNotify = true;
        }
    } else {
        $canNotify = true; // No record found, so we can notify
	$sendAlert = true;
    }

    // Send notification if the online count is less than 500 and it's been at least an hour since the last one
    //if (true) {
    if ($sendAlert && $canNotify) {
        // Initialize a cURL session for posting the notification
        $curlPost = curl_init();
        curl_setopt_array($curlPost, [
            CURLOPT_URL => "http://$nfty_server/server_down",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
	    //CURLOPT_VERBOSE => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                        "Content-Type: text/plain", // Ensure the Content-Type header is set to text/plain if the server expects plain text
                ],
                ]);

        // Execute the cURL request for posting the notification
        $postResponse = curl_exec($curlPost);
        $postErr = curl_error($curlPost);
        curl_close($curlPost);

        if (!$postErr) {
            echo "Notification sent successfully.\n";
            file_put_contents($timeTrackerFile, time()); // Update the time of the last notification
        } else {
            echo "Failed to send notification.\n";
        }

	// Call the Twillo Server

	$curl = curl_init();
	$postData = array();

        $postData = [
                'monitor_name' => $friendlyName,
                'monitor_status' => 'having some issues, please check on this O.L.T as a large number of O.N.U are offline.  It would appear to be a '.$reason
        ];

// Encode the data as a URL-encoded string
$urlEncodedData = http_build_query($postData);
    
// Set the cURL options
curl_setopt_array($curl, [
    CURLOPT_URL => $twillo_server,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => $urlEncodedData,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/x-www-form-urlencoded",
        "Content-Length: " . strlen($urlEncodedData)
    ],
]);


// Set cURL options for getting ONUs statuses
// Execute the cURL request for getting ONUs statuses

$response = curl_exec($curl);
$err = curl_error($curl);

// Close the cURL session for getting ONUs statuses
curl_close($curl);

if ($err) {
    echo "cURL Error #:" . $err;
}

}

}

