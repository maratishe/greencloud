<?php

class MyGA extends GA {
	public $size;
	public $vmdist = array();
	public $pmdist = array(); 
	public $VM2PM;
	public $PMs;
	public $VMstats = array();
	public $PMstats = array();
	public function fitness( $g) { return 0; } // do not extend fitness -- will extend that in each individual class
	public function isvalid( &$g) { // prune migrations above the count
		$ks = hk( $g); lshift( $ks); lpop( $ks); $count = $g[ 0];
		$h = array(); foreach ( $ks as $k) if ( $g[ $k]) $h[ $k] = true;
		while ( count( $h) > $count) { $k = lr( hk( $h)); $g[ $k] = 0; unset( $h[ $k]); }
		return true;
	}
	public function makechromosome( &$g, $pos) { // backup, traditional method  
		if ( $pos == 0 && isset( $g[ $pos])) return; // do not change the setup
		if ( $pos == 0) {	// sample from the distribution
			$g = array();	// initialize... just in case
			// prepare distributions
			asort( $this->pmdist, SORT_NUMERIC);
			list( $count, $count2) = hfirst( $this->pmdist);
			$VMs = $this->VMstats; $PMs = $this->PMstats; $lost = 0;
			asort( $VMs, SORT_NUMERIC); arsort( $PMs, SORT_NUMERIC); 
			$migrations = array(); // { vm, load, from, to, order}
			for ( $i = 0; $i < $count && count( $migrations) < $this->size - 2; $i++) {
				$PMs2 = $PMs; foreach ( $PMs2 as $pm => $left) if ( $left == 1) unset( $PMs2[ $pm]);
				arsort( $PMs2, SORT_NUMERIC); list( $from, $left) = hfirst( $PMs2);
				$R =& $this->PMs[ $from][ 'VMs']; if ( ! count( $R)) continue;
				$map = array(); foreach ( $R as $vm => $h) $map[ $vm] = isset( $h[ 'curload']) ? $h[ 'curload'] : $h[ 'load'];
				arsort( $map, SORT_NUMERIC);
				foreach ( $map as $vm => $load) {
					if ( count( $migrations) >= $this->size - 2) break;	// too many migrations
					asort( $PMs, SORT_NUMERIC);
					foreach ( $PMs as $to => $left) {
						if ( $to != $from && $left > $load) break;
						$to = null;
					}
					if ( $to === null) continue;	// failed to allocate this one
					$order = count( $migrations);
					$PMs[ $from] -= $load;
					$PMs[ $to] += $load;
					lpush( $migrations, compact( ttl( 'vm,from,to,load,order')));
				}
				
			}
			//if ( count( $migrations)) echo " migrations(" . count( $migrations) . ")\n";
			$this->pmdist[ $count]++;	// increase the count
			while ( count( $migrations) < $this->size - 2) lpush( $migrations, 0);
			shuffle( $migrations);
			lpush( $g, compact( ttl( 'lost,count,VMs,PMs')));
			foreach ( $migrations as $migration) lpush( $g, $migration);
			lpush( $g, true);
			if ( count( $g) != $this->size) { jsondump( $g, 'gene.json'); die( " FILED to create good-sized gene! (" . count( $g) . " != " . $this->size . ") count=$count gene:" . json_encode( $g) . "\n"); }
			return;
		}
		if ( $pos > 0 && $pos < $this->size - 1 && ! isset( $g[ $this->size - 1])) return; // mid-pos of a new creation, do nothing
		if ( $pos > 0 && $pos < $this->size - 1 && $g[ $pos] == 0) return;	// no migration in this one
		if ( $pos == 0 || $pos == $this->size - 1) return;	// ignore -- repeated constaint
		// mutation, try to find new machine for this virtual machine 
		extract( $g[ $pos]); // vm, load, from, to, order
		extract( $g[ 0]); // PMs, VMs, lost, count
		$load = $VMs[ $vm];
		$pms = hk( $PMs); shuffle( $pms); 	// random selection
		while ( count( $pms)) {
			$pm = lshift( $pms);
			if ( $pm != $from && $pm != $to && $PMs[ $pm] > $load) break;
			$pm = null;
		}
		if ( $pm === null) return;	// failed to change PM for this migration
		$PMs[ $to] += $load; // cancel old migration 
		$to = $pm; 	// FROM remains the same, only TO is changed
		$g[ $pos] = compact( ttl( 'vm,load,from,to,order'));
		$PMs[ $pm] -= $load;	// to check later
		$g[ 0] = compact( hk( $g[ 0]));
		$g[ $pos] = compact( hk( $g[ $pos]));	
	}
	public function makechromosome2( &$g, $pos) { // backup, traditional method  
		if ( $pos == 0 && isset( $g[ $pos])) return; // do not change the setup
		if ( $pos == 0) {	// sample from the distribution
			$g = array();	// initialize... just in case
			// prepare distributions
			asort( $this->vmdist, SORT_NUMERIC);
			list( $count, $count2) = hfirst( $this->vmdist);
			$VMs = $this->VMstats; $PMs = $this->PMstats; $lost = 0;
			asort( $VMs, SORT_NUMERIC); asort( $PMs, SORT_NUMERIC); $vms = hk( $VMs);
			for ( $i = 0; $i < $this->size - 2; $i++) { // create migrations
				if ( $i >= $count) { lpush( $g, 0); continue; }
				$vm = $vms[ $i]; $load = $VMs[ $vm];
				$pms = hk( $PMs); shuffle( $pms); 	// random selection
				$from = $this->VM2PM[ $vm]; 
				while ( count( $pms)) {
					$pm = lshift( $pms);
					if ( $pm != $from && $PMs[ $pm] > $load) break;
					$pm = null;
				}
				if ( $pm === null) { $lost++; lpush( $g, 0); continue; }
				$to = $pm; $order = $i;
				lpush( $g, compact( ttl( 'vm,load,from,to,order')));
				$PMs[ $pm] -= $load;	// to check later
			}
			$this->vmdist[ $count]++;	// increase the count
			shuffle( $g);
			lunshift( $g, compact( ttl( 'lost,count,VMs,PMs')));
			lpush( $g, true);
			if ( count( $g) != $this->size) { jsondump( $g, 'gene.json'); die( " FILED to create good-sized gene! (" . count( $g) . " != " . $this->size . ") count=$count gene:" . json_encode( $g) . "\n"); }
			return;
		}
		if ( $pos > 0 && $pos < $this->size - 1 && ! isset( $g[ $this->size - 1])) return; // mid-pos of a new creation, do nothing
		if ( $pos > 0 && $pos < $this->size - 1 && $g[ $pos] == 0) return;	// no migration in this one
		if ( $pos == 0 || $pos == $this->size - 1) return;	// ignore -- repeated constaint
		// mutation, try to find new machine for this virtual machine 
		extract( $g[ $pos]); // vm, load, from, to, order
		extract( $g[ 0]); // PMs, VMs, lost, count
		$load = $VMs[ $vm];
		$pms = hk( $PMs); shuffle( $pms); 	// random selection
		while ( count( $pms)) {
			$pm = lshift( $pms);
			if ( $pm != $from && $pm != $to && $PMs[ $pm] > $load) break;
			$pm = null;
		}
		if ( $pm === null) return;	// failed to change PM for this migration
		$PMs[ $to] += $load; // cancel old migration 
		$to = $pm; 	// FROM remains the same, only TO is changed
		$g[ $pos] = compact( ttl( 'vm,load,from,to,order'));
		$PMs[ $pm] -= $load;	// to check later
		$g[ 0] = compact( hk( $g[ 0]));
		$g[ $pos] = compact( hk( $g[ $pos]));	
	}
	
}
class ConventionalGA extends MyGA { public function fitness( $g) { 
	global $B, $w1, $w2, $LM; 
	extract( $g[ 0]); 
	$info = array(); lpop( $g); lshift( $g); foreach ( $g as $h) { if ( $h == 0) continue; extract( $h); $info[ $order] = $h; }
	ksort( $info, SORT_NUMERIC);
	// implement the scenario
	$PMs = $this->PM; $VMs = $this->VM2PM;
	foreach ( $info as $order => $h) { 
		extract( $h); // vm, load, from, to
		integrity( $PMs, $VMs); $from = $VMs[ $vm];
		$h2 = $PMs[ $from][ 'VMs'][ $vm];
		unset( $PMs[ $from][ 'VMs'][ $vm]);
		$PMs[ $to][ 'VMs'][ $vm] = $h2;
	}
	list( $eval, $abort) = evalconventional( $PMs, $VMs, hv( $info)); 
	if ( $abort) $this->abort();
	//echo json_encode( compact( ttl( 'w1,v1,w2,v2,diffs'))) . " eval($eval)\n"; usleep( 300000);
	return $eval;
}}
class ProposedGA extends MyGA { public function fitness( $g) { 
	global $B, $w1, $w2, $w4, $LM; 
	extract( $g[ 0]); 
	$info = array(); lpop( $g); lshift( $g); foreach ( $g as $h) { if ( $h == 0) continue; extract( $h); $info[ $order] = $h; }
	ksort( $info, SORT_NUMERIC);
	// implement the scenario
	$PMs = $this->PMs; $VMs = $this->VM2PM; 
	foreach ( $info as $order => $h) { 
		extract( $h); // vm, load, from, to
		integrity( $PMs, $VMs); $from = $VMs[ $vm];
		$h2 = $PMs[ $from][ 'VMs'][ $vm];
		unset( $PMs[ $from][ 'VMs'][ $vm]);
		$PMs[ $to][ 'VMs'][ $vm] = $h2;
	}
	list( $eval, $abort) = evalproposed( $PMs, $VMs, hv( $info)); 
	if ( $abort) $this->abort();
	//echo json_encode( compact( ttl( 'w1,v1,w2,v2,diffs'))) . " eval($eval)\n"; usleep( 300000);
	return $eval;
}}


