<?php
// Use environment variables to store sensitive information
$servername = getenv('DB_SERVERNAME') ?: 'localhost';
$username = getenv('DB_USERNAME') ?: 'username';
$password = getenv('DB_PASSWORD') ?: 'password';
$dbname = getenv('DB_NAME') ?: 'database_name';

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . htmlspecialchars($conn->connect_error));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searchTerm = filter_input(INPUT_POST, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if ($searchTerm !== null && $searchTerm !== false && !empty($searchTerm)) {
        // Prevent excessive input length
        if (strlen($searchTerm) > 255) {
            echo "<div>Search term is too long</div>";
            exit;
        }

        // Prepare SQL query
        $stmt = $conn->prepare("SELECT * FROM table_name WHERE column_name LIKE ?");

        if ($stmt === false) {
            echo "<div>Failed to prepare the SQL statement</div>";
            error_log("MySQL Prepare Error: " . $conn->error);
            exit;
        }

        $likeTerm = "%" . $searchTerm . "%";
        $stmt->bind_param("s", $likeTerm);

        // Execute query
        if (!$stmt->execute()) {
            echo "<div>Error executing query</div>";
            error_log("MySQL Execute Error: " . $stmt->error);
            exit;
        }

        $result = $stmt->get_result();

        if ($result) {
            if ($result->num_rows > 0) {
                // Output results
                while ($row = $result->fetch_assoc()) {
                    echo "<div>" . htmlspecialchars($row['column_name']) . "</div>";
                }
            } else {
                echo "<div>No results found</div>";
            }
        } else {
            echo "<div>Error retrieving results</div>";
            error_log("MySQL Result Error: " . $stmt->error);
        }

        $stmt->close();
    } else {
        echo "<div>Please enter a valid search term</div>";
    }
}

$conn->close();
?>
