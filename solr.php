#!/usr/bin/php
<?php
#
# Munin Plugin for Apache Solr 4.0
#
# Installation:
# Debian: apt-get install php5-cli
#
# In /etc/php5/cli/php.ini, set variables_order = "EGPCS"
# (needed to override defaults for port, path and authentication)
#
# in /etc/munin/plugin-conf.d/munin-node, add
#   [solr*]
#   env.port 8080
#   env.path /solr
#   env.user munin
#   env.password munin
# and edit according to your solr/tomcat configuration


$port = array_key_exists("port", $_ENV) ? $_ENV['port'] : 8080;
$path = array_key_exists("path", $_ENV) ? $_ENV['path'] : "/solr";
$user = array_key_exists("user", $_ENV) ? $_ENV['user'] : null;
$pass = array_key_exists("pass", $_ENV) ? $_ENV['pass'] : null;
$solrBase = "http://localhost:".$port.$path."/";
$arrOptions = array('http'=>array('method'=>'GET'));
if($user !== null && $pass !== null) {
	$arrOptions['http']['header'] = 'Authorization: Basic '.base64_encode($user.':'.$pass);
}
$solrContext = stream_context_create($arrOptions);


function getCores($solr, $context)
{
	$data = file_get_contents($solr."admin/cores?action=STATUS&wt=json", false, $context);
	$arrData = json_decode($data, true);
	return $arrData['status'];
}

function getCoreInfo($core, $solr, $context)
{
	$arrResult = array();
	$data = file_get_contents($solr.$core."/admin/mbeans?stats=true&wt=json", false, $context);
	$arrData = json_decode($data, true);
	$arrBeans = $arrData['solr-mbeans'];
	while(count($arrBeans)) {
		$arrResult[array_shift($arrBeans)] = array_shift($arrBeans);
	}
	return $arrResult;
}
$cores = getCores($solrBase, $solrContext);
if( $argc > 1 && $argv[1] == "co nfig") {
	print_r($_ENV);
	exit;
}

if( $argc > 1 && $argv[1] == "config") {
	foreach( $cores as $internal=>$coreData) {
		echo "multigraph solr_qps_".$internal."
graph_title Queries {$coreData['name']}
graph_vlabel	qps
qps.label	qps
qps.draw	LINE2
qps.type	DERIVE
qps.min		0
graph_category solr

";
		echo "multigraph solr_size_".$internal."
graph_title Core Size {$coreData['name']}
graph_vlabel	Bytes
graph_category solr
size.label	size
size.draw	LINE2
graph_args	--base 1024

";
		echo "multigraph solr_cacheq_".$internal."
graph_title QueryCache {$coreData['name']}
graph_vlabel	Items
graph_args	--logarithmic
graph_category solr
size.label	size
size.draw	LINE2
lookup.label	Lookup
lookup.draw	LINE1
lookup.type	DERIVE
lookup.min	0
hit.label	Hit
hit.draw	LINE1
hit.type	DERIVE
hit.min		0
insert.label	Insert
insert.draw	LINE1
insert.type	DERIVE
insert.min	0
eviction.label	Eviction
eviction.draw	LINE1
eviction.type	DERIVE
eviction.min	0

";
		echo "multigraph solr_cached_".$internal."
graph_title DocumentCache {$coreData['name']}
graph_vlabel	Items
graph_category solr
graph_args	--logarithmic
size.label	size
size.draw	LINE2
lookup.label	Lookup
lookup.draw	LINE1
lookup.type	DERIVE
lookup.min	0
hit.label	Hit
hit.draw	LINE1
hit.type	DERIVE
hit.min		0
insert.label	Insert
insert.draw	LINE1
insert.type	DERIVE
insert.min	0
eviction.label	Eviction
eviction.draw	LINE1
eviction.type	DERIVE
eviction.min	0

";
		echo "multigraph solr_cachef_".$internal."
graph_title FilterCache {$coreData['name']}
graph_vlabel	Items
graph_category solr
graph_args	--logarithmic
size.label	size
size.draw	LINE2
lookup.label	Lookup
lookup.draw	LINE1
lookup.type	DERIVE
lookup.min	0
hit.label	Hit
hit.draw	LINE1
hit.type	DERIVE
hit.min		0
insert.label	Insert
insert.draw	LINE1
insert.type	DERIVE
insert.min	0
eviction.label	Eviction
eviction.draw	LINE1
eviction.type	DERIVE
eviction.min	0

";
		echo "multigraph solr_handler_".$internal."
graph_title Handlers {$coreData['name']}
graph_category solr
graph_args	--logarithmic
graph_vlabel	time\n";
		foreach (array( "PingRequest", "UpdateRequest", "JsonUpdateRequest", "Replication", "DataImport", "DumpRequest", "Search") as $handler) {
			echo $handler.".label $handler\n$handler.draw LINE1\n";
			echo $handler.".type DERIVE\n";
			echo $handler.".min 0\n";
		}
		echo "\n";
	}
	exit();
}

