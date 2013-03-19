<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
clhelp( "PURPOSE: to create actrivity trend for all existing machines");
clhelp( " [count] how many machines to create");
clhelp( " [activitydef] coef:min:max   min and max activity  -- the range for final mapping");
clhelp( " [loadef] coef:min:max   min and max load per VM -- the range for distribution");
clhelp( " [root] root for output file (.pdf) (.json)");
htg( clget( 'count,activitydef,loadef,root'));
extract( lth( ttl( $activitydef, ':'), ttl( 'acoef,amin,amax')));
extract( lth( ttl( $loadef, ':'), ttl( 'lcoef,lmin,lmax')));

echo "\n\n";
// LAM map
$map = array(); for ( $i = 1; $i <= $count; $i++) lpush( $map, $i);
$map = mmap( $map, 0, 1, 4);
for ( $i = 0; $i < $count; $i++) $map[ $i] = exp( $acoef * $i);
//die( " map " . json_encode( $map) . "\n");
$amap = mmap( $map, $amin, $amax, 3);
rsort( $amap, SORT_NUMERIC);
// state map   LSM
$map = array(); for ( $i = 1; $i <= $count; $i++) lpush( $map, $i);
$map = mmap( $map, 0, 1, 4);
for ( $i = 0; $i < $count; $i++) $map[ $i] = exp( $lcoef * $i);
//die( " map " . json_encode( $map) . "\n");
$lmap = mmap( $map, $lmin, $lmax, 3);
rsort( $lmap, SORT_NUMERIC);


$FS = 18; $BS = 3;
$S = new ChartSetupStyle(); $S->fill = null; $S->draw = '#000'; $S->lw = 0.1;
list( $C, $CS) = chartsplitpage( 'L', $FS, '1', '1', '0.15,0.15', '0.15:0.1:0.15:0.2'); $C2 = $CS[ 0];
$C2->train( array( 0, $count, $count), array( 0, $amax, $lmax));
$C2->autoticks( null, null, 10, 10, 'xmin=0,ymin=0');
$C2->frame( 'Sequence of VMs', 'Value per VM');
chartline( $C2, hk( $amap), $amap, $S);
chartscatter( $C2, hk( $amap), $amap, 'circle', $BS, $S);
chartline( $C2, hk( $lmap), $lmap, $S);
chartscatter( $C2, hk( $lmap), $lmap, 'rect', $BS, $S);
$CL = new ChartLegendOR( $C2);
$CL->add( 'circle', 4, 0.1, 'Load volatility range', $S);
$CL->add( 'rect', 4, 0.1, 'Initial/average load', $S);
$CL->draw();
$C->train( ttl( '0,1'), ttl( '0,1')); chartext( $C, ttl( '0:20'), ttl( '1'), ttl( "load($loadef) range($activitydef)"), null, null, 'plotstringtl');
$C->dump( "$root.pdf");

$h = array();
for ( $i = 0; $i < count( $amap); $i++) {
	$load = round( $lmap[ $i], 2); $range = round( $amap[ $i], 2);
	if ( $load < 0) $load = 0; if ( $load > 0.98) $load = 0.98;
	lpush( $h, compact( ttl( 'load,range')));
}
jsondump( $h, "$root.json");
echo "OK\n";




?>