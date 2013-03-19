<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
//clhelp( '');
//htg( clget( ''));


echo "\n\n"; $e = echoeinit(); $in = finopen( 'data.bz64jsonl'); 
$H = array(); // [ { method, m1, m2, m3, m4, fitness}, ...]
while ( ! findone( $in)) { 
	list( $h, $p) = finread( $in); if ( ! $h) continue; echoe( $e, "reading $p > " . count( $H));
	//die( jsondump( $h, 'temp2.json'));
	extract( $h); // w1, w2, w3, w4
	//die( " w1=$w1,w2=$w2,w3=$w3,w4=$w4\n");
	foreach ( ttl( 'CON,BRU,OPP,MIN') as $method) {
		$v = $$method; 
		foreach ( $v as $h) {
			unset( $Mcooked); extract( $h); if ( ! isset( $Mcooked)) continue;
			//die( " h:" . json_encode( $h) . "\n");
			extract( tth( $Mcooked)); $h = compact( ttl( 'method,fitness'));
			for ( $i = 1; $i < 5; $i++) { $k = "M$i"; $v = $$k; $k = "w$i"; $v2 = $$k; $h[ "M$i"] = $v * $v2; }
			//die( " h:" . json_encode( $h) . "\n");
			lpush( $H, $h);
		}
		
	}
	//echo " done \n";
}
finclose( $in); echo " OK\n";
jsondump( $H, 'temp.json');

$CC = array();
$cases = lth( ttl( 'M1:M2:M3,M1:M2:M3,M1:M2:M3:M4,M1:M2:M3:M4'), ttl( 'CON,BRU,OPP,MIN'));
foreach ( $cases as $m => $case) { 
	$L2 = array();
	foreach ( ttl( $case, ':') as $letter) {
		//die( jsondump( hlf( $H, 'method', $m), 'temp.json'));
		$one = hltl( hlf( $H, 'method', $m), 'fitness'); 
		$two = hltl( hlf( $H, 'method', $m), $letter);
		$ccf = @Rccfsimple( $one, $two); if ( ! $ccf) $ccf = 0;
		echoe( $e, "$m  $case    $letter > $ccf");
 		lpush( $L2, $ccf);
	}
	$CC[ $m] = $L2;
}
echo " OK\n";

$FS = 22; $BS = 5; 
class MyChartFactory extends ChartFactory { public function make( $C, $margins) { return new ChartLP( $C->setup, $C->plot, $margins);}}
$S = new ChartSetupStyle(); $S->style = 'D'; $S->lw = 0.1; $S->draw = '#000'; $S->fill = null;
$S2 = clone $S; $S2->lw = 0.5;
list( $C, $CS, $CST) = chartlayout( new MyChartFactory(), 'L', '1x1', 25, '0.15:0.15:0.15:0.2');
$C2 = lshift( $CS);
foreach ( $CC as $m => $h) { $C2->train( hk( $h), $h); }
$C2->autoticks( null, null, 10, 10);
$C2->frame( ttl( 'Term 1,Term 2,Term 3,Term 4'), 'CCF');
$bullets = lth( ttl( 'cross,circle,rect,triangle'), ttl( 'CON,BRU,OPP,MIN'));
$tags = lth( ttl( 'Conventional,Brutal (proposed),Opportunistic (proposed),Miminal (proposed)'), ttl( 'CON,BRU,OPP,MIN'));
foreach ( $CC as $m => $h) chartscatter( $C2, hk( $h), hv( $h), $bullets[ $m], $BS, $S2);
foreach ( $CC as $m => $h) if ( count( $h) > 1) chartline( $C2, hk( $h), hv( $h), $S2);
chartline( $C2, ttl( '-0.2,3.2'), ttl( '0,0'), $S);
$CL = new ChartLegendOR( $C2);
foreach ( $bullets as $m => $b) $CL->add( $b, $BS, 0.3, $tags[ $m], $S2);
$CL->draw();
$C->dump( 'report.ccf.pdf');




?>