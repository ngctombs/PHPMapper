<?php
foreach (glob("src/*.php") as $class_file) {
    require $class_file;
}

foreach (glob("tests/*.php") as $class_file) {
    require $class_file;
}
$rand_graphs = new MapGenerator(10, 10);
echo json_encode($rand_graphs->randomMap());
die();