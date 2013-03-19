<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
clhelp( "PURPOSE: to be run by CORES/runs");
htg( clget( 'core,wdir,watch'));

class MyGA extends GA {	// generic class used by all the particular methods 
	public $DC;
	public $movelist; 	// list of migrating vms
	public $lastfitness = null;
	public function init( $DC, $movelist) { $this->DC = $DC; $this->movelist = $movelist; }
	public function gene2hash( $g, $keepnull = true) {	// { vm > pm | null} for each vm 
		$h = array();
		foreach ( $g as $c) foreach ( $c as $vm =>$to) $h[ $vm] = $to;
		if ( $keepnull) return $h;
		$h2 = array(); foreach ( $h as $k => $v) if ( $v !== null) $h2[ $k] = $v;
		return $h2;
	}
	public function dryrun( $g) { // returns [ PM2VM, VM2PM]
		$PM2VM = $this->DC->PM2VM;
		$VM2PM = $this->DC->VM2PM;
		foreach ( $g as $c) { foreach ( $c as $vm => $to) {
			$from = $VM2PM[ $vm][ 'pm'];
			if ( $from !== null) unset( $PM2VM[ $from][ $vm]);
			$VM2PM[ $vm][ 'pm'] = null;
			if ( $to === null) continue;
			htouch( $PM2VM, $to);
			$PM2VM[ $to][ $vm] = true;
			$VM2PM[ $vm][ 'pm'] = $to;
		}}
		return array( $PM2VM, $VM2PM);
	}
	public function prefitness( $g) { 
		list( $PM2VM, $VM2PM) = $this->dryrun( $g);
		$info1 = $this->gene2hash( $g); // { vm: pm} -- keep null
		$info2 = $this->gene2hash( $g, false);	// no nulls
		$pmstats = $this->DC->pmstats( null, null, null, true);
		$vmstats = $this->DC->vmstats( hk( $info2)); // { vm: util, ...}
		$M1 = round( mvar( hv( $pmstats)), 3);
		$M2 = round( msum( hv( $info1)), 3);
		$M3 = count( $info1) - count( $info2);	// how many mirgations are to null places -- rejected
		$count = 0; foreach ( $PM2VM as $pm => $h) if ( ! count( $h)) $count++;
		$M4 = round( $count / count( $PM2VM), 3);
		$this->DC->updatemargins( $M1, $M2, $M3, $M4);
		$raw = compact( ttl( 'M1,M2,M3,M4'));
		// scale
		$M1 = $this->DC->scale( 'M1', $M1);
		$M2 = $this->DC->scale( 'M2', $M2);
		$M3 = $this->DC->scale( 'M3', $M3);
		$M4 = $this->DC->scale( 'M4', $M4);
		$R = 0.001 * mt_rand( 1, 9);
		$cooked = compact( ttl( 'M1,M2,M3,M4'));
		$this->lastfitness = compact( ttl( 'raw,cooked'));
		return array( $M1, $M2, $M3, $M4, $R);
	}
	// GA interface
	public function fitness( $g) { // default fitness, used by all proposed methods 
		list( $M1, $M2, $M3, $M4, $R) = $this->prefitness( $g);
		// calculate fitness
		$E = $this->DC->w1 * $M1 - $this->DC->w2 * $M2 - $this->DC->w3 * $M3 - $this->DC->w4 * $M4 + $R;
		return $E;
	}
	public function isvalid( $g) { 
		list( $PM2VM, $VM2PM) = $this->dryrun( $g);
		$stats = $this->DC->pmstats( $PM2VM, $VM2PM);
		if ( round( mmax( hv( $stats)), 1) > $this->DC->maxpmload) return false;
		return true;
	}
	public function makechromosome( &$g, $pos, $new = false) {	// rewritten in OptimalGA method, the other two use this one 
		$vm = $this->movelist[ $pos];
		//$g[ $pos] = array( $vm => null);	// first, remove current mapping if any
		list( $PM2VM, $VM2PM) = $this->dryrun( $g);
		$vm = $this->movelist[ $pos]; 
		extract( $VM2PM[ $vm]); $from = $pm;	// pm, baseutil, util, up, ups
		$stats = $this->DC->pmstats( $PM2VM, $VM2PM);
		$goodto = null;
		$limit = 100; while ( $limit--) {
			$to = lr( hk( $PM2VM)); if ( $to == $from) continue;	// cannot move to the same machine
			if ( $stats[ $to] + $util > $this->DC->maxpmload) continue;	// failed to find the mapping
			$goodto = $to;
			break;
		}
		$g[ $pos] = array( $vm => $goodto);
	}
	public function generationreport( $gen, $evals) { }
}
class OptimalGA extends MyGA { // rewrites makechromosome, fitness( M1)
	public $state = null;
	public function makechromosome( &$g, $pos, $new = false) {	// rewritten in OptimalGA method, the other two use this one 
		$vm = $this->movelist[ $pos];
		//$g[ $pos] = array( $vm => null);	// first, remove current mapping if any
		list( $PM2VM, $VM2PM) = $this->dryrun( $g);
		$vm = $this->movelist[ $pos]; 
		extract( $VM2PM[ $vm]); $from = $pm; 
		$stats = $this->DC->pmstats( $PM2VM, $VM2PM); asort( $stats, SORT_NUMERIC);
		list( $to, $util2) = hfirst( $stats); if ( $to === $from) hshift( $stats);
		list( $to, $util2) = hfirst( $stats); if ( round( $util2 + $util, 1) > $this->DC->maxpmload) $to = null; 
		$g[ $pos] = array( $vm => $to);
	}
	public function fitness( $g) {
		list( $M1, $M2, $M3, $M4, $R) = $this->prefitness( $g);
		// calculate fitness
		$E = $this->DC->w1 * $M1 + $R;
		//die( " fitness($E)\n");
		return $E;
	}
	
}
class ConventionalGA extends MyGA { public function fitness( $g) {  // fitness( M1-M3)
	list( $M1, $M2, $M3, $M4, $R) = $this->prefitness( $g);
	// calculate fitness
	$E = $this->DC->w1 * $M1 - $this->DC->w2 * $M2 - $this->DC->w3 * $M3 + $R;
	return $E;
}}
class BrutalGA extends MyGA { public function makechromosome( &$g, $pos, $new = false) { 
	$vm = $this->movelist[ $pos];
	$g[ $pos] = array( $vm => null);	// first, remove current mapping if any
	list( $PM2VM, $VM2PM) = $this->dryrun( $g);
	$vm = $this->movelist[ $pos]; 
	extract( $VM2PM[ $vm]); $from = $pm;	// pm, baseutil, util, up, ups
	$stats = $this->DC->pmstats( $PM2VM, $VM2PM);
	$goodto = null;
	$limit = 100; while ( $limit--) {
		$to = lr( hk( $PM2VM)); 
		if ( $to == $from) continue;	// cannot move to the same machine
		if ( $stats[ $to] === 0) continue;	// try not to map to an empty machine
		if ( $stats[ $to] + $util > $this->DC->maxpmload) continue;	// failed to find the mapping
		$goodto = $to;
		break;
	}
	$g[ $pos] = array( $vm => $goodto);
}}
class OpportunisticGA extends MyGA { public function makechromosome( &$g, $pos, $new = false) { 
	$vm = $this->movelist[ $pos];
	$g[ $pos] = array( $vm => null);	// first, remove current mapping if any
	list( $PM2VM, $VM2PM) = $this->dryrun( $g);
	$vm = $this->movelist[ $pos]; 
	extract( $VM2PM[ $vm]); $from = $pm;	// pm, baseutil, util, up, ups
	$stats = $this->DC->pmstats( $PM2VM, $VM2PM);
	$goodto = null;
	$limit = 100; while ( $limit--) {
		$to = lr( hk( $PM2VM)); 
		if ( $to == $from) continue;	// cannot move to the same machine
		if ( $stats[ $to] === 0) continue;	// try not to map to an empty machine
		if ( $stats[ $to] + $util > $this->DC->maxpmload) continue;	// failed to find the mapping
		$goodto = $to;
		break;
	}
	$g[ $pos] = array( $vm => $goodto);
}}
class MinimalGA extends MyGA { }	// both makechromosome() and fitness() are in the base class
class DataCenter {
	public $movelist;
	public $maxpmload = 0.8;	// maximum PM load
	public $unitload; 
	public $LM;
	public $AM;
	public $PM2VM = array(); 	// { pm: { vm: TRUE, ...}, ...}
	public $VM2PM = array(); // { vm: { baseutil, util, up, ups}, ...}
	public $margins = array(); // { M1: { min, max}, ...}
	public $w1, $w2, $w3, $w4;
	// used by subclasses
	public function __construct( $unitload, $LM, $AM, $pack, $w1, $w2, $w3, $w4) {
		$this->unitload = $unitload;
		$this->LM = $LM;
		$this->AM = $AM;
		$this->w1 = $w1; $this->w2 = $w2; $this->w3 = $w3; $this->w4 = $w4;
		$ups = Rdist( 'rgeom( 1000, 1 / 10)');
		$map = array(); for ( $pm = 0; $pm < 100; $pm++) $map[ $pm] = 0;
		$count = round( $pack * ( 1 / $unitload) * 100);	// number of vms
		for ( $vm = 0; $vm < $count; $vm++) {
			asort( $map, SORT_NUMERIC); list( $pm, $util) = hfirst( $map);
			//echo " $vm > $pm ($util)\n";
			$pos = mt_rand( 0, count( $ups) - 101);
			$ups2 = array(); for ( $i = $pos; $i < $pos + 100; $i++) lpush( $ups2, $ups[ $i]);
			lpush( $ups2, lfirst( $ups2));
			$userh = lth( array( $unitload, $unitload, round( htv( lr( $AM), 'range'), 1), $ups2), ttl( 'baseutil,util,up,ups'));
			//die( " userh:" . json_encode( $userh) . "\n");
			$this->map( $vm, $pm, $userh);
			$map[ $pm] += $unitload;
		}
		//die( ' VMs: ' . json_encode( $this->VM2PM) . "\n");
	}
	public function map( $vm, $pm, $h) { // h: { baseutil, util, up, ups} 
		htouch( $this->PM2VM, $pm);
		htouch( $this->VM2PM, $vm);
		$this->PM2VM[ $pm][ $vm] = true;
		$this->VM2PM[ $vm] = hm( $h, tth( "pm=$pm"));
	} 
	public function migrate( $vm, $frompm, $topm) { $this->VM2PM[ $vm][ 'pm'] = $topm; }
	public function next() { foreach ( $this->VM2PM as $vm => $pm) { 
		$h =& $this->VM2PM[ $vm]; extract( $h); // pm,baseutil, util, up, ups
		//die( " vm[$vm] pm[$pm] h:" . json_encode( $h) . "\n");
		//die( " up($up) \n");
		$ups[ 0]--; 
		if ( ! $ups[ 0]) {  $util = round( $baseutil + $up * $baseutil, 2); lshift( $ups); lpush( $ups, lfirst( $ups)); }
		else $util = $baseutil;
		$h = compact( ttl( 'pm,baseutil,util,up,ups'));
		if ( ! isset( $h[ 'pm'])) $h[ 'pm'] = null;	// null assignment
	}}
	public function stats( $PM2VM = null, $VM2PM = null) { // returns [ badpms: { pm: util, ...}, badvms: { vm: util, ...}]
		if ( ! $VM2PM) $VM2PM = $this->VM2PM;
		$pms = array(); 
		foreach ( $PM2VM ? $PM2VM : $this->PM2VM as $pm => $h) {
			$util = msum( hltl( hlf( hv( $VM2PM), 'pm', $pm), 'util'));
			if ( round( $util, 1) > $this->maxpmload) $pms[ $pm] = $util;
		}
		$vms = array();
		foreach ( $VM2PM as $vm => $h) { extract( $h); if ( $util > $baseutil) $vms[ $vm] = round( $util / $baseutil, 2); }
		return array( $pms, $vms);
	}
	public function pmstats( $PM2VM = null, $VM2PM = null, $thre = null, $asresponsetime = false) {
		$h = array(); 
		if ( ! $PM2VM) $PM2VM = $this->PM2VM;
		if ( ! $VM2PM) $VM2PM = $this->VM2PM;
		foreach ( $PM2VM as $pm => $h2) {
			$h3 = array(); foreach ( $h2 as $vm => $v) $h3[ $vm] = $VM2PM[ $vm][ 'util'];
			$v = msum( hv( $h3));
			if ( $thre === null) { $h[ $pm] = $v; continue; }
			if ( $v > $thre) continue;
			$h[ $pm] = $v;
		}
		if ( ! $asresponsetime) return $h;
		$LM = $this->LM;
		//die( " LM:" . json_encode( $LM) . "\n");
		$h2 = array();
		foreach ( $h as $k => $v) {
			if ( $v > 0.98) $v = 0.98;
			if ( $v < 0) $v = 0;
			$h2[ $k] = $LM[ '' . round( $v, 2)];
		}
		return $h2;
	}
	public function vmstats( $vms = null) {
		if ( $vms === null) $vms = hk( $this->VM2PM);
		$h = array();
		foreach ( $this->VM2PM as $vm => $h2) if ( $this->VM2PM[ $vm][ 'pm'] !== null) $h[ $vm] = $this->VM2PM[ $vm][ 'util'];
		return $h;
	}
	public function vmap( $vms = null, $VM2PM = null) { 
		$h = array(); 
		if ( ! $VM2PM) $VM2PM = $this->VM2PM;
		if ( ! $vms) $vms = hk( $VM2PM);
		foreach ( $vms as $vm) $h[ $vm] = $VM2PM[ $vm][ 'pm'];
		return $h;
	}
	public function updatemargins( $M1, $M2 = null, $M3 = null, $M4 = null) {
		$margins =& $this->margins;
		htouch( $margins, 'M1');
		htouch( $margins[ 'M1'], 'max', $M1, false, true);
		htouch( $margins[ 'M1'], 'min', $M1, true, false);
		if ( $M2 === null) return;
		htouch( $margins, 'M2');
		htouch( $margins[ 'M2'], 'max', $M2, false, true);
		htouch( $margins[ 'M2'], 'min', $M2, true, false);
		if ( $M3 === null) return;
		htouch( $margins, 'M3');
		htouch( $margins[ 'M3'], 'max', $M3, false, true);
		htouch( $margins[ 'M3'], 'min', $M3, true, false);
		if ( $M4 === null) return;
		htouch( $margins, 'M4');
		htouch( $margins[ 'M4'], 'max', $M4, false, true);
		htouch( $margins[ 'M4'], 'min', $M4, true, false);
		return $margins;
	}
	public function scale( $k, $v) { extract( $this->margins[ $k]); return round( ( $v - $min) / ( $max === $min ? 1 : $max - $min), 4); }
	public function implement( $g) { $D = array(); foreach ( $g as $c) { foreach ( $c as $vm => $to) {
		$from = $this->VM2PM[ $vm][ 'pm'];
		if ( $from !== null) unset( $this->PM2VM[ $from][ $vm]);
		$this->VM2PM[ $vm][ 'pm'] = null;
		if ( $to === null) continue;
		$this->PM2VM[ $to][ $vm] = true;
		$this->VM2PM[ $vm][ 'pm'] = $to;
		lpush( $D, array( $vm => $to));
	}}; return $D; }
	
}


