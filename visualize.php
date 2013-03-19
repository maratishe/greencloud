<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
clhelp( 'PURPOSE: to visualize migration decisions');
clhelp( 'NOTE: expects raw.bz64jsonl in this folder');
clhelp( '[skip] (double)(0..1) with what probability to skip data lines (sampling)');
clhelp( '[howmany] when to stop -- number of frames');
htg( clget( 'skip,howmany'));

$FS = 10; $FS2 = 10; $FS3 = 11; $FSB = 40; $FSL = 20; $BS = 5;
class MyChartFactory extends ChartFactory { public function make( $C, $margins) { return new ChartLP( $C->setup, $C->plot, $margins);}}
$S = new ChartSetupStyle(); $S->style = 'D'; $S->lw = 0.1; $S->draw = '#000'; $S->fill = null;
$SW = clone $S; $SW->draw = '#fff';
$SNORMAL = clone $S; $SNORMAL->style = 'F'; $SNORMAL->lw = 0; $SNORMAL->draw = null; $SNORMAL->fill = '#888'; $SNORMAL->alpha = 1.0;
$SNORMAL2 = clone $SNORMAL; $SNORMAL2->fill = '#333'; //$SNORMAL2->alpha = 0.5;
$SOLD = clone $SNORMAL; $SOLD->fill = '#7BF'; $SOLD->alpha = 1.0;
$SOLD2 = clone $SOLD; $SOLD2->fill = '#28F';
$SSPECIAL = clone $SNORMAL; $SSPECIAL->fill = '#FAA'; $SSPECIAL->alpha = 1.0; // $SSPECIAL->alpha = 0.5;
$SSPECIAL2 = clone $SSPECIAL; $SSPECIAL2->fill = '#f00';


