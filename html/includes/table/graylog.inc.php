<?php
/*
 * LibreNMS
 *
 * Copyright (c) 2014 Neil Lathwood <https://github.com/laf/ http://www.lathwood.co.uk/fa>
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 *
 * @package    LibreNMS
 * @subpackage webui
 * @link       http://librenms.org
 * @copyright  2017 LibreNMS
 * @author     LibreNMS Contributors
*/

$filter_hostname = mres($_POST['hostname']);
$filter_range = mres($_POST['range']);

if (isset($searchPhrase) && !empty($searchPhrase)) {
    $query = 'message:"'.$searchPhrase.'"';
} else {
    $query = '*';
}

if (isset($current)) {
    $offset = ($current * $rowCount) - ($rowCount);
    $limit = $rowCount;
}

if ($rowCount != -1) {
    $extra_query = "&limit=$limit&offset=$offset";
}

if (!empty($filter_hostname)) {
    if (!empty($query)) {
        $query .= ' && ';
    }
    $ip = gethostbyname($filter_hostname);
    $device = device_by_name($filter_hostname);
    $query .= 'source:"'.$filter_hostname.'" || source:"'.$ip.'"';
    if (isset($device['ip']) && $ip != $device['ip']) {
        $query .= ' || source:"'.$device['ip'].'"';
    }
}

if (isset($config['graylog']['base_uri'])) {
    $graylog_base = $config['graylog']['base_uri'];
} elseif (version_compare($config['graylog']['version'], '2.1', '>=')) {
    $graylog_base = '/api/search/universal/relative';
} else {
    $graylog_base = '/search/universal/relative';
}

$graylog_url = $config['graylog']['server'] . ':' . $config['graylog']['port'] . $graylog_base . '?query=' . urlencode($query) . '&range='. $filter_range . $extra_query;

$context = stream_context_create(array(
    'http' => array(
        'header'  => "Authorization: Basic " . base64_encode($config['graylog']['username'].':'.$config['graylog']['password']) . "\r\n" .
                     "Accept: application/json",
    )
));

$messages = json_decode(file_get_contents($graylog_url, false, $context), true);

foreach ($messages['messages'] as $message) {
    if (isset($config['graylog']['timezone'])) {
        $userTimezone = new DateTimeZone($config['graylog']['timezone']);
        $graylogTime = new DateTime($message['message']['timestamp']);
        $offset = $userTimezone->getOffset($graylogTime);

        $timeInterval = DateInterval::createFromDateString((string)$offset . 'seconds');
        $graylogTime->add($timeInterval);
        $displayTime = $graylogTime->format('Y-m-d H:i:s');
    } else {
        $displayTime = $message['message']['timestamp'];
    }


    $response[] = array(
                      'rowLabel' => graylog_severity_label($message['message']['level']),
                      'timestamp' => $displayTime,
                      'source'    => '<a href="'.generate_url(array('page'=>'device', 'device'=>$message['message']['source'])).'">'.$message['message']['source'].'</a>',
                      'message'    => $message['message']['message'],
                      'facility'  => $message['message']['facility'],
                      'level'     => $message['message']['level'],
    );
}

if (empty($messages['total_results'])) {
    $total = 0;
} else {
    $total = $messages['total_results'];
}

$output = array('current'=>$current,'rowCount'=>$rowCount,'rows'=>$response,'total'=>$total);
echo _json_encode($output);