// setup
$unitload = round( 0.05 * mt_rand( 1, 5), 2);
$load2timeN = 0.5; 
$load2timeK = 0.1; 
$pms = 100;
$c = "php $wdir/load.php $load2timeN $load2timeK $watch.a"; echopipee( $c);
$LM = jsonload( "$watch.a.json"); `rm -Rf $watch.a.json`;
$acoef = 0.1; 
$amax = round( 0.5  *  mt_rand( 1, 4), 1); $amin = $amax - 0.02;
if ( $amax > 0) $pack = 0.7; // round( 0.1 * mt_rand( 4, 7), 1); 
if ( $amax > 0.5) $pack = 0.6;
if ( $amax > 1) $pack = 0.5;
if ( $amax > 1.5) $pack = 0.4;
$lcoef = 0.05; $lmin = 0.1; $lmax = 0.3;
$c = "php $wdir/activity.php 100 $acoef:$amin:$amax $lcoef:$lmin:$lmax $watch.b"; echopipee( $c);
$AM = jsonload( "$watch.b.json"); `rm -Rf $watch.b.json`;
$w1 = 3; 
$w2 = round( 0.5 * mt_rand( 1, 3), 1); 
$w3 = round( 0.5 * mt_rand( 1, 3), 1); 
$w4 = round( 0.5 * mt_rand( 1, 3), 1);
$epochs = 15;
$generations = 5;
$setup = compact( ttl( 'unitload,load2timeN,load2timeK,pms,LM,acoef,amin,amax,AM,pack,w1,w2,w3,w4,epochs,generations'));


