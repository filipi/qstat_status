#!/usr/bin/php
<?PHP
   /***************************************************************************
    * Torque qstat and pbsnodes wrapper script                                *
    * 13 de abril de 2011                                                     *
    * Author: Gabriel Dalpiaz, Timoteo Lange, Rafael Bellé  e Filipi Vianna   *
    * $Id: qstat_status.php,v 1.26 2018/03/14 12:38:52 filipi Exp $           *
    ***************************************************************************/
   /* Change log:

Release 0.9 (CVS = 1.25) ) October, 16th 2016.
Fixed the way "checktime" function deals with months. The array must contain an
strings representing the numbers of the months. They must be explycit strings,
using quotation, for compatibility with PHP7.
Also added a "waiting" message to be displayed when qstat_status is being used
remotely throught SSH.
Added a date at the change log.

Release 0.8 (CVS = 1.21)
Added "Separated" state for hosts who has "down,job-exclusive" state on pbsnodes

Release 0.7 (CVS = 1.21)
Minor fixes with undefined indexes.

Release 0.6 (CVS = 1.19)
Added info on about execution time and job state on queued jobs block.
 Job Id  Username   Group   Job name   Reserved     Status Cores   Scheduled to
 ------ --------- ------- ---------- ---------- ---------- ----- --------------
  25552 afonso.sa paleopr CASE78-SP-   10:00:00    waiting 01x16 16/11 17:00:00

Release 0.5 (CVS = 1.18)
Fixed to show jobs with W (waiting) status
Fixed to better show the last line of cluster nodes.

Release 0.4 (CVS = 1.15)
Minor visual changes
Added system time to the header
Fixed another shared count bug (now using count($node['jobsInfo']))
  =============================================================================
  System time: May  20 15:13:02   
  =============================================================================
  Cluster: gates - 1 Nodes Free / 12 Exclusive / 0 Shared                       
      gates01:      free        gates19: exclusive        gates20: exclusive    
      gates22: exclusive        gates23: exclusive        gates24: exclusive    
      gates25: exclusive        gates26: exclusive        gates27: exclusive    
      gates28: exclusive        gates29: exclusive        gates30: exclusive    
      gates31: exclusive                                                        
 Jobs running ================================================================= 
 Job Id  Username   Group   Job name   Reserved    Elapsed Cores     Started at 
 ------ --------- ------- ---------- ---------- ---------- --------- ---------- 
  23232 timoteo.l     lad teste_fila   05:00:00   01:14:25 12x02  20/5 13:53:08 
 Queued jobs ================================================================== 
 Job Id              Username        Group          Job name    Reserved  Cores 
 ------ --------------------- ------------ ----------------- ----------- ------ 
  23233         timoteo.lange          lad       teste_filas    05:00:00  00x02 

Release 0.3 (CVS = 1.13)
Fixed bug with unformated job['start_time']
Fixed bug with shared nodes
Fixed PHP notices on later PHP versions
Fixed bug on number of clusters not multiple by 3
Changed character separating nodes and cores to x (times)
Parsed output "down, exclusive", to show only "down".
Eliminated unused columns (fields) shown for queued jobs
Added version information to the header (visible using $showVersion bool var)
Configurable column delimiter ($columnDelimiter)
========================================[Torque qstat/pbsnodes wrapper v0.3]== 
Cluster: atlantica - 10 Nodes Free / 0 Exclusive / 0 Shared                   
atlantica01:      free    atlantica02:      free    atlantica03:      free    
atlantica04:      free    atlantica05:      free    atlantica06:      free    
atlantica07:      free    atlantica08:      free    atlantica09:      free    
atlantica10:      free                                                        
None running jobs------------------------------------------------------------- 
None queued jobs-------------------------------------------------------------- 
------------------------------------------------------------------------------ 

Release 0.2 (CVS = v 1.8)
Interface changed to english
Added NIS (users/groups) and pbsnodes support
Accepting commandline arguments to specify clusters
Format shrunk to 80 columns
|==============================================================================|
| Cluster: atlantica - 10 Nodes Free / 0 Exclusive / 0 Shared                  |
| atlantica01:      free    atlantica02:      free    atlantica03:      free   |
| atlantica04:      free    atlantica05:      free    atlantica06:      free   |
| atlantica07:      free    atlantica08:      free    atlantica09:      free   |
|None running jobs-------------------------------------------------------------|
|None queued jobs--------------------------------------------------------------|
|------------------------------------------------------------------------------|

Release 0.1 (CVS = 1.0)
Torque qstat wrapper (portuguese version)
-----------------------------------------------------------------------------------------------------------------
|    Nome do Job | Reservado | Decorrido | Nodo-Cores-Cluster  |              Estado |              Iniciado em |
-----------------------------------------------------------------------------------------------------------------
   */


