<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
clhelp( "PURPOSE: to make the chart and data for dependency of response time on the load");
clhelp( " NOTE: outputs load.pdf and load.json");
clhelp( " [n] (double) 0..1 knee in utilization  -- a utilization which is the knee (turning point) in graph");
clhelp( " [k] (double) coefficient for pre-knee shape of the curve");
clhelp( " [output] (root) full path to the output files (.pdf) (.json)");
htg( clget( 'n,k,output'));

echo "\n\n"; $x = array(); $y = array(); $h = array();
for ( $L = 0; $L < 0.99; $L += 0.01) {
	$R = 0.5 * ( ( $L - $n) + pow( pow( $L - $n, 2) + $k, 0.5) / ( 1 - $L));
	$R = round( $R, 3);
	lpush( $x, $L); lpush( $y, $R);
	$h[ "$L"] = $R;
}
$FS = 22; $BS = 4.5; 
list( $C, $CS) = chartsplitpage( 'L', $FS, '1', '1', '0.15,0.15', '0.15:0.1:0.15:0.2'); $C2 = lshift( $CS);
$C2->train( $x, $y);
$C2->autoticks( null, null, 10, 10, 'xmin=0,ymin=0');
$C2->frame( 'PM load', 'VM response time (s)');
chartline( $C2, $x, $y);
chartscatter( $C2, $x, $y, 'circle', 3);
$C->train( ttl( '0,1'), ttl( '0,1')); chartext( $C, ttl( '0:20'), ttl( '1'), ttl( "n=$n k=$k"));
$C->dump( "$output.pdf"); 
jsondump( $h, "$output.json");
echo "OK\n";


?>