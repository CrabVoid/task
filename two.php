<?php
$orderFile = 'orders.json';

$orders = [];
if (file_exists($orderFile)) {
    $rawData = json_decode(file_get_contents($orderFile), true);
    if (is_array($rawData)) {
        foreach ($rawData as $entry) {
            if (isset($entry['order_id'], $entry['customer'], $entry['product'])) {
                $orders[] = $entry;
            }
        }
    }
}

function getNextOrderId($orders)
{
    return empty($orders) ? 1 : max(array_column($orders, 'order_id')) + 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order_id'])) {
    $deleteId = (int)$_POST['delete_order_id'];
    $orders = array_filter($orders, fn($order) => $order['order_id'] !== $deleteId);
    file_put_contents($orderFile, json_encode(array_values($orders), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['customer']) && isset($_POST['products'])) {
    $customer = trim($_POST['customer']);
    $products = array_filter($_POST['products'], fn($p) => trim($p) !== '');

    if ($customer && !empty($products)) {
        $existingIds = array_column(array_filter($orders, fn($o) => $o['customer'] === $customer), 'order_id');
        $orderId = empty($existingIds) ? getNextOrderId($orders) : max($existingIds);

        foreach ($products as $product) {
            $orders[] = [
                'order_id' => $orderId,
                'customer' => $customer,
                'product' => trim($product)
            ];
        }

        file_put_contents($orderFile, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

$groupedOrders = [];
foreach ($orders as $order) {
    if (!isset($order['order_id'], $order['customer'], $order['product'])) continue;

    $id = $order['order_id'];
    if (!isset($groupedOrders[$id])) {
        $groupedOrders[$id] = [
            'customer' => $order['customer'],
            'products' => [],
        ];
    }
    $groupedOrders[$id]['products'][] = $order['product'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Order Manager</title>
    <style>
        :root {
            --bg-color: #f0f4f8;
            --text-color: #333;
            --box-bg: white;
            --primary-color: #0066cc;
            --input-bg: white;
            --table-border: #ddd;
            --hover-row: #f9f9f9;
            --delete-color: #dc3545;
        }

        body.dark {
            --bg-color: #121212;
            --text-color: #e0e0e0;
            --box-bg: #1e1e1e;
            --primary-color: #4ea8ff;
            --input-bg: #2b2b2b;
            --table-border: #444;
            --hover-row: #2b2b2b;
            --delete-color: #ff6b6b;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
            transition: background 0.3s, color 0.3s;
        }

        .container {
            max-width: 800px;
            margin: auto;
            background: var(--box-bg);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: var(--primary-color);
            border-bottom: 1px solid var(--table-border);
            padding-bottom: 10px;
        }

        form {
            margin-bottom: 30px;
        }

        input[type="text"] {
            width: calc(100% - 20px);
            padding: 10px;
            margin: 5px 0 10px;
            background: var(--input-bg);
            color: var(--text-color);
            border: 1px solid var(--table-border);
            border-radius: 6px;
        }

        input[type="submit"] {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s ease-in-out;
        }

        input[type="submit"]:hover {
            background-color: #218838;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--table-border);
        }

        tr:hover {
            background-color: var(--hover-row);
        }

        .delete-btn {
            background-color: var(--delete-color);
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            color: white;
            cursor: pointer;
        }

        .delete-btn:hover {
            opacity: 0.85;
        }

        .products-list {
            color: var(--text-color);
        }

        .toggle-btn {
            position: absolute;
            top: 20px;
            right: 30px;
            background-color: #555;
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s ease-in-out;
        }

        .toggle-btn:hover {
            background-color: #333;
        }
    </style>
</head>

<body>
    <button class="toggle-btn" onclick="toggleDarkMode()">Toggle Dark Mode</button>
    <div class="container">
        <h2>Add New Order</h2>
        <form method="POST">
            <label>Customer Name:</label><br>
            <input type="text" name="customer" required><br>

            <label>Products:</label><br>
            <?php for ($i = 0; $i < 5; $i++): ?>
                <input type="text" name="products[]" placeholder="Product <?= $i + 1 ?>"><br>
            <?php endfor; ?>

            <input type="submit" value="Add Order">
        </form>

        <h2>Order Table</h2>
        <table>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Products</th>
                <th>Action</th>
            </tr>
            <?php foreach ($groupedOrders as $orderId => $data): ?>
                <tr>
                    <td><?= htmlspecialchars($orderId) ?></td>
                    <td><?= htmlspecialchars($data['customer']) ?></td>
                    <td class="products-list"><?= htmlspecialchars(implode(', ', $data['products'])) ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="delete_order_id" value="<?= htmlspecialchars($orderId) ?>">
                            <input class="delete-btn" type="submit" value="Delete"
                                onclick="return confirm('Are you sure you want to delete this order?');">
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <!-- 👻 Hidden Scare Container -->
    <div id="jumpscare" style="
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: black;
    z-index: 10000;
    justify-content: center;
    align-items: center;
">
        <img id="scareImg" src="data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxITEhUSExIVFRUVFRoYFxYVFhUXGxcXFhcXGBcYGBUYHSggGBomGxgYITEiJSkrLi4uFx8zODMtNygtLisBCgoKDg0OGxAQGy0lICUtLTUtLS0tLS0yLS8tLS0tLS0rLS0tLTY1LS0tLS0tLS0tLy0tLS0tLS0tLS0tLS0tLf/AABEIASsAqAMBIgACEQEDEQH/xAAcAAAABwEBAAAAAAAAAAAAAAAAAQIDBAUGBwj/xABCEAABAwIEAwYEBAQEAwkAAAABAAIRAyEEEjFBBVFhBhMicYGRBzKhsRRCwdFS4fDxIzNDYoKS0hYkNFNyc4Oisv/EABoBAAIDAQEAAAAAAAAAAAAAAAMEAAEFAgb/xAAwEQACAgEEAQEGBQQDAAAAAAAAAQIRAwQSITFBBRMiUWFxgRQykcHhobHw8SNC0f/aAAwDAQACEQMRAD8A4sgjhGAiAbChCEpGroliYQSoQIU2sqxKCVCOFe0lhPaAYBkc4j6FJS8qKFNpLEpT3TFgIEW3uTJ63j0QyooVUyWJhFCWiIVF2HSoudIa0mAXGBMNFyT0CbS2uI0JFotyOyTChY9np91lyHvM8581skRly85vKYRolRBJCJLRQodCUEEFCx8BLYQJkAyIGtri4j2vzRAKVWwbm0WVSLPc4N65dT7yPRE6A2RQEqETEpqiaZTChCEsBHlXVHNiMqEJzKhClEsbhFCcyoQpRLG0ITjrmTukwpRdiISSE4QihVRdjcIoTkIiFVF2NwiISyEqjSLnBo1Jhc0dWMo4sb/zUirVAJDB4dL7xufW/so0LksSQgjQUOiWxhJAGpsPM6LX9vGMpU8NhmwTTafbST5uzeyz/BcgrMfU+Rhzu6hgzQOpIj1TPFce+vVfWf8AM8zHIbNHQBEk+KAJWyK9qOiYIPIz7FJc9OhlkCToNFWS8dijWqOquDQXGSGNDQNrAaJnKhQFyFIyJvG90bFZra6I+RHki8TBE+pi6kimjDCupQbXBxGfPJE7qLH6IFqk92nzhBkLpuG5vS+vsqa2rk6T3MrSxAtEbzul0DIulliiVq0RunRGLUWVPlqewVBrnHO7K0Akne2w6riXuq2dw950iDUABgepSSE64JJClF2NEIAxolkJDguWdJjcJVakWktdqNUYMXSXkkknU3K4aO0xuEEohBQuyY9piyjB6sWtSnYYHa6JkxNq0Bx5UnyQmsTuZSqmDAiCLjW4g7gj9VHrMIMEJC2pVI0K3RuIKbTtrKtKTbaAgjcT6+ahNbAB1myf4fW8eQ7zHnqmtPkW7a+mKanG1Hcu0SO6R90pwoo+5WntMveV/wCHJsNToomDrOzmk83NgRygn2hXlOkZEayrPE8Mw7y1zZZWIykR4bbdEDPi3Lug2HNtfVmRp4TJImb/ANkusS67iTAAvyaIA9AFbV6JZmYQJCg1aUAR6+fT0VJbEos6ct7ckQH0yY6GbdbXSCxTmvcA5oJAdGYc4MifVMuYutqK3ERzE2WqU5iZcBouJI7TGHBIcE+Wo2FoBkEuBtygTM87x7Ib4CRGXMDRf5uXIfuo5SzJJJ3QyoavyFGyEECEapllyxik02JDaQIg6KZQown4rkzpS4EihIghQOI4dzIMEtG+sdCr6lSUtuHBEESDspl00cq57Ji1UsT46+BmKDw5uyl8EwEvdUIsPl8zb7J/F9nnAg0dJuwnTqDy6LQ4bChjQ3kP6KU0+jnHL73SG9TroSw1HtkVtBH3CnikjqUTr1nzWq0ZCdkTB0QHZjo2/rslY8wQ6xnxDpuPVLoVm5Q2fEQDBib2Fgk4pzGjxFoMaOMWQpqMlz0EhKUXx2RGcSZTlz2uzOkAt3zNywTsIn3WYwL3ucKerjYHr1V605y4eHJMNc0zMi56JrgfD2uzPDyC0OaHC0FwIHrY6c0jmcoyuPkfw7ZQal4KN9ct1uSBH6yn2ODhIRcSwZBOY3aIvqTeVEw9QtgazqqhkafPR1PGmuOyZXpgOIDgRs4AifIfuorqfPVS3Ac1HxNXIJGuyNKkrYKFt0iLiZb6hR6Z25oVqpdE6jf1lCgdTy3Sl2+RulFcCiE2Qnu9GkeqQ4Ii5OOhFQNgRM3nSNoge6JGQiVM7TNPSYptCmmKDVZUGLSijKmxyjSU2lSRUaamspb/AG/VFF2xrLCbJ9U5VSA1U2WkOvo5TrIWK7S8aqVnmmMzWMJGWYkg3JjyWzdTJByxnaJbOziDlJHmsFjC9pcx48bnZnl1ySb6+v1SWpbSSHdJGLbYjh2NcyqKrvG/TxEz4hGYndWuJpuaJcHuJvNiSeapGMggEb3+i3HBMK2rT3cWm19gdt/7rK1GpliSro2dLpYZm2+zGjEuz5m+E8unVXmCLhTEWYDmMfxRqRrF4lXGO4KwFtSLixEaiRc9RdPY/BOANOm0BpGp84seX7FLw10nJJDOT06EYNsx1XxuzHbT7KHWaZ8rKUWEHy/qUsYYvE7D7rR9pGS4M32UoumVuIp2BJ1UWs6VNxbpPQWHkobmqm7LSoRQp5j03SHOOgsClO6WRsw7pjRUnfCI1XLF0sORdBylTCYqOkko+2uAG6+RlyCNwQVNLydo1L8NnAEwQQQeo0lW2DBgZgAdwLj3UKgnOC4h73PFQZYd4RBu0zfkVoJqMvqZjTcX8i/w7FYMpiLqPQplTKgaB4zAjT+tdUZtUK02xirQYJc4hrBuSPus7i+1OGY9rWtc9s+N+kDmBuqTj+Kq1HlpzBrTAbP1sqZrFnT1Tk6gakNGoq5+Ts/DeGMe1znQ5r2AS2Rmb4iIjodepXOOO0m1KxdTY5rSAA105vCMsmfL7q07OcerUqQo5iWiCwgiWjceXRTK81TnJ8eWJMbX15KZZqUNxWDG45NrMfhcIXPDNJP0WtwlFzYLXQDpFpj+Sqn8NcCDBG+8/wAlf4Th2Ic0ZhlZqHu8M9eZ9ln58EstbTUwamGBPfx8y/wOJpvDWOgVB9f2VnjeDd6xtMAAuBIJ0aQSW+n7lZVnd0nhxqTBExN43BWl/wC3uD8IIqNiJMA/qkM/pWqj70YMbh6rglwpIxfFey9anBc2GB0Z+cmJA1iEzxThop0w0R+/VdQp4jA44ZaeJaXwMrSYcDvDHRI8lle0HAapOURaQANT1AVRlkx0p8BVLHlTaOVVMISYG2vT1UPF02iMpm1+Xotrx/hDKNLum+Os4g1HT4W/7RHzEbnRZGrhsji14Nrn2nTyKbx5N3Qrlx7e+iNka1kx4pTDKhBnn9U9i6oMZRAHNNUimrroTpPsmUGZyBuUrieDFIhudr3RcN26EoqAa0Tq7qbfzUdx1NhKPubQFxSYzUpEQSNdNNrI05TpZjCC5tIvk2GCoh0ySPJWFFtNh1Bd9QPJUWBD2gAuBO9tuisGuEzvzT8XaujOmqddk/8AF4sOL6bWPYJJaSQ6ItHVX+A4nSqUWiveo2ZLJZ0gXM2Oh3Cy1DHUSchq5XgwA0mSeSJ0gnT0Q5c+TuP0GuPYc03TT/xKRaLuHny6bqrxFBjmZ2iDE/uOqv2VyGzq0W0s2dAT1TGOwVNwzUxkNswnwmUhkwPdvizSxahbNkkUmDfpGy1/Zmm/MW/MXDwM1BJESQdRErN4TBEErq3AsAMNh2PeIdUuf4g03Ak6SIKNGG73RbLlUPfJuA4RSpAPfBeBE7AcgP1Kzfa/ijiDlacguXc/5BSuJcbkluYX2nQD9VnONY0OaRMgj7pvGvYNOhF3n7Mlj+JE7qtp4uXtnSUrHYU5jl0/qyrzmaQYMjmpm1LmN4sMYouHxMgkHpzW47HdqKzTkrHvWOGUVP8AUYPPUhc6ZWJF7eSlcIxhbUs6w5rMz41kjTHMOSUHwdK4tQfSz1BTaGDR5kkz8oa24MnyWJxrCWl79yRAuSSfuSupdmcdSxFB1CsM4LfIieThoQbhY/iXY5zHn/EL2we7L25Q1o3N9dPNZ2HJHBal2zQmp6prwl2c1xVMgkRF9CmWNK0mM4Wxp8VQa6mB9f01VHiiySGGRNrGYG9+adgpVyL5JQb45Do+IwbmNf2UZ4EkD6/VGK7mghtp1Np99ky3zRLdUwVK7Re8Ppim3vXDSLbknRBVXemACSQNkFas5ZqKZUhqiUyn2uWrZlD7AGnMY/VMVa8puq6U24IEr8BYryyTh6p/keanYcTYqspHdXFBpDWktgulwJ3GmmwkHzXEYpLnsJOTb93pdFvwHhgq4mlTgFpOZ0bNZ4tesR6rWduuIhrA1VXYUxVc4xLaUepcD9hCru2lfO8if6lFwUsly8C2dOUdqMZxHirgbbGf2VfjeLFzZGs+LyPL7KLxPE5icoGUcvaVX0ql1NRl3sYw4tqH3Ywk7xsnCZRBojRJc/kk7DjVd+o0TNCpBT1ZpcLJsYaxJMQqbVchIo0vCePuYWhpgaeXrzXQeM4PEY7DUxSxBpDLJaBao6fzOF5/fRcdwHzLrPAeF/icNlZialB8i7XGHEWALdh5R6rI1z2OLTp32bGkipY3asw/EexeLpgueyY3zAz9ZWdq4V9yGOMcgT9V6AfwF9Oh3dSu7EENJzxBm8NF733K5rTozmYXAASTmEEuOxM2Ig7IGPVzTe7mhiOlx5Y+7wYGsxzSAWkGJgi99E2GE6BXPE8Ee9lxABdEh06aRztunOINpZWCm10gHO5zvmM2ytAsI5krXx7pxTSMfKoY5uLfRT90/lCCkOH9kEwsXHIt7X4I0OBjOJcBNr6RI/XdaCpweuAR3Zj0v6ai/losccbUnw0z1sfpC6LwbiT30Gms4l+XzMDQGN7q8byPOq/L5/Y4yrHHA7/N4/f7GYxfD6gEta0gGHHMLex1sUw6lKt+LA92adFracXAgSYM3O/LpKq8PxJgE1ac1G/k0YTtmIM+g15hGk9srn/H+wMVujUP5/0R8RTLQDmgT1E9eqtMHVdUIvJMAeQ0A5ABG6cSQatzEACBlAuA1osB0U3hmEbTLpaTbwnl+yS/Epzqh/8ACNQtsu+zdbu31BInJMjoR+6q+01eZOhcPbZK4c4NrAmwMh19nWn019FG7TUXCQdQY9/5hMyyqvmKQwS3X4ObVnHRJo+aexlM5j5qO2keS4c7DKDRJNa8JLnJl1J2u6S7NuubRNrRNomyBqAiNz/QUET1UtlXSy4mEguSTgcMQQdTNlufxOIwuGLqTA+Re7paCLOaG6wVT8DfTMDM2eRIBW6q8PeMMahAytbbI5zTLYjQXCw9VmbyJNfqei02KEMLqXfwKT4fdt8U95w1Q96HMJa50ZmZRJl0S4H3Ce4pXmo3vo7tzhmDdb7mLx5clhsTWbSealMGkSTGR5tNnAbwm2ccdlIdlcObgcwnk4X95RJ6b2k98OEL4sqwRcJ9vyDGUzncy5aHODHOkWBt+6ilu0zB1tYwo9bFl1rx5kpVOYk6LXwLb+Yx9TLfxEKqUEgPmbIJm7FKotC6DnJMAac0uj2hqsMsgDkRKZq0iWxIv/V1X0aLnOy9b8grzTlHorDjjLtWWPE+I1KzhWcItAgm0ax91ZVMCXU2V8pzQCQYGaOf3TNJ7AGssco3tPVK4hjH5DfUxYmyFDHJpym/sFnkimo418OS/wADUBIIhp5HbnotAXiMrQYO/PePoub4GvAsSDN7m/Ire9i+JUPkrF17tOci4uQT/Wiz57MT3d0akZ5Msdv9h/BYdpJka2v+gVrxTg/esDhqBHmAI+32V1Vp0KgD2yABJcYMgdeaHDuPYE1BR72SDFhaTYX/AG5pGeoeSVw+4eMFCNSRyLtBwVzAX5Dbkl9m+xNbFUzXfUbh6Oz6gnMOYbIhvUnyXaOOcIYWksAM9J0581k+ItNRrX42sxlKnVzZcmWk5wDBTDg5zjaXHLMEtBhG0+olK4Pv9Sp44SSnHr4FTwH4c02OqfinMqjKDSyucwFpDszjF8wtaSBM3VL287FU8NTbiKBcac5XhxzFrjoZjTa+8c10vheJpVGUmsqtr08pZnbF6jIMHL8pIm3QqqrOwbqFShVrNa1xIDczjEAZXZbnW4npyTK1FKmDel3flOK4XhxdfQKVX4Y5rc4BIm5A06qzbTeCRax2Fo6StBwfAPe4Ay6YMbew1S2bVyg7GsGhhJNP9TAkiNFZcC4xVo1ARUc1jT4mZjlcNILd/NdHZwHBMf8A94Yxxkh7PzDMPCIbcHebLKdouz7WPnDsd3TrhrsxewdZElc/jYZVtkq+oOOhcJXF2ZPH1A5zt5JI0FlDNIqTUZ4rbJDjl1TkKXCFsybtyGmMi5SKtabbI61UHRMkppCL+Q9TdA9UaSNEEZcIC+WWbaicY4D1URjlIYmOxboW7ujd5E+ZlOYxnhAaAByQbChYsOB1JG2tkPLaR3ipySYYfA8tVJwvEzTMgAnrt16qBTdtCmcO4RWr1WUabC57zDR95OwAWfLG2ujThlS6Zo+B9sjTBZWaalNxkwYM202i2ibwWLw/4h1VvegmoXNaS063uY1mbfVdE4F8JMKwB2Kc6o4j5GuLWN6SIc7zn0V6zsDwwaYYSNDnqf8AUgPR3e3iw8dZFNbuaFYPHNptbTdULqjmhxYROWRIHt9lmO2WAwNaoypXrP8AAP8ALbOV7pBu0XmLSCPOytO0nZmjQoVq1Nzw4NHzOz6EAAE3Gw1XOXuJMkyeZSK008E7b/Q0tJix6q5LryaHifagOpChh6IosAhpEBzRp4Wts2089VnGhGE7WpRcaLu2zZx4YY1UUE9oiQYdzVXxLiFekJpgg/8AmAyR+ys6bJSCFFSdtWDz6ZZE0m0/ijJ8K4zWoVxiGOmpMuL/ABB+/jB+b7q34r2sq1nmqTkLrmm2csxEgE2G6b4pwoO8TBDtxs7qOqoKlKobik+BuGO+8J3Zjze9R5vIs+jltv6P4h0cTlJtr9EnFYjNeLqK510/Qozc6bIscNy4Fp6hqFNkcqQynudUoUY6oOKbhBx7Epz3dFxwHstisYHuotGVgu95LWzyDoMny0QWm4Z22o4fhbsNTLnVWy1uZsAmoS5zxFsoLnCDcwOaCz55dTKb2UkviO48WBRW92/kYVjlJoSTA+tlBaU8y61lIy5RJrKo2MxunJlRg2LRCPu5G/WN+ivc1G2VsTlSJjAJkhdU+C2ABFfEOgnMKbOYAaHO9yR7LlPDsOTbxOttJiNzy8l074O1XOpVqP5G1s7naTLWtDeg8Mn2QpZlJUuAscLi7Z08VA8nKZjWNPfSU3XrtZ8xA89/IboYnjFGk35mgAaCLAeVgufdpO3GeWUNNM23v+b7eaBPJGKtjeHT5M0tsFYr4g9oW1GjD0jIJDqh6C7W+9/RYyiwEFN1HkkuJJJuSd09QswnmsvLk9pKz2Gj0y0+JQX3+pDcYPQ/Qqwp3Z5KvxQ8DvJP4DFSz0+qGM3zQVJ8OTuKbeeaiVHw9vWR9ip9cS0FQtMhuC7j2dqtOGoFo8Pc046eAWXEqbZkey2Xw94u/OMK58CSWh1wRqQ3kdT/AGTWkklKn5Mf1nDKeJSXg23H+zGExrC2rSbmIs8AB7T0dr6Gy889quA1MDiH4ep+W7HDR7D8rhy6jYgr0+aRAluvVca+O1Rj/wAK4WqeNrhuAMpg+pK04Sa4PKygn2csYwumNgT6DVMOKBckOKvemytlIBKJJJQUOkhxpUig9oN5jooYclgqzlosg9pbO6RTqEaSCDq1xB+kKNSBMxspuBoz4joPquXKMItyYTHhnnyKMFy/8sm8NxOIzRmLmG5B1J2MxOvOy0fCuI1qLXNp5Gh7szpBcZgDnG31UHCYXEOb/h0XEcw0wesmxTNZ1VrslTMw7giDHPyWXPJNy3JUen0+j00YqEnuf+eC0xWLqVP8yoXDkYA/5RZNAptz2i5I95RU6ofof3/sl23LlmrCEILbFJD1JmY9Br+ycr1JtsEgOIEBM164aL68lydddjWPqQ2NymMDXAkEwm6bH1XhrGl73GGtaCSegC6h2U+GVNoFXHODnHSiHQ0cs7hd56C3mjY8TnwhHU6yGH3pP7HMsXiAXCD8v3VnRxXh6Fdjfw/h92vo0BTjKxvdsgibugDQkQPKd1g+1PYB8l+Ah9J1zSziWH/a5xu3oTI+xZ6WSVrkWwerY5zqSoy87hSLmHtJDhBBBggi4II0Khy5rjTqNLKjbOa4QZ8k7TcWmyV5XZrJqStdGz4T8Q61MZK7O8A/M3wu9Rofost8SMRRxeTENeA4OyltwcpFrHW416pHeNOqi1wwy2xBtBTMNTNcPky9R6RhnzDh/wBDKOwJ2uoVWiQVb8TBomB8rtOnRVRxtja5R8cm/oYmoxqEnGXDRHexBJ726CZTEWhAKUCmwrChwx7gDoToNV2mRoYbUIMyr7s1w04h0G1Nl3R+YnZUFekWOykgkclvexrO7wuc/nJP6D7IOontx2h/0zB7XUU+q5+fy/U6v2Jx1KpQbRytFSk0Nc2Bdos1w5iInkfRK4p2XZXqHMxndwC2RMOvmiLgab7rmFPG1GvFRjyxwMhzdR+46LZ8K+Itg3EUiXfxUov1LDp7oGLURkqkN6z0zLjm54eV/VGY7d9lvwxbUpshhs7LJA5OvpyPpzWRBXTO2Xan8ThnUMO19Nzo/wAR4bZv5gACSCRaVzF3Z7EDSo33I/Rczhjk+JJB9Pl1WOFTxt/PyOd87+I+6cwuGNRwBdlG7yHED0FyeiY4ZhazahDwIA1MG/RXTQl8iUJVdmjp5Szw3NOP1NV2f41gcE0ijSqPqOEOruDZPk2Za0fwj1lLxHaik8+LvDOriOfkcw9AqPhmAp1Acxv0MFQOL4d1HbM3Z3LzRI6iS6SF8npWGTcpOTfxv+DXVe01En83/KdItA5Cwus3xr4gvpPLKDASAP8AEJcLxs0Qfcqup1sw8I99v3VdjOCCo4vzwTtH80XFqlf/ACCWr9IqCentv5tdf0IWN7QV6rzWqvLnuOsDXQCBoAALLc9nOz1bFta6m6mAfmJLvCQYIygXO4E3WBxnBKjRLfHzj/p/mtr2A7SvwjjmaTTqZc7NDMfM2dxy3V5PZSak+jjSx1mGMoRtNVSfXzo6NV7G0G0GsZTa+o0tJe4DM+4zSTtG2iz3xNwDafCnNygOD6bwAIyhrgLdYeffote3tdgizP8AiGC3yuOV3lldBXMPiF2yp1qdSkzxmpbN+UAGYbz010TCcIrgzJRzzb3387Od1seX0Sx5lzCC08xoQfdVqmVGBwuL81DqsIUUV4OZ5JSacueBCCIoK6OS5weCyiXW5nl5ck7UxkCxgdFUVMY5zQ0mw2jU8zzKZ6qNWVHjsssJgjWeAHa3PMN3P9c1ue8hjWCzWiAOgWW7LuDW1HRJkC2u61DqDgA5wy7hp/VIamTcq8I9N6RhjDFv8y/sBlKbkw36n9ksVos0QE2TKJK2a9CjUPMoiUSCogQCNM135fFtv5c08FZL8BtcRcGFMp4jO0sfedFCRtNwomQbpNjw8ktJe7xnqD97fcpSjKRK4e6mHHOLQme8a15IEtJ0P0KbSagsfJWmRocx/C21Gl1GA6LDYn9FgK1V4eQ8eIGCDt0W+pVi24Ky/aylmcK4Ficp6kaO/T0Ca001e1mR6tgcse+L68fuQWMloPNNYinITdHGERbQIVKpcZ58k7XJ5tvgRTpCUSQX3QVlDSIISgrLLfs1jxTrNzfKXNnoZgH6rf8AFHS7pC5StdwrtAHtayofE2BJ3GgPnolNTib95G56Tq4xvFN/T/wuBSI0cfWCrvhnZPHV6XfU208p+XO5zC8bkCCI6k3UbgWB/EYilR2e65H8IBc6P+EFd1w9JgaGgAAAAAbAWACFp8KnbkM+pa6Wnahj7fP2OCcS4RjaH+ZhnAc2+If/AFkqsZjxuI+q9GVsMDaxB2KyHaXsLQry4Myv5tsfffyM+iLPSL/qJ4fWpJ1lX6HJ8SzOwxvonaTpAPRPcW4JWwZMgvp7uiMp/wBw/L56KIzFsO8eaTnCUeGbmHPjyrfFj6IlMPxjRvPkqziPFABcx0Gp81zGDk6R3kzQxq5Mn4Wpme47AABTFU8PxIF9Q68qxbiGn8wUkuSY5Jqx1DIXeEausPM2H1TL8U0bj0utR8PeDPxNYV3NijTNp/O/9h9/JdYsbnJJAtVqY4cTk2UHEOymMp0xVeAacgFwcTlnSQQLbSLSqLtJlbhy2bkiBzMyfpK7V8Qu02EwmGfTrGXVWOayk2MxkEAx+VoO55LzbxDGvrOzO9ANAOQT34epprpGCvU3PBKM17z4+xHlAFABEUyZQEaSjULoJGiQUIKEIkSNQhq+xnbA4Os2o8OeGggZYJuI0drqd11XAfFvBujNUDTyeyo36gEfVef053Ry548ObLNtYnTXRUkl0dZJynTlzXB6TpfEnAn/AF6Q/wDlb+sIYv4j4ENkV6bjIt3jDaROjuUrzSihXYPaj1nRZTxVFtQgEPEhw5HTzEeix/aDsHh2g1ILG7lhyxPNpkeysfhDi3VOFYfNqzPT9GPIb9IHopHFcYatQ38DSQ0dRYu8zeOnmVUoqS5Lx5J45e46OXdrezfc4d1ajUfDPmDwAYMCRYRcrmznbldU+KfHGso/hWmX1YLv9rAZv1JHsCuUqoxUegk8s8jubs6p2Z7EtOGY6pUeH1AHwIhgIkCDqYIm6m4fsE97iG1Zjbu9tjOeIVp2M4qzEYSm4EZmNDHj+FzRGnXX1WiwVfu6jH7Aw7/0usZ6Czv+FcvFCTtoJDV5scdsZcFVwz4eYejFTEvkC8OIDbAkzFgIB1JVX2o+LeHoNNDh9MVCLCoRlpN28LdX/QdStX8Uez78ZgKjKZd3lM941oMZ8gMsI3kadQF5mBXaio8IDPLPLzN2SeI4+rXqOrVqjqlR13Odqf2HQWCjIIpVnIZRIDVBQsCCCChAIIIKEAgggoQEoSggrog7hqbXOAc7ICbugmPQXK1fBvhrxOuWH8OaTH/6lUtZlHMsJz/RPfBzB06vFKQqNDgxj3tB0D2jwmN4XpFUcydGc4dw5nDsCzDUzJaModu6o+S58e5joAqXG4htKk+oflpsc4+TWk/orTjVQmu4E2aAGjlIBPufsFnu1f8A4LE/+xU//BUZSOF4/GvrVH1ahl7zJP2A6AW9FHRIKUdlz2W4+/B1hUHiYbVGfxN6f7hsu54XENqsbUYczHtDgeYcJXnNdr+GzyeH0pMwXgeQe6AoRnSOB4/O3u3HxsG/5mjQ9Tsf5rjnxJ+GeIp1qmJwlM1aL3F7qbLvpl13QzVzZ0jSdLLoJGh3FwRYg8wRopvAuK1n1cjny0cw2feJVnFV0eYSIsbEILVfEui1nEMSGtDR+IebAD5m03H6kn1Kyqh2BBBBUQCCCChD/9k=" alt="BOO!" style="max-width:100%; max-height:100%;">
    </div>



    <script>
        // Dark mode persistence
        function toggleDarkMode() {
            document.body.classList.toggle('dark');
            localStorage.setItem('darkMode', document.body.classList.contains('dark') ? '1' : '0');
        }

        // Apply saved mode on load
        window.onload = function() {
            if (localStorage.getItem('darkMode') === '1') {
                document.body.classList.add('dark');
            }
        };


        const konamiCode = [38, 38, 40, 40, 37, 39, 37, 39, 66, 65];
        let keySequence = [];

        window.addEventListener('keydown', function(e) {
            keySequence.push(e.keyCode);
            if (keySequence.length > konamiCode.length) {
                keySequence.shift();
            }

            if (JSON.stringify(keySequence) === JSON.stringify(konamiCode)) {
                triggerJumpScare();
                keySequence = []; // reset
            }
        });

        function triggerJumpScare() {
            const jumpscare = document.getElementById('jumpscare');
            const img = document.getElementById('scareImg');

            // Show
            jumpscare.style.display = "flex";

            // Hide after 2ms
            setTimeout(() => {
                jumpscare.style.display = "none";
            }, 60);
        }
    </script>
</body>

</html>