<?php
session_start();
include("db.php");

// ✅ Fetch all categories
$categoryQuery = "SELECT * FROM category ORDER BY categoryName ASC";
$categoryResult = $conn->query($categoryQuery);

// ✅ Fetch ENUM values for eventCountry dynamically
$countries = [];
$enumQuery = "SHOW COLUMNS FROM event LIKE 'eventCountry'";
$enumResult = $conn->query($enumQuery);

if ($enumResult && $row = $enumResult->fetch_assoc()) {
    $type = $row['Type']; 
    preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
    $countries = explode("','", $matches[1]);
}

// ✅ Fetch latest upcoming events (ONLY APPROVED)
$eventQuery = "
    SELECT 
        e.eventID, e.eventName, e.eventLocation, e.eventCountry, e.startDate, e.endDate, 
        e.deadline, e.participantNum, e.maxParticipant,
        e.coverImage,
        c.categoryName
    FROM event e
    LEFT JOIN category c ON e.categoryID = c.categoryID
    WHERE e.endDate >= CURDATE() AND e.status = 'approved'
    ORDER BY e.startDate ASC
    LIMIT 3
";
$eventResult = $conn->query($eventQuery);

// ✅ Fetch latest upcoming courses (ONLY APPROVED)
// Note: Assuming 'course' table also has a 'status' column. 
// If not, remove 'AND c.status = 'approved'' from the query below.
$courseQuery = "
    SELECT 
        c.courseID, c.courseName, c.courseLocation, c.courseCountry, 
        c.startDate, c.endDate, c.deadline, c.participantNum, c.maxParticipant, c.fee,
        c.coverImage,
        cat.categoryName
    FROM course c
    LEFT JOIN category cat ON c.categoryID = cat.categoryID
    WHERE c.endDate >= CURDATE() AND c.status = 'approved'
    ORDER BY c.startDate ASC
    LIMIT 3
