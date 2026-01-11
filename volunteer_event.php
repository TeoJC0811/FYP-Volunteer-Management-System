<?php
session_start();
include 'db.php';

$userID = $_SESSION['userID'] ?? null;

// --- Get filter options ---
$categoryResult = $conn->query("SELECT * FROM category ORDER BY categoryName ASC");

// Get ENUM values for country
$countries = [];
$countryEnumResult = $conn->query("SHOW COLUMNS FROM event LIKE 'eventCountry'");
if ($countryEnumResult && $countryRow = $countryEnumResult->fetch_assoc()) {
    if (!empty($countryRow['Type'])) {
        preg_match_all("/'([^']+)'/", $countryRow['Type'], $countryMatches);
        $countries = $countryMatches[1] ?? [];
    }
}

$search   = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$country  = $_GET['country'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Volunteer Events</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
html, body {
    height: 100%;
    margin: 0;
    display: flex;
    flex-direction: column;
}
.main-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 20px; 
    max-width: 1200px; 
    margin: 0 auto; 
    width: 100%;
}

.page-header {
    padding: 30px 20px;   
    text-align: center;
}
.page-header h1 {
    font-size: 32px;
    margin: 0;
    font-weight: 700;
}
.page-header p {
    font-size: 16px;
    margin: 5px 0 0;
    color: #666;
}

.search-bar form { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; }
.search-bar input, .search-bar select, .search-bar button {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
}
.search-bar button { background-color: #007BFF; color: white; border: none; cursor: pointer; }

.btn-clear { 
    padding: 10px 15px; 
    background-color: #6c757d; 
    color: white; 
    text-decoration: none; 
    border-radius: 5px; 
    font-size: 14px;
    display: flex;
    align-items: center;
}

.category-tag {
    position: absolute;
    top: 12px;
    left: 12px;
    background: rgba(0, 123, 255, 0.9);
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 6px;
}
.wishlist-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(0,0,0,0.45);
    border: none;
    border-radius: 50%;
    width: 42px;
    height: 42px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s ease, background 0.2s ease;
}
.wishlist-btn:hover { transform: scale(1.1); background: rgba(0,0,0,0.65); }
.wishlist-btn svg {
    width: 22px;
    height: 22px;
    stroke: #fff;
    stroke-width: 2;
    fill: none;
}
.wishlist-btn.active svg { fill: #fff; stroke: #fff; }

.event-container { 
    display: grid; 
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); 
    gap: 25px; 
    margin-top: 20px; 
}

.event-card { 
    box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
    border-radius: 8px; 
    overflow: hidden; 
    background: white; 
    max-width: 380px; 
    width: 100%;
}

.event-image { height: 180px; background-size: cover; background-position: center; position: relative; }

