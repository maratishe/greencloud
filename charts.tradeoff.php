<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
//clhelp( '');
//htg( clget( ''));


echo "\n\n"; $e = echoeinit(); $in = finopen( 'data.bz64jsonl'); 
$H = array(); // [ { method: { vmloss, freepms, migrations}, ... ] 
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
		//if ( $method == 'OPT') 
		$H2[ $method] = compact( ttl( 'vmloss,freepms,migrations'));
	}
	lpush( $H, $H2);
}
finclose( $in); echo " OK\n";
jsondump( $H, 'temp.json');


$FS = 20; $BS = 5; 
class MyChartFactory extends ChartFactory { public function make( $C, $margins) { return new ChartLP( $C->setup, $C->plot, $margins);}}
$S = new ChartSetupStyle(); $S->style = 'D'; $S->lw = 0.1; $S->draw = '#000'; $S->fill = null;
$bullets = lth( ttl( 'plus,cross,circle,rect,triangle'), ttl( 'OPT,CON,BRU,OPP,MIN'));
$tags = lth( ttl( 'Optimal,Conventional,Brutal (proposed),Opportunistic (proposed),Miminal (proposed)'), ttl( 'OPT,CON,BRU,OPP,MIN'));
`rm -Rf tempdf*`; `rm -Rf report.tradeoff.pdf`;
for ( $plot = 0; $plot < 10; $plot++) {
	echoe( $e, "plot $plot");
	shuffle( $H); 
	$H2 = array(); for ( $i = 0; $i < 50; $i++) lpush( $H2, $H[ $i]);
	$H3 = array(); foreach ( $H2 as $h1) foreach ( $h1 as $m => $h) { $h[ 'method'] = $m; lpush( $H3, $h); }
	//die( jsondump( $H3, 'temp2.json'));
	list( $C, $CS, $CST) = chartlayout( new MyChartFactory(), 'L', '2x1', 25, '0.2:0.1:0.2:0.15');
	// VM loss VS free PMs
	$C2 = lshift( $CS); 
	$C2->train( hltl( $H3, 'vmloss'), hltl( $H3, 'freepms'));
	$C2->autoticks( null, null, 6, 6);
	$C2->frame( 'VM drop count', 'Free PM count');
	foreach ( $tags as $m => $method) chartscatter( $C2, hltl( hlf( $H3, 'method', $m), 'vmloss'), hltl( hlf( $H3, 'method', $m), 'freepms'), $bullets[ $m], $BS, $S);
	$CL = new ChartLegendOR( $C2);
	foreach ( $bullets as $m => $b) $CL->add( $b, $BS, 0.3, $tags[ $m], $S);
	$CL->draw();
	// VM loss VS free PMs
	$C2 = lshift( $CS); 
	$C2->train( hltl( $H3, 'migrations'), hltl( $H3, 'freepms'));
	$C2->autoticks( null, null, 6, 6);
	$C2->frame( 'Migration count', 'Free PM count');
	foreach ( $tags as $m => $method) chartscatter( $C2, hltl( hlf( $H3, 'method', $m), 'migrations'), hltl( hlf( $H3, 'method', $m), 'freepms'), $bullets[ $m], $BS, $S);
	$C->dump( sprintf( 'tempdf.%04d.pdf', $plot));
}
echo " OK\n";
echo "procpdftk..."; procpdftk( 'tempdf*', 'report.tradeoff.pdf'); echo " OK\n";


?>