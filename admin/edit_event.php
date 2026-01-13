<?php
session_start();
include("../db.php");

// Only allow admins + organizer
if (!isset($_SESSION['userID']) || !in_array($_SESSION['role'], ['admin', 'organizer'])) {
    header("Location: login.php");
    exit();
}

$eventID = $_GET['id'] ?? null;
if (!$eventID) {
    header("Location: manage_event.php");
    exit();
}

$userID = $_SESSION['userID'];
$userRole = $_SESSION['role'];
$message = $error = ""; 

/* ==================================
    HANDLE GALLERY IMAGE DELETION (DB)
   ================================== */
if (isset($_GET['delete_img'])) {
    $imgID = intval($_GET['delete_img']);
    $checkImg = $conn->prepare("SELECT imageUrl FROM activitygallery WHERE galleryID = ? AND activityID = ?");
    $checkImg->bind_param("ii", $imgID, $eventID);
    $checkImg->execute();
    $imgRes = $checkImg->get_result();
    
    if ($row = $imgRes->fetch_assoc()) {
        $filePath = "../" . $row['imageUrl'];
        if (file_exists($filePath)) unlink($filePath);
        $del = $conn->prepare("DELETE FROM activitygallery WHERE galleryID = ?");
        $del->bind_param("i", $imgID);
        if ($del->execute()) {
            $message = "✅ Image deleted successfully!";
        }
    }
}

/* ==================================
    HANDLE CERTIFICATE REMOVAL
   ================================== */
if (isset($_GET['remove_cert'])) {
    $stmtCert = $conn->prepare("SELECT certificate_template FROM event WHERE eventID = ?");
    $stmtCert->bind_param("i", $eventID);
    $stmtCert->execute();
    $resCert = $stmtCert->get_result()->fetch_assoc();

    if ($resCert && !empty($resCert['certificate_template'])) {
        $filePath = "../uploads/certificates/" . $resCert['certificate_template'];
        if (file_exists($filePath)) unlink($filePath);
        
        $updateCert = $conn->prepare("UPDATE event SET certificate_template = NULL WHERE eventID = ?");
        $updateCert->bind_param("i", $eventID);
        if ($updateCert->execute()) {
            header("Location: edit_event.php?id=$eventID&msg=cert_removed");
            exit();
        }
    }
}

/* ==========================
    FETCH EVENT
   ========================== */
