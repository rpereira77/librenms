<?php
/*
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 *
 * @package    LibreNMS
 * @subpackage graphs
 * @link       http://librenms.org
 * @copyright  2017 LibreNMS
 * @author     LibreNMS Contributors
*/

require 'includes/graphs/common.inc.php';

$stacked = generate_stacked_graphs();

$units_descr = substr(str_pad($units_descr, 18), 0, 18);

if ($format == 'octets' || $format == 'bytes') {
    $units = 'Bps';
    $format = 'bits';
} else {
    $units = 'bps';
    $format = 'bits';
}

$i = 0;
$rrd_options .= " COMMENT:'$units_descr Now       Avg      Max'";
if (!$nototal) {
    $rrd_options .= " COMMENT:'Total'";
}

$rrd_options .= " COMMENT:'\\n'";

foreach ($rrd_list as $rrd) {
    if (!$config['graph_colours'][$colours_in][$iter] || !$config['graph_colours'][$colours_out][$iter]) {
        $iter = 0;
    }

    $colour_in = $config['graph_colours'][$colours_in][$iter];
    $colour_out = $config['graph_colours'][$colours_out][$iter];

    if ($rrd['colour_area_in']) {
        $colour_in = $rrd['colour_area_in'];
    }

    if ($rrd['colour_area_out']) {
        $colour_out = $rrd['colour_area_out'];
    }

    $rrd_options .= ' DEF:inB' . $i . '=' . $rrd['filename'] . ':' . $rrd['ds_in'] . ':AVERAGE ';
    $rrd_options .= ' DEF:outB' . $i . '=' . $rrd['filename'] . ':' . $rrd['ds_out'] . ':AVERAGE ';
    $rrd_options .= ' CDEF:octets' . $i . '=inB' . $i . ',outB' . $i . ',+';
    $rrd_options .= ' CDEF:inbits' . $i . '=inB' . $i . ",$multiplier,* ";
    $rrd_options .= ' CDEF:outbits' . $i . '=outB' . $i . ",$multiplier,*";
    $rrd_options .= ' CDEF:outbits' . $i . '_neg=outbits' . $i . ',' . $stacked['stacked'] . ',*';
    $rrd_options .= ' CDEF:bits' . $i . '=inbits' . $i . ',outbits' . $i . ',+';

    if ($_GET['previous']) {
        $rrd_options .= ' DEF:inB' . $i . 'X=' . $rrd['filename'] . ':' . $ds_in . ':AVERAGE:start=' . $prev_from . ':end=' . $from;
        $rrd_options .= ' DEF:outB' . $i . 'X=' . $rrd['filename'] . ':' . $ds_out . ':AVERAGE:start=' . $prev_from . ':end=' . $from;
        $rrd_options .= ' SHIFT:inB' . $i . "X:$period";
        $rrd_options .= ' SHIFT:outB' . $i . "X:$period";
        $in_thingX .= $seperatorX . 'inB' . $i . 'X,UN,0,' . 'inB' . $i . 'X,IF';
        $out_thingX .= $seperatorX . 'outB' . $i . 'X,UN,0,' . 'outB' . $i . 'X,IF';
        $plusesX .= $plusX;
        $seperatorX = ',';
        $plusX = ',+';
    }

    if (!$args['nototal']) {
        $in_thing .= $seperator . 'inB' . $i . ',UN,0,' . 'inB' . $i . ',IF';
        $out_thing .= $seperator . 'outB' . $i . ',UN,0,' . 'outB' . $i . ',IF';
        $pluses .= $plus;
        $seperator = ',';
        $plus = ',+';

        $rrd_options .= ' VDEF:totinB' . $i . '=inB' . $i . ',TOTAL';
        $rrd_options .= ' VDEF:totoutB' . $i . '=outB' . $i . ',TOTAL';
        $rrd_options .= ' VDEF:tot' . $i . '=octets' . $i . ',TOTAL';
    }

    if ($i) {
        $stack = ':STACK';
    }

    $rrd_options .= ' AREA:inbits' . $i . '#' . $colour_in . $stacked['transparency'] . ":'" . rrdtool_escape($rrd['descr'], 9) . "In '$stack";
    $rrd_options .= ' GPRINT:inbits' . $i . ':LAST:%6.2lf%s';
    $rrd_options .= ' GPRINT:inbits' . $i . ':AVERAGE:%6.2lf%s';
    $rrd_options .= ' GPRINT:inbits' . $i . ':MAX:%6.2lf%s';

    if (!$nototal) {
        $rrd_options .= ' GPRINT:totinB' . $i . ":%6.2lf%s$total_units";
    }

    $rrd_options .= " COMMENT:'\\n'";
    $rrd_optionsb .= ' AREA:outbits' . $i . '_neg#' . $colour_out . $stacked['transparency'] . ":$stack";
    $rrd_options .= ' HRULE:999999999999999#' . $colour_out . ":'" . str_pad('', 10) . "Out'";
    $rrd_options .= ' GPRINT:outbits' . $i . ':LAST:%6.2lf%s';
    $rrd_options .= ' GPRINT:outbits' . $i . ':AVERAGE:%6.2lf%s';
    $rrd_options .= ' GPRINT:outbits' . $i . ':MAX:%6.2lf%s';

    if (!$nototal) {
        $rrd_options .= ' GPRINT:totoutB' . $i . ":%6.2lf%s$total_units";
    }

    $rrd_options .= " COMMENT:'\\n'";
    $i++;
    $iter++;
}