`rm -Rf visualization.*`;
echo "\n\n"; $e = echoeinit(); $e2 = echoeinit(); $in = finopen( 'raw.bz64jsonl'); $count = 0; $good = 0; $bad = 0; $gcount = 0;
function hash2text( $h) { if ( ! $h || ! is_array( $h) || ! count( $h)) return ''; $L = array(); foreach( $h as $k => $v) lpush( $L, "$k(" . round( $v, 2) . ")"); return ltt( $L, ' '); }
function pm2xy( $pm) { return lth( array( 0.1 * ( $pm  - 10 * ( int)( $pm / 10)), 0.1 * ( int)( $pm / 10)), ttl( 'x,y')); }
function makebox( $x, $y, $w = 0.035) {  // returns xys (chartshape)
	$xys = array();
	lpush( $xys, $x - $w); lpush( $xys, $y - $w);
	lpush( $xys, $x + $w); lpush( $xys, $y - $w);
	lpush( $xys, $x + $w); lpush( $xys, $y + $w);
	lpush( $xys, $x - $w); lpush( $xys, $y + $w);
	return $xys;
}
while ( ! findone( $in) && $good < $howmany) {
	`rm -Rf tempdf.*`;
	list( $h, $p) = finread( $in); if ( ! $h) continue; 
	//die( jsondump( $h, 'temp.json'));
	echoe( $e2, ''); echoe( $e, "reading $p($count)  good=$good,bad=$bad:  "); $count++;
	if ( mt_rand( 0, 9) < 10 * $skip) { $bad++; continue; }
	$good++;
	extract( $h); // setup, data
	extract( $setup); 	// unitload, pack, amax, w1, w2, w3, w4
	for ( $epoch = 0; $epoch < count( lfirst( hv( $data))); $epoch++) {
		echoe( $e2, "epoch($epoch)");
		list( $C, $CS, $CST) = chartlayout( new MyChartFactory(), 'P', '2x4', 15, '0.1:0.05:0.05:0.05');
		$C->train( ttl( '0,1'), ttl( '0,1'));
		chartext( $C, ttl( '0.5'), ttl( '1:10'), htt( compact( ttl( 'unitload,pack,amax,w1,w2,w3,w4'))), $S, $FS);
		chartext( $C, ttl( '0:10'), ttl( '1:5'), "$gcount/$epoch", $S, $FSB);
		$method2short = tth( 'Optimal=OPT,Conventional=CON,Brutal=BRU,Opportunistic=OPP,Minimal=MIN');
		$short2method = hvak( $method2short);
		foreach ( $data as $method => $data2) {
			if ( $method == 'OPT') continue;	// nothing here to draw
			// left chart
			$C2 = lshift( $CS); extract( $data2[ $epoch]); // fitness, solution, details, before, after, beforemap, aftermap
			$h = array(); foreach ( $solution as $h) foreach ( $h as $vm => $pm) if ( $pm !== null) $h[ $vm] = $pm; $solution = $h;
			$map2 = array(); foreach ( $solution as $vm => $pm) $map2[ $vm] = $beforemap[ $vm]; // before map
			$map3 = hvak( $map2, true, true);
			extract( $details); 	// raw, cooked
			$specials = array();
			$C2->train( ttl( '0,1'), ttl( '0,1'));
			$C2->autoticks( null, null, 10, 10);
			foreach ( $before as $pm => $util) if ( ! isset( $map3[ $pm]) && $util > 0) chartshape( $C2, makebox( htv( pm2xy( $pm), 'x'), htv( pm2xy( $pm), 'y'), 0.04), $SNORMAL);
			foreach ( $before as $pm => $util) if ( ! isset( $map3[ $pm]) && $util > 0)  chartshape( $C2, makebox( htv( pm2xy( $pm), 'x'), htv( pm2xy( $pm), 'y'), 0.04 * $util), $SNORMAL2);
			foreach ( $before as $pm => $util) if ( ! isset( $map3[ $pm]) && $util > 0) chartext( $C2, array( htv( pm2xy( $pm), 'x')), array( htv( pm2xy( $pm), 'y') . ':-0.3'), '' . round( $util, 1), $SW, $FS3);
			foreach ( $solution as $vm => $pm) { $pm = $beforemap[ $vm]; if ( isset( $before[ $pm])) chartshape( $C2, makebox( htv( pm2xy( $pm), 'x'), htv( pm2xy( $pm), 'y'), 0.04), $SSPECIAL); }
			foreach ( $solution as $vm => $pm) { $pm = $beforemap[ $vm]; if ( isset( $before[ $pm])) chartshape( $C2, makebox( htv( pm2xy( $pm), 'x'), htv( pm2xy( $pm), 'y'), 0.04 * $before[ $pm]), $SSPECIAL2); }
			foreach ( $solution as $vm => $pm) { $pm = $beforemap[ $vm]; if ( isset( $before[ $pm])) chartext( $C2, array( htv( pm2xy( $pm), 'x')), array( htv( pm2xy( $pm), 'y') . ':-0.3'), '' . round( $before[ $pm], 1), $S, $FS3); }
			chartext( $C2, ttl( '0:-3'), ttl( '1'), $short2method[$method]. ": before >> after", $S, $FSL, 'plotstring'); 
			// right chart
			$C2 = lshift( $CS);
			$C2->train( ttl( '0,1'), ttl( '0,1'));
			$C2->autoticks( null, null, 10, 10);
			$map2 = array(); foreach ( $solution as $vm => $pm) $map2[ $vm] = $aftermap[ $vm]; // after map
			$map3 = hvak( $map2, true, true);
			foreach ( $after as $pm => $util) if ( ! isset( $map3[ $pm]) && $util > 0) chartshape( $C2, makebox( htv( pm2xy( $pm), 'x'), htv( pm2xy( $pm), 'y'), 0.04), $SNORMAL);
			foreach ( $after as $pm => $util) if ( ! isset( $map3[ $pm]) && $util > 0)  chartshape( $C2, makebox( htv( pm2xy( $pm), 'x'), htv( pm2xy( $pm), 'y'), 0.04 * $util), $SNORMAL2);
			foreach ( $after as $pm => $util) if ( ! isset( $map3[ $pm]) && $util > 0) chartext( $C2, array( htv( pm2xy( $pm), 'x')), array( htv( pm2xy( $pm), 'y') . ':-0.3'), '' . round( $util, 1), $SW, $FS3);
			foreach ( $solution as $vm => $pm) { chartshape( $C2, makebox( htv( pm2xy( $pm), 'x'), htv( pm2xy( $pm), 'y'), 0.04), $SOLD); }
			foreach ( $solution as $vm => $pm) { chartshape( $C2, makebox( htv( pm2xy( $pm), 'x'), htv( pm2xy( $pm), 'y'), 0.04 * $util), $SOLD2); }
			foreach ( $solution as $vm => $pm) { chartext( $C2, array( htv( pm2xy( $pm), 'x')), array( htv( pm2xy( $pm), 'y') . ':-0.3'), '' . round( $after[ $pm], 1), $S, $FS3); }
			// legend
			$CL = new ChartLegendOR( $C2, -3, 8, 1);
			$CL->add( null, 3, 0.1, 'migrations: ' . count( $solution), $S);
			$CL->add( null, 3, 0.1, 'raw: ' . ( count( $solution) ? hash2text( $raw) : 'none'), $S);
			$CL->add( null, 3, 0.1, 'cooked: ' . ( count( $solution) ? hash2text( $cooked) : 'none'), $S);
			$CL->add( null, 3, 0.1, 'fitness: ' . round( $fitness, 4), $S);
			$CL->draw( true);
		}
		$C->dump( sprintf( 'tempdf.%04d.pdf', $count)); $count++;
	}
	//echoe( $e2, 'procpdftk...'); procpdftk( 'tempdf*', sprintf( 'tempdf2.%03d.pdf', $gcount)); 
	echoe( $e2, "procpdftk..."); procpdftk( 'tempdf*', sprintf( 'visualization.%03d.pdf', $gcount)); echo " OK\n";
	$gcount++;
}
finclose( $in); echo " DONE\n";


?>