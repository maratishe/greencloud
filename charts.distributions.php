<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
//clhelp( '');
//htg( clget( ''));


echo "\n\n"; $e = echoeinit(); $in = finopen( 'data.bz64jsonl'); 
$H = array(); // [ { method: { vmloss, freepms, migrations}, ... }, ...] 
while ( ! findone( $in)) { 
	list( $h, $p) = finread( $in); if ( ! $h) continue; echoe( $e, "reading $p");
	//die( jsondump( $h, 'temp2.json'));
	extract( $h); // unitload, amax
	$H2 = array();
	foreach ( ttl( 'OPT,CON,BRU,OPP,MIN') as $method) {
		$v = $$method; 
		extract( llast( $v));	// last epoch
		$vmloss = $vmoffafter; if ( $vmloss < 0) $vmloss = 0;
		$freepms = $pmfreeafter;
		$migrations = msum( hltl( $v, 'migrations'));
		if ( $method == 'OPT') { 	// unique way to calculate migrations
			$migrations = 0;
			foreach ( $v as $h2) {
				//die( " beforemap[$beforemap]\n");
				//$beforemap = tth( $beforemap);
				//$aftermap = tth( $aftermap);
				foreach ( $beforemap as $vm => $before) if ( $aftermap[ $vm] != $before) $migrations++;
			}
			
		}
		$H2[ $method] = compact( ttl( 'vmloss,freepms,migrations'));
	}
	lpush( $H, $H2);
}
finclose( $in); echo " OK\n";
jsondump( $H, 'temp.json');


$H2 = array();
foreach ( $H as $h) { foreach ( $h as $m => $h2) { 
	extract( $h2);
	htouch( $H2, $m);
	lpush( $H2[ $m], $migrations);
}}
foreach ( $H2 as $m => $L) {
	rsort( $L, SORT_NUMERIC);
	$step = round( count( $L) / 50); $L2 = array();
	for ( $i = 0; $i < count( $L) - $step; $i += $step) {
		echoe( $e, "$m ($i)");
		$L3 = array();
		for ( $ii = $i; $ii < $i + $step; $ii++) lpush( $L3, $L[ $ii]);
		$v = mavg( $L3); $v2 = 0;
		if ( $v <= 1) $v2 = $v;
		if ( $v < 0) $v2 = 0;
		if ( $v > 1) $v2 = log10( $v);
		$v2 = round( $v2, 2);
		lpush( $L2, $v2);
	}
	rsort( $L2, SORT_NUMERIC); $H2[ $m] = $L2;
}

$FS = 22; $BS = 5; 
class MyChartFactory extends ChartFactory { public function make( $C, $margins) { return new ChartLP( $C->setup, $C->plot, $margins);}}
$S = new ChartSetupStyle(); $S->style = 'D'; $S->lw = 0.1; $S->draw = '#000'; $S->fill = null;
$S2 = clone $S; $S2->lw = 0.5;
list( $C, $CS, $CST) = chartlayout( new MyChartFactory(), 'L', '1x1', 25, '0.25:0.15:0.25:0.2');
$C2 = lshift( $CS);
foreach ( $H2 as $m => $h) { $C2->train( hk( $h), $h); }
$C2->autoticks( null, null, 10, 10);
$C2->frame( 'Distribution sequence', 'log( migrations)');
$bullets = lth( ttl( 'plus,cross,circle,rect,triangle'), ttl( 'OPT,CON,BRU,OPP,MIN'));
$tags = lth( ttl( 'Optimal,Conventional,Brutal (proposed),Opportunistic (proposed),Miminal (proposed)'), ttl( 'OPT,CON,BRU,OPP,MIN'));
foreach ( $H2 as $m => $h) chartscatter( $C2, hk( $h), hv( $h), $bullets[ $m], $BS, $S);
$CL = new ChartLegendOR( $C2);
foreach ( $bullets as $m => $b) $CL->add( $b, $BS, 0.3, $tags[ $m], $S2);
$CL->draw();
$C->dump( 'report.distributions.pdf');




?>