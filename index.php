<?php
    require "connect.php";

    $query = "
    SELECT
        ROW_NUMBER() OVER (ORDER BY TotalSpending DESC) AS No,
        c.CustomerID,
        c.CompanyName,
        c.Country,
        COALESCE(SUM(ol.TotalPrice), 0) AS TotalSpending
    FROM
        customers c
    LEFT JOIN
        orderlist ol ON c.CustomerID = ol.CustomerID
    GROUP BY
        c.CustomerID, c.CompanyName, c.Country
    ORDER BY
        TotalSpending DESC;
    ";

    if (isset($_GET['country']) && $_GET['country'] !== '') {
        $query = "
            SELECT
                ROW_NUMBER() OVER (ORDER BY TotalSpending DESC) AS No,
                c.CustomerID,
                c.CompanyName,
                c.Country,
                COALESCE(SUM(ol.TotalPrice), 0) AS TotalSpending
            FROM
                customers c
            LEFT JOIN
                orderlist ol ON c.CustomerID = ol.CustomerID
            WHERE
                c.Country = :country
            GROUP BY
                c.CustomerID, c.CompanyName, c.Country
            ORDER BY
                TotalSpending DESC;
        ";
    }

    // Execute the SQL query
    $stmt = $conn->prepare($query);
    if (isset($_GET['country']) && $_GET['country'] !== '') {
        $stmt->bindParam(':country', $_GET['country']);
    }
    $stmt->execute();
    $result = $stmt->fetchAll();    

    $revenue_by_semester_query = "SELECT YEAR(OrderDate) AS Year, MONTH(OrderDate) AS Month, SUM(TotalPrice) AS MonthlyRevenue
        FROM orderlist
        GROUP BY Year, Month
        ORDER BY Year, Month";

    $revenue_stmt = $conn->query($revenue_by_semester_query);
    $revenue_data = $revenue_stmt->fetchAll(PDO::FETCH_ASSOC);

    
    // Prepare data for the line chart
    $labels = [];
    $data = [];

    foreach ($revenue_data as $row) {
        // Assuming Semester 1 is from January to June and Semester 2 is from July to December
        $semester = ($row['Month'] <= 6) ? 'Semester 1' : 'Semester 2';
        $label = $row['Year'] . ' ' . $semester;

        // Check if the label already exists in the $labels array
        if (!in_array($label, $labels)) {
            $labels[] = $label;
        }
        
        // Find the index of the label and insert the revenue in the corresponding position in the $data array
        $index = array_search($label, $labels);
        $data[$index] = $row['MonthlyRevenue'];
    }

    // Convert PHP arrays to JavaScript arrays
    $labels_js = json_encode($labels);
    $data_js = json_encode($data);

    $revenue_by_semester_query = "
        SELECT 
            YEAR(OrderDate) AS Year,
            CASE 
                WHEN MONTH(OrderDate) BETWEEN 1 AND 6 THEN 'Semester 1'
                ELSE 'Semester 2'
            END AS Semester,
            SUM(TotalPrice) AS TotalRevenue 
        FROM 
            orderlist o
        JOIN 
            customers c ON o.CustomerID = c.CustomerID ";

    if (isset($_GET['country']) && $_GET['country'] !== '') {
        $revenue_by_semester_query .= "WHERE c.Country = :country ";
    }

    $revenue_by_semester_query .= "GROUP BY Year, Semester ORDER BY Year, Semester";

    $revenue_stmt = $conn->prepare($revenue_by_semester_query);

    if (isset($_GET['country']) && $_GET['country'] !== '') {
        $revenue_stmt->bindParam(':country', $_GET['country']);
    }

    $revenue_stmt->execute();
    $revenue_data = $revenue_stmt->fetchAll(PDO::FETCH_ASSOC);

    
    
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <!-- CDN for Tailwind -->
    <script src="https://cdn.tailwindcss.com/3.3.0"></script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio,line-clamp"></script>
    <!-- CDN for Tailwind Element -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tw-elements/dist/css/tw-elements.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
        <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.1/jquery.min.js" integrity="sha512-aVKKRRi/Q/YV+4mjoKBsE4x3H+BkegoM/em46NNlCqNTmUYADjBbeNefNxYV7giUp0VxICtqdrbqU7iVaeZNXA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    
    <!-- DataTables JS -->
    <!-- <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

      
