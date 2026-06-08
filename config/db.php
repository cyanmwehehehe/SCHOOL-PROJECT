<?php
//OLTP connection
function getOLTP() {
    $conn = new mysqli("localhost", "root", "", "canteen_oltp");
    if ($conn->connect_error) {
        die("OLTP Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

//OLAP connection
function getOLAP() {
    $conn = new mysqli("localhost", "root", "", "canteen_olap");
    if ($conn->connect_error) {
        die("OLAP Connection failed: " . $conn->connect_error);
    }
    return $conn;
}
?>