$options = getopt("c:h");
//var_dump($options);
foreach ($options as $key => $value){
  if ($key == 'h'){
    //Crono v1.4.1
    echo "Usage: qstat -c <cluster>\n";
    echo "   qstat --help\n\n";

    echo "        -c <cluster>            : Cluster name\n";
    echo "        --help                  : Display this help and exit\n\n";

    echo "The missing parameters will be filled out using the default file. \n";
    echo "To manipulate the default file use the crsetdef program.    \n";
    exit(1);
  }
}

/*
//echo `whoami`;

root@marfim:/usr/local/bin# crqview -h

*/

///////////////////////////////////////////////////////////////// Configuration
$columnDelimiter = " ";
$showVersion = false;
$staticTest = false;

$clusters[] = "atlantica";
$clusters[] = "cerrado";

$GROUPID = posix_getgid();
if ($GROUPID == 0 || $GROUPID == 1000 || $GROUPID == 1019 || $GROUPID == 1029)
	$clusters[] = "plumes";

$clusters[] = "amazonia";

//$clusters[] = "gates";
//$clusters[] = "pantanal";

$ypcat_path = "/usr/bin/ypcat";

$torque_path = "/usr/local/torque-4.2.9";
$pbsnodes_path = $torque_path . "/bin/pbsnodes";
$qstat_path = $torque_path . "/bin/qstat";

//$ssh_connection_string = "ssh user@hostname ";
//$ssh_connection_string = "ssh host1 ssh host2 ";

$ssh_connection_string = "";

ini_set('memory_limit', 67108864);

///////////////////////////////////////////////////////////////////// Functions
function stripLineBreaks($str1, $marker){
  if (!$marker)
    return preg_replace("/(\r|\n)/", ', ', $str1);
  else
    return preg_replace("/(\r|\n)/", $marker, $str1);
}

function checkTime($str){
  $months['Jan'] = '01';
  $months['Feb'] = '02';
  $months['Mar'] = '03';
  $months['Apr'] = '04';
  $months['May'] = '05';
  $months['Jun'] = '06';
  $months['Jul'] = '07';
  $months['Aug'] = '08';
  $months['Sep'] = '09';
  $months['Oct'] = '10';
  $months['Nov'] = '11';
  $months['Dec'] = '12';

  if (is_int($str))
    return date("d/m H:i:s", $job['start_time']);
  else{
    $start_time = explode(" ", $str);

    if (intval($start_time[2]))
      return $start_time[2] . "/" . $months[$start_time[1]] . " " . $start_time[3];
    else
      return $start_time[3] . "/" . $months[$start_time[1]] . " " . $start_time[4];
  }
}

//////////////////////////////////////////////////////////////// Work variables
$states['C'] = "finished";
$states['E'] = "being finished";
$states['H'] = "blocked";
$states['Q'] = "queued";
$states['R'] = "running";
$states['T'] = "being moved";
$states['W'] = "waiting";
$states['S'] = "suspended";

$i = 0;
foreach ($clusters as $cluster){
  $clustersInfo[$cluster]['name'] = $cluster;
  $clustersInfo[$cluster]['shared'] = 0;
  $clustersInfo[$cluster]['free'] = 0;
  $clustersInfo[$cluster]['exclusive'] = 0;
  $clustersInfo[$cluster]['separated'] = 0;
  $i++;
}

//////////////////////////////////////////////////////////// Commandline parser
$i = 0;

foreach ($_SERVER['argv'] as $key => $val){
  if ($_SERVER['argc']>1){
    if (in_array($val, $clusters))
      $clustersToShow[] = $val;
    if (trim(strtoupper($val)) == "ALL")
      $clustersToShow = $clusters;
  }	
}
if (!isset($clustersToShow) || !count($clustersToShow)){
  $clustersToShow = $clusters;
//   echo "Usage:\n";
//   echo $argv[0] . " [clustername] | [all]\n";
//   exit(1);
 }

if ($staticTest)
  $commandLine = "cat offlineFiles/group";
else
  $commandLine = $ssh_connection_string . $ypcat_path  . " group";

