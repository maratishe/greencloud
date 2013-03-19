<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
clhelp( "PURPOSE: to visualize what actually happens in conventional and proposed methods");
//htg( clget( ''));



// final chart
$FS = 18; $BS = 3; 
list( $C, $CS) = chartsplitpage( 'L', $FS, '1', '0.5,0.5', '0.15,0.15', '0.15:0.1:0.15:0.2');
$C2 = $CS[ 0]; $C3 = $CS[ 1];
$x1 = array(); $x2 = array();
foreach ( $D as $time => $h) { foreach ( $h as $method => $h2) { 
	extract( $h2); // unused, count
	$C2->train( array( $time), array( $unused));
	$C3->train( array( $time), array( $count));
	htouch( $x1, $method); $x1[ $method][ $time] = $unused;
	htouch( $x2, $method); $x2[ $method][ $time] = $count;
}}
$C2->autoticks( null, null, 6, 6, 'xmin=0,ymin=0');
$C2->frame( 'Time', 'Free PM count');
$C3->autoticks( null, null, 6, 6, 'xmin=0,ymin=0');
$C3->frame( 'Time', 'Migration count');
$CL2 = new ChartLegendOR( $C2);
$CL3 = new ChartLegendOR( $C3);
$bullets = tth( "conventional=circle,proposed=rect");
$CLS = lth( array( $CL2, $CL3), hk( $bullets));
$S = new ChartSetupStyle(); $S->lw = 0.1; $S->draw = '#000'; $S->fill = null; $S->alpha = 1.0;
foreach ( $bullets as $method => $bullet) {
	$x = hk( $x1[ $method]); $y = $x1[ $method];
	chartline( $C2, $x, $y, $S);
	chartscatter( $C2, $x, $y, $bullet, $BS, $S);
}
foreach ( $bullets as $method => $bullet) {
	$x = hk( $x2[ $method]); $y = $x2[ $method];
	chartline( $C3, $x, $y, $S);
	chartscatter( $C3, $x, $y, $bullet, $BS, $S);
}
foreach ( $bullets as $method => $bullet) $CL2->add( $bullet, 4, 0.1, $method, $S);
foreach ( $bullets as $method => $bullet) $CL3->add( $bullet, 4, 0.1, $method, $S);
$CL2->draw(); $CL3->draw();
$C->dump( "$watch.d.pdf");


?>