#!/usr/bin/php
<?PHP
  /****************************************************************************
   * Script para filtrar saida do qstat                                       *
   * 13 de abril de 2011                                                      *
   * Author: Gabriel Dalpiaz, Timoteo Lange e Filipi Vianna                   *
   * $Id: pbsNodes.php,v 1.2 2011/05/13 17:37:55 filipi Exp $             *
   ****************************************************************************/

ini_set('memory_limit', 67108864);
function tiraQuebrasDeLinha($str1, $marcador){
  if (!$marcador)
    return preg_replace("/(\r|\n)/", ', ', $str1);
  else
    return preg_replace("/(\r|\n)/", $marcador, $str1);
}

$pbsnodes_path = "/usr/bin/pbsnodes";

//$ssh_connection_string = "ssh user@host ";
$ssh_connection_string = " ";

$commandLine = $ssh_connection_string . $pbsnodes_path;
$nodes = `$commandLine`;

///////////////////////////////////////////////////// Informacoes sobre os nohs

$nodes = tiraQuebrasDeLinha($nodes, "<|>");
$nodes = explode("<|><|>", $nodes);

$i = 0;
foreach ($nodes as $node){
  $node = explode("<|>", $node);
  $nodesInfo[$i]['clusterName'] = $node[0];
  foreach ($node as $value){
    $temp = explode("=", trim($value));
    if (count($temp)>1){
      $nodesInfo[$i][trim($temp[0])] = trim($temp[1]);
    }
    $jobsInfo = explode(",", $nodesInfo[$i]['jobs']);
    $j = 0;
    foreach($jobsInfo as $jobInfo){
      if (trim($jobInfo)){
        $nodesInfo[$i]['jobsInfo'][$j] = $jobInfo;      
        $j++;
      }
    }    
  
    $nodesInfo[$i]['nroJobs'] = $j;  
  }
  $i++;
}
$i = 0;
foreach ($nodesInfo as $node){
  if ( (intval($node['nroJobs']) < intval($node['np'])) && intval($node['nroJobs']) ){
    $nodesInfo[$i]['state'] = "shared";
  }
  $i++;
  $clusterName = preg_replace("/\d.*/", '', $node['clusterName']);
  if ($clusterName)
    $lad[$clusterName][] = $node;
}

///////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////// Monta a informacao para apresentar

foreach ($nodesInfo as $node){
  $clusterName = preg_replace("/\d.*/", '', $node['clusterName']);
  //$lad[$clusterName][] = $node;

  echo $node['clusterName'] . " " . $node['state'] . " " . $node['nroJobs'] . "\n";
}

echo "\n";
var_dump($lad);
echo "\n";