function prepare() { // returns { FSS, FSB, C, C1, C2, SB, rows, cols}
	global $pmcount;
	list( $C, $CS) = chartsplitpage( 'L', 18, '1', '0.5,0.5', '0.02,0.02', '0.1:0.05:0.05:0.05');
	for ( $i = 0; $i < 2; $i++) { $k = "C$i"; $CS[ $i]->train( ttl( '0,1'), ttl( '0,1')); $$k = $CS[ $i]; }
	$SB = new ChartSetupStyle(); $SB->lw = 0; $SB->draw = null; $SB->fill = '#000'; $SB->alpha = 0.3;
	$h = 2; while ( $h * ( $h - 1) < $pmcount) $h++; $w = $h - 1;
	$rows = $h; $cols = $w; 
	$C1 = $CS[ 0]; $C2 = $CS[ 1];
	$C1->autoticks( null, null, 10, 10); $C2->autoticks( null, null, 10, 10);
	return compact( ttl( 'FSS,FSB,C,C1,C2,SB,rows,cols'));
}
function initialize() { // returns PMs
	global $rows, $cols, $pmcount;
 	$h = round( 1 / $rows - $rows * 0.01, 2); 
	$w = round( 1 / $cols - $cols * 0.01, 2);
	$PMs = array(); 
	for ( $y = 0; $y < $rows && count( $PMs) < $pmcount; $y++) {
		$y2 = ( $y + 1) * $h + ( $y * 0.01) - 0.5 * $h;
		for ( $x = 0; $x < $cols && count( $PMs) < $pmcount; $x++) {
			$x2 = ( $x + 1) * $w + ( $x * 0.01) - 0.5 * $w;
			lpush( $PMs, tth( "x=$x2,y=$y2,w=$w,h=$h"));
		}
		
	}
	return $PMs;
}
function distribute( &$PMs, $e) { // adds VMs[ { load, loadrange},...]  to each PM in PMs
	global $n, $vmcount, $pmcount, $pmpackmax, $AM;
	$vms = hk( $AM); shuffle( $vms); $VM2PM = array(); 
	$map = array(); foreach ( $PMs as $pm => $h) $map[ $pm] = 1;
	while ( count( $vms)) {
		$vm = lshift( $vms); extract( $AM[ $vm]); // load, range
		asort( $map, SORT_NUMERIC);
		foreach ( $map as $pm => $left) { if ( $left - $load >= 1 - $pmpackmax) break; $pm = null; }
		if ( ! $pm) continue;
		$h = tth( "vid=$vm,load=$load,loadrange=$range");
		htouch( $PMs[ $pm], 'VMs'); $PMs[ $pm][ 'VMs'][ $vm] = $h;
		$VM2PM[ $vm] = $pm;
	}
	foreach ( $PMs as $pm => $h) htouch( $PMs[ $pm], 'VMs');	// just in case it was left unoccupied
	return $VM2PM;
}
function activity( &$VMs, &$PMs, $e) { global $activityprob, $P; foreach ( $VMs as $vm => $pm) { 
	echoe( $e, "activity vm=$vm");
	$R =& $PMs[ $pm][ 'VMs'][ $vm]; htouch( $R, 'curload'); htouch( $R, 'probs'); 
	extract( $R);   // vid, load, loadrange, curload, probs
	if ( ! $curload) $curload = $load;
	//if ( ! $P) $P = Rdist( "rgeom( 1000, $activityprob)");
	if ( ! $P) { $P = array(); for ( $i = 0; $i < 1000; $i++) $P[ $i] = 0; }
	if ( ! $probs) { $probs = $P; $v = mt_rand( 0, count( $P)); for ( $i = 0; $i < $v; $i++) lpush( $probs, lshift( $probs)); }
	$R = compact( hk( $R));
	$probs[ 0]--; if ( $probs[ 0] > 0) continue;	// no change
	lshift( $probs); lpush( $probs, lfirst( $probs));
	$curload = round( $load + ( mt_rand( 0, 9) > 4 ? -1 : 1) * $load * $loadrange, 2);
	if ( $curload < 0) $curload = 0;
	if ( $curload > 0.98) $curload = 0.98;
	$R = compact( hk( $R));
}}
function makeGA( &$VM2PM, &$PMs, $type = 'CONVENTIONAL') {
	global $migrationlimit;
	if ( $type == 'CONVENTIONAL') $GA = new ConventionalGA();
	else $GA = new ProposedGA();
	$GA->VM2PM =& $VM2PM;
	$GA->PMs =& $PMs;
	$GA->size = round( $migrationlimit * count( $VM2PM)) + 2;	// max number of migrations, gene size = size + 1 (head) + 1 (tail)
	foreach ( $VM2PM as $vm => $pm) $GA->VMstats[ $vm] = $PMs[ $pm][ 'VMs'][ $vm][ 'curload'];
	foreach ( $PMs as $pm => $h) $GA->PMstats[ $pm] = 1 - msum( hltl( $h[ 'VMs'], 'curload'));
	for ( $v = 0; $v < $GA->size; $v ++) $GA->vmdist[ $v] = 0;	// counts
	for ( $v = 0; $v < count( $PMs) - 1; $v++) $GA->pmdist[ $v] = 0; // PM dist
	return $GA;
}
function implement( $gene, &$VM2PM, &$PMs) { // returns the count
	lshift( $gene); lpop( $gene); 
	$h = array(); foreach ( $gene as $h2) { if ( $h2 == 0) continue; $h[ $h2[ 'order']] = $h2; }
	//echo "\n\n"; echo "IMPLEMENT\n"; echo "h: " . json_encode( $h) . "\n"; echo "gene: " . json_encode( $gene) . "\n";
	ksort( $h, SORT_NUMERIC);
	foreach ( $h as $order => $h2) {
		extract( $h2); // vm, load, from, to, order
		integrity( $PMs, $VM2PM); $from = $VM2PM[ $vm];
		$h3 = $PMs[ $from][ 'VMs'][ $vm];
		unset( $PMs[ $from][ 'VMs'][ $vm]);
		$PMs[ $to][ 'VMs'][ $vm] = $h3;
		$VM2PM[ $vm] = $to;
	}
	return count( $h);
}
function integrity( &$PMs, &$VM2PM) {
	foreach ( $PMs as $pm => $h) foreach ( $h[ 'VMs'] as $vm => $h) $VM2PM[ $vm] = $pm;
}
function bestgene( $genes, $evals) { // returns eval, gene --- selects the gene with the least migration count 
	arsort( $evals, SORT_NUMERIC); list( $id, $eval) = hfirst( $evals);
	$h = array(); foreach ( $evals as $id => $eval2) if ( $eval == $eval2) $h[ $id] = $genes[ $id][ 0][ 'count'];
	asort( $h, SORT_NUMERIC); list( $id, $count) = hfirst( $h);
	return array( $eval, $genes[ $id]);
}
function vmstats( &$PMs, &$VMs) { 
	$L = array(); 
	foreach ( $VMs as $vm => $pm) {
		$R =& $PMs[ $pm][ 'VMs'][ $vm];
		lpush( $L, isset( $R[ 'curload']) ? $R[ 'curload'] : 0);
	}
	return mstats( $L, 3);
}
function pmstats( &$PMs) {
	$L = array(); foreach (  $PMs as $pm => $h) { htouch( $L, $pm); foreach ( $h[ 'VMs'] as $h2) { lpush( $L[ $pm], $h2[ 'curload']); }}
	$count = 0; foreach ( $L as $L2) if ( ! count( $L2)) $count++;
	for  ( $i = 0; $i < count( $L); $i++) $L[ $i] = ltt( $L[ $i]);
	$layout = $L; $unused = $count;
	return compact( ttl( 'unused,layout'));
}
function draw( &$PMs, $C, $method) {
	global $FSS, $FSB, $SB, $rows, $cols;
	$SB = new ChartSetupStyle(); $SB->lw = 0.1; $SB->draw = '#000'; $SB->fill = null; $SB->alpha = 0.5;
	$SB2 = clone $SB; $SB2->lw = 1.5;
	$C->frame( null, null);
	$good = 0; 
	foreach ( $PMs as $pm => $h) {
		extract( $h);
		$xs = array( $x - 0.5 * $w, $x - 0.5 * $w, $x + 0.5 * $w, $x + 0.5 * $w, $x - 0.5 * $w);
		$ys = array( $y + 0.5 * $h, $y - 0.5 * $h, $y - 0.5 * $h, $y + 0.5 * $h, $y + 0.5 * $h);
		$points = array();
		for ( $i = 0; $i < count( $xs); $i++) lpush( $points, array( $xs[ $i], $ys[ $i]));
		chartshape( $C, $points, count( $VMs) ? $SB2 : $SB);
		if ( ! count( $VMs)) { $good++; continue; }
		$util = msum( hltl( $VMs, 'curload')); if ( $util > 1) $util = 1; 
		$util = round( $util, 1);
		chartext( $C, array( $x), array( $y), array( "$util"), null, $FSS);
	}
	$CL = new ChartLegendOR( $C);
	$CL->add( null, 4, 0.1, $method);
	$CL->add( null, 4, 0.1, "Free: $good");
	$CL->draw( true);
}


