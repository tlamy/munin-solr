#!/usr/bin/php
<?php

$solrBase = "http://localhost:8080/solr/";

function getCores($solr)
{
	$data = file_get_contents($solr."admin/core?action=STATUS&wt=json");
	$arrData = json_decode($data, true);
	return $arrData['status'];
}

function getCoreInfo($core, $solr)
{
	$data = file_get_contents($solr.$core."(admin/mbeans?stats=true&wt=json");
	$arrData = json_decode($data, true);
	return $arrData['solr-mbeans'];
}
$cores = getCores($solrBase);

if( $argc > 1 && $argv[1] == "config") {
	foreach( $cores as $internal=>$coreData) {
		echo "multigraph solr_qps_".$internal."
graph_title Queries {$coreData['name']}
graph_vlabel	qps
qps.label	qps
qps.draw	LINE2
graph_category solr

";
		echo "multigraph solr_size_".$internal."
graph_title Core Size {$coreData['name']}
graph_vlabel	bytes
size.label	size
size.draw	LINE2
graph_category solr

";
		echo "multigraph solr_cacheq_".$internal."
graph_title QueryCache {$coreData['name']}
graph_vlabel	bytes
size.label	size
size.draw	LINE2
lookup.label	size
lookup.draw	LINE1
hit.label	size
hit.draw	LINE1
insert.label	size
insert.draw	LINE1
eviction.label	size
eviction.draw	LINE1
graph_category solr

";
		echo "multigraph solr_cached_".$internal."
graph_title DocumentCache {$coreData['name']}
graph_vlabel	bytes
graph_category solr
size.label	size
size.draw	LINE2
lookup.label	size
lookup.draw	LINE1
hit.label	size
hit.draw	LINE1
insert.label	size
insert.draw	LINE1
eviction.label	size
eviction.draw	LINE1

";
		echo "multigraph solr_cachef_".$internal."
graph_title FilterCache {$coreData['name']}
graph_vlabel	bytes
graph_category solr
size.label	size
size.draw	LINE2
lookup.label	size
lookup.draw	LINE1
hit.label	size
hit.draw	LINE1
insert.label	size
insert.draw	LINE1
eviction.label	size
eviction.draw	LINE1

";
		echo "multigraph solr_handler_".$internal."
graph_title Handlers {$coreData['name']}
graph_category solr
graph_vlabel	time\n";
		foreach (array( "PingRequest", "UpdateRequest", "JsonUpdateRequest", "Replication", "DumpRequest", "Search") as $handler) {
			echo $handler.".label $handler\n$handler.draw LINE1\n";
		}
		echo "\n";
	}
	exit();
}

foreach( $cores as $internal=>$coreData) {
	$coreData2 = getCoreInfo($internal, $solrBase);

	echo "multigraph solr_qps_".$internal."
	qps.value	".$coreData2['QUERYHANDLER']['/select']['stats']['requests']."\n";

	echo "multigraph solr_size_".$internal."
	size.value	".$coreData['index']['sizeInBytes']."\n";

	foreach( array("cacheq"=>"queryResultCache", "cached"=>"documentCache", "cachef"=>"filterCache") as $munin=>$solr) {
		echo "multigraph solr_".$munin."_".$internal."\n";
		echo "size.value	".$coreData2['CACHE'][$solr]['stats']['size']."\n";
		echo "lookup.value	".$coreData2['CACHE'][$solr]['stats']['lookups']."\n";
		echo "hit.value	".$coreData2['CACHE'][$solr]['stats']['hits']."\n";
		echo "insert.value	".$coreData2['CACHE'][$solr]['stats']['inserts']."\n";
		echo "eviction.value	".$coreData2['CACHE'][$solr]['stats']['evictions']."\n";
		echo "\n";
	}

	echo "multigraph solr_handler_".$internal."\n";
	foreach (array( "PingRequest", "UpdateRequest", "JsonUpdateRequest", "Replication", "DumpRequest", "Search") as $handler) {
		echo $handler.".value	".$coreData2['QUERYHANDLER']["org.apache.solr.handler.component.".$handler."Handler"]["totalTime"]."\n";
	}
	echo "\n";
}
