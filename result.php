<?php

include_once "utility.php";

class Result {
    public $routes;
    public $distance;
    public $time;
    public $height_up;
    public $height_down;
    public $start;
    public $end;
    private $c;

    function __construct($c, $start, $end) {
        $this->c = $c;
        $this->start = $start;
        $this->end = $end;
        $this->routes = array();
        $this->distance = 0;
        $this->time = 0;
        $this->height_up = 0;
        $this->height_down = 0;
    }

    function add($id, $substract = false) {
        $s = execute($this->c, "SELECT distance, time, height_to_go_up, height_to_go_down FROM Route WHERE id = " . $id);
        $row = oci_fetch_array($s, OCI_BOTH);
        $add = ($substract) ? -1 : 1;
        $this->distance += $add * $row[0];
        $this->time += $add * $row[1];
        $this->height_up += $add * $row[2];
        $this->height_down += $add * $row[3];
    }

    function replace($pos, $id) {
        if(isset($this->routes[$pos]))
            $this->add($this->routes[$pos], true);
        $this->routes[$pos] = $id;
        $this->add($id);
    }

    function copyFrom($r) {
        $this->c = $r->c;
        $this->start = $r->start;
        $this->end = $r->end;
        $this->routes = $r->routes;
        $this->distance = $r->distance;
        $this->time = $r->time;
        $this->height_up = $r->height_up;
        $this->height_down = $r->height_down;
    }

    function filter($query) {
        if($query->min_distance > $this->distance || ($query->max_distance != -1 && $this->distance > $query->max_distance))
            return false;

        if($query->min_time > $this->time || ($query->max_time != -1 && $this->time > $query->max_time))
            return false;

        return true;
    }

    function details($c, $nr) {
        global $tab;
        $count = 0;
        echo "<h3>Trasa nr " . $nr . " z " . $this->start . " do " . $this->end . ": " . $tab;
        createButton("back", "Powrót");
        echo "</h3>";

        echo "<fieldset>";
        echo "<legend>Podsumowanie</legend>";
        echo "<p align = 'center'>Dystans: " . formatDistance($this->distance) . $tab . $tab . "Czas przejścia: " . formatTime($this->time) . $tab . $tab;
        echo "Suma podejść w górę: " . formatDistance($this->height_up) . $tab . $tab . "Suma zejść w dół: " . formatDistance($this->height_down) . "</p>";
        echo "</fieldset><br>";


        echo "<h4>Kolejne odcinki trasy:</h4>";
        for($i = 0; $i < count($this->routes); $i++) {
            $id = $this->routes[$i];
            $how_many = getexec($c, "SELECT COUNT(*) FROM RouteSegment WHERE route_id = " . $id);
            $place = getexec($c, "SELECT place_id FROM RouteSegment WHERE route_id = ". $id . " AND position = 1");
            $s = execute($c, "SELECT id, name, height FROM Place WHERE id = " . $place);
            $place = oci_fetch_array($s);

            if($i != 0) {
                echo "<fieldset>";
                echo "<legend>Punkt pośredni osiągnięty!</legend>";
                echo "<h3 align='center'>Dotarłeś do punktu pośredniego " . $place[1] . "!<h3>";
                echo "</fieldset>";
            }
            
            for($j = 1; $j < $how_many; $j++) {
                $count++;
                $next = getexec($c, "SELECT place_id FROM RouteSegment WHERE route_id = ". $id . " AND position = " . ($j + 1));
                $s = execute($c, "SELECT id, name, height FROM Place WHERE id = " . $next);
                $next = oci_fetch_array($s);

                $s = execute($c, "SELECT time_to, distance, colour FROM Edge WHERE start_point = " . $place[0] . " AND end_point = " . $next[0]);
                if(!($edge = oci_fetch_array($s))) {
                    $s = execute($c, "SELECT time_from, distance, colour FROM Edge WHERE start_point = " . $next[0] . " AND end_point = " . $place[0]);
                    $edge = oci_fetch_array($s);
                }

                echo "<fieldset>";
                echo "<legend>Odcinek nr " . $count . "</legend>";
                echo "Idź z <b>" . $place[1] . "</b> do <b>" . $next[1] . "</b>.";
                $colour = $edge[2];
                switch($colour) {
                    case "czerwony":
                        $colour = "#ff0000";
                        break;
                    case "zielony":
                        $colour = "#009933";
                        break;
                    case "niebieski":
                        $colour = "#0000ff";
                        break;
                    case "żółty":
                        $colour = "#ffff00";
                        break;
                    case "czarny":
                        $colour = "#000000";
                        break;
                    default:
                        $colour = NULL;
                } 

                if($colour == NULL)
                    echo "<br>Kolor szlaku: " . $edge[2] . "<br>";
                else
                    echo "<div style='display:table'><p style='display:table-cell;vertical-align:middle'>Kolor szlaku: </p><div style='height:20px;width:20px;background-color:" . $colour . ";display:inline-block'></div></div>";

                $sign = '+';
                if($place[2] > $next[2])
                    $sign = '-';

                echo "Czas przejścia: " . formatTime($edge[0]) . $tab . "Odległość: " . formatDistance($edge[1]);
                echo "<br>Wysokość punktu początkowego: " . $place[2] . " metrów" . $tab . "Wysokość punktu końcowego: " . $next[2] . " metrów" . $tab . "Przewyższenie: " . $sign . abs($place[2] - $next[2]) . " metrów";
                echo "</fieldset>";

                $place = $next;
            }
        }

        echo "<fieldset>";
        echo "<legend>Cel osiągnięty!</legend>";
        echo "<h3 align = 'center'>Dotarłeś do celu w " . $place[1] . "!</h3>";
        echo "</fieldset>";
    }
}

function drawResults($res, $how_many) {
    global $tab;
    $query = new Query(((empty($_GET['min_time'])) ? 0 : $_GET['min_time']), 
                       ((empty($_GET['max_time'])) ? -1 : $_GET['max_time']),
                       ((empty($_GET['min_distance'])) ? 0 : $_GET['min_distance']),
                       ((empty($_GET['max_distance'])) ? -1 : $_GET['max_distance']));
    $results = 0;
    for($i = 0; $i < count($res); $i++) {
        $current = $res[$i];
        if(!$current->filter($query))
            continue;
        $results++;

        if($results == 1)
            echo "<h3>Wyniki:</h3>";
        echo "<fieldset>";
        echo "<legend>Trasa nr " . $results . "</legend>";
        echo "<p align = 'center'>Dystans: " . formatDistance($current->distance) . $tab . $tab . "Czas przejścia: " . formatTime($current->time) . $tab . $tab;
        echo "Suma podejść w górę: " . formatDistance($current->height_up) . $tab . $tab . "Suma zejść w dół: " . formatDistance($current->height_down) . "</p>";
        echo "<p align='center'>";
        createButton("details" . $i, "Szczegóły");
        echo "</p>";
        echo "</fieldset>";

        if($results == $how_many)
            break;
    }
    if($results == 0) {
        echo "<h3>Nie znaleziono żadnej trasy</h3>";
        exit();
    }
}

?>