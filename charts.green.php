<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
//clhelp( '');
//htg( clget( ''));


echo "\n\n"; $e = echoeinit(); $in = finopen( 'data.bz64jsonl'); 
$H = array(); // { method: { x(amax+unitload): [ number of free machines], ...}, ...} 
while ( ! findone( $in)) { 
	list( $h, $p) = finread( $in); if ( ! $h) continue; echoe( $e, "reading $p");
	//die( jsondump( $h, 'temp2.json'));
	extract( $h); // unitload, amax
	$x = round( $amax + $unitload, 2);
	foreach ( ttl( 'CON,BRU,OPP,MIN') as $m) {
		$v = $$m; 
		extract( lpop( $v));	// last epoch
		htouch( $H, $m);
		htouch( $H[ $m], "$x");
		lpush( $H[ $m][ "$x"], $pmfreeafter);
	}
	
}
finclose( $in);

// process averages
foreach ( $H as $m => $h1) ksort( $H[ $m], SORT_NUMERIC);
foreach ( $H as $m => $h1) { foreach ( $H[ $m] as $x => $L) { $H[ $m][ "$x"] = round( mavg( $L), 1); }}
//die( jsondump( $H, 'temp.json'));

$FS = 20; $BS = 5; 
class MyChartFactory extends ChartFactory { public function make( $C, $margins) { return new ChartLP( $C->setup, $C->plot, $margins);}}
$S = new ChartSetupStyle(); $S->style = 'D'; $S->lw = 0.1; $S->draw = '#000'; $S->fill = null;
$S2 = clone $S; $S2->lw = 0.5;
list( $C, $CS, $CST) = chartlayout( new MyChartFactory(), 'L', '1x1', 25, '0.15:0.15:0.15:0.2');
$C2 = lshift( $CS); 
foreach ( $H as $m => $h) $C2->train( hk( $h), hv( $h));
$C2->autoticks( null, null, 10, 10);
$xticks = array(); for ( $x = 0.5; $x <= 2.0; $x += 0.5) $xticks[ "$x"] = "SLA violation($x).."; 
$C2->frame( $xticks, 'Free PM count');
$bullets = lth( ttl( 'cross,circle,rect,triangle'), hk( $H));
$tags = lth( ttl( 'Conventional,Brutal (proposed),Opportunistic (proposed),Miminal (proposed)'), hk( $H));
$ys = array(); foreach ( $H as $m => $h) foreach ( $h as $x => $y) lpush( $ys, $y);
for ( $x = 0.5; $x < 2.0; $x += 0.5) chartline( $C2, ttl( "$x,$x"), array( mmin( $ys), mmax( $ys)), $S);
foreach ( $H as $m => $h) chartline( $C2, hk( $h), hv( $h), $S2);
foreach ( $H as $m => $h) chartscatter( $C2, hk( $h), hv( $h), $bullets[ $m], $BS, $S2);
$CL = new ChartLegendTL( $C2);
foreach ( $bullets as $m => $b) $CL->add( $b, $BS, 0.3, $tags[ $m], $S2);
$CL->draw();
$C->dump( 'report.green.pdf');


?>