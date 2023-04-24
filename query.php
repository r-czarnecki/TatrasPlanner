<?php

class Query {
    public $min_time;
    public $max_time;
    public $min_distance;
    public $max_distance;

    function __construct($min_time, $max_time, $min_distance, $max_distance) {
        $this->min_time = 60 * $min_time;
        $this->max_time = ($max_time == -1) ? $max_time : 60 * $max_time;
        $this->min_distance = 1000 * $min_distance;
        $this->max_distance = ($max_distance == -1) ? $max_distance : 1000 * $max_distance;
    }
}

?>