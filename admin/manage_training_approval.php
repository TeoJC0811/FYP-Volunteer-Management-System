<?php
session_start();
include("../db.php");

// 🔒 Security Check
$sessionRole = $_SESSION['role'] ?? '';
if (!isset($_SESSION['userID']) || $sessionRole !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// 1. Handle Approval/Rejection Actions for Training Courses
if (isset($_GET['action']) && isset($_GET['id'])) {
    $courseID = intval($_GET['id']);
    $status = ($_GET['action'] === 'approve') ? 'approved' : 'rejected';
    
    $stmt = $conn->prepare("UPDATE course SET status = ? WHERE courseID = ?");
    $stmt->bind_param("si", $status, $courseID);
    
    if ($stmt->execute()) {
        header("Location: manage_training_approval.php?status=updated");
    } else {
        header("Location: manage_training_approval.php?status=error");
    }
    exit();
}

// 2. Fetch all Pending training courses
$sql = "SELECT c.*, u.userName as organizerName, cat.categoryName 
        FROM course c 
        JOIN user u ON c.organizerID = u.userID 
        LEFT JOIN category cat ON c.categoryID = cat.categoryID
        WHERE c.status = 'pending' 
        ORDER BY c.startDate ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Training Approval Queue</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { padding: 40px; }
        
        .page-header { margin-bottom: 30px; }
        .page-header h2 { margin: 0; color: #1c1e21; display: flex; align-items: center; gap: 12px; }

        /* Approval Grid */
        .approval-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px; }

        /* Course Card */
        .event-card { 
            background: white; 
            border-radius: 12px; 
            overflow: hidden; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.08); 
            display: flex; 
            flex-direction: column;
            border: 1px solid #e1e4e8;
        }

        .card-banner { height: 120px; background-size: cover; background-position: center; position: relative; }
        .category-badge { 
            position: absolute; bottom: 10px; left: 10px; 
            background: rgba(108, 92, 231, 0.9); color: white; 
            padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: bold; 
        }

        .card-body { padding: 20px; flex-grow: 1; }
        .event-name { font-size: 18px; font-weight: bold; margin: 0 0 10px 0; color: #333; }
        
        .info-row { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #666; margin-bottom: 6px; }
        .info-row i { width: 18px; color: #6c5ce7; }

        .card-footer { 
            background: #f8f9fa; 
            padding: 15px 20px; 
            border-top: 1px solid #eee; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }

        /* Action Buttons */
        .action-btns { display: flex; gap: 10px; }
        .btn-action { 
            padding: 8px 16px; border-radius: 6px; text-decoration: none; 
            font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px;
            transition: 0.2s;
        }
        .btn-review { background: #f3f0ff; color: #6c5ce7; border: 1px solid #dcd6ff; }
        .btn-approve { background: #dafbe1; color: #1a7f37; border: 1px solid #bcdec4; }
        .btn-reject { background: #ffebe9; color: #cf222e; border: 1px solid #ffcfcc; }
        
        .btn-action:hover { opacity: 0.8; transform: translateY(-1px); }

        .empty-state { grid-column: 1 / -1; text-align: center; padding: 80px 20px; color: #777; }
        .empty-state i { font-size: 48px; margin-bottom: 15px; color: #ccc; }
    </style>
</head>
<body>
    <?php include("sidebar.php"); ?>

    <div class="main-content">
        <div class="page-header">
            <h2><i class="fa-solid fa-graduation-cap"></i> Training Approval Queue</h2>
            <p style="color: #666; margin-top: 5px;">Review and authorize training courses submitted by organizers.</p>
        </div>

        <div class="approval-grid">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): 
                    $coverImg = !empty($row['coverImage']) ? '../uploads/course_cover/'.$row['coverImage'] : 'https://via.placeholder.com/400x120';
                ?>
                <div class="event-card">
                    <div class="card-banner" style="background-image: url('<?= htmlspecialchars($coverImg) ?>');">
                        <span class="category-badge"><?= htmlspecialchars($row['categoryName'] ?? 'Training') ?></span>
                    </div>

                    <div class="card-body">
                        <h3 class="event-name"><?= htmlspecialchars($row['courseName']) ?></h3>
                        
                        <div class="info-row">
                            <i class="fa-solid fa-user"></i> 
                            Organizer: <strong><?= htmlspecialchars($row['organizerName']) ?></strong>
                        </div>
                        <div class="info-row">
                            <i class="fa-solid fa-calendar-days"></i> 
                            Start Date: <?= date('d M Y', strtotime($row['startDate'])) ?>
                        </div>
                        <div class="info-row">
                            <i class="fa-solid fa-money-bill-wave"></i> 
                            Fee: RM <?= number_format($row['fee'], 2) ?>
                        </div>
                    </div>

                    <div class="card-footer">
                        <a href="../course_detail.php?id=<?= $row['courseID'] ?>" target="_blank" class="btn-action btn-review">
                            <i class="fa-solid fa-eye"></i> Review Detail
                        </a>
                        
                        <div class="action-btns">
                            <a href="manage_training_approval.php?action=reject&id=<?= $row['courseID'] ?>" 
                               class="btn-action btn-reject" 
                               onclick="return confirm('Reject this training course?')">
                               <i class="fa-solid fa-xmark"></i>
                            </a>
                            <a href="manage_training_approval.php?action=approve&id=<?= $row['courseID'] ?>" 
                               class="btn-action btn-approve"
                               onclick="return confirm('Approve this training course? Volunteers will be able to register immediately.')">
                               <i class="fa-solid fa-check"></i> Approve
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-circle-check"></i>
                    <h3>Queue is empty!</h3>
                    <p>There are no pending training courses requiring approval.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>