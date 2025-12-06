<?php
// -------------------- DATABASE CONNECTION --------------------
$servername = "localhost";
$username = "root";
$password = "mysql";
$database = "uber_db";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ----------------------------------------------------------
   HANDLE FORM ACTIONS (ADD USER / ADD DRIVER)
-----------------------------------------------------------*/
if (isset($_POST['action'])) {

    // ADD USER
    if ($_POST['action'] == "add_user") {
        $conn->query("
            INSERT INTO users (fname, lname, email, address, latitude, longitude)
            VALUES (
                '{$_POST['fname']}', '{$_POST['lname']}',
                '{$_POST['email']}', '{$_POST['address']}',
                '{$_POST['latitude']}', '{$_POST['longitude']}'
            )
        ");
    }

    // ADD DRIVER
    if ($_POST['action'] == "add_driver") {
        $conn->query("
            INSERT INTO driver (fname, lname, email, phone, address, latitude, longitude, radius)
            VALUES (
                '{$_POST['fname']}', '{$_POST['lname']}',
                '{$_POST['email']}', '{$_POST['phone']}',
                '{$_POST['address']}',
                '{$_POST['latitude']}', '{$_POST['longitude']}',
                '{$_POST['radius']}'
            )
        ");
    }

    header("Location: index.php?tab=admin");
    exit();
}

/* ----------------------------------------------------------
   DELETE USER — DELETE CHILD ROWS FIRST (FK SAFE)
-----------------------------------------------------------*/
if (isset($_GET['delete_user'])) {
    $id = intval($_GET['delete_user']);

    // CHILD TABLES FIRST
    // restaurant_delivery depends on restaurant_order, so clear it first
    $conn->query("DELETE FROM restaurant_delivery WHERE user_id = $id");

    $conn->query("DELETE FROM grocery_delivery   WHERE user_id = $id");
    $conn->query("DELETE FROM ride               WHERE user_id = $id");
    $conn->query("DELETE FROM rental             WHERE user_id = $id");
    $conn->query("DELETE FROM userreview         WHERE user_id = $id");

    // PARENT TABLES SECOND (these may be referenced by the above)
    $conn->query("DELETE FROM grocery_order      WHERE user_id = $id");
    $conn->query("DELETE FROM restaurant_order   WHERE user_id = $id");

    // Now it's safe to delete the user
    $conn->query("DELETE FROM users WHERE user_id = $id");

    header("Location: index.php?tab=admin");
    exit();
}

/* ----------------------------------------------------------
   DELETE DRIVER — DELETE CHILD ROWS FIRST
-----------------------------------------------------------*/
if (isset($_GET['delete_driver'])) {
    $id = intval($_GET['delete_driver']);

    // 1. Delete driver-only reviews
    $conn->query("DELETE FROM driverreview WHERE driver_id = $id");

    // 2. Delete userreview where ride belongs to this driver
    $conn->query("
        DELETE FROM userreview
        WHERE ride_id IN (SELECT ride_id FROM ride WHERE driver_id = $id)
    ");

    // 3. Delete deliveries done by driver
    $conn->query("DELETE FROM grocery_delivery WHERE driver_id = $id");
    $conn->query("DELETE FROM restaurant_delivery WHERE driver_id = $id");

    // 4. Delete ride_details BEFORE ride
    $conn->query("
        DELETE FROM ride_details 
        WHERE ride_id IN (SELECT ride_id FROM ride WHERE driver_id = $id)
    ");

    // 5. Delete rides
    $conn->query("DELETE FROM ride WHERE driver_id = $id");

    // ❌ 6. DO NOT delete from restaurant_order (no driver_id column)

    // 7. Delete driver
    $conn->query("DELETE FROM driver WHERE driver_id = $id");

    header("Location: index.php?tab=admin");
    exit();
}




// Load table names for dropdown
$tables = $conn->query("SHOW TABLES");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Uber Database Viewer</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eef3fa;
            margin: 0;
            padding: 0;
        }

        /* ------------------ NAVIGATION TABS ------------------ */
        .nav {
            display: flex;
            background: #1e90ff;
            padding: 15px;
            justify-content: center;
            gap: 40px;
        }

        .nav a {
            color: white;
            font-weight: bold;
            font-size: 18px;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 6px;
            transition: 0.25s;
        }

        .nav a:hover {
            background: #0c67c2;
            transform: scale(1.07);
        }

        .active-tab {
            background: #0c67c2;
        }

        h1 {
            text-align: center;
            padding-top: 30px;
            color: #0a3d62;
        }

        /* ------------------ DROPDOWN SELECT ------------------ */
        .dropdown-section {
            text-align: center;
            margin-top: 20px;
        }

        select {
            padding: 12px;
            font-size: 16px;
            border-radius: 8px;
            border: 2px solid #1e90ff;
            width: 250px;
            outline: none;
            transition: 0.25s;
        }

        select:hover {
            border-color: #0c67c2;
        }

        .btn {
            padding: 12px 18px;
            background: #1e90ff;
            color: white;
            border: none;
            font-size: 16px;
            border-radius: 7px;
            cursor: pointer;
            margin-left: 10px;
            transition: 0.25s;
        }

        .btn:hover {
            background: #0c67c2;
            transform: scale(1.05);
        }

        /* ------------------ TABLE STYLE ------------------ */
        table {
            width: 90%;
            margin: 30px auto;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        th, td {
            border: 1px solid #d7dcea;
            padding: 11px;
            text-align: left;
        }

        th {
            background: #1e90ff;
            color: white;
        }

        tr:nth-child(even) {
            background: #f5f8ff;
        }

        /* ------------------ CARD STYLE (USED FOR DRIVERS & USERS) ------------------ */
        .driver-card {
            width: 500px;
            background: white;
            padding: 18px;
            border-radius: 12px;
            margin: 20px auto;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
            transition: 0.2s;
        }

        .driver-card:hover {
            transform: scale(1.03);
            box-shadow: 0 6px 22px rgba(0,0,0,0.18);
        }

        .driver-card img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #1e90ff;
        }

        .driver-info {
            flex: 1;
        }

        .driver-info h3 {
            margin: 0;
            font-size: 20px;
            color: #0a3d62;
        }

        .driver-info p {
            margin: 4px 0;
            font-size: 15px;
            color: #333;
        }

        .rating-box {
            background: #1e90ff;
            padding: 8px 12px;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            font-size: 18px;
            text-align: center;
            min-width: 65px;
        }
    </style>

</head>

<body>

<!-- ------------------ NAV TABS ------------------ -->
<div class="nav">
    <a href="index.php?tab=users"
       class="<?php echo (isset($_GET['tab']) && $_GET['tab']=='users') ? 'active-tab' : ''; ?>">
       Users
    </a>

    <a href="index.php"
       class="<?php echo !isset($_GET['tab']) ? 'active-tab' : ''; ?>">
       Database Viewer
    </a>

    <a href="index.php?tab=Drivers"
       class="<?php echo (isset($_GET['tab']) && $_GET['tab']=="Drivers") ? 'active-tab' : ''; ?>">
       Drivers
    </a>

    <a href="index.php?tab=admin"
       class="<?php echo (isset($_GET['tab']) && $_GET['tab']=='admin') ? 'active-tab' : ''; ?>">
       Admin
    </a>
</div>


<!-- ------------------ DATABASE VIEWER TAB ------------------ -->
<?php
if (!isset($_GET['tab'])) {
?>

<h1>Database Viewer</h1>

<div class="dropdown-section">
    <form method="GET" action="">
        <select name="table">
            <option value="">Select a Table</option>

            <?php
            mysqli_data_seek($tables, 0);
            while ($row = $tables->fetch_array()) {
                $table = $row[0];
                $label = ucwords(str_replace("_", " ", $table));
                echo "<option value='$table'>$label</option>";
            }
            ?>
        </select>

        <button class="btn" type="submit">View</button>
    </form>
</div>

<?php
    // If a table was selected, show it
    if (isset($_GET['table']) && $_GET['table'] !== "") {

        $selectedTable = $_GET['table'];

        echo "<h2 style='text-align:center; color:#0a3d62;'>Showing Data from: <strong>"
             . ucwords(str_replace("_", " ", $selectedTable)) . "</strong></h2>";

        $data = $conn->query("SELECT * FROM `$selectedTable`");

        if ($data->num_rows > 0) {

            echo "<table><tr>";

            while ($field = $data->fetch_field()) {
                echo "<th>" . $field->name . "</th>";
            }

            echo "</tr>";

            while ($row = $data->fetch_assoc()) {
                echo "<tr>";
                foreach ($row as $val) echo "<td>$val</td>";
                echo "</tr>";
            }

            echo "</table>";

        } else {
            echo "<p style='text-align:center;'>No data found.</p>";
        }
    }
}
?>


<!-- ------------------ DRIVERS TAB ------------------ -->
<?php
if (isset($_GET['tab']) && $_GET['tab']=="Drivers") {
?>

<h1>Drivers</h1>

<?php
// Fetch drivers + average rating
$sql = "
    SELECT d.driver_id, d.fname, d.lname, d.phone, d.email, d.address,
           d.longitude, d.latitude, d.radius,
           AVG(r.rating) AS avg_rating
    FROM driver d
    LEFT JOIN driverreview r ON d.driver_id = r.driver_id
    GROUP BY d.driver_id
";

$drivers = $conn->query($sql);

while ($driver = $drivers->fetch_assoc()) {

    $initials = strtoupper($driver['fname'][0] . $driver['lname'][0]);
    $img_url = "https://ui-avatars.com/api/?name=$initials&background=1e90ff&color=fff&size=128";
    $avg = $driver['avg_rating'] ? number_format($driver['avg_rating'], 1) : "N/A";
    $fullname = $driver['fname']." ".$driver['lname'];

    echo "
    <a href='index.php?tab=driverdetails&driver_id={$driver['driver_id']}' style='text-decoration:none; color:inherit;'>
        <div class='driver-card'>
            <img src='$img_url'>
            <div class='driver-info'>
                <h3>$fullname</h3>
                <p><strong>Email:</strong> {$driver['email']}</p>
                <p><strong>Phone:</strong> {$driver['phone']}</p>
                <p><strong>Address:</strong> {$driver['address']}</p>
            </div>
            <div class='rating-box'>$avg ⭐</div>
        </div>
    </a>
    ";
}
}
?>


<!-- ------------------ USERS TAB ------------------ -->
<?php
if (isset($_GET['tab']) && $_GET['tab']=="users") {

    echo "<h1>User List</h1>";

    $users = $conn->query("
        SELECT 
            u.user_id,
            u.fname,
            u.lname,
            u.email,
            u.address,
            AVG(ur.rating) AS avg_rating
        FROM users u
        LEFT JOIN userreview ur ON u.user_id = ur.user_id
        GROUP BY u.user_id
        ORDER BY u.user_id ASC
    ");

    while ($u = $users->fetch_assoc()) {

        $initials = strtoupper($u['fname'][0] . $u['lname'][0]);
        $img_url = "https://ui-avatars.com/api/?name=$initials&background=0c67c2&color=fff&size=128";
        $avg = $u['avg_rating'] ? number_format($u['avg_rating'], 1) : "N/A";
        $fullname = $u['fname']." ".$u['lname'];

        echo "
        <a href='index.php?tab=userdetails&user_id={$u['user_id']}' style='text-decoration:none; color:inherit;'>
            <div class='driver-card'>
                <img src='$img_url'>
                <div class='driver-info'>
                    <h3>$fullname</h3>
                    <p><strong>Email:</strong> {$u['email']}</p>
                    <p><strong>Address:</strong> {$u['address']}</p>
                </div>
                <div class='rating-box'>$avg ⭐</div>
            </div>
        </a>
        ";
    }
}
?>


<!-- ------------------ ADMIN TAB ------------------ -->
<?php
if (isset($_GET['tab']) && $_GET['tab']=="admin") {

    echo "<h1>Admin Panel</h1>";
?>
    
<div style="width:90%; margin:auto;">

    <!-- ------------------- ADD USER FORM ------------------- -->
    <h2>Add User</h2>
    <form method="POST" action="index.php?tab=admin">
        <input type="hidden" name="action" value="add_user">

        <label>First Name:</label><br>
        <input type="text" name="fname" required><br><br>

        <label>Last Name:</label><br>
        <input type="text" name="lname" required><br><br>

        <label>Email:</label><br>
        <input type="email" name="email" required><br><br>

        <label>Address:</label><br>
        <input type="text" name="address" required><br><br>

        <label>Latitude:</label><br>
        <input type="number" step="0.000001" name="latitude" required><br><br>

        <label>Longitude:</label><br>
        <input type="number" step="0.000001" name="longitude" required><br><br>

        <button class="btn" type="submit">Add User</button>
    </form>

    <hr><br>

    <!-- ------------------- ADD DRIVER FORM ------------------- -->
    <h2>Add Driver</h2>
    <form method="POST" action="index.php?tab=admin">
        <input type="hidden" name="action" value="add_driver">

        <label>First Name:</label><br>
        <input type="text" name="fname" required><br><br>

        <label>Last Name:</label><br>
        <input type="text" name="lname" required><br><br>

        <label>Email:</label><br>
        <input type="email" name="email" required><br><br>

        <label>Phone:</label><br>
        <input type="text" name="phone" required><br><br>

        <label>Address:</label><br>
        <input type="text" name="address" required><br><br>

        <label>Latitude:</label><br>
        <input type="number" step="0.000001" name="latitude" required><br><br>

        <label>Longitude:</label><br>
        <input type="number" step="0.000001" name="longitude" required><br><br>

        <label>Radius (miles):</label><br>
        <input type="number" step="0.1" name="radius" required><br><br>

        <button class="btn" type="submit">Add Driver</button>
    </form>

    <hr><br>

    <!-- ------------------- USER LIST WITH DELETE BUTTON ------------------- -->
    <h2>All Users</h2>
    <table>
        <tr>
            <th>User ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Delete</th>
        </tr>

        <?php
        $users = $conn->query("SELECT * FROM users ORDER BY user_id ASC");
        while ($u = $users->fetch_assoc()) {
            $name = $u['fname']." ".$u['lname'];
            echo "
            <tr>
                <td>{$u['user_id']}</td>
                <td>$name</td>
                <td>{$u['email']}</td>
                <td><a href='index.php?tab=admin&delete_user={$u['user_id']}' class='btn'>Delete</a></td>
            </tr>
            ";
        }
        ?>
    </table>

    <hr><br>

    <!-- ------------------- DRIVER LIST WITH DELETE BUTTON ------------------- -->
    <h2>All Drivers</h2>
    <table>
        <tr>
            <th>Driver ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Delete</th>
        </tr>

        <?php
        $drivers = $conn->query("SELECT * FROM driver ORDER BY driver_id ASC");
        while ($d = $drivers->fetch_assoc()) {
            $name = $d['fname']." ".$d['lname'];
            echo "
            <tr>
                <td>{$d['driver_id']}</td>
                <td>$name</td>
                <td>{$d['email']}</td>
                <td><a href='index.php?tab=admin&delete_driver={$d['driver_id']}' class='btn'>Delete</a></td>
            </tr>
            ";
        }
        ?>
    </table>

</div>

<?php } ?>



<!-- ------------------ DRIVER DETAILS PAGE ------------------ -->
<?php
if (isset($_GET['tab']) && $_GET['tab']=="driverdetails" && isset($_GET['driver_id'])) {

    $driver_id = intval($_GET['driver_id']);

    echo "<h1>Driver Details</h1>";

    // Get driver info
    $d = $conn->query("SELECT * FROM driver WHERE driver_id=$driver_id")->fetch_assoc();

    $fullname = $d['fname']." ".$d['lname'];
    $initials = strtoupper($d['fname'][0].$d['lname'][0]);
    $img_url = "https://ui-avatars.com/api/?name=$initials&background=1e90ff&color=fff&size=128";

    echo "
    <div class='driver-card' style='width:600px; margin-top:30px;'>
        <img src='$img_url'>
        <div class='driver-info'>
            <h3>$fullname</h3>
            <p><strong>Email:</strong> {$d['email']}</p>
            <p><strong>Phone:</strong> {$d['phone']}</p>
            <p><strong>Address:</strong> {$d['address']}</p>
            <p><strong>Radius:</strong> {$d['radius']} miles</p>
        </div>
    </div>
    ";

    /* ------------------------------------------------------
       1️⃣ REVIEWS FOR THIS DRIVER (FIRST)
    ------------------------------------------------------ */
    echo "<h2 style='text-align:center; margin-top:40px;'>Reviews for $fullname</h2>";

    $reviews = $conn->query("
        SELECT 
            r.rating,
            r.comments,
            u.fname,
            u.lname
        FROM driverreview r
        JOIN users u ON r.user_id = u.user_id
        WHERE r.driver_id = $driver_id
        ORDER BY r.rating DESC
    ");

    if ($reviews->num_rows > 0) {
        echo "<table>
                <tr>
                    <th>User</th>
                    <th>Rating</th>
                    <th>Comment</th>
                </tr>";
        
        while ($rv = $reviews->fetch_assoc()) {
            $uname = $rv['fname']." ".$rv['lname'];
            echo "
            <tr>
                <td>$uname</td>
                <td>{$rv['rating']} ⭐</td>
                <td>{$rv['comments']}</td>
            </tr>";
        }

        echo "</table>";
    } else {
        echo "<p style='text-align:center;'>No reviews for this driver yet.</p>";
    }

    /* ------------------------------------------------------
       2️⃣ USERS THIS DRIVER CAN DELIVER TO (SECOND)
    ------------------------------------------------------ */
    echo "<h2 style='text-align:center; margin-top:40px;'>Users This Driver Can Deliver To</h2>";

    // Haversine query
    $sql = "
        SELECT 
            U.user_id,
            U.fname,
            U.lname,
            U.email,
            (
                3959 * ACOS(
                    COS(RADIANS(U.latitude)) *
                    COS(RADIANS({$d['latitude']})) *
                    COS(RADIANS({$d['longitude']}) - RADIANS(U.longitude)) +
                    SIN(RADIANS(U.latitude)) *
                    SIN(RADIANS({$d['latitude']}))
                )
            ) AS distance_to_user
        FROM users U
        HAVING distance_to_user <= {$d['radius']}
        ORDER BY distance_to_user ASC
    ";

    $users = $conn->query($sql);

    if ($users->num_rows > 0) {

        echo "<table>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Distance (Miles)</th>
                </tr>";

        while ($u = $users->fetch_assoc()) {

            $name = $u['fname']." ".$u['lname'];
            $distance = number_format($u['distance_to_user'], 2);

            echo "
            <tr>
                <td>{$u['user_id']}</td>
                <td>$name</td>
                <td>{$u['email']}</td>
                <td>$distance</td>
            </tr>";
        }

        echo "</table>";

    } else {
        echo "<p style='text-align:center;'>No users found within this driver's radius.</p>";
    }
}
?>


<!-- ------------------ USER DETAILS PAGE ------------------ -->
<?php
if (isset($_GET['tab']) && $_GET['tab']=="userdetails" && isset($_GET['user_id'])) {

    $user_id = intval($_GET['user_id']);

    echo "<h1>User Details</h1>";

    // Fetch user info
    $u = $conn->query("SELECT * FROM users WHERE user_id=$user_id")->fetch_assoc();

    $fullname = $u['fname'] . " " . $u['lname'];
    $initials = strtoupper($u['fname'][0] . $u['lname'][0]);
    $img_url = "https://ui-avatars.com/api/?name=$initials&background=0c67c2&color=fff&size=128";

    // User profile card
    echo "
    <div class='driver-card' style='width:600px; margin-top:30px;'>
        <img src='$img_url'>
        <div class='driver-info'>
            <h3>$fullname</h3>
            <p><strong>Email:</strong> {$u['email']}</p>
            <p><strong>Address:</strong> {$u['address']}</p>
        </div>
    </div>
    ";

    /* ------------------------------------------------------
       1️⃣ REVIEWS GIVEN TO THIS USER (FIRST)
    ------------------------------------------------------ */
    echo "<h2 style='text-align:center; margin-top:40px;'>Reviews Given to This User</h2>";

    $reviews = $conn->query("
        SELECT 
            ur.rating,
            ur.comments,
            d.fname AS dfname,
            d.lname AS dlname
        FROM userreview ur
        JOIN driver d ON ur.driver_id = d.driver_id
        WHERE ur.user_id = $user_id
        ORDER BY ur.rating DESC
    ");

    if ($reviews->num_rows > 0) {

        echo "<table>
                <tr>
                    <th>Driver</th>
                    <th>Rating</th>
                    <th>Comment</th>
                </tr>";

        while ($rv = $reviews->fetch_assoc()) {

            $driverName = $rv['dfname'] . " " . $rv['dlname'];

            echo "
            <tr>
                <td>$driverName</td>
                <td>{$rv['rating']} ⭐</td>
                <td>{$rv['comments']}</td>
            </tr>
            ";
        }

        echo "</table>";

    } else {
        echo "<p style='text-align:center;'>This user has not received any reviews.</p>";
    }

    /* ------------------------------------------------------
       2️⃣ DRIVERS WHO CAN DELIVER TO THIS USER (SECOND)
    ------------------------------------------------------ */
    echo "<h2 style='text-align:center; margin-top:40px;'>Drivers Who Can Deliver to This User</h2>";

    $sql = "
        SELECT 
            d.driver_id,
            d.fname,
            d.lname,
            d.email,
            d.radius,
            (
                3959 * ACOS(
                    COS(RADIANS({$u['latitude']})) *
                    COS(RADIANS(d.latitude)) *
                    COS(RADIANS(d.longitude) - RADIANS({$u['longitude']})) +
                    SIN(RADIANS({$u['latitude']})) *
                    SIN(RADIANS(d.latitude))
                )
            ) AS distance_to_driver
        FROM driver d
        HAVING distance_to_driver <= radius
        ORDER BY distance_to_driver ASC
    ";

    $drivers = $conn->query($sql);

    if ($drivers->num_rows > 0) {

        echo "<table>
                <tr>
                    <th>Driver</th>
                    <th>Email</th>
                    <th>Distance (Miles)</th>
                </tr>";

        while ($dr = $drivers->fetch_assoc()) {
            $driverName = $dr['fname'] . " " . $dr['lname'];
            $dist = number_format($dr['distance_to_driver'], 2);

            echo "
            <tr>
                <td>$driverName</td>
                <td>{$dr['email']}</td>
                <td>$dist</td>
            </tr>";
        }

        echo "</table>";

    } else {
        echo "<p style='text-align:center;'>No drivers found within range.</p>";
    }

}
?>

<?php $conn->close(); ?>
</body>
</html>
