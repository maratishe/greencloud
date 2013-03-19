
This software contains several VM migration optimizations. Some of the methods are 'green' in that they attempt to create and contain PMs with zero load -- such machines can be turned off.

VM: Virtual Machine (cloud)


-----------
Features
-----------

(1) Platform independent.  Input is raw data on PMs and VMs.  Output is migration scenario as { vm: pm} hash. 

(2) Based on quick optimziations using Generic Algorithm.  PHP classes for GA are part of the software package.  Highly flexible and tunable.

(3) Written entirely in PHP.


-----------
Installation
-----------

(1) unpack ajaxkit.rar, it will open into the ajaxkit folder. Keep it in the working folder as PHP sources heavily depends on the PHP libraries inside.  The code will self-configure and find the ajaxkit folder. 

(2) unpack data.rar (will open into data.bz64jsonl) if you need to use data processing scripts, like visualize.php.


-----------
Execution
-----------

(1) run.php is the main script.  It randomly selects a 100 PM layout, randomly configures itself and then runs the optimizations in all the methods. 

(2) output from run.php goes into a JSON file where 'data' key contains all the results.  Just see one of such files to understand the structure -- not that difficult. 

(3) if you need to process results, you need to aggregate JSON[ 'data'] into a bz64jsonl file. The following code allows you to read and write bz64jsonl files where each line is baze64( bzip2( JSON)).

	// writing logic
	$out = foutopen( 'somefile.bz64jsonl', 'w'); // 'a' for append
	foutwrite( $out, $JSONDATA);
	$filesize = foutclose( $out); // you can use filesize for monitoring
	
	// reading logic
	$in = finopen( 'somefile.bz64jsonl');
	while ( ! findone( $in)) {
		list( $JSONDATA, $progress) = finread( $in); // use $progress for monitoring
	}
	finclose( $in);
	