if ($_GET['previous'] == 'yes') {
    $rrd_options .= ' CDEF:inBX=' . $in_thingX . $plusesX;
    $rrd_options .= ' CDEF:outBX=' . $out_thingX . $plusesX;
    $rrd_options .= ' CDEF:octetsX=inBX,outBX,+';
    $rrd_options .= ' CDEF:doutBX=outBX,' . $stacked['stacked'] . ',*';
    $rrd_options .= ' CDEF:inbitsX=inBX,8,*';
    $rrd_options .= ' CDEF:outbitsX=outBX,8,*';
    $rrd_options .= ' CDEF:bitsX=inbitsX,outbitsX,+';
    $rrd_options .= ' CDEF:doutbitsX=doutBX,8,*';
    $rrd_options .= ' VDEF:percentile_inX=inbitsX,' . $config['percentile_value'] . ',PERCENT';
    $rrd_options .= ' VDEF:percentile_outX=outbitsX,' . $config['percentile_value'] . ',PERCENT';
    $rrd_options .= ' CDEF:dpercentile_outXn=doutbitsX,' . $stacked['stacked'] . ',*';
    $rrd_options .= ' VDEF:dpercentile_outX=dpercentile_outXn,' . $config['percentile_value'] . ',PERCENT';
    $rrd_options .= ' CDEF:dpercentile_outXn=doutbitsX,doutbitsX,-,dpercentile_outX,' . $stacked['stacked'] . ',*,+';
    $rrd_options .= ' VDEF:dpercentile_outX=dpercentile_outXn,FIRST';
}

if ($_GET['previous'] == 'yes') {
    $rrd_options .= ' AREA:in' . $format . 'X#99999999' . $stacked['transparency'] . ':';
    $rrd_optionsb .= ' AREA:dout' . $format . 'X#99999999' . $transparency . ':';
    $rrd_options .= ' LINE1.25:in' . $format . 'X#666666:';
    $rrd_optionsb .= ' LINE1.25:dout' . $format . 'X#666666:';
}

if (!$args['nototal']) {
    $rrd_options .= ' CDEF:inB=' . $in_thing . $pluses;
    $rrd_options .= ' CDEF:outB=' . $out_thing . $pluses;
    $rrd_options .= ' CDEF:octets=inB,outB,+';
    $rrd_options .= ' CDEF:doutB=outB,' . $stacked['stacked'] . ',*';
    $rrd_options .= ' CDEF:inbits=inB,8,*';
    $rrd_options .= ' CDEF:outbits=outB,8,*';
    $rrd_options .= ' CDEF:bits=inbits,outbits,+';
    $rrd_options .= ' CDEF:doutbits=doutB,8,*';
    $rrd_options .= ' VDEF:percentile_in=inbits,' . $config['percentile_value'] . ',PERCENT';
    $rrd_options .= ' VDEF:percentile_out=outbits,' . $config['percentile_value'] . ',PERCENT';
    $rrd_options .= ' CDEF:dpercentile_outn=doutbits,' . $stacked['stacked'] . ',*';
    $rrd_options .= ' VDEF:dpercentile_outnp=dpercentile_outn,' . $config['percentile_value'] . ',PERCENT';
    $rrd_options .= ' CDEF:dpercentile_outnpn=doutbits,doutbits,-,dpercentile_outnp,' . $stacked['stacked'] . ',*,+';
    $rrd_options .= ' VDEF:dpercentile_out=dpercentile_outnpn,FIRST';
    $rrd_options .= ' VDEF:totin=inB,TOTAL';
    $rrd_options .= ' VDEF:avein=inbits,AVERAGE';
    $rrd_options .= ' VDEF:totout=outB,TOTAL';
    $rrd_options .= ' VDEF:aveout=outbits,AVERAGE';
    $rrd_options .= ' VDEF:tot=octets,TOTAL';

    $rrd_options .= " COMMENT:' \\n'";

    $rrd_options .= " HRULE:999999999999999#FFFFFF:'" . str_pad('Total', 10) . "In '";
    $rrd_options .= ' GPRINT:inbits:LAST:%6.2lf%s';
    $rrd_options .= ' GPRINT:inbits:AVERAGE:%6.2lf%s';
    $rrd_options .= ' GPRINT:inbits:MAX:%6.2lf%s';
    $rrd_options .= " GPRINT:totin:%6.2lf%s$total_units";
    $rrd_options .= " COMMENT:'\\n'";

    $rrd_options .= " HRULE:999999999999990#FFFFFF:'" . str_pad('', 10) . "Out'";
    $rrd_options .= ' GPRINT:outbits:LAST:%6.2lf%s';
    $rrd_options .= ' GPRINT:outbits:AVERAGE:%6.2lf%s';
    $rrd_options .= ' GPRINT:outbits:MAX:%6.2lf%s';
    $rrd_options .= " GPRINT:totout:%6.2lf%s$total_units";
    $rrd_options .= " COMMENT:'\\n'";

    $rrd_options .= " HRULE:999999999999990#FFFFFF:'" . str_pad('', 10) . "Agg'";
    $rrd_options .= ' GPRINT:bits:LAST:%6.2lf%s';
    $rrd_options .= ' GPRINT:bits:AVERAGE:%6.2lf%s';
    $rrd_options .= ' GPRINT:bits:MAX:%6.2lf%s';
    $rrd_options .= " GPRINT:tot:%6.2lf%s$total_units";
    $rrd_options .= " COMMENT:'\\n'";
}

$rrd_options .= $rrd_optionsb;
$rrd_options .= ' HRULE:0#999999';