foreach( $cores as $internal=>$coreData) {
	$coreData2 = getCoreInfo($internal, $solrBase, $solrContext);

	echo "\nmultigraph solr_qps_".$internal."\n";
	echo "qps.value	".$coreData2['QUERYHANDLER']['/select']['stats']['requests']."\n";

	echo "\nmultigraph solr_size_".$internal."\n";
	echo "size.value	".($coreData['index']['sizeInBytes'])."\n";

	foreach( array("cacheq"=>"queryResultCache", "cached"=>"documentCache", "cachef"=>"filterCache") as $munin=>$solr) {
		echo "\nmultigraph solr_".$munin."_".$internal."\n";
		echo "size.value	".$coreData2['CACHE'][$solr]['stats']['size']."\n";
		echo "lookup.value	".$coreData2['CACHE'][$solr]['stats']['lookups']."\n";
		echo "hit.value	".$coreData2['CACHE'][$solr]['stats']['hits']."\n";
		echo "insert.value	".$coreData2['CACHE'][$solr]['stats']['inserts']."\n";
		echo "eviction.value	".$coreData2['CACHE'][$solr]['stats']['evictions']."\n";
		echo "\n";
	}

	echo "\nmultigraph solr_handler_".$internal."\n";
	foreach (array(
			"PingRequest"=>'org.apache.solr.handler.PingRequestHandler',
			"UpdateRequest"=>'org.apache.solr.handler.UpdateRequestHandler',
			"JsonUpdateRequest"=>'org.apache.solr.handler.JsonUpdateRequestHandler',
			"Replication"=>'org.apache.solr.handler.ReplicationHandler',
			"DataImport"=>'org.apache.solr.handler.dataimport.DataImportHandler',
			"DumpRequest"=>'org.apache.solr.handler.DumpRequestHandler',
			"Search"=>'org.apache.solr.handler.component.SearchHandler') as $handler=>$class) {
		// echo "'".implode("', '", array_keys($coreData2['QUERYHANDLER']))."'\n"; exit;
		if(isset($coreData2['QUERYHANDLER'][$class]) && isset($coreData2['QUERYHANDLER'][$class]['stats']["totalTime"])) {
			echo $handler.".value	".(int)$coreData2['QUERYHANDLER'][$class]['stats']["totalTime"]."\n";
		} else if($handler == "DataImport") {
			for($i=0; isset($coreData2['QUERYHANDLER'][$class]) && $i < count($coreData2['QUERYHANDLER'][$class]['stats']); $i += 2) {
				if($coreData2['QUERYHANDLER'][$class]['stats'][$i] == "totalTime") {
					echo $handler.".value	".(int)$coreData2['QUERYHANDLER'][$class]['stats'][$i+1]."\n";
				}
			}
		}
	}
	echo "\n";
}