</head>
<body class="w-screen justify-center items-center overflow-x-hidden overflow-y-auto">
   <!-- Sidebar -->
    <div class="w3-sidebar w3-bar-block w3-border-right bg-amber-100" style="display:none" id="mySidebar">
        <button onclick="w3_close()" class="w3-bar-item w3-large"> &times;</button>
        <a href="#" class="w3-bar-item w3-button">Link 1</a>
        <a href="#" class="w3-bar-item w3-button">Link 2</a>
        <a href="#" class="w3-bar-item w3-button">Link 3</a>
    </div>

    <div class="w3-khaki p-3 shadow-2xl flex items-center">
        <div class="w-16">
            <button class="text-khaki text-3xl" onclick="w3_open()">☰</button>
        </div>
        <h1 class="text-3xl font-bold flex-1 text-center ml-4 ">Revenue Trend</h1>
    </div>
    <form action="" method="GET">
            <div class="container w-1/4 mt-4">
                <div class="card p-2">
                    <select class="form-select mr-4 " id="country" name="country">
                    <option value="">All</option>
                    <!-- Populate options dynamically from database -->
                    <?php
                        $country_query = "SELECT DISTINCT Country FROM customers";
                        $country_stmt = $conn->query($country_query);
                        $countries = $country_stmt->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($countries as $country) {
                            $selected = ($_GET['country'] ?? '') === $country ? 'selected' : '';
                            echo "<option value='$country' $selected>$country</option>";
                        }
                    ?>
                </select>
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4  mt-1">Select Country</button>
                </div>
            </div>
    </form>
    <div class="container mt-4 w-1/2 d-flex justify-center">
            <div class="card mb-4 shadow-xl p-2">
                <div class="card-body text-center">
                    <h2 class="text-xl font-semibold">Total Revenue</h2>
                    <?php
                    $countryFilter = isset($_GET['country']) ? $_GET['country'] : '';
                    //    $grand_total_query = "SELECT SUM(TotalPrice) AS GrandTotal FROM orderlist";

                    if ($countryFilter == '') {
                        $grand_total_query = "SELECT SUM(TotalPrice) AS GrandTotal FROM orderlist";
                    } else {
                        $grand_total_query = " SELECT SUM(TotalPrice) AS GrandTotal FROM orderlist WHERE CustomerID IN (SELECT CustomerID FROM customers WHERE Country = :country)";
                    }

                    $grand_total_stmt = $conn->prepare($grand_total_query);

                    if ($countryFilter !== 'All') {
                        $grand_total_stmt->execute([':country' => $countryFilter]);
                    } else {
                        $grand_total_stmt->execute();
                    }

                    $grand_total_result = $grand_total_stmt->fetch();
                    $grand_total = number_format($grand_total_result['GrandTotal'] ?? 0, 0, ',', ',') . ' (USD)';
                ?>
                <p class="text-2xl font-bold"><?= $grand_total ?></p>
                </div>
            </div>
        </div>
    <div class="container d-flex">
        <div class="container mt-2 mb-4 w-1/2">
            <div class="card mb-4 shadow-xl">
                <div class="card-body text-center">
                    <h2 class="text-xl font-semibold mb-2">Revenue by Semester</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Year</th>
                                <th scope="col">Semester</th>
                                <th scope="col">Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                // Calculate revenue by semester and year with the country filter
                                // Use the modified SQL query for revenue calculation provided earlier in the PHP code
                                foreach ($revenue_data as $semester_year_data) :
                            ?>
                                <tr>
                                    <td><?= $semester_year_data['Year'] ?></td>
                                    <td><?= $semester_year_data['Semester'] ?></td>
                                    <td>$<?= number_format($semester_year_data['TotalRevenue'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="container mt-2 w-1/2">
            <div class="card shadow-xl">
                <canvas id="revenueChart" width="800" height="300"></canvas>
            </div>
        </div>
    </div>

    <script>
        var ctx = document.getElementById('revenueChart').getContext('2d');
        var labels = <?= $labels_js ?>;
        var data = <?= $data_js ?>;

        var revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Total Revenue',
                    data: data,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1,
                    fill: false
                }]
            },
            options: {
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Yearly Semesters' // Set x-axis title
                        }
                    },
                    y: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Total Revenue'
                        }
                    }
                }
            }
        });
        
    </script>
    </div>
    
    <div class="container mt-5 w-3/4" style="height: 550px; overflow-y-auto">
        <div class="card p-2 shadow-xl">
            <table id="customerTable" class="table">
                <thead>
                    <tr>
                        <th class="text-align: center">No.</th>
                        <th>CustomerID</th>
                        <th>CompanyName</th>
                        <th>Country</th>
                        <th>Total Spending</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result as $row): ?>
                        <tr>
                            <td><?= $row['No'] ?></td>
                            <td><?= $row['CustomerID'] ?></td>
                            <td><?= $row['CompanyName'] ?></td>
                            <td><?= $row['Country'] ?></td>
                            <td><?= number_format($row['TotalSpending'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>

<script>
function w3_open() {
  document.getElementById("mySidebar").style.display = "block";
}

function w3_close() {
  document.getElementById("mySidebar").style.display = "none";
}

$(document).ready(function() {
                $('#customerTable').DataTable({
                    "columnDefs": [
                        { "width": "5%", "targets": [0] }, //No.
                        { "width": "20%", "targets": [1] }, //CustomerID
                        { "width": "%", "targets": [2] }, //CompanyName
                        { "width": "20%", "targets": [3] }, //Country
                        { "width": "20%", "targets": [4] }, //Spending
                        { "className": "text-center", "targets": [0, 1, 3] } // Center content in column 0
                    
                    ],
                });
            });
</script>
</html>