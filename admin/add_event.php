<?php
session_start();
include("../db.php");

// Only allow admins or organizers
if (!isset($_SESSION['userID']) || !in_array($_SESSION['role'], ['admin', 'organizer'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];

// UPDATED: Change success message to reflect the pending status
$message = (isset($_GET['status']) && $_GET['status'] == 'success') 
    ? "✅ Event submitted! It will be visible to the public once an Admin approves it." 
    : "";
$error = "";
$countries = [];
$categories = [];

/* ==========================
    FETCH ENUMS & LISTS
========================== */
// Get Country Enum
$countryEnumResult = $conn->query("SHOW COLUMNS FROM event LIKE 'eventCountry'");
if ($countryEnumResult && $countryRow = $countryEnumResult->fetch_assoc()) {
    preg_match_all("/'([^']+)'/", $countryRow['Type'], $matches);
    $countries = $matches[1] ?? [];
}

// Get Categories
$resCat = $conn->query("SELECT categoryID, categoryName FROM category ORDER BY categoryName ASC");
while ($row = $resCat->fetch_assoc()) {
    $categories[] = $row;
}

/* ==========================
    FETCH ALL PAST EVENTS
========================== */
$pastEvents = [];
$pe = $conn->query("SELECT eventID, eventName, startDate, endDate FROM event ORDER BY startDate DESC");
while ($row = $pe->fetch_assoc()) {
    $pastEvents[] = $row;
}

/* ==========================
    HANDLE FORM SUBMISSION
========================== */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $eventName        = trim($_POST['eventName']);
    $eventLocation    = trim($_POST['eventLocation']);
    $eventCountry     = trim($_POST['eventCountry']);
    $categoryID       = intval($_POST['categoryID']);
    $startDate        = $_POST['startDate'];
    $startTime        = $_POST['startTime']; 
    $endDate          = $_POST['endDate'];
    $endTime          = $_POST['endTime'];   
    $deadline         = $_POST['deadline'];
    $maxParticipant   = intval($_POST['maxParticipant']);
    $point            = intval($_POST['point']);
    $description      = trim($_POST['description']);

    /* ==========================
        READ CSV PAST EVENT IDS
    ========================== */
    $selectedPastEvents = [];
    if (!empty($_POST['pastEvents'][0])) {
        $selectedPastEvents = explode(",", $_POST['pastEvents'][0]);
        $selectedPastEvents = array_map('intval', $selectedPastEvents);
    }

    if (!empty($eventName) && !empty($eventLocation) && !empty($eventCountry) &&
        !empty($startDate) && !empty($startTime) && !empty($endDate) && !empty($endTime) && !empty($deadline) &&
        $maxParticipant > 0 && $categoryID > 0) {

        $today = new DateTime(date("Y-m-d"));
        $start = new DateTime($startDate);
        $end   = new DateTime($endDate);
        $dl    = new DateTime($deadline);

        $isError = false;
        if ($start < $today) {
            $error = "⚠️ Start date cannot be in the past.";
            $isError = true;
        }
        elseif ($end < $start) {
            $error = "⚠️ End date cannot be earlier than start.";
            $isError = true;
        }
        elseif ($dl < $today) {
            $error = "⚠️ Deadline cannot be in the past.";
            $isError = true;
        }

        if (!$isError) {
            /* COVER IMAGE */
            $coverImageName = null;
            if (!empty($_FILES['coverImage']['name'])) {
                $ext = pathinfo($_FILES['coverImage']['name'], PATHINFO_EXTENSION);
                $coverImageName = time() . "_cover." . $ext;
                $uploadDir = "../uploads/event_cover/"; 
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                if (!move_uploaded_file($_FILES['coverImage']['tmp_name'], $uploadDir . $coverImageName)) {
                     $error = "❌ Failed to upload cover image.";
                     $isError = true;
                }
            } else {
                 $error = "❌ Cover image is required.";
                 $isError = true;
            }
        }
        
        if (!$isError) {
            /* INSERT EVENT - Added status column set to 'pending' */
            $sql = "INSERT INTO event 
                    (eventName, coverImage, eventLocation, eventCountry, description,
                     startDate, startTime, endDate, endTime, deadline, participantNum, 
                     maxParticipant, point, organizerID, categoryID, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 'pending')";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssssssssssiiii",
                $eventName,
                $coverImageName,
                $eventLocation,
                $eventCountry,
                $description,
                $startDate,
                $startTime,
                $endDate,
                $endTime,
                $deadline,
                $maxParticipant,
                $point,
                $userID,
                $categoryID
            );

            if ($stmt->execute()) {
                $newEventID = $stmt->insert_id;

                /* SAVE PAST EVENT LINKING */
                if (!empty($selectedPastEvents)) {
                    $insertPE = $conn->prepare("INSERT INTO eventpast (eventID, pastEventID) VALUES (?, ?)");
                    foreach ($selectedPastEvents as $peID) {
                        $insertPE->bind_param("ii", $newEventID, $peID);
                        $insertPE->execute();
                    }
                }

                /* UPLOAD GALLERY IMAGES */
                if (!empty($_FILES['galleryImages']['name'][0])) {
                    $uploadGalleryDir = "../uploads/event_gallery/"; 
                    if (!is_dir($uploadGalleryDir)) {
                        mkdir($uploadGalleryDir, 0777, true);
                    }
                    
                    foreach ($_FILES['galleryImages']['tmp_name'] as $i => $tmpName) {
                        $fileName = time() . "_" . basename($_FILES['galleryImages']['name'][$i]);
                        move_uploaded_file($tmpName, $uploadGalleryDir . $fileName);
                        $imgPath = "uploads/event_gallery/" . $fileName;
                        $g = $conn->prepare("
                            INSERT INTO activitygallery (activityID, activityType, imageUrl, caption)
                            VALUES (?, 'event', ?, '')
                        ");
                        $g->bind_param("is", $newEventID, $imgPath);
                        $g->execute();
                    }
                }

                header("Location: add_event.php?status=success");
                exit();

            } else {
                $error = "❌ Database error: " . $stmt->error;
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
<title>Add Event - Admin</title>
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">

<style>
:root { --form-width: 800px; }
* { box-sizing: border-box; }
.form-container { max-width: var(--form-width); margin:30px auto; background:#f9f9f9; padding:30px; border-radius:10px; }
.form-row { display: flex; gap: 20px; margin-bottom: 5px; }
.form-row > div { flex: 1; }
.form-row input, .form-row select { width: 100%; }
.form-row label { margin-top: 15px; }
.form-container form > label:first-of-type { margin-top: 0; }
.form-row > div > label:first-child { margin-top: 0; }

.req { color: red; }
label { font-weight: bold; margin-top: 12px; display: block; }
.success { color: #155724; background-color: #d4edda; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
.error { color: #721c24; background-color: #f8d7da; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb; }

input:not([type="submit"]):not([type="button"]), select, textarea {
    width: 100%; padding: 12px; border-radius: 6px; border: 1px solid #bbb; margin-top: 5px; box-sizing: border-box; height: 44px;
}
textarea { height: 130px; resize: none; }
input[type="file"] { height: auto; padding: 10px 12px; }
select { height: 44px; }

button { width: 100%; padding: 14px; font-size: 16px; background: #007bff; border: none; color: white; border-radius: 6px; margin-top: 22px; cursor: pointer; }
button[onclick="openModal()"] { background:#6c5ce7; margin-top:15px; padding:14px 12px; } 
button[type="submit"] { background: #333; }

.modal-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.45); display: none; justify-content: center; align-items: center; z-index: 1000; }
.modal-box { width: 420px; background: white; padding: 20px 22px; border-radius: 10px; max-height: 75vh; overflow-y: auto; box-shadow: 0 5px 25px rgba(0,0,0,0.15); position: relative; }
.modal-close { position: absolute; top: 12px; right: 15px; font-size: 20px; cursor: pointer; color: #777; }
#searchPastInput { width: 90%; padding: 10px; border-radius: 8px; border: 1px solid #ccc; display: block; margin: 0 auto 12px auto; }
.event-row { display: flex; align-items: center; padding: 10px 6px; border-bottom: 1px solid #eee; gap: 10px; }
.check-col { flex: 0 0 24px; display: flex; justify-content: center; }
.event-info { flex: 1; }

#coverImagePreview { display: none; width: 100%; max-height: 250px; object-fit: cover; margin-top: 15px; border-radius: 8px; border: 1px solid #ddd; }
#galleryImagesPreview { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; margin-bottom: 10px; }
.gallery-img-preview { width: 100px; height: 100px; object-fit: cover; border-radius: 6px; border: 1px solid #ccc; }
</style>
</head>
<body>

<?php include("sidebar.php"); ?>

<div class="main-content">
    <div class="form-container">
        <h2>Add Event</h2>
        <?= $message ? "<div class='success'>$message</div>" : "" ?>
        <?= $error ? "<div class='error'>$error</div>" : "" ?>

        <form method="post" enctype="multipart/form-data">
            <label>Event Name <span class="req">*</span></label>
            <input type="text" name="eventName" value="<?= htmlspecialchars($_POST['eventName'] ?? '') ?>" required>

            <label>Category <span class="req">*</span></label>
            <select name="categoryID" required>
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['categoryID'] ?>" <?= (intval($_POST['categoryID'] ?? 0) == $cat['categoryID']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['categoryName']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Event Description <span class="req">*</span></label>
            <textarea name="description" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

            <label>Cover Image <span class="req">*</span></label>
            <input type="file" name="coverImage" id="coverImageInput" accept="image/*" required onchange="previewCoverImage(event)">
            <img id="coverImagePreview" src="#" alt="Cover Image Preview">

            <label>Gallery Images (optional)</label>
            <input type="file" name="galleryImages[]" multiple id="galleryImagesInput" onchange="previewGalleryImages(event)">
            <div id="galleryImagesPreview"></div> 

            <div class="form-row">
                <div>
                    <label>Event Location <span class="req">*</span></label>
                    <input type="text" name="eventLocation" value="<?= htmlspecialchars($_POST['eventLocation'] ?? '') ?>" required>
                </div>
                <div>
                    <label>Country <span class="req">*</span></label>
                    <select name="eventCountry" required>
                        <option value="">-- Select Country --</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= $c ?>" <?= ($_POST['eventCountry'] ?? '') == $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label>Start Date <span class="req">*</span></label>
                    <input type="date" name="startDate" id="startDate" value="<?= htmlspecialchars($_POST['startDate'] ?? '') ?>" required>
                </div>
                <div>
                    <label>Start Time <span class="req">*</span></label>
                    <input type="time" name="startTime" id="startTime" value="<?= htmlspecialchars($_POST['startTime'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label>End Date <span class="req">*</span></label>
                    <input type="date" name="endDate" id="endDate" value="<?= htmlspecialchars($_POST['endDate'] ?? '') ?>" required>
                </div>
                <div>
                    <label>End Time <span class="req">*</span></label>
                    <input type="time" name="endTime" id="endTime" value="<?= htmlspecialchars($_POST['endTime'] ?? '') ?>" required>
                </div>
            </div>

            <label>Registration Deadline <span class="req">*</span></label>
            <input type="date" name="deadline" id="deadline" value="<?= htmlspecialchars($_POST['deadline'] ?? '') ?>" required>

            <div class="form-row">
                <div>
                    <label>Max Participants <span class="req">*</span></label>
                    <input type="number" name="maxParticipant" value="<?= htmlspecialchars($_POST['maxParticipant'] ?? '') ?>" min="1" required>
                </div>
                <div>
                    <label>Points (Reward) <span class="req">*</span></label>
                    <input type="number" name="point" value="<?= htmlspecialchars($_POST['point'] ?? '') ?>" min="0" required>
                </div>
            </div>

            <p style="background:#eef; padding:14px; border-radius:6px; line-height:1.5; margin-top:14px;">
                <strong>Optional: Link Past Volunteer Event</strong><br>
                You may link previous volunteer event that are related to this course.
                This allows participants to view review left by past participants.
                If this is a brand-new event, you can safely skip this step.
            </p>

            <button type="button" onclick="openModal()" style="background:#6c5ce7;">Select Past Events</button>
            <input type="hidden" name="pastEvents[]" id="pastEventsHolder" value="<?= htmlspecialchars(implode(',', $_POST['pastEvents'] ?? [])) ?>">

            <button type="submit">Add Event</button>
        </form>
    </div>
</div>

<div class="modal-bg" id="pastEventModal">
    <div class="modal-box">
        <span class="modal-close" onclick="closeModal()">✖</span>
        <h3>Select Past Events</h3>
        <input type="text" id="searchPastInput" placeholder="Search event name...">
        <div id="pastEventList">
            <?php foreach ($pastEvents as $ev): ?>
                <div class="event-row">
                    <div class="check-col">
                        <input type="checkbox" value="<?= $ev['eventID'] ?>" class="pastCheck" 
                        <?= in_array($ev['eventID'], $selectedPastEvents ?? []) ? 'checked' : '' ?>>
                    </div>
                    <div class="event-info">
                        <strong><?= htmlspecialchars($ev['eventName']) ?></strong>
                        <small><?= $ev['startDate'] ?> → <?= $ev['endDate'] ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button onclick="applyPastSelection()">✔ Confirm Selection</button>
    </div>
</div>

<script>
let initialPastEvents = document.getElementById("pastEventsHolder").value;
let selectedPastEvents = initialPastEvents ? initialPastEvents.split(',').map(Number) : [];

function previewCoverImage(event) {
    const reader = new FileReader();
    const imagePreview = document.getElementById('coverImagePreview');
    if (event.target.files && event.target.files[0]) {
        reader.onload = function(e) {
            imagePreview.src = e.target.result;
            imagePreview.style.display = 'block';
        }
        reader.readAsDataURL(event.target.files[0]);
    }
}

function previewGalleryImages(event) {
    const previewContainer = document.getElementById('galleryImagesPreview');
    previewContainer.innerHTML = ''; 
    if (event.target.files) {
        Array.from(event.target.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'gallery-img-preview';
                previewContainer.appendChild(img);
            }
            reader.readAsDataURL(file);
        });
    }
}

function openModal() { document.getElementById("pastEventModal").style.display = "flex"; }
function closeModal() { document.getElementById("pastEventModal").style.display = "none"; }
function applyPastSelection() {
    let selected = [];
    document.querySelectorAll("#pastEventModal .pastCheck:checked").forEach(c => selected.push(c.value));
    document.getElementById("pastEventsHolder").value = selected.join(",");
    closeModal();
}

document.getElementById("searchPastInput").addEventListener("keyup", function(){
    let term = this.value.toLowerCase();
    document.querySelectorAll("#pastEventList .event-row").forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(term) ? "flex" : "none";
    });
});

const startInput = document.getElementById("startDate");
const endInput = document.getElementById("endDate");
const deadlineInput = document.getElementById("deadline");
let today = new Date().toISOString().split("T")[0];

startInput.setAttribute("min", today);
deadlineInput.setAttribute("min", today);

startInput.addEventListener("change", function() {
    let startDate = this.value;
    endInput.setAttribute("min", startDate);
    if (endInput.value < startDate) endInput.value = startDate;
    deadlineInput.setAttribute("max", startDate);
    if (deadlineInput.value > startDate) deadlineInput.value = startDate;
});
</script>
</body>
</html>