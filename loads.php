<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
clhelp( "PURPOSE: runs load.php many times for a range of parameters for n and k");
clhelp( " [ndef] min:max:step   for  n");
clhelp( " [kdef] min:max:step   for  k");
htg( clget( 'ndef,kdef'));
extract( lth( ttl( $ndef, ':'), ttl( 'nmin,nmax,nstep')));
extract( lth( ttl( $kdef, ':'), ttl( 'kmin,kmax,kstep')));

$FS = 18; $BS = 3; `rm -Rf tempdf*`; `rm -Rf loads.pdf`;
list( $C, $CS) = chartsplitpage( 'L', $FS, '1', '1', '0.15,0.15', '0.15:0.1:0.15:0.2'); $C2 = lshift( $CS); 
$xs = array(); $ys = array(); $e = echoeinit(); $id = 1;
for ( $n = $nmin; $n <= $nmax; $n += $nstep) { for ( $k = $kmin; $k <= $kmax; $k += $kstep) { 
	echoe( $e, "n=$n,k=$k"); `rm -Rf load.pdf`; `rm -Rf load.json`;
	echopipee( "php load.php $n $k load"); if ( ! is_file( 'load.json') || ! is_file( 'load.pdf')) die( " FAILED!\n");
	$h = jsonload( 'load.json'); $x = hk( $h); $y = hv( $h);
	$C2->train( $x, $y); lpush( $xs, $x); lpush( $ys, $y);
	$file = sprintf( "tempdf.%05d", $id); $id++; `mv load.pdf $file`;
}}
echo " OK\n";
$C2->autoticks( null, null, 10, 10, 'xmin=0,ymin=0');
$C2->frame( 'PM load', 'VM response time (s)');
for ( $i = 0; $i < count( $xs); $i++) chartline( $C2, $xs[ $i], $ys[ $i]);
$C->dump( 'tempdf.00000.pdf');
echo "procpdftk..."; procpdftk( 'tempdf*', 'loads.pdf'); echo " OK\n";

?>