<?php
session_start();
include("db.php");

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit;
}

// Fetch tags
$tagResult = $conn->query("SELECT * FROM Tag ORDER BY tagName ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    // Content is now optional: set to NULL if empty
    $content = !empty(trim($_POST['content'])) ? $_POST['content'] : null;
    $userID = $_SESSION['userID'];
    $tagID = isset($_POST['tagID']) ? intval($_POST['tagID']) : 0;
    
    $forumImage = null; 

    // --- Handle Single File Upload ---
    if (isset($_FILES['forumImage']) && $_FILES['forumImage']['error'] === 0) {
        $targetDir = "uploads/forum_images/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES["forumImage"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

        $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
        if (in_array(strtolower($fileType), $allowTypes)) {
            if (move_uploaded_file($_FILES["forumImage"]["tmp_name"], $targetFilePath)) {
                $forumImage = $targetFilePath;
            }
        }
    }

    // --- Insert forum using Prepared Statement (Saves NULL properly) ---
    $stmt = $conn->prepare("INSERT INTO Forum (title, content, forumImage, userID) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $title, $content, $forumImage, $userID);
    
    if ($stmt->execute()) {
        $forumID = $stmt->insert_id;
        $stmt->close();

        // Insert the selected tag (only if chosen)
        if ($tagID > 0) {
            $stmtTag = $conn->prepare("INSERT INTO ForumTag (forumID, tagID) VALUES (?, ?)");
            $stmtTag->bind_param("ii", $forumID, $tagID);
            $stmtTag->execute();
            $stmtTag->close();
        }

        header("Location: community_forum.php");
        exit;
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Create Forum</title>
<link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    .form-container { 
        max-width: 600px; 
        margin: auto; 
        padding: 20px; 
    }
    label {
        font-weight: bold;
        display: block;
        margin-bottom: 5px;
    }
    .required {
        color: red;
        margin-left: 4px;
    }
    .optional {
        color: #888;
        font-size: 12px;
        font-weight: normal;
        margin-left: 5px;
    }
    input, textarea, button, select { 
        width: 100%; 
        margin-bottom: 15px; 
        padding: 10px; 
        border-radius: 5px; 
        border: 1px solid #ccc; 
        font-size: 14px;
        box-sizing: border-box;
    }
    textarea {
        resize: none;
        min-height: 120px;
    }

    /* --- Custom Drag & Drop Zone --- */
    .upload-area {
        width: 100%;
        height: 120px;
        background-color: #f9f9f9; /* Matches your form background */
        border: 2px dashed #ccc;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        cursor: pointer;
        transition: 0.3s;
        margin-bottom: 15px;
        color: #666;
    }
    .upload-area:hover {
        border-color: #007bff;
        background-color: #f0f7ff;
    }
    .upload-content {
        display: flex;
        align-items: center;
        gap: 15px;
        font-size: 16px;
    }
    .cloud-icon {
        background: #e9ecef;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 18px;
        color: #333;
    }
    #forumImage {
        display: none; /* Hide original input */
    }

    /* --- Image Preview --- */
    #previewContainer {
        position: relative;
        display: none;
        width: 100%;
        margin-bottom: 15px;
    }
    #previewImage {
        width: 100%;
        border-radius: 8px;
        border: 1px solid #ccc;
    }
    .remove-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(220, 53, 69, 0.85);
        color: white;
        border: none;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        cursor: pointer;
        display: flex;
        justify-content: center;
        align-items: center;
        transition: 0.2s;
    }
    .remove-btn:hover { background: #dc3545; transform: scale(1.1); }

    button { 
        background: #007bff; 
        color: white; 
        border: none; 
        cursor: pointer; 
        transition: 0.3s;
    }
    button:hover {
        background: #0056b3;
    }
</style>
</head>
<body>
<?php include("user_navbar.php"); ?>

<div class="form-container">
    <h2>📝 Create Forum Post</h2>
    <form method="POST" enctype="multipart/form-data" id="forumForm">
        <label for="title">Title <span class="required">*</span></label>
        <input type="text" name="title" id="title" placeholder="Give your post a title..." required>

        <label for="content">Content <span class="optional">(Optional)</span></label>
        <textarea name="content" id="content" placeholder="Share your thoughts..."></textarea>

        <label for="forumImage" class="upload-area" id="dropZone">
            <div class="upload-content">
                <span>Drag and Drop or upload media</span>
                <div class="cloud-icon">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
            </div>
            <input type="file" name="forumImage" id="forumImage" accept="image/*">
        </label>

        <div id="previewContainer">
            <img id="previewImage" src="" alt="Preview">
            <button type="button" class="remove-btn" id="removeImg" title="Remove Photo">
                <i class="fas fa-trash"></i>
            </button>
        </div>

        <label for="tagID">Select Tag (Optional)</label>
        <select name="tagID" id="tagID">
            <option value="">-- No Tag --</option>
            <?php while ($row = $tagResult->fetch_assoc()): ?>
                <option value="<?= $row['tagID'] ?>">
                    <?= htmlspecialchars($row['tagName']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <button type="submit">Create Post</button>
    </form>
</div>

<script>
    const fileInput = document.getElementById('forumImage');
    const dropZone = document.getElementById('dropZone');
    const previewContainer = document.getElementById('previewContainer');
    const previewImage = document.getElementById('previewImage');
    const removeBtn = document.getElementById('removeImg');

    // Handle File Selection
    fileInput.addEventListener('change', function() {
        showPreview(this.files[0]);
    });

    // Drag and Drop Logic
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = "#007bff";
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.style.borderColor = "#ccc";
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            showPreview(files[0]);
        }
    });

    function showPreview(file) {
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                previewContainer.style.display = 'block';
                dropZone.style.display = 'none'; // Hide upload area
            }
            reader.readAsDataURL(file);
        }
    }

    // Remove Image Logic
    removeBtn.addEventListener('click', function() {
        fileInput.value = ""; // Reset input
        previewContainer.style.display = 'none';
        dropZone.style.display = 'flex'; // Show upload area again
        dropZone.style.borderColor = "#ccc";
    });
</script>

<?php include 'includes/footer.php'; ?>

</body>
</html>