if ($ssh_connection_string)
  echo "Please wait... Gathering remote cluster information...";

$groupsInfo = `$commandLine`;
if ($staticTest)
  $commandLine = "cat offlineFiles/passwd";
else
  $commandLine = $ssh_connection_string . $ypcat_path .  " passwd";
$usersInfo = `$commandLine`;
if ($staticTest)
  $commandLine = "cat offlineFiles/pbsnodes";
else
  $commandLine = $ssh_connection_string . $pbsnodes_path;
$nodes = `$commandLine`;
if ($staticTest)
  $commandLine = "cat offlineFiles/qstat-f1";
else
  $commandLine = $ssh_connection_string . $qstat_path . " -f1";
$qstat = `$commandLine`;

//////////////////////////////////////////////////////// Informacoes dos grupos
$groupsInfo = stripLineBreaks($groupsInfo, "<|>");
$groupsInfo = explode("<|>", $groupsInfo);
while (list($key, $group) = each($groupsInfo)){
  $group = explode(":", $group);
  if (count($group)>1){
    $groups[$group[2]]['name']  = $group[0];
    $groups[$group[2]]['id']  = $group[2];
  }
 }
// $groups[group id]['group name']
// $groups[group id]['grop id']

///////////////////////////////////////////////// Informacoes sobre os usuarios
$usersInfo = stripLineBreaks($usersInfo, "<|>");
$usersInfo = explode("<|>", $usersInfo);
while (list($key, $user) = each($usersInfo)){
  $user = explode(":", $user);
  $users[$user[0]]['username']  = $user[0];
  if (count($user)>1){
    $users[$user[0]]['groupid']  = $user[3];
    if (isset($groups[$user[3]]))
      $users[$user[0]]['groupname']  = $groups[$user[3]]['name'];
  }
 }
// $users[user id]['user name']
// $users[user id]['grop id']
// $users[user id]['grop name']

///////////////////////////////////////////////////// Informacoes sobre os nohs


$nodes = stripLineBreaks($nodes, "<|>");
$nodes = explode("<|><|>", $nodes);




$i = 0; $j = 0;
foreach ($nodes as $node){
  $node = explode("<|>", $node);
  $nodesInfo[$i]['clusterName'] = $node[0];
  foreach ($node as $value){
    $temp = explode("=", trim($value));
    if (count($temp)>1){
      $nodesInfo[$i][trim($temp[0])] = trim($temp[1]);
    }
    if (isset($nodesInfo[$i]['jobs'])){
      $jobsInfo = explode(",", $nodesInfo[$i]['jobs']);
      $j = 0;
      foreach($jobsInfo as $jobInfo){
        if (trim($jobInfo)){
          $nodesInfo[$i]['jobsInfo'][$j] = $jobInfo;      
          $j++;
        }
      }
    }
    $nodesInfo[$i]['nroJobs'] = $j;  
  }
  $i++;
}
$i = 0;
foreach ($nodesInfo as $node){
   if (  isset($node['jobsInfo']) )
     if ( isset($node['np']) && (intval(count($node['jobsInfo'])) < intval($node['np'])) && intval($node['nroJobs']) )
       $node['state'] = "shared";
   $clusterName = preg_replace("/\d.*/", '', $node['clusterName']);
   if ($clusterName)
     $ladNodes[$clusterName][] = $node;
}
///////////////////////////////////////////////////// Informacoes sobre os jobs
$qstat = stripLineBreaks($qstat, "<|>");
$qstat = explode("Job Id: ", $qstat);

foreach ($qstat as $job){
  $jobs[] = explode("<|>", $job);
}

while (list($key, $job) = each($jobs)){
  $jobId = explode(".", $job[0]);
  $jobs[$key]['id'] = $jobId[0];
  foreach ($job as $property){
    $temp = explode(" = ", $property);
    if (count($temp)>1)
      $jobs[$key][trim($temp[0])] = $temp[1];
  }
  if (isset($jobs[$key]['Resource_List.nodes'])){
    $nodes = explode(":", $jobs[$key]['Resource_List.nodes']);
    foreach($nodes as $node){
      if (strpos("_" . $node, "cluster-"))
        $jobs[$key]['cluster'] = str_replace("cluster-", "", $node);
      else
        if (strpos("_" . $node, "ppn="))
          $jobs[$key]['ppn'] = str_replace("ppn=", "", $node);
        else
          $jobs[$key]['nodes'] = $node;
    }
  }
 }

