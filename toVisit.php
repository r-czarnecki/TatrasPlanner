<?php

include_once "utility.php";

class to_visit {
    public $places;
    private $c;

    function __construct($c) {
        $this->places = array();
        $this->c = $c;
        for($i = 0; isset($_GET["visit" . $i]); $i++)
            array_push($this->places, $_GET["visit" . $i]);

        $operations = array("up", "down", "delete", "new");

        for($i = 0; $i < count($operations); $i++)
            for($j = 0; $j < max(count($this->places), 1); $j++)
                if(isset($_GET[$operations[$i] . $j])) 
                    switch($operations[$i]) {
                        case "up":
                            $tmp = $this->places[$j];
                            $this->places[$j] = $this->places[$j - 1];
                            $this->places[$j - 1] = $tmp;
                            break;
                        case "down":
                            $tmp = $this->places[$j];
                            $this->places[$j] = $this->places[$j + 1];
                            $this->places[$j + 1] = $tmp;
                            break;
                        case "delete":
                            array_splice($this->places, $j, 1);
                            break;
                        case "new":
                            array_splice($this->places, $j, 0, "");
                            break;
                    }
    }

    function add($pos, $elem) {
        $places = array_splice($this->places, $pos, 0, $elem);
    }

    function draw() {
        if(count($this->places) == 0) {
            echo "<br>";
            createButton("new0", "Utwórz punkt pośredni");
            echo "<br>";
            return;
        }

        for($i = 0; $i < count($this->places); $i++) {
            echo "<br>" . $i . ": ";
            inputList($this->c, 'visit' . $i, $this->places[$i]);
            if($i != 0)
                createButton("up" . $i, "^ Do góry");
            if($i != count($this->places) - 1)
                createButton("down" . $i, "v Do dołu");
            createButton("delete" . $i, "- Usuń punkt");
            createButton("new" . $i, "+ Nowy punkt");
            echo "<br>"; 
        }
    }
}

?>