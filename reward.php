<?php
session_start();
include 'db.php'; 

$userID = $_SESSION['userID'] ?? 1; 

// --- Get user total points ---
$userQuery = $conn->prepare("SELECT totalPoints FROM user WHERE userID = ?");
$userQuery->bind_param("i", $userID);
$userQuery->execute();
$userResult = $userQuery->get_result();
$totalPoints = $userResult->fetch_assoc()['totalPoints'] ?? 0;

// --- Get Filters & Sorting ---
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';
$sort = $_GET['sort'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rewards</title>
    <link rel="stylesheet" href="style.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            max-width: 1200px;
            width: 100%;
            margin: auto;
            /* Added margin to push the footer further down */
            margin-bottom: 80px; 
        }

        /* 🔹 PAGE HEADER */
        .page-header {
            padding: 30px 20px;
            text-align: center;
        }
        .page-header h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }
        .page-header p {
            margin: 0;
            font-size: 15px;
            opacity: 0.95;
        }

        .search-bar {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px;
        }

        .search-bar form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-bar input,
        .search-bar select,
        .search-bar button {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .point-box {
            background-color: #f0f0f0;
            padding: 10px 15px;
            border-radius: 10px;
            font-weight: bold;
        }

        .reward-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            /* Extra breathing room inside the container */
            padding-bottom: 40px; 
        }

        @media (max-width: 768px) {
            .reward-container {
                grid-template-columns: 1fr;
            }
        }

        .reward-card {
            display: flex;
            align-items: flex-start;
            border: 1px solid #ddd;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            background: #fff;
            padding: 15px;
        }

        .reward-image {
            flex: 0 0 150px;
            height: 150px;
            background-size: cover;
            background-position: center;
            border: 2px solid #ccc;
            border-radius: 8px;
            margin-right: 15px;
        }

        .reward-info {
            flex: 1;
            text-align: left;
        }

        .reward-info h3 {
            margin: 0 0 10px;
        }

        .claim-button {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 12px;
            background: #007BFF;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .claim-button:hover {
            background: #0056b3;
        }

        footer {
            margin-top: auto;
            width: 100%;
        }
    </style>
</head>
<body>

<?php include 'user_navbar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h1>🎁 Rewards & Redemption</h1>
        <p>Use your earned points to redeem rewards. Browse available items and claim what you’ve earned through your contributions.</p>
    </div>

    <div class="search-bar">
        <form method="GET">

            <input type="text" name="search" placeholder="Search rewards..." 
                   value="<?= htmlspecialchars($search) ?>">

            <select name="filter">
                <option value="">Filter: All</option>
                <option value="claimable" <?= $filter=='claimable'?'selected':'' ?>>Can Claim</option>
                <option value="not_claimable" <?= $filter=='not_claimable'?'selected':'' ?>>Cannot Claim</option>
            </select>

            <select name="sort">
                <option value="">Sort: Default</option>
                <option value="low_high" <?= $sort=='low_high'?'selected':'' ?>>Points: Low → High</option>
                <option value="high_low" <?= $sort=='high_low'?'selected':'' ?>>Points: High → Low</option>
                <option value="az" <?= $sort=='az'?'selected':'' ?>>Name A–Z</option>
                <option value="za" <?= $sort=='za'?'selected':'' ?>>Name Z–A</option>
            </select>

            <button type="submit"><i class="fas fa-search"></i> Search</button>
        </form>

        <div class="point-box">
            Available Points: <?= htmlspecialchars($totalPoints) ?>
        </div>
    </div>

    <div class="reward-container">
    <?php
        $sql = "SELECT * FROM reward WHERE 1=1";

        if (!empty($search)) {
            $s = $conn->real_escape_string($search);
            $sql .= " AND rewardName LIKE '%$s%'";
        }

        if ($filter === "claimable") {
            $sql .= " AND pointRequired <= $totalPoints";
        } else if ($filter === "not_claimable") {
            $sql .= " AND pointRequired > $totalPoints";
        }

        if ($sort === "low_high") {
            $sql .= " ORDER BY pointRequired ASC";
        } else if ($sort === "high_low") {
            $sql .= " ORDER BY pointRequired DESC";
        } else if ($sort === "az") {
            $sql .= " ORDER BY rewardName ASC";
        } else if ($sort === "za") {
            $sql .= " ORDER BY rewardName DESC";
        }

        $result = $conn->query($sql);

        if ($result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
                $imagePath = !empty($row['rewardImage'])
                    ? 'uploads/rewards/' . basename($row['rewardImage'])
                    : 'https://via.placeholder.com/200x150';
    ?>
        <div class="reward-card">
            <div class="reward-image" style="background-image: url('<?= htmlspecialchars($imagePath) ?>');"></div>
            <div class="reward-info">
                <h3><?= htmlspecialchars($row['rewardName']) ?></h3>
                <p><?= htmlspecialchars($row['description']) ?></p>
                <p>🎯 <?= htmlspecialchars($row['pointRequired']) ?> Points Required</p>
                <a href="reward_claim.php?id=<?= $row['rewardID'] ?>" class="claim-button">Claim</a>
            </div>
        </div>
    <?php
            endwhile;
        else:
            echo "<p>No rewards found.</p>";
        endif;

        $conn->close();
    ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>