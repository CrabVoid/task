<?php
// Database credentials
$servername = "localhost";
$username = "RalfsEgle";
$password = "password";
$dbname = "php27032025";

// Step 1: Create a connection to the database
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    // Set PDO to throw exceptions on error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}

// Step 2: Perform the LEFT JOIN query
$sql = "
    SELECT 
        post.id AS post_id,
        post.title,
        post.content,
        comments.comment_id AS comment_id,
        comments.content
    FROM post
    LEFT JOIN comments ON post.id = comments.post_id
    ORDER BY post.id, comments.comment_id
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Step 3: Build the hierarchical associative array
$data = [];

foreach ($rows as $row) {
    $postId = $row['post_id'];

    // If the post doesn't exist in the array, initialize it
    if (!isset($data[$postId])) {
        $data[$postId] = [
            'title' => $row['title'],
            'content' => $row['content'],
            'comments' => []
        ];
    }

    // If there's a comment, add it to the post's comments array
    if (!empty($row['comment_id'])) {
        $data[$postId]['comments'][] = [
            'content' => $row['content']
        ];
    }
}

// Step 4: Output the data as a hierarchical HTML list
echo "<ul>";
foreach ($data as $post) {
    echo "<li>";
    echo "<strong>" . htmlspecialchars($post['title']) . "</strong><br>";
    echo nl2br(htmlspecialchars($post['content']));

    // If there are comments, display them in a nested <ul>
    if (!empty($post['comments'])) {
        echo "<ul>";
        foreach ($post['comments'] as $comment) {
            echo "<li>" . htmlspecialchars($comment['content']) . "</li>";
        }
        echo "</ul>";
    }

    echo "</li>";
}
echo "</ul>";
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