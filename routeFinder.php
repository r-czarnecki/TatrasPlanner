<?php

include_once "result.php";
include_once "utility.php";

$path = array();
$visited = array();
$start_time = 0;
$results = 0;
$id = 0;
$time = array();
$path_exists = false;
$total_time = 0;

function dijkstra($c, $place, $goal) {
    global $time, $path_exists;

    $to_check = array();
    $time = array();
    $time[$place] = 0;

    array_push($to_check, $place);

    while(count($to_check) > 0) {
        $current = $to_check[0];
        $pos = 0;
        for($i = 1; $i < count($to_check); $i++)
            if($time[$to_check[$i]] < $time[$current]) {
                $current = $to_check[$i];
                $pos = $i;
            }

        array_splice($to_check, $pos, 1);
        if($current == $goal)
            $path_exists = true;

        $neighbours = array();
        $neighbourstime = array();
        $s = execute($c, "SELECT end_point, time_from FROM Edge WHERE start_point = " . $current);
        while(($row = oci_fetch_array($s, OCI_BOTH))) {
            array_push($neighbours, $row[0]);
            array_push($neighbourstime, $row[1]);
        }

        $s = execute($c, "SELECT start_point, time_to FROM Edge WHERE end_point = " . $current);
        while(($row = oci_fetch_array($s, OCI_BOTH))) {
            array_push($neighbours, $row[0]);
            array_push($neighbourstime, $row[1]);
        }

        for($i = 0; $i < count($neighbours); $i++) {
            if(!isset($time[$neighbours[$i]])) {
                $time[$neighbours[$i]] = $time[$current] + $neighbourstime[$i];
                array_push($to_check, $neighbours[$i]);
            }
            else if($time[$current] + $neighbourstime[$i] < $time[$neighbours[$i]])
                $time[$neighbours[$i]] = $time[$current] + $neighbourstime[$i];
        }
    }
}

function dfs($c, $place, $goal, $length, $segment, $timelimit) {
    global $path, $start_time, $results, $id, $visited, $time, $total_time;
    
    if($results != 0 && ($results >= 20 || microtime(true) - $start_time > min(10, $timelimit) || $total_time > $timelimit))
        return;

    if($length != 0 && $place == $goal) {
        execute($c, "INSERT INTO Route VALUES (" . $id . ", " . $segment . ", 0, 0, 0, 0)");
        for($i = 0; $i < count($path); $i++) {
            execute($c, "INSERT INTO RouteSegment VALUES (" . $path[$i] . ", " . $id . ", " . ($i + 1) . ")");
        }
        execute($c, "INSERT INTO RouteSegment VALUES (" . $goal . ", " . $id . ", " . (count($path) + 1) . ")");
        $id++;
        $results++;
        return;
    }

    if(array_key_exists($place, $visited)  || ($results != 0 && $length > 30))
        return;
    
    $visited[$place] = 1;
    array_push($path, $place);
    $neighbours = array();
    $times = array();
    $added_neighbours = array();
    $s = execute($c, "SELECT end_point, time_to FROM Edge WHERE start_point = " . $place);
    while(($row = oci_fetch_array($s, OCI_BOTH))) 
        if(!isset($added_neighbours[$row[0]])) {
            array_push($neighbours, $row[0]);
            array_push($times, $row[1]);
            $added_neighbours[$row[0]] = 1;
        }

    $s = execute($c, "SELECT start_point, time_from FROM Edge WHERE end_point = " . $place);
    while(($row = oci_fetch_array($s, OCI_BOTH))) 
        if(!isset($added_neighbours[$row[0]])) {
            array_push($neighbours, $row[0]);
            array_push($times, $row[1]);
            $added_neighbours[$row[0]] = 1;
        }

    while(count($neighbours) > 0) {
        $tmp = $neighbours[0];
        $pos = 0;
        for($i = 1; $i < count($neighbours); $i++)
            if($time[$neighbours[$i]] + $times[$i] < $time[$tmp] + $times[$pos]) {
                $tmp = $neighbours[$i];
                $pos = $i;
            }
        
        array_splice($neighbours, $pos, 1);
        dfs($c, $tmp, $goal, $length + 1, $segment, $timelimit);
    }

    array_pop($path);
    unset($visited[$place]);
}

function combineResults($how_many, $begin, $end) {
    global $c, $results;
    $last_result = new Result($c, $begin, $end);
    $res = array();
    $chosen = array();
    $ids = array();
    $times = array();
    for($i = 0; $i < $how_many; $i++) {
        array_push($ids, array());
        array_push($times, array());
        array_push($chosen, 0);
        $s = execute($c, "SELECT id, time FROM Route WHERE segment = " . $i . " ORDER BY time");
        while(($row = oci_fetch_array($s, OCI_BOTH))) {
            array_push($ids[$i], $row[0]);
            array_push($times[$i], $row[1]);
        }
        $last_result->replace($i, $ids[$i][0]);
    }
    array_push($res, $last_result);

    while(true) {
        $break = true;
        $min_dif = NULL;
        $min_id = NULL;
        for($i = 0; $i < $how_many; $i++) {
            $j = $chosen[$i];
            if($j + 1 == count($ids[$i]))
                continue;
            $break = false;
            
            $dif = $times[$i][$j + 1] - $times[$i][$j];
            if($min_dif == NULL || $min_dif > $dif) {
                $min_dif = $dif;
                $min_id = $i;
            }
        }

        if($break)
            break;

        $chosen[$min_id]++;
        $tmp = new Result($c, $begin, $end);
        $tmp->copyFrom($last_result);
        $tmp->replace($min_id, $ids[$min_id][$chosen[$min_id]]);
        $last_result = $tmp;
        array_push($res, $last_result);
    }

    $_SESSION['Results'] = $res;
}

function getResult($to_visit, $timelimit) {
    global $c, $path, $start_time, $results, $id, $visited, $path_exists, $total_time;
    execute($c, "DELETE Route");
    execute($c, "DELETE RouteSegment");
    $id = 0;
    $total_time = 0;
    $first_place = $to_visit->places[0];
    $last_place = $first_place;
    
    for($i = 0; $i < count($to_visit->places) - 1; $i++) {
        $path = array();
        $visited = array();
        $results = 0;
        $path_exists = false;
        $last_place = $to_visit->places[$i + 1];

        $start = getexec($c, "SELECT id FROM Place WHERE name = '" . $to_visit->places[$i] . "'");
        $end = getexec($c, "SELECT id FROM Place WHERE name = '" . $to_visit->places[$i + 1] . "'");

        dijkstra($c, $end, $start);
        if(!$path_exists)
            return false;

        $start_time = microtime(true);
        dfs($c, $start, $end, 0, $i, $timelimit);
        $total_time += microtime(true) - $start_time;
    }
    combineResults(count($to_visit->places) - 1, $first_place, $last_place);
    return true;
}

?>