";
$courseResult = $conn->query($courseQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Volunteering Management System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* 🌄 Hero Banner Section */
        .hero-banner {
            position: relative;
            background: url('uploads/volunteer landing page.jpg') center/cover no-repeat;
            height: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white; 
        }
        
        .hero-banner::after { 
            content:""; 
            position:absolute; 
            top:0; left:0; width:100%; height:100%; 
            background:rgba(0,0,0,0.6); 
        }

        .hero-content { position:relative; z-index:2; max-width:800px; padding:20px; }
        
        .hero-content h1 { 
            font-size:3.5rem; 
            margin-bottom:15px; 
            font-weight:800; 
            text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.7); 
        }

        .hero-content p { 
            font-size:1.3rem; 
            margin-bottom:30px; 
            color:#ffffff; 
            text-shadow: 1px 1px 5px rgba(0, 0, 0, 0.8);
        }

        /* 🔍 Hero Search Form */
        .hero-search-form { display:flex; justify-content:center; align-items:center; gap:8px; margin-top:20px; flex-wrap:wrap; }
        .hero-search-form input[type="text"], .hero-search-form select {
            padding:16px 18px; border-radius:50px; border:none; font-size:1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .hero-search-form input[type="text"] { width:280px; max-width:90%; }
        .hero-search-form select { width:170px; }
        .hero-search-form button {
            padding:16px 25px; border-radius:50px; border:none; background-color:#2575fc;
            color:#fff; font-weight:bold; cursor:pointer; display:flex; align-items:center;
            gap:6px; transition:all 0.3s ease;
            box-shadow: 0 4px 15px rgba(37, 117, 252, 0.4);
        }
        .hero-search-form button:hover { background-color:#1b5ed8; transform:translateY(-2px); }

        /* 🎯 Category Section */
        .category-section { text-align:center; padding:60px 20px; background:#f7faff; }
        .category-section h2 { margin-bottom:35px; font-size:2.4rem; color:#222; }
        .category-grid { display:flex; justify-content:center; flex-wrap:wrap; gap:30px; }
        .category-box {
            background:white; border-radius:15px; padding:25px 30px; text-align:center;
            width:220px; box-shadow:0 4px 10px rgba(0,0,0,0.05); transition:all 0.3s ease;
            text-decoration:none; color:#222; border: 1px solid #eee;
        }
        .category-box:hover { transform:translateY(-8px); box-shadow:0 10px 20px rgba(0,0,0,0.1); border-color: #2575fc; }
        .category-box i { font-size:40px; color:#2575fc; margin-bottom:12px; }
        .category-box h3 { font-size:1.1rem; font-weight:bold; }

        /* 🖼️ Event Cards */
        .event-section { padding:80px 20px; text-align:center; background:#fff; }
        .event-grid { display:flex; flex-wrap:wrap; justify-content:center; gap:30px; }
        .event-card {
            width:300px; background:#fff; border-radius:15px; overflow:hidden;
            box-shadow:0 6px 18px rgba(0,0,0,0.08); transition:all 0.3s ease;
            text-decoration: none; display: flex; flex-direction: column;
        }
        .event-card:hover { transform:translateY(-10px); box-shadow:0 12px 25px rgba(0,0,0,0.15); }
        
        .event-card .img-container { width: 100%; height: 180px; overflow: hidden; }
        .event-card img { 
            width:100%; height:100%; object-fit:cover; 
            transition: transform 0.5s ease;
        }
        .event-card:hover img { transform: scale(1.1); }
        
        .event-card .content { padding:20px; text-align: left; flex-grow: 1; }
        .event-card h3 { color:#222; margin-bottom:8px; font-size:1.15rem; font-weight:bold; }
        .event-card p { color:#555; font-size:0.9rem; margin-bottom: 6px; line-height: 1.4; display: flex; align-items: flex-start; gap: 8px; }
        .event-card i { color: #2575fc; margin-top: 3px; font-size: 0.9rem; width: 14px; }

        .btn-more {
            display: inline-block; margin-top: 40px; text-decoration:none; 
            padding:14px 30px; background:#2575fc; color:#fff; 
            border-radius:50px; font-weight:bold; transition: 0.3s;
        }
        .btn-more:hover { background: #1b5ed8; transform: scale(1.05); }
    </style>
</head>
<body>

<?php include("user_navbar.php"); ?>

<section class="hero-banner">
    <div class="hero-content">
        <h1>Make a Difference Today</h1>
        <p>Join thousands of volunteers working together to create a better world.</p>

        <form action="volunteer_event.php" method="get" class="hero-search-form">
            <input type="text" name="search" placeholder="Search for volunteering events...">

            <select name="category">
                <option value="">All Categories</option>
                <?php
                if ($categoryResult && $categoryResult->num_rows > 0) {
                    $categoryResult->data_seek(0);
                    while ($row = $categoryResult->fetch_assoc()) {
                        echo "<option value='{$row['categoryID']}'>{$row['categoryName']}</option>";
                    }
                }
                ?>
            </select>

            <select name="country">
                <option value="">All Countries</option>
                <?php
                foreach ($countries as $countryName) {
                    echo "<option value='" . htmlspecialchars($countryName) . "'>" . htmlspecialchars($countryName) . "</option>";
                }
                ?>
            </select>

            <button type="submit"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
</section>

<section class="event-section">
    <h2>🌟 Upcoming Volunteer Events</h2>
    <div class="event-grid">
        <?php
        if ($eventResult && $eventResult->num_rows > 0) {
            while ($event = $eventResult->fetch_assoc()) {
                $cover = $event['coverImage'] ?? '';
                $imagePath = "uploads/event_cover/" . htmlspecialchars(basename($cover));
                $title = htmlspecialchars($event['eventName']);
                $fullAddress = htmlspecialchars($event['eventLocation'] . ", " . $event['eventCountry']);
                
                $dateText = ($event['startDate'] === $event['endDate']) 
                    ? date('M d, Y', strtotime($event['startDate'])) 
                    : date('M d', strtotime($event['startDate'])) . " - " . date('M d, Y', strtotime($event['endDate']));

                echo "
                <a href='event_detail.php?id={$event['eventID']}' class='event-card'>
                    <div class='img-container'>
                        <img src='{$imagePath}' alt='{$title}'>
                    </div>
                    <div class='content'>
                        <h3>{$title}</h3>
                        <p><i class='fas fa-map-marker-alt'></i> <span>{$fullAddress}</span></p>
                        <p><i class='far fa-calendar-alt'></i> <span>{$dateText}</span></p>
                    </div>
                </a>";
            }
        } else {
            echo "<p>No upcoming events found.</p>";
        }
        ?>
    </div>
    <a href="volunteer_event.php" class="btn-more">See More Events →</a>
</section>

<section class="event-section" style="background:#f9fbff;">
    <h2>📚 Latest Training Courses</h2>
    <div class="event-grid">
        <?php
        if ($courseResult && $courseResult->num_rows > 0) {
            while ($course = $courseResult->fetch_assoc()) {
                $cover = $course['coverImage'] ?? '';
                $imagePath = "uploads/course_cover/" . htmlspecialchars(basename($cover));
                $title = htmlspecialchars($course['courseName']);
                $fullAddress = htmlspecialchars($course['courseLocation'] . ", " . $course['courseCountry']);
                
                $dateText = ($course['startDate'] === $course['endDate']) 
                    ? date('M d, Y', strtotime($course['startDate'])) 
                    : date('M d', strtotime($course['startDate'])) . " - " . date('M d, Y', strtotime($course['endDate']));

                echo "
                <a href='course_detail.php?id={$course['courseID']}' class='event-card'>
                    <div class='img-container'>
                        <img src='{$imagePath}' alt='{$title}'>
                    </div>
                    <div class='content'>
                        <h3>{$title}</h3>
                        <p><i class='fas fa-map-marker-alt'></i> <span>{$fullAddress}</span></p>
                        <p><i class='far fa-calendar-alt'></i> <span>{$dateText}</span></p>
                    </div>
                </a>";
            }
        } else {
            echo "<p>No training courses found.</p>";
        }
        ?>
    </div>
    <a href="training_course.php" class="btn-more">See More Courses →</a>
</section>

<section class="category-section">
    <h2>🎯 Explore Event Categories</h2>
    <div class="category-grid">
        <?php
        $icons = [
            'Education'=>'fa-book','Environment'=>'fa-tree','Animal Rescue'=>'fa-paw',
            'Health'=>'fa-heartbeat','Community'=>'fa-users','Sports'=>'fa-futbol','Technology'=>'fa-laptop-code'
        ];
        if ($categoryResult && $categoryResult->num_rows > 0) {
            $categoryResult->data_seek(0);
            while ($row = $categoryResult->fetch_assoc()) {
                $icon = $icons[$row['categoryName']] ?? 'fa-hand-holding-heart';
                echo "<a href='volunteer_event.php?category={$row['categoryID']}' class='category-box'>
                        <i class='fas {$icon}'></i><h3>{$row['categoryName']}</h3>
                      </a>";
            }
        }
        ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

</body>
</html>