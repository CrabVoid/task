<?php
// //$fruit = array("Ābols", "Banāns", "Ķirsis", "Dateles");

// //var_dump($fruit);

// #Izveidojiet asociatīvo masīvu ar nosaukumu $ages ar šādiem atslēgu-vērtību pāriem:
// #"Alise" => 30
// #"Bobs" => 25
// #"Čārlijs" => 35
// #Izdrukājiet "Boba" vecumu.

// //$age = array("Alise" => 30, "Bobs" => 25, "Čārlijs" => 35);

// //echo $age["Bobs"];

// $people = array(
//     array("Džons", 20, array_sum([90, 85, 88]) / count([90, 85, 88])),
//     array("Džena", 22, array_sum([92, 80, 84]) / count([92, 80, 84])),
//     array("Džo", 21, array_sum([78, 85, 90]) / count([78, 85, 90]))
// );
// echo $people[0][0] . $people[0][1] . $people[0][2];

// for ($row = 0; $row < 3; $row++) {
//          echo "<p><b>Row number $row</b></p>";
//          echo "<ul>";
//          for ($col = 0; $col < 3; $col++) {
//              echo "<li>" . $people[$row][$col] . "</li>";
//      }
//       echo "</ul>";
//  }


// $orders = array(
//     array(1, 'Alise', 'Grāmata', 'Pildspalva'),
//     array(2, 'Bobs', 'Dators', 'Pelīte'),
//     array(3, 'Čārlijs', 'Kafijas automāts')
// );

// echo $orders[0][0] . $orders[0][1] . $orders[0][2] . $orders[0][3];

//Izveidojiet daudzdimensiju masīvu ar nosaukumu $groupedOrders, kur katrs pasūtījums satur savu identifikatoru, klienta vārdu un masīvu ar produktiem.

// $groupedOrders = array(
//     array(
//         1,
//         'Alise',
//         'Grāmata',
//         'Pildspalva'
//     ),
//     array(
//         'id' => '12312332',
//         'name' => 'Bobs',
//         'products' => []
//     ),
//     array(
//         3,
//         'Čārlijs',
//         'Kafijas automāts'
//     ),
// );

$groupedOrders = [];
$orders = [
    ['order_id' => 1, 'customer' => 'Alise', 'product' => 'Grāmata'],
    ['order_id' => 1, 'customer' => 'Alise', 'product' => 'Pildspalva'],
    ['order_id' => 2, 'customer' => 'Bobs', 'product' => 'Dators'],
    ['order_id' => 2, 'customer' => 'Bobs', 'product' => 'Pelīte'],
    ['order_id' => 3, 'customer' => 'Čārlijs', 'product' => 'Kafijas automāts'],
];


echo "order id:" . $groupedOrders[0][0] . "_" . "customer:" . $groupedOrders[0][1] . "_" . "product:" . $groupedOrders[0][2];

for ($row = 0; $row < count($groupedOrders); $row++) {
    echo "<p><b>Row number $row</b></p>";
    echo "<ul>";
    for ($col = 0; $col < 4; $col++) {
        echo "<li>" . $groupedOrders[$row][$col] . "</li>";
    }
    echo "</ul>";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>

</body>

</html>