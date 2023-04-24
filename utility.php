<?php

$tab = "&emsp;&emsp;&emsp;&emsp;";

function inputList($c, $name, $value = "") {
    $query = "select name from Place";
    $s = oci_parse($c, $query);
    oci_execute($s);

    echo "<input list='" . $name . "' name='" . $name . "' value = '" . $value . "' size = 30>";
    echo "<datalist id = '" . $name . "'>";
    while (($row = oci_fetch_array($s, OCI_BOTH)) != false)
        echo "<option value='" . $row[0] . "' />";
    echo "</datalist>";
}

function inputField($title, $name, $msg_after = "", $value = "") {
    echo "<br>" . $title . ": ";
    echo "<input type = 'text' name = '" . $name . "' value = '" . (!empty($_GET[$name]) ? $_GET[$name] : $value) . "'> " . $msg_after . "<br>";
}

function createButton($name, $value) {
    echo "<input type = 'submit' name = '" . $name . "' value = '" . $value . "'>";
}

function execute($c, $q) {
    $s = oci_parse($c, $q);
    oci_execute($s);
    return $s;
}

function getexec($c, $q) {
    $s = execute($c, $q);
    $row = oci_fetch_array($s);
    return $row[0];
}

function filterCheck() {
    if(!empty($_GET['nr_of_results']) && (!is_numeric($_GET['nr_of_results']) || intval($_GET['nr_of_results']) < 0))
        return "Nieprawidłowa ilość wyników";
    
    $min_time = (!empty($_GET['min_time'])) ? $_GET['min_time'] : 0;
    $min_dist = (!empty($_GET['min_distance'])) ? $_GET['min_distance'] : 0;
    
    if(!is_numeric($min_time) || $min_time < 0)
        return "Nieprawidłowy czas minimalny";

    $max_time = (!empty($_GET['max_time'])) ? $_GET['max_time'] : $min_time + 1;
    if(!is_numeric($max_time) || $max_time < 0)
        return "Nieprawidłowy czas maksymalny";

    if($min_time > $max_time)
        return "Czas minimalny nie może być większy niż czas maksymalny";

    if(!is_numeric($min_dist) || $min_dist < 0)
        return "Nieprawidłowa odległość minimalna";
        
    $max_dist = (!empty($_GET['max_distance'])) ? $_GET['max_distance'] : $min_dist + 1;
    if(!is_numeric($max_dist) || $max_dist < 0)
        return "Nieprawidłowa odległość maksymalna";

    if($min_dist > $max_dist)
        return "Odległość minimalna nie może być większa niż odległość maksymalna";
    
    return NULL;
}

function doesPlaceExist($c, $place) {
    $how_many = getexec($c, "SELECT COUNT(*) FROM Place WHERE name = '" . $place . "'");
    return ($how_many != 0);
}

function queryCheck($c, $to_visit) {
    if(!isset($_GET['start']) || !doesPlaceExist($c, $_GET['start']))
        return "Nieprawidłowy punkt początkowy";

    if(!isset($_GET['is_cycle']) && (!isset($_GET['end']) || !doesPlaceExist($c, $_GET['end'])))
        return "Nieprawidłowy punkt końcowy";

    if(!empty($_GET['timelimit']) && (!is_numeric($_GET['timelimit']) || intval($_GET['timelimit']) <= 0))
        return "Nieprawidłowy maksymalny czas obliczeń";

    if(isset($_GET['is_cycle']) && count($to_visit->places) == 0)
        return "Opcja cyklu wymaga co najmniej jednego punktu pośredniego";

    for($i = 0; $i < count($to_visit->places); $i++)
        if(empty($to_visit->places[$i]) || !doesPlaceExist($c, $to_visit->places[$i]))
            return "Nieprawidłowy punkt pośredni nr " . $i;

    return NULL;
}

function formatTime($time) {
    if($time < 60)
        return $time . " min";

    $res = floor($time / 60) . " h";
    if($time % 60 != 0)
        $res = $res . " " . ($time % 60) . " min";
    return $res;
}

function formatDistance($distance) {
    if($distance < 1000)
        return $distance . " m";

    $res = floor($distance / 1000) . " km";
    if($distance % 1000 != 0)
        $res = $res . " " . ($distance % 1000) . " m";
    return $res;
}

?>