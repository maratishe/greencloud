<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
//sclhelp( '');
//htg( clget( ''));

echo "\n\n"; $e = echoeinit(); $out = foutopen( 'data.bz64jsonl', 'w'); $gcount = 0; $size = 0;
foreach ( flget( '.', 'raw', '', 'bz64jsonl') as $file) { $in = finopen( $file); while ( ! findone( $in)) { 
	list( $h, $p) = finread( $in); if ( ! $h) continue; echoe( $e, "$file($p) > $gcount($size)");
	//die( jsondump( $h, 'temp.json'));	
	extract( $h); // setup, data
	//die( jsondump( $h, 'temp3.json'));
	$h = $setup; foreach ( ttl( 'LM,AM,epochs,generations') as $k) unset( $h[ $k]);
	foreach ( $data as $method => $epochs) { foreach ( $epochs as $epoch => $h2) {
		$h2 =& $data[ $method][ "$epoch"]; extract( $h2);
		$h2[ 'migrations'] = count( $solution);
		if ( count( $solution)) $h2[ "Mraw"] = htt( $details[ 'raw']); 
		if ( count( $solution)) $h2[ "Mcooked"] = htt( $details[ 'cooked']);
		$count = 0; foreach ( $before as $pm => $load) if ( ! $load) $count++; $h2[ 'pmfreebefore'] = $count;
		$count = 0; foreach ( $after as $pm => $load) if ( ! $load) $count++; $h2[ 'pmfreeafter'] = $count;
		$count = 0; foreach ( $beforemap as $v) if ( $v == 0) $count++; $h2[ 'vmoffbefore'] = $count;
		$count = 0; foreach ( $aftermap as $v) if ( $v == 0) $count++; $h2[ 'vmoffafter'] = $count;
		$h2[ "vmloss"] = $h2[ 'vmoffafter'] - $h2[ 'vmoffbefore'];
		foreach ( ttl( 'solution,details,before,after') as $k) unset( $h2[ $k]);
		if ( $method != 'OPT') foreach ( ttl( 'beforemap,aftermap') as $k) unset( $h2[ $k]);
		htouch( $h, $method); lpush( $h[ $method], $h2);
		unset( $h2);
	}}
	$size = foutwrite( $out, $h); $gcount++;	
}; echo " OK\n"; }
foutclose( $out); echo " OK\n";

?>