`rm -Rf $watch.*`;
echo "\n\n"; $e = echoeinit(); $e2 = echoeinit(); 
jsondump( tth( 'progress=init'), $watch, true, true);
$DC = new DataCenter( $unitload, $LM, $AM, $pack, $w1, $w2, $w3, $w4);
$PM2VM = array(); $VM2PM = array();
$method2short = tth( 'Optimal=OPT,Conventional=CON,Brutal=BRU,Opportunistic=OPP,Minimal=MIN');
$DCS = array(); foreach ( $method2short as $method => $short) $DCS[ $short] = clone $DC;
$D = array(); 	// { method: [ { fitness, solution, details}, ...], ...}
function nextConventional() {
	global $DC, $PM2VM, $VM2PM;
	$one = array(); list( $pms, $vms) = $DC->stats();
	foreach ( $pms as $pm => $util) { 
		$h = array(); foreach ( $PM2VM[ $pm] as $vm => $v) $h[ $vm] = $VM2PM[ $vm][ 'util'];
		arsort( $h, SORT_NUMERIC);
		while ( count( $h) && msum( hv( $h)) > $DC->maxpmload) { 
			list( $vm, $util) =  hshift( $h);
			$one[ $vm] = $pm;
		}
		
	}
	$DC->movelist = hk( $one);
}
function nextBrutal() {	// move all VMs above their SLA
	global $DC, $PM2VM, $VM2PM;
	$one = array(); list( $pms, $vms) = $DC->stats();
	$DC->movelist = hk( $vms);	// move all VMs above their utility
}
function nextOpportunistic() { // move all VMs in machines over threshold
	global $DC, $PM2VM, $VM2PM;
	list( $pms, $vms) = $DC->stats();
	$L = array(); foreach ( $pms as $pm => $util) foreach ( $PM2VM[ $pm] as $vm => $v) $L[ $vm] = true;
	$DC->movelist = hk( $L);	// move all VMs above their utility
}
function nextMinimal() {	// same as in conventional, but fitness calculated differently
	global $DC, $PM2VM, $VM2PM;
	$one = array(); list( $pms, $vms) = $DC->stats();
	foreach ( $pms as $pm => $util) { 
		$h = array(); foreach ( $PM2VM[ $pm] as $vm => $v) $h[ $vm] = $VM2PM[ $vm][ 'util'];
		arsort( $h, SORT_NUMERIC);
		while ( count( $h) && msum( hv( $h)) > $DC->maxpmload) { 
			list( $vm, $util) =  hshift( $h);
			$one[ $vm] = $pm;
		}
		
	}
	$DC->movelist = hk( $one);
}
for ( $epoch = 0; $epoch < $epochs; $epoch++) { 	// optimal run
	$DC = $DCS[ 'OPT']; 
	echoe( $e2, ''); echoe( $e, "optimal($epoch) ");
	jsondump( tth( "progress=OPT($epoch)"), $watch, true, true);
	htouch( $D, 'OPT');
	$before = $DC->pmstats(); $beforemap = $DC->vmap();
	$DC->next();
	$map = array(); for ( $pm = 0; $pm < 100; $pm++) $map[ $pm] = 0;
	$DC->PM2VM = array();
	shuffle( $DC->VM2PM);
	foreach ( $DC->VM2PM as $vm => $h) {
		unset( $h); $h =& $DC->VM2PM[ $vm];
		asort( $map, SORT_NUMERIC); list( $pm, $util) = hfirst( $map);
		if ( $util > $DC->maxpmload) continue;	// could not map this one
		$h[ 'pm'] = $pm;
		htouch( $DC->PM2VM, $pm);
		$DC->PM2VM[ $pm][ $vm] = true;
	}
	unset( $h);
	$after = $DC->pmstats(); $aftermap = $DC->vmap();
	lpush( $D[ 'OPT'], lth( array( 0, array(), array(), $before, $beforemap, $after, $aftermap), ttl( 'fitness,solution,details,before,beforemap,after,aftermap')));
}
echo " OK\n"; unset( $method2short[ 'Optimal']);
foreach ( $method2short as $method => $short) { for ( $epoch = 0; $epoch < $epochs; $epoch++) {
	$DC = $DCS[ $short];
	$PM2VM =& $DC->PM2VM; $VM2PM =& $DC->VM2PM;
	$before = $DC->pmstats(); $beforemap = $DC->vmap();
	$DC->next();
	$after = $DC->pmstats(); $aftermap = $DC->vmap();
	$m = "next$method"; $m();	// DC now has movelist
	//die( " movelist:" . json_encode( $DC->movelist) . "\n");
	if ( ! count( $DC->movelist)) {	// empty move list
		htouch( $D, $short);
		lpush( $D[ $short], lth( array( 0, array(), array(), $before, $beforemap, $after, $aftermap), ttl( 'fitness,solution,details,before,beforemap,after,aftermap')));
		echo " nothing to do\n";
		continue;
	}
	list( $pms, $vms) = $DC->stats();
	$genecount = 100;
	//die( " pms:" . json_encode( $pms) . "\n");
	//die( " vms:" . json_encode( $vms) . "\n");
	echoe( $e2, ''); echoe( $e, "$method($epoch)  pms/vms(" . count( $pms). "/" . count( $vms) . ") genes($genecount):  ");
	jsondump( tth( "progress=$short($epoch)"), $watch, true, true);
	//echo "ML[" . ltt( $movelist) . "] ";
	//if ( ! count( $movelist)) continue;
	$method2 = $method . 'GA'; $GA = new $method2(); $GA->init( $DC, $DC->movelist);
	list( $genes, $evals) = $GA->optimize( $genecount, count( $GA->movelist), 0.5, 0.5, 0.3, 5, $generations, 4, 1);
	//die( " evals:" . json_encode( $evals) . "\n");
	list( $id, $eval) = hfirst( $evals);
	$details = $GA->lastfitness;
	$before = $DC->pmstats();
	$beforemap = $DC->vmap();
	$solution = $DC->implement( $genes[ $id]);
	$after = $DC->pmstats();
	$aftermap = $DC->vmap();
	list( $pmcheck, $vmcheck) = $DC->stats(); if ( count( $pmcheck)) die( " ERROR! pmcheck is not empty: " . json_encode( $pmcheck) . "\n");
	htouch( $D, $short);
	lpush( $D[ $short], lth( array( $eval, $solution, $details, $before, $beforemap, $after, $aftermap), ttl( 'fitness,solution,details,before,beforemap,after,aftermap')));
	echo "id[$id] eval[$eval] solution[" . json_encode( $solution) . "]\n";
}}
$data = $D; echo " OK\n";

// done, output the result
$sh = tth( 'progress=done');
$sh[ 'data'] = compact( ttl( 'setup,data'));
jsondump( $sh, $watch, true, true);
echo "ALL DONE\n";

?>