$stmt = $conn->prepare("
    SELECT e.*, c.categoryName
    FROM event e
    LEFT JOIN category c ON e.categoryID = c.categoryID
    WHERE e.eventID = ?
");
$stmt->bind_param("i", $eventID);
$stmt->execute();
$result = $stmt->get_result();
$event = $result->fetch_assoc();

if (!$event) {
    header("Location: manage_event.php?error=EventNotFound");
    exit();
}

// Organizer restriction
if ($userRole === 'organizer' && $event['organizerID'] != $userID) {
    header("Location: manage_event.php?error=UnauthorizedAccess");
    exit();
}

/* ==========================
    GET COUNTRY ENUM
   ========================== */
$countries = [];
$countryEnumResult = $conn->query("SHOW COLUMNS FROM event LIKE 'eventCountry'");
if ($countryEnumResult && $countryRow = $countryEnumResult->fetch_assoc()) {
    preg_match_all("/'([^']+)'/", $countryRow['Type'], $matches);
    $countries = $matches[1] ?? [];
}

/* ==========================
    GET CATEGORIES
   ========================== */
$categories = [];
$resCat = $conn->query("SELECT categoryID, categoryName FROM category ORDER BY categoryName ASC");
while ($row = $resCat->fetch_assoc()) {
    $categories[] = $row;
}

/* ==========================
    FETCH ALL OTHER PAST EVENTS (FOR MODAL)
========================== */
$pastEvents = [];
$q = $conn->query("
    SELECT eventID, eventName, startDate, endDate
    FROM event
    WHERE eventID != $eventID
    ORDER BY startDate DESC
");
while ($r = $q->fetch_assoc()) {
    $pastEvents[] = $r;
}

/* ==========================
    FETCH SELECTED PAST EVENTS (FOR MODAL)
========================== */
$selectedPastEvents = [];
$sp = $conn->prepare("SELECT pastEventID FROM eventpast WHERE eventID = ?");
$sp->bind_param("i", $eventID);
$sp->execute();
$resSp = $sp->get_result();
while ($r = $resSp->fetch_assoc()) {
    $selectedPastEvents[] = $r['pastEventID'];
}


/* ==========================
    HANDLE UPDATE
   ========================== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $eventName       = trim($_POST['eventName']);
    $description     = trim($_POST['description']);
    $location        = trim($_POST['eventLocation']);
    $country         = trim($_POST['eventCountry']);
    $start           = $_POST['startDate'];
    $startTime       = $_POST['startTime']; 
    $end             = $_POST['endDate'];
    $endTime         = $_POST['endTime'];   
    $deadline        = $_POST['deadline'];
    $maxParticipant = intval($_POST['maxParticipant']);
    $point           = intval($_POST['point']);
    $categoryID      = intval($_POST['categoryID']);
    
    // Server-side Validation
    $today = new DateTime(date("Y-m-d"));
    $startDateTime = new DateTime($start);
    $endDateTime = new DateTime($end);
    $deadlineDateTime = new DateTime($deadline);
    $isError = false;

    if ($startDateTime < $today) {
        $error = "⚠️ Start date cannot be in the past.";
        $isError = true;
    } elseif ($endDateTime < $startDateTime) {
        $error = "⚠️ End date cannot be earlier than start.";
        $isError = true;
    } elseif ($deadlineDateTime > $startDateTime) { 
        $error = "⚠️ Deadline cannot be later than the event start date.";
        $isError = true;
    }

    if (!$isError) {
        $pastEventIDs = [];
        if (!empty($_POST['pastEvents'][0])) {
            $pastEventIDs = array_map('intval', explode(",", $_POST['pastEvents'][0]));
        }

        /* COVER IMAGE */
        $coverImageName = $event['coverImage']; 
        $uploadDir = "../uploads/event_cover/"; 
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }

        if (!empty($_FILES['coverImage']['name'])) {
            $ext = pathinfo($_FILES['coverImage']['name'], PATHINFO_EXTENSION);
            $coverImageName = time() . "_cover." . $ext;
            move_uploaded_file($_FILES['coverImage']['tmp_name'], $uploadDir . $coverImageName);
        }

        /* CERTIFICATE PDF UPLOAD */
        $certName = $event['certificate_template'];
        if (!empty($_FILES['certFile']['name'])) {
            $ext = strtolower(pathinfo($_FILES['certFile']['name'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $certName = time() . "_cert.pdf";
                $uploadDirCert = "../uploads/certificates/"; 
                if (!is_dir($uploadDirCert)) { mkdir($uploadDirCert, 0777, true); }
                move_uploaded_file($_FILES['certFile']['tmp_name'], $uploadDirCert . $certName);
            } else {
                $error = "❌ Only PDF files are allowed for certificates.";
                $isError = true;
            }
        }

        if (!$isError) {
            /* UPDATE EVENT */
            $stmt = $conn->prepare("
                UPDATE event
                SET eventName=?, description=?, coverImage=?, eventLocation=?, eventCountry=?, 
                    startDate=?, startTime=?, endDate=?, endTime=?, deadline=?, 
                    maxParticipant=?, point=?, categoryID=?, certificate_template=?
                WHERE eventID=?
            ");

            $stmt->bind_param(
                "ssssssssssiiisi",
                $eventName, $description, $coverImageName, $location, $country,
                $start, $startTime, $end, $endTime, $deadline, 
                $maxParticipant, $point, $categoryID, $certName,
                $eventID
            );

            if ($stmt->execute()) {
                /* UPDATE PAST EVENT LINKING */
                $conn->query("DELETE FROM eventpast WHERE eventID = $eventID");
                if (!empty($pastEventIDs)) {
                    $ins = $conn->prepare("INSERT INTO eventpast (eventID, pastEventID) VALUES (?, ?)");
                    foreach ($pastEventIDs as $pid) {
                        $ins->bind_param("ii", $eventID, $pid);
                        $ins->execute();
                    }
                }

                /* UPLOAD NEW GALLERY IMAGES */
                if (!empty($_FILES['galleryImages']['name'][0])) {
                    $uploadGalleryDir = "../uploads/event_gallery/"; 
                    if (!is_dir($uploadGalleryDir)) { mkdir($uploadGalleryDir, 0777, true); }
                    
                    foreach ($_FILES['galleryImages']['tmp_name'] as $i => $tmpName) {
                        if ($_FILES['galleryImages']['error'][$i] == 0) {
                            $fileName = time() . "_" . basename($_FILES['galleryImages']['name'][$i]);
                            move_uploaded_file($tmpName, $uploadGalleryDir . $fileName);
                            $imgPath = "uploads/event_gallery/" . $fileName;
                            $g = $conn->prepare("INSERT INTO activitygallery (activityID, activityType, imageUrl) VALUES (?, 'event', ?)");
                            $g->bind_param("is", $eventID, $imgPath);
                            $g->execute();
                        }
                    }
                }
                header("Location: edit_event.php?id=$eventID&msg=updated");
                exit();
            } else {
                $error = "❌ Database error: " . $stmt->error;
            }
        }
    }
}

if(isset($_GET['msg'])) {
    if($_GET['msg'] == 'updated') $message = "✅ Event updated successfully!";
    if($_GET['msg'] == 'cert_removed') $message = "✅ Custom certificate removed. System will use default.";
}

/* ==========================
    GET CURRENT GALLERY IMAGES
   ========================== */
$gallery = $conn->prepare("SELECT * FROM activitygallery WHERE activityID=? AND activityType='event'");
$gallery->bind_param("i", $eventID);
$gallery->execute();
$galleryResult = $gallery->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Event</title>
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
    <style>
        :root { --form-width: 800px; }
        * { box-sizing: border-box; }
        .form-container { max-width: var(--form-width); margin:0 auto; }
        .req { color: red; margin-left: 2px; }
        label { font-weight:bold; margin-top:12px; display:block; }
        .form-row { display: flex; gap: 20px; }
        .form-row > div { flex: 1; }
        .form-row label { margin-top: 15px; }
        input:not([type="submit"]):not([type="button"]):not([type="checkbox"]), select, textarea { width:100%; padding:12px; border-radius:6px; border:1px solid #ccc; margin-top:5px; box-sizing:border-box; height:44px; }
        textarea { height:120px; resize:none; }
        input[type="file"] { height: auto; padding: 10px 12px; }
        button { background:#333; color:#fff; border:none; cursor:pointer; margin-top:20px; padding:14px 12px; border-radius:6px; font-size:16px; width:100%; }
        button:hover { background:#555; }
        button[onclick="openModal()"] { background:#6c5ce7; margin-top:5px; } 

        /* CERTIFICATE STYLING */
        .cert-management-box { background: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-top: 10px; }
        .cert-actions { display: flex; gap: 10px; margin-top: 10px; align-items: center; }
        .btn-view-pdf { display: inline-block; background: #333; color: #fff !important; padding: 10px 15px; border-radius: 6px; text-decoration: none; font-size: 14px; }
        .btn-remove-cert { display: inline-block; background: #ff4757; color: #fff !important; padding: 10px 15px; border-radius: 6px; text-decoration: none; font-size: 14px; }

        .modal-bg { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.45); display:none; justify-content:center; align-items:center; z-index:1000; }
        .modal-box { width:420px; background:#fff; border-radius:10px; max-height:80vh; position:relative; box-shadow: 0 5px 25px rgba(0,0,0,0.15); display: flex; flex-direction: column; overflow: hidden; }
        .modal-header { padding: 20px 22px 10px 22px; border-bottom: 1px solid #eee; }
        .modal-footer { padding: 10px 22px 20px 22px; border-top: 1px solid #eee; background: #fff; }
        #pastEventList { flex: 1; overflow-y: auto; padding: 0 22px; }
        .modal-close { position:absolute; top:12px; right:15px; cursor:pointer; font-size:20px; color: #777; z-index: 1001; }
        #searchPastInput { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ccc; margin-top: 10px; height: 44px; }
        .event-row { display: flex; align-items: center; padding: 10px 6px; border-bottom: 1px solid #eee; gap: 10px; }
        .check-col { flex: 0 0 24px; display: flex; justify-content: center; }
        .event-info { flex: 1; }
        .gallery-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap:15px; margin-top:10px; }
        .gallery-item { position: relative; width: 100%; height: 110px; }
        .gallery-item img { width:100%; height:110px; object-fit:cover; border-radius:6px; border:1px solid #ccc; }
        .btn-del-img { position: absolute; top: -8px; right: -8px; background: #ff4757; color: white !important; border: none; width: 24px; height: 24px; border-radius: 50%; font-size: 14px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; text-decoration: none; z-index: 10; box-shadow: 0 2px 5px rgba(0,0,0,0.2); padding: 0; line-height: 1; }
        .preview-badge { position: absolute; bottom: 5px; left: 5px; background: #2ed573; color: white; font-size: 10px; padding: 2px 5px; border-radius: 4px; font-weight: bold; pointer-events: none; }
        .back-link-wrapper { max-width: var(--form-width); margin: 30px auto 10px auto; padding: 0 30px; }
        .back-link { display: inline-block; color: #333 !important; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<?php include("sidebar.php"); ?>

<div class="main-content">
    <div class="back-link-wrapper">
        <a href="manage_event.php" class="back-link">← Back to Manage Events</a>
    </div>

    <div class="form-container">
        <h2>Edit Event</h2>
        
        <?php if ($message): ?>
            <p style="background:#d4edda; color:#155724; padding:10px; border-radius:5px;"><?= $message ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p style="background:#f8d7da; color:#721c24; padding:10px; border-radius:5px;"><?= $error ?></p>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <label>Event Name <span class="req">*</span></label>
            <input type="text" name="eventName" value="<?= htmlspecialchars($event['eventName']) ?>" required>

            <label>Description <span class="req">*</span></label>
            <textarea name="description" required><?= htmlspecialchars($event['description']) ?></textarea>

            <label>Current Cover Image</label>
            <?php if ($event['coverImage']): ?>
                <img src="../uploads/event_cover/<?= $event['coverImage'] ?>" style="width:100%; max-height:250px; object-fit:cover; border-radius:8px; margin-bottom:10px;">
            <?php endif; ?>

            <label>Replace Cover Image</label>
            <input type="file" name="coverImage" accept="image/*">

            <label>Gallery Images (Existing & New)</label>
            <input type="file" id="galleryInputPicker" accept="image/*" multiple>
            <input type="file" name="galleryImages[]" id="galleryPostInput" multiple style="display:none;">

            <div class="gallery-grid" id="galleryGrid">
                <?php while ($row = $galleryResult->fetch_assoc()): ?>
                    <div class="gallery-item">
                        <a href="edit_event.php?id=<?= $eventID ?>&delete_img=<?= $row['galleryID'] ?>" 
                           class="btn-del-img" 
                           onclick="return confirm('Delete this image?')">×</a>
                        <img src="../<?= $row['imageUrl'] ?>">
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="form-row">
                <div>
                    <label>Location <span class="req">*</span></label>
                    <input type="text" name="eventLocation" value="<?= htmlspecialchars($event['eventLocation']) ?>" required>
                </div>
                <div>
                    <label>Country <span class="req">*</span></label>
                    <select name="eventCountry" required>
                        <option value="">-- Select Country --</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= $c ?>" <?= ($event['eventCountry'] == $c) ? 'selected' : '' ?>>
                                <?= $c ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label>Category <span class="req">*</span></label>
            <select name="categoryID" required>
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['categoryID'] ?>" 
                        <?= ($event['categoryID'] == $cat['categoryID']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['categoryName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <div class="form-row">
                <div>
                    <label>Start Date <span class="req">*</span></label>
                    <input type="date" name="startDate" id="startDateInput" value="<?= $event['startDate'] ?>" required>
                </div>
                <div>
                    <label>Start Time <span class="req">*</span></label>
                    <input type="time" name="startTime" value="<?= $event['startTime'] ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label>End Date <span class="req">*</span></label>
                    <input type="date" name="endDate" id="endDateInput" value="<?= $event['endDate'] ?>" required>
                </div>
                <div>
                    <label>End Time <span class="req">*</span></label>
                    <input type="time" name="endTime" value="<?= $event['endTime'] ?>" required>
                </div>
            </div>

            <label>Deadline <span class="req">*</span></label>
            <input type="date" name="deadline" id="deadlineInput" value="<?= $event['deadline'] ?>" required>

            <div class="form-row">
                <div>
                    <label>Max Participants <span class="req">*</span></label>
                    <input type="number" name="maxParticipant" value="<?= $event['maxParticipant'] ?>" min="1" required>
                </div>
                <div>
                    <label>Points <span class="req">*</span></label>
                    <input type="number" name="point" value="<?= $event['point'] ?>" min="0" required>
                </div>
            </div>

            <label style="margin-top:25px;">Certificate Template Configuration</label>
            <div class="cert-management-box">
                <?php if (!empty($event['certificate_template'])): ?>
                    <span style="color: #27ae60; font-weight: bold;">🟢 Custom Template Uploaded</span>
                    <div class="cert-actions">
                        <a href="../uploads/certificates/<?= $event['certificate_template'] ?>" target="_blank" class="btn-view-pdf">👁️ View Uploaded PDF</a>
                        <a href="edit_event.php?id=<?= $eventID ?>&remove_cert=1" class="btn-remove-cert" onclick="return confirm('Remove custom certificate? System will revert to the default template.')">🗑️ Remove Custom</a>
                    </div>
                <?php else: ?>
                    <span style="color: #7f8c8d; font-weight: bold;">⚪ Using System Default Template</span>
                    <div class="cert-actions">
                        <a href="../uploads/certificates/certificate_template_demo.pdf" target="_blank" class="btn-view-pdf">👁️ View Default Template</a>
                    </div>
                <?php endif; ?>
            </div>

            <label>Upload/Replace Custom Template (PDF Only)</label>
            <input type="file" name="certFile" accept=".pdf">
            
            <p style="background:#eef;padding:14px;border-radius:6px;margin-top:25px;">
                <strong>Optional: Link Past Events</strong><br>
                Link previous events to show continuity or history.
            </p>

            <button type="button" onclick="openModal()" style="background:#6c5ce7;">Select Past Events</button>
            <input type="hidden" name="pastEvents[]" id="pastEventsHolder" value="<?= implode(',', $selectedPastEvents) ?>">

            <button type="submit">Update Event</button>
        </form>
    </div>
</div>

<div class="modal-bg" id="pastModal">
    <div class="modal-box" id="modalBox">
        <span class="modal-close" onclick="applyPast()">✖</span>
        <div class="modal-header">
            <h3>Select Past Events</h3>
            <input type="text" id="searchPastInput" placeholder="Search event name..." onkeyup="searchPastEvents(this.value)">
        </div>
        <div id="pastEventList">
            <?php foreach ($pastEvents as $pe): ?>
                <div class="event-row">
                    <div class="check-col">
                        <input type="checkbox" class="pastCheck" value="<?= $pe['eventID'] ?>"
                        <?= in_array($pe['eventID'], $selectedPastEvents) ? 'checked' : '' ?>>
                    </div>
                    <div class="event-info">
                        <strong><?= htmlspecialchars($pe['eventName']) ?></strong>
                        <small><?= $pe['startDate'] ?> → <?= $pe['endDate'] ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="modal-footer">
            <button type="button" onclick="applyPast()" style="background:#333;">✔ Confirm Selection</button>
        </div>
    </div>
</div>

<script>
/* MULTIPLE UPLOAD & INDIVIDUAL PREVIEW DELETE */
const dt = new DataTransfer(); 
const galleryPicker = document.getElementById('galleryInputPicker');
const galleryPostInput = document.getElementById('galleryPostInput');
const galleryGrid = document.getElementById('galleryGrid');

galleryPicker.addEventListener('change', function() {
    for (let file of this.files) { dt.items.add(file); }
    this.value = ''; 
    refreshGalleryDisplay();
});

function refreshGalleryDisplay() {
    document.querySelectorAll('.is-new-preview').forEach(el => el.remove());
    galleryPostInput.files = dt.files; 
    for (let i = 0; i < dt.files.length; i++) {
        const file = dt.files[i];
        const reader = new FileReader();
        reader.onload = function(e) {
            const item = document.createElement('div');
            item.className = 'gallery-item is-new-preview';
            item.innerHTML = `<a href="javascript:void(0)" class="btn-del-img" onclick="removeSelectedFile(${i})">×</a><span class="preview-badge">NEW</span><img src="${e.target.result}">`;
            galleryGrid.appendChild(item);
        }
        reader.readAsDataURL(file);
    }
}

function removeSelectedFile(index) {
    if(confirm("Delete this image?")) {
        dt.items.remove(index);
        refreshGalleryDisplay();
    }
}

/* MODAL & SEARCH FUNCTIONS */
const modalBg = document.getElementById("pastModal");
const modalBox = document.getElementById("modalBox");
function openModal(){ modalBg.style.display="flex"; }
function closeModal(){ modalBg.style.display="none"; }

function applyPast(){
    let selected = [];
    document.querySelectorAll("#pastModal .pastCheck:checked").forEach(c => selected.push(c.value));
    document.getElementById("pastEventsHolder").value = selected.join(",");
    closeModal();
}

modalBg.addEventListener("click", function(event) {
    if (!modalBox.contains(event.target)) { applyPast(); }
});

function searchPastEvents(term) {
    term = term.toLowerCase();
    document.querySelectorAll("#pastEventList .event-row").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(term) ? "flex" : "none";
    });
}

/* DATE VALIDATION */
const startDateInput = document.getElementById('startDateInput');
const endDateInput = document.getElementById('endDateInput');
const deadlineInput = document.getElementById('deadlineInput');

startDateInput.addEventListener('change', function() {
    endDateInput.setAttribute("min", this.value);
    if (endDateInput.value < this.value) endDateInput.value = this.value;
    deadlineInput.setAttribute('max', this.value);
    if (deadlineInput.value > this.value) deadlineInput.value = this.value;
});

if (startDateInput.value) {
    deadlineInput.setAttribute('max', startDateInput.value);
}
</script>
</body>
</html>