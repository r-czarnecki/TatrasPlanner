<?php

include_once "utility.php";
include_once "toVisit.php";
include_once "routeFinder.php";
include_once "result.php";
include_once "query.php";

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 'On');

if(isset($_POST['login'])) {
    $_SESSION['username'] = $_POST['username'];              
    $_SESSION['password'] = $_POST['password'];   
}       
 
$c = oci_connect($_SESSION['username'], $_SESSION['password'], NULL, 'AL32UTF8');
if (!$c) {
    $m = oci_error();
    trigger_error('Could not connect to database: '. $m['message'], E_USER_ERROR);
}

$visit = new to_visit($c);
echo "<form id='main_form' method = 'get'>";
echo "<fieldset>";
echo "<legend>Wyszukaj trasę</legend>";
echo "<br>Punkt początkowy: ";
inputList($c, "start", !empty($_GET['start']) ? $_GET['start'] : "");
echo "<br>";
$checked = "";
if(isset($_GET['is_cycle']))
    $checked = "checked";
else {
    echo "<br>Punkt końcowy: ";
    inputList($c, "end", !empty($_GET['end']) ? $_GET['end'] : "");
    echo "<br>";
}
echo "<br><input type = 'checkbox' name = 'is_cycle' value = 'true' onchange=\"document.getElementById('main_form').submit()\"" . $checked . "/>";
echo "Czy cykl?<br>";
inputField("Maksymalna ilość wyników", "nr_of_results", "", "5");
inputField("Czas minimalny (opcjonalne)", "min_time", "godzin");
inputField("Czas maksymalny (opcjonalne)", "max_time", "godzin");
inputField("Odległość minimalna (opcjonalne)", "min_distance", "kilometrów");
inputField("Odległość maksymalna (opcjonalne)", "max_distance", "kilometrów");
inputField("Maksymalny czas obliczeń", "timelimit", "sekund", "20");
echo "<br><input type = 'submit' name = 'filter' value = 'Filtruj'";

echo "<br><fieldset>";
echo "<legend>Punkty pośrednie:</legend>";
$visit->draw();
echo "</fieldset><br>";
echo "<input type='hidden' name='show_results'>";
echo "<input type = 'submit' name = 'search' value = 'Szukaj trasy'>";
echo "</fieldset>";

if(!isset($_GET['show_results'])) {
    unset($_SESSION['Results']);
    exit();
}

if(isset($_GET['filter']) || isset($_GET['search'])) {
    $msg = filterCheck();
    if($msg != NULL) {
        echo "<h3><font color = 'red'>BŁĄD: " . $msg . "</font></h3>";
        exit();
    }
}

if(isset($_GET['search'])) {
    unset($_SESSION['Results']);
    $msg = queryCheck($c, $visit);
    if($msg != NULL) {
        echo "<h3><font color = 'red'>BŁĄD: " . $msg . "</font></h3>";
        exit();
    }

    $visit->add(0, $_GET['start']);
    $visit->add(count($visit->places), isset($_GET['end']) ? $_GET['end'] : $_GET['start']);
    if(!getResult($visit, (!empty($_GET['timelimit'])) ? $_GET['timelimit'] : 20))
        echo "<h3>Nie znaleziono żadnej trasy</h3>";
}

if(isset($_SESSION['Results'])) {
    $res = $_SESSION['Results'];
    $details = false;
    for($i = 0; $i < count($res); $i++)
        if(isset($_GET['details' . $i])) {
            $res[$i]->details($c, $i + 1);
            $details = true;
        }

    if(!$details)
        drawResults($_SESSION['Results'], (!empty($_GET['nr_of_results'])) ? $_GET['nr_of_results'] : 5);
}
echo "</form>";

?>