.event-info {
    padding: 20px;
    font-size: 15px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.event-info h3 {
    font-size: 20px;
    margin: 0;
    color: #333;
    text-align: left;
    margin-bottom: 8px;
}
.event-meta {
    display: flex;
    flex-direction: column;
    gap: 6px;
    text-align: left;
}
.meta-chip {
    font-size: 14px;
    color: #555;
    display: flex;
    align-items: center;
}
.meta-chip .emoji {
    display: inline-block;
    width: 22px;
    text-align: center;
}
.event-desc {
    font-size: 14px;
    color: #666;
    line-height: 1.5em;
    text-align: left;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    min-height: 4.5em;
    margin-bottom: 10px;
}
.interest-tag {
    display:inline-block;
    background:#ffefc2;
    color:#b37400;
    font-size:13px;
    font-weight:600;
    padding:4px 8px;
    border-radius:6px;
    margin-bottom:6px;
}
.no-event {
    text-align: center;
    width: 100%;
    font-size: 20px;
    padding: 50px;
    color: #666;
    flex-grow: 1;
    display: flex;
    justify-content: center;
    align-items: center;
}
.join-button { display: block; text-align: center; padding: 10px; background: #007BFF; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px; }

</style>
</head>
<body>

<?php include("user_navbar.php"); ?>

<div class="page-header">
    <h1>🤝 Volunteer Events</h1>
    <p>Find meaningful ways to contribute to your community.</p>
</div>

<div class="main-content">

    <div class="search-bar">
        <form method="GET" id="filterForm">
            <input type="text" name="search" placeholder="Search volunteering events..." value="<?= htmlspecialchars($search ?? '') ?>">
            
            <select name="category" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php $categoryResult->data_seek(0); while ($row = $categoryResult->fetch_assoc()): ?>
                    <option value="<?= $row['categoryID'] ?>" <?= ($category == $row['categoryID']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['categoryName']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <select name="country" onchange="this.form.submit()">
                <option value="">All Countries</option>
                <?php foreach ($countries as $c): ?>
                    <option value="<?= $c ?>" <?= ($country == $c) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Search</button>

            <?php if(!empty($search) || !empty($category) || !empty($country)): ?>
                <a href="volunteer_event.php" class="btn-clear">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="event-container">
    <?php
        $sql = "
            SELECT 
                e.*,
                c.categoryName,
                CASE WHEN uc.categoryID IS NOT NULL THEN 1 ELSE 0 END AS isInterested
            FROM event e
            LEFT JOIN category c ON e.categoryID = c.categoryID
            LEFT JOIN usercategory uc 
                ON uc.categoryID = e.categoryID AND uc.userID = " . intval($userID) . "
            WHERE e.endDate >= CURDATE()
        ";

        if (!empty($search)) {
            $s = $conn->real_escape_string($search);
            $sql .= " AND (e.eventName LIKE '%$s%' OR e.eventLocation LIKE '%$s%')";
        }
        if (!empty($category)) {
            $sql .= " AND e.categoryID = '" . $conn->real_escape_string($category) . "'";
        }
        if (!empty($country)) {
            $sql .= " AND e.eventCountry = '" . $conn->real_escape_string($country) . "'";
        }

        $sql .= " GROUP BY e.eventID 
                  ORDER BY isInterested DESC, e.startDate ASC";

        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
                $cover = $row['coverImage'];
                $imagePath = "https://via.placeholder.com/300x150";

                if (!empty($cover)) {
                    $cover = str_replace("\\", "/", $cover); 
                    if (strpos($cover, "uploads/event_cover/") !== false) {
                        $imagePath = $cover;
                    } else {
                        $imagePath = "uploads/event_cover/" . basename($cover);
                    }
                }
                
                $isWishlisted = false;
                if ($userID) {
                    $check = $conn->query("SELECT 1 FROM wishlist WHERE userID='".intval($userID)."' AND eventID='".$row['eventID']."' LIMIT 1");
                    $isWishlisted = ($check && $check->num_rows > 0);
                }

                // Date logic
                $startDate = $row['startDate'];
                $endDate = $row['endDate'];

                if ($startDate === $endDate) {
                    $dateDisplay = date('d M Y', strtotime($startDate));
                } else {
                    $dateDisplay = date('d M', strtotime($startDate)) . " - " . date('d M Y', strtotime($endDate));
                }
    ?>

        <div class="event-card">
            <div class="event-image" style="background-image: url('<?= htmlspecialchars($imagePath) ?>');">
                <div class="category-tag"><?= htmlspecialchars($row['categoryName'] ?? 'No Category') ?></div>

                <?php if ($userID): ?>
                <button class="wishlist-btn <?= $isWishlisted ? 'active' : '' ?>"
                        onclick="toggleWishlist(<?= $row['eventID'] ?>, 'event', this)">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                        <path d="M2.5 1.5h11a.5.5 0 0 1 .5.5v12.8a.3.3 0 0 1-.45.26L8 12.5l-5.55 2.56a.3.3 0 0 1-.45-.26V2a.5.5 0 0 1 .5-.5z"/>
                    </svg>
                </button>
                <?php endif; ?>
            </div>

            <div class="event-info">
                <h3><?= htmlspecialchars($row['eventName']) ?></h3>

                <?php if ($row['isInterested']): ?>
                    <div class="interest-tag">✨ You might be interested</div>
                <?php endif; ?>

                <div class="event-meta">
                    <span class="meta-chip">
                        <span class="emoji">📍</span>
                        <?= htmlspecialchars($row['eventLocation']) ?>, 
                        <?= htmlspecialchars($row['eventCountry']) ?>
                    </span>
                    <span class="meta-chip">
                        <span class="emoji">📅</span>
                        <?= $dateDisplay ?>
                    </span>
                </div>

                <p class="event-desc"><?= htmlspecialchars($row['description']) ?></p>

                <a href="event_detail.php?id=<?= $row['eventID'] ?>" class="join-button">View Details</a>
            </div>
        </div>

    <?php endwhile;
        else:
            echo "<div class='no-event'>No upcoming events available.</div>";
        endif;
        $conn->close();
    ?>
    </div>
</div>

<script>
function toggleWishlist(id, type, btn) {
    fetch('wishlist_toggle.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, type: type })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'added') btn.classList.add('active');
        if (data.status === 'removed') btn.classList.remove('active');
    });
}
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>