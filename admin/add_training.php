<?php
session_start();
include("../db.php");

// Only allow admins or organizers
if (!isset($_SESSION['userID']) || !in_array($_SESSION['role'], ['admin', 'organizer'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];
// Check for success status in URL to display message after redirect
$error = "";
$message = (isset($_GET['status']) && $_GET['status'] == 'success') ? "✅ Training course added successfully!" : "";

/* ==========================
    FETCH COUNTRIES
========================== */
$countries = [];
$res = $conn->query("SHOW COLUMNS FROM course LIKE 'courseCountry'");
if ($res && $row = $res->fetch_assoc()) {
    preg_match_all("/'([^']+)'/", $row['Type'], $m);
    $countries = $m[1] ?? [];
}

/* ==========================
    FETCH CATEGORIES
========================== */
$categories = [];
$result = $conn->query("SELECT categoryID, categoryName FROM category ORDER BY categoryName ASC");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

/* ==========================
    FETCH PAST COURSES
========================== */
$pastCourses = [];
$pc = $conn->query("SELECT courseID, courseName, startDate FROM course ORDER BY startDate DESC");
while ($row = $pc->fetch_assoc()) {
    $pastCourses[] = $row;
}

/* ==========================
    FORM SUBMISSION
========================== */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $courseName     = trim($_POST['courseName'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $courseLocation = trim($_POST['courseLocation'] ?? '');
    $courseCountry  = trim($_POST['courseCountry'] ?? '');
    $startDate      = $_POST['startDate'] ?? ''; // Updated field
    $endDate        = $_POST['endDate'] ?? '';   // New field
    $startTime      = $_POST['startTime'] ?? '';
    $endTime        = $_POST['endTime'] ?? '';
    $deadline       = $_POST['deadline'] ?? ''; 
    
    $maxParticipant = intval($_POST['maxParticipant'] ?? 0);
    $fee            = floatval($_POST['fee'] ?? 0);
    $categoryID     = intval($_POST['categoryID'] ?? 0);

    $selectedPastCourses = [];
    $pastCoursesData = $_POST['pastCourses'] ?? ''; 

    if (is_array($pastCoursesData)) {
        $pastCoursesString = $pastCoursesData[0] ?? '';
    } else {
        $pastCoursesString = $pastCoursesData;
    }

    if (!empty($pastCoursesString)) {
        $selectedPastCourses = array_map('intval', explode(",", $pastCoursesString));
    }

    if (
        $courseName && $description && $courseLocation && $courseCountry &&
        $startDate && $endDate && $startTime && $endTime && $deadline &&
        $maxParticipant > 0 && $categoryID > 0
    ) {

        $startObj = DateTime::createFromFormat('Y-m-d', $startDate);
        $endObj   = DateTime::createFromFormat('Y-m-d', $endDate);
        $dlObj    = DateTime::createFromFormat('Y-m-d', $deadline);

        if (!$startObj || !$endObj || !$dlObj) {
            $error = "⚠️ Invalid date format received.";
        }
        
        if (empty($error)) {
            $today = new DateTime(date("Y-m-d"));
            
            if ($startObj < $today) {
                $error = "⚠️ Course start date cannot be in the past.";
            } elseif ($endObj < $startObj) {
                $error = "⚠️ End date cannot be earlier than start date.";
            } elseif ($endTime <= $startTime && $startDate === $endDate) {
                $error = "⚠️ End time must be after start time on the same day.";
            } elseif ($dlObj > $startObj) {
                $error = "⚠️ Deadline cannot be later than the course start date.";
            } else {
                
                $coverImageName = null;
                if (!empty($_FILES['coverImage']['name'])) {
                    $ext = pathinfo($_FILES['coverImage']['name'], PATHINFO_EXTENSION);
                    $coverImageName = time() . "_cover." . $ext;
                    $uploadDir = "../uploads/course_cover/"; 
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    move_uploaded_file($_FILES['coverImage']['tmp_name'], $uploadDir . $coverImageName);
                }

                $sql = "INSERT INTO course
                    (courseName, coverImage, description, courseLocation, courseCountry,
                     startDate, endDate, startTime, endTime, deadline,
                     participantNum, maxParticipant, fee, organizerID, categoryID)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)";
    
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssssssidii", 
                    $courseName, $coverImageName, $description, $courseLocation, 
                    $courseCountry, $startDate, $endDate, $startTime, $endTime, $deadline, 
                    $maxParticipant, $fee, $userID, $categoryID
                );
    
                if ($stmt->execute()) {
                    $newCourseID = $stmt->insert_id;
    
                    if (!empty($selectedPastCourses)) {
                        $cp = $conn->prepare("INSERT INTO coursepast (courseID, pastCourseID) VALUES (?, ?)");
                        foreach ($selectedPastCourses as $pcID) {
                            $cp->bind_param("ii", $newCourseID, $pcID);
                            $cp->execute();
                        }
                    }
    
                    if (!empty($_FILES['galleryImages']['name'][0])) {
                        $uploadGalleryDir = "../uploads/course_gallery/"; 
                        if (!is_dir($uploadGalleryDir)) { mkdir($uploadGalleryDir, 0777, true); }
                        
                        foreach ($_FILES['galleryImages']['tmp_name'] as $i => $tmpName) {
                            $fileName = time() . "_" . basename($_FILES['galleryImages']['name'][$i]);
                            move_uploaded_file($tmpName, $uploadGalleryDir . $fileName);
                            $imgPath = "uploads/course_gallery/" . $fileName;
                            $g = $conn->prepare("INSERT INTO activitygallery (activityID, activityType, imageUrl, caption) VALUES (?, 'course', ?, '')");
                            $g->bind_param("is", $newCourseID, $imgPath);
                            $g->execute();
                        }
                    }
                    header("Location: add_training.php?status=success");
                    exit();
                } else {
                    $error = "❌ Database error: " . $stmt->error;
                }
            }
        }
    } else {
        $error = "⚠️ Please fill all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Training</title>
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">

<style>
:root { --form-width: 800px; }
* { box-sizing: border-box; }
.form-container { max-width: var(--form-width); margin:30px auto; background:#f9f9f9; padding:30px; border-radius:10px; }
.form-row { display: flex; gap: 20px; margin-bottom: 5px; align-items: flex-start; }
.form-row > div { flex: 1; }
label { font-weight: bold; margin-top: 15px; display: block; }
.req { color: red; }
.form-container form > label:first-of-type, .form-row > div > label:first-child { margin-top: 0; }
input:not([type="submit"]):not([type="button"]), select, textarea { width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid #bbb; margin-top: 5px; box-sizing: border-box; height: 44px; font-size: 15px; display: block; }
select { line-height: 1.5; background-color: #fff; }
textarea { height: 130px; resize: none; }
input[type="file"] { height: auto; padding: 10px 12px; }
button { width: 100%; padding: 14px; font-size: 16px; background: #007bff; border: none; color: white; border-radius: 6px; margin-top: 22px; cursor: pointer; }
button.secondary { background: #6c5ce7; margin-top: 15px; }
button[type="submit"] { background: #333; }
#coverPreview { width: 100%; max-height: 250px; object-fit: cover; margin-top: 15px; border-radius: 8px; border: 1px solid #ddd; }
.gallery-preview { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
.gallery-preview img { width: 100px; height: 100px; object-fit: cover; border-radius: 6px; border: 1px solid #ccc; }
.modal-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.45); display: none; justify-content: center; align-items: center; z-index: 1000; }
.modal-box { width: 420px; background: white; padding: 20px 22px; border-radius: 10px; max-height: 75vh; overflow-y: auto; box-shadow: 0 5px 25px rgba(0,0,0,0.15); position: relative; }
.modal-close { position: absolute; top: 12px; right: 15px; font-size: 20px; cursor: pointer; color: #777; }
#searchPastInput { width: 90%; padding: 10px; border-radius: 8px; border: 1px solid #ccc; display: block; margin: 0 auto 12px auto; }
.event-row { display: flex; align-items: center; padding: 10px 6px; border-bottom: 1px solid #eee; gap: 10px; }
.check-col { flex: 0 0 24px; display: flex; justify-content: center; }
.event-info { flex: 1; }
</style>
</head>
<body>

<?php include("sidebar.php"); ?>

<div class="main-content">
    <div class="form-container">
        <h2>Add Training</h2>

        <?= $message ? "<p class='success'>$message</p>" : "" ?>
        <?= $error ? "<p class='error'>$error</p>" : "" ?>

        <form method="post" enctype="multipart/form-data">

            <label>Course Name <span class="req">*</span></label>
            <input type="text" name="courseName" required value="<?= htmlspecialchars($_POST['courseName'] ?? '') ?>">

            <label>Category <span class="req">*</span></label>
            <select name="categoryID" required>
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['categoryID'] ?>" <?= (($_POST['categoryID'] ?? 0) == $c['categoryID']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['categoryName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Description <span class="req">*</span></label>
            <textarea name="description" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

            <label>Cover Image <span class="req">*</span></label>
            <input type="file" name="coverImage" accept="image/*" required onchange="previewCover(this)">
            <img id="coverPreview" style="display:none;">

            <label>Gallery Images (optional)</label>
            <input type="file" name="galleryImages[]" multiple accept="image/*" onchange="previewGallery(this)">
            <div class="gallery-preview" id="galleryPreview"></div>

            <div class="form-row">
                <div>
                    <label>Location <span class="req">*</span></label>
                    <input type="text" name="courseLocation" required value="<?= htmlspecialchars($_POST['courseLocation'] ?? '') ?>">
                </div>
                <div>
                    <label>Country <span class="req">*</span></label>
                    <select name="courseCountry" required>
                        <option value="">-- Select Country --</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= $c ?>" <?= (($_POST['courseCountry'] ?? '') == $c) ? 'selected' : '' ?>>
                                <?= $c ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label>Start Date <span class="req">*</span></label>
                    <input type="date" name="startDate" id="startDateInput" required value="<?= htmlspecialchars($_POST['startDate'] ?? '') ?>">
                </div>
                <div>
                    <label>End Date <span class="req">*</span></label>
                    <input type="date" name="endDate" id="endDateInput" required value="<?= htmlspecialchars($_POST['endDate'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label>Start Time <span class="req">*</span></label>
                    <input type="time" name="startTime" required value="<?= htmlspecialchars($_POST['startTime'] ?? '') ?>">
                </div>
                <div>
                    <label>End Time <span class="req">*</span></label>
                    <input type="time" name="endTime" required value="<?= htmlspecialchars($_POST['endTime'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label>Registration Deadline <span class="req">*</span></label>
                    <input type="date" name="deadline" id="deadlineInput" required value="<?= htmlspecialchars($_POST['deadline'] ?? '') ?>">
                </div>
                <div>
                    <label>Max Participants <span class="req">*</span></label>
                    <input type="number" name="maxParticipant" min="1" required value="<?= htmlspecialchars($_POST['maxParticipant'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label>Fee (RM) <span class="req">*</span></label>
                    <input type="number" step="0.01" name="fee" required value="<?= htmlspecialchars($_POST['fee'] ?? '') ?>">
                </div>
            </div>

            <p style="background:#eef; padding:14px; border-radius:6px; line-height:1.5; margin-top:14px;">
                <strong>Optional: Link Past Training Courses</strong><br>
                You may link previous training sessions that are related to this course.
            </p>

            <button type="button" class="secondary" onclick="openCourseModal()">Select Past Training</button>
            <input type="hidden" name="pastCourses[]" id="pastCoursesHolder" value="<?= htmlspecialchars($_POST['pastCourses'][0] ?? '') ?>">

            <button type="submit">Add Training</button>
        </form>
    </div>
</div>

<div class="modal-bg" id="pastCourseModal">
    <div class="modal-box">
        <span class="modal-close" onclick="closeCourseModal()">✖</span> 
        <h3>Select Past Training</h3>
        <input type="text" id="searchPastInput" placeholder="Search course name..." onkeyup="searchPastCourses(this.value)">
        <div id="pastCourseList">
            <?php foreach ($pastCourses as $pc): ?>
                <div class="event-row">
                    <div class="check-col">
                        <input type="checkbox" value="<?= $pc['courseID'] ?>" class="pastCheck">
                    </div>
                    <div class="event-info">
                        <strong><?= htmlspecialchars($pc['courseName']) ?></strong>
                        <small><?= $pc['startDate'] ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button onclick="applyCourseSelection()">✔ Confirm Selection</button>
    </div>
</div>

<script>
function previewCover(input) {
    const img = document.getElementById('coverPreview');
    if (input.files && input.files[0]) {
        img.src = URL.createObjectURL(input.files[0]);
        img.style.display = 'block';
    }
}

function previewGallery(input) {
    const box = document.getElementById('galleryPreview');
    box.innerHTML = '';
    if (input.files) {
        Array.from(input.files).forEach(file => {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            box.appendChild(img);
        });
    }
}

function openCourseModal() { document.getElementById("pastCourseModal").style.display = "flex"; }
function closeCourseModal() { document.getElementById("pastCourseModal").style.display = "none"; }

function applyCourseSelection() {
    let selected = [];
    document.querySelectorAll("#pastCourseModal .pastCheck:checked").forEach(c => selected.push(c.value));
    document.getElementById("pastCoursesHolder").value = selected.join(",");
    closeCourseModal();
}

function searchPastCourses(term) {
    term = term.toLowerCase();
    document.querySelectorAll("#pastCourseList .event-row").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(term) ? "flex" : "none";
    });
}

// Date Logic for StartDate, EndDate, and Deadline
const startDateInput = document.getElementById('startDateInput');
const endDateInput = document.getElementById('endDateInput');
const deadlineInput = document.getElementById('deadlineInput');
const today = new Date().toISOString().split('T')[0];

startDateInput.setAttribute('min', today);
deadlineInput.setAttribute('min', today);

startDateInput.addEventListener('change', function() {
    endDateInput.setAttribute("min", this.value);
    if (endDateInput.value < this.value) endDateInput.value = this.value;
    
    deadlineInput.setAttribute('max', this.value);
    if (deadlineInput.value > this.value) deadlineInput.value = this.value;
});

if (window.location.search.includes('status=success')) {
    document.getElementById('coverPreview').style.display = 'none';
    document.getElementById('galleryPreview').innerHTML = '';
    document.getElementById('pastCoursesHolder').value = '';
}
</script>
</body>
</html>