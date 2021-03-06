<?php
$name = 'bind';
$app_id = $app['app_id'];
$unit_text     = 'In Hash Table';
$colours       = 'psychedelic';
$dostack       = 0;
$printtotal    = 0;
$addarea       = 1;
$transparency  = 15;

$rrd_filename = rrd_name($device['hostname'], array('app', 'bind', $app['app_id'], 'adb'));

$rrd_list=array();
if (rrdtool_check_rrd_exists($rrd_filename)) {
    $rrd_list[]=array(
        'filename' => $rrd_filename,
        'descr'    => 'Addresses',
        'ds'       => 'aiht',
    );
    $rrd_list[]=array(
        'filename' => $rrd_filename,
        'descr'    => 'Names',
        'ds'       => 'niht',
    );
} else {
    d_echo('RRD "'.$rrd_filename.'" not found');
}

require 'includes/graphs/generic_multi_line.inc.php';