///////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////// Monta a informacao para apresentar

echo "\n" . $columnDelimiter;
if ($showVersion)
  echo "========================================[Torque qstat/pbsnodes wrapper v0.9]==";
 else
   echo "##############################################################################";
echo $columnDelimiter . "\n";

echo $columnDelimiter;
$headerInfo = " System time: " . date('M  d H:i:s', time());
echo $headerInfo;
for ($j=0;$j<79 - strlen($headerInfo); $j++) echo " ";
echo $columnDelimiter;

foreach ($clustersToShow as $clusterToShow){ 
  $shared = 0;
  $exclusive = 0;
  $free = 0;
  $separated = 0;
  if (isset($ladNodes[$clusterToShow]))
    foreach($ladNodes[$clusterToShow] as $node){
      switch ($node['state']){
      case "free":
	$free++;
	break;
      case "job-exclusive":
	$exclusive++;
	break;
      case "down,job-exclusive":
	$separated++;
	break;
      case "shared":
	$shared++;
	break;
      }
    }
  /* a partir daqui trocar os "" . $columnDelimiter . "" por " " */
  echo "\n" . $columnDelimiter;
  echo "##############################################################################";
  echo $columnDelimiter . "\n";
  $headerInfo  = "" . $columnDelimiter . " Cluster: " . $clusterToShow . " - ";
  $headerInfo .= $free . " Nodes Free / ";
  $headerInfo .= $exclusive . " Exclusive / ";
  $headerInfo .= $shared . " Shared / ";
  $headerInfo .= $separated . " Separated";
  echo $headerInfo;
  for ($j=0;$j<79 - strlen($headerInfo); $j++) echo " ";
  echo "" . $columnDelimiter . "\n" . $columnDelimiter . "";
  $i = 0; $toEcho = ""; $k=0;
  //if (isset($ladNodes))
    $nroNodes = count($ladNodes[$clusterToShow]);
  //else
  //  $nroNodes = 0;
  if (isset($ladNodes[$clusterToShow]))
    foreach($ladNodes[$clusterToShow] as $node){
      //echo $node['clusterName'] . ": " .  $node['state'] . "\n ";
      $toEcho .= sprintf(" %11s: %9s   ", 
			 $node['clusterName'], 
			 str_replace("down,exclusive", "separated", str_replace("job-exclusive", "exclusive", $node['state'])));
      $i++;
      if ( $i>2 ) {
	echo $toEcho;
	for ($j=0;$j<77 - strlen($toEcho); $j++) echo " ";
	echo "" . $columnDelimiter . "";
	$k++;
	if ($k<=intval($nroNodes/3)) echo "\n" . $columnDelimiter . "";
	$i = 0;
	$toEcho = "";
      }
      if (($nroNodes-(($k*3)+$i))==0 ) {
	echo $toEcho;
	if (strlen($toEcho)){
	  for ($j=0;$j<77 - strlen($toEcho); $j++) echo " ";
	  echo " " . $columnDelimiter . "";
	}
      }
    }
  echo "\n";
  $running = 0;
  foreach ($jobs as $job)
    if ( isset($job['cluster']) && 
	 (strtoupper($job['cluster']) == strtoupper($clusterToShow)) && 
	 $job['job_state'] == "R")
      $running++;  
  echo "" . $columnDelimiter;
  echo "##############################################################################";
  echo $columnDelimiter . "\n";
  echo "" . $columnDelimiter;
  echo "                                                                              ";
  echo $columnDelimiter . "\n";       
  if ($running){
    echo "" . $columnDelimiter;
    echo "Jobs running                                                                  ";
    echo $columnDelimiter . "\n";
    printf("" . $columnDelimiter . "%6s" . 
	   $columnDelimiter . "%9s" . 
	   $columnDelimiter . "%7s" . 
	   $columnDelimiter . "%10s" . 
	   $columnDelimiter . "%10s" . 
	   $columnDelimiter . "%10s" . 
	   $columnDelimiter . "%5s" . 
	   $columnDelimiter . "%14s" . 
	   $columnDelimiter . "\n", 
	   "Job Id", "Username", "Group", "Job name", "Reserved", "Elapsed", "Cores", "Started at");
    echo "" . $columnDelimiter;
    echo "------ --------- ------- ---------- ---------- ---------- ----- --------------";
    echo $columnDelimiter . "\n";
    foreach ($jobs as $job){
      if (isset($job['Job_Owner']))
	$userName = preg_replace("/@.*/", '', $job['Job_Owner']);
      if ( isset($job['cluster']) && 
	   (strtoupper($job['cluster']) == strtoupper($clusterToShow)) && 
	   $job['job_state'] == "R"){
	$userName = preg_replace("/@.*/", '', $job['Job_Owner']);

	printf("" . $columnDelimiter . "%6s" . 
	       $columnDelimiter . "%9s" . 
	       $columnDelimiter . "%7s" . 
	       $columnDelimiter . "%10s" . 
	       $columnDelimiter . "%10s" . 
	       $columnDelimiter . "%10s" . 
	       $columnDelimiter . "%02dx%02d" . 
	       $columnDelimiter . "%14s" . 
	       $columnDelimiter . "\n", 
	       $job['id'], substr($userName, 0, 9), 
	       substr($users[$userName]['groupname'], 0, 7),
	       substr($job['Job_Name'], 0, 10), 
               isset($job['Resource_List.walltime']) ? $job['Resource_List.walltime'] : "-",
               isset($job['resources_used.walltime']) ? $job['resources_used.walltime'] : "-",	       
               $job['nodes'], 
	       $job['ppn'], checkTime($job['start_time']));
      }
    }
  }
  else{
    echo "" . $columnDelimiter;
    echo "None running jobs                                                             ";
    echo $columnDelimiter . "\n";
  }
  $quewed = 0;
  foreach ($jobs as $job)
    if ( isset($job['cluster']) && 
         (strtoupper($job['cluster']) == strtoupper($clusterToShow)) && 
         ($job['job_state'] == "Q" || $job['job_state'] == "W") )
      $quewed++;  
  echo "" . $columnDelimiter;
  echo "                                                                              ";
  echo $columnDelimiter . "\n";
  if ($quewed){
    echo "" . $columnDelimiter;
    echo "Queued jobs                                                                   ";
    echo $columnDelimiter . "\n";
    printf("" . $columnDelimiter . "%6s" . 
	   $columnDelimiter . "%9s"  . 
	   $columnDelimiter . "%7s"  . 
	   $columnDelimiter . "%10s" . 
	   $columnDelimiter . "%10s" . 
	   $columnDelimiter . "%10s" . 
	   $columnDelimiter . "%5s"  . 
	   $columnDelimiter . "%14s" . 
	   $columnDelimiter . "\n", 
	   "Job Id", "Username", "Group", "Job name", "Reserved", "Status", "Cores", "Scheduled to");
    echo "" . $columnDelimiter;
    echo "------ --------- ------- ---------- ---------- ---------- ----- --------------";
    echo $columnDelimiter . "\n";
    foreach ($jobs as $job){
      if (isset($job['Job_Owner']))
	$userName = preg_replace("/@.*/", '', $job['Job_Owner']);
      if ( isset($job['cluster']) && 
	   (strtoupper($job['cluster']) == strtoupper($clusterToShow)) && 
	   ($job['job_state'] == "Q" || $job['job_state'] == "W") ){
	$userName = preg_replace("/@.*/", '', $job['Job_Owner']);

	printf("" . $columnDelimiter . "%6s" . 
	       $columnDelimiter . "%9s" . 
	       $columnDelimiter . "%7s" . 
	       $columnDelimiter . "%10s" . 
	       $columnDelimiter . "%10s" . 
	       $columnDelimiter . "%10s" . 
	       $columnDelimiter . "%02dx%02d" . 
	       $columnDelimiter . "%14s" . 
	       $columnDelimiter . "\n", 
	       $job['id'], substr($userName, 0, 9), 
	       isset($users[$userName]['groupname']) ? substr($users[$userName]['groupname'], 0, 7) : "-",
	       substr($job['Job_Name'], 0, 10), 
               isset($job['Resource_List.walltime']) ? $job['Resource_List.walltime'] : "-",
	       $states[$job['job_state']], $job['nodes'], 
	       $job['ppn'], isset($job['Execution_Time']) ? checkTime($job['Execution_Time']) : "-");
      }
    }
  }
  else{
    echo "" . $columnDelimiter;
    echo "None queued jobs                                                              ";
    echo $columnDelimiter . "\n";
  }
  echo "" . $columnDelimiter;
  //echo "------------------------------------------------------------------------------";
  echo $columnDelimiter . "\n";
}
?>