// PMs: [ { x, y, h, w, VMs: { }}, ...]
// VMs: { vm: pm}
// migrations: [ { vm, from, to, load}, ...]
function evalconventional( &$PMs, &$VMs, $migrations) { // [ eval, status]
	global $LM, $B, $w1, $w2;
	// calculate v1
	$L = array(); //echo "\n";
	foreach ( $PMs as $pm => $h) lpush( $L, 1 - msum( hltl( $h[ 'VMs'], 'curload')));
	$v1 = round( mvar( $L), 3);
	// calculate v2
	$L = array(); foreach ( $migrations as $h) lpush( $L, $h[ 'load']);
	$v2 = round( msum( $L), 3); $before = $B;
	foreach ( ttl( 'v1,v2') as $k) { foreach ( ttl( 'min,max') as $k2) { 
		$k3 = $k . $k2; 
		htouch( $B, $k3, $$k, $k2 == 'min' ? true : false, $k2 == 'max' ? true : false);
	}}
	//$B[ 'v1min'] = 0; $B[ 'v2min'] = 0;
	extract( $B);
	$v1 = lshift( mmap( array( $v1), $v1min, $v1max));
	$v2 = lshift( mmap( array( $v2), $v2min, $v2max));
	$eval = $w1 * $v1 - $w2 * $v2;
	return array( $eval, htt( $before) == htt( $B) ? false : true);
}
function evalproposed( &$PMs, &$VMs, $migrations) {
	global $w4, $B;
	list( $eval, $status) = evalconventional( $PMs, $VMs, $migrations);
	$pms = array(); foreach ( $VMs as $vm => $pm) $pms[ $pm] = true;
	$v4 = ( count( $pms) / count( $PMs));
	$before = $B;
	htouch( $B, 'v4min', $v4, true, false);
	htouch( $B, 'v4max', $v4, false, true);
	extract( $B);
	$v4 = lshift( mmap( array( $v4), $v4min, $v4max));
	$status2 = $status ? (  htt( $B) == htt( $before) ? true : false) : false;
	$eval -= $w4 * $v4;
	return array( $eval, $status2);
}



?>