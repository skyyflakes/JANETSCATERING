<?php
/**
 * Reports Page - Janet's Quality Catering System
 * Features: Full CRUD (Owner only for Create/Edit/Delete)
 */
$page_title = "Reports | Janet's Quality Catering";
$current_page = 'reports';

require_once 'includes/auth_check.php';

$pdo = getDBConnection();
$action = $_GET['action'] ?? '';
$report_id = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? '';
    
    // Only Owner can create/edit/delete reports
    if ($current_user['role'] !== 'OWNER' && in_array($form_action, ['add_report', 'edit_report', 'delete_report'])) {
        setFlash('Unauthorized! Only the Owner can perform this action.', 'danger');
        redirect('reports.php');
    }
    
    if ($pdo) {
        try {
            switch ($form_action) {
                case 'add_report':
                    $stmt = $pdo->prepare("INSERT INTO reports (title, content, created_by, status) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        sanitize($_POST['title']),
                        sanitize($_POST['content']),
                        $current_user['username'],
                        $_POST['status'] ?? 'Generated'
                    ]);
                    setFlash('Report created successfully!', 'success');
                    break;

                case 'edit_report':
                    $stmt = $pdo->prepare("UPDATE reports SET title = ?, content = ?, status = ? WHERE id = ?");
                    $stmt->execute([
                        sanitize($_POST['title']),
                        sanitize($_POST['content']),
                        $_POST['status'],
                        $_POST['report_id']
                    ]);
                    setFlash('Report updated successfully!', 'success');
                    break;

                case 'delete_report':
                    $stmt = $pdo->prepare("DELETE FROM reports WHERE id = ?");
                    $stmt->execute([$_POST['report_id']]);
                    setFlash('Report deleted successfully!', 'success');
                    break;
            }
        } catch (PDOException $e) {
            setFlash('Error: ' . $e->getMessage(), 'danger');
        }
    }
    
    redirect('reports.php');
}

// Fetch reports with analytics
$reports = [];
$edit_report = null;
$analytics_data = [];

if ($pdo) {
    $stmt = $pdo->query("SELECT * FROM reports ORDER BY created_at DESC");
    $reports = $stmt->fetchAll();

    // Fetch inventory analytics
    $stmt = $pdo->query("SELECT 
        c.category_id, 
        c.category_name, 
        COUNT(i.item_id) as total_items,
        SUM(i.ending_qty) as total_qty,
        AVG(i.ending_qty) as avg_qty,
        MIN(i.ending_qty) as min_qty,
        MAX(i.ending_qty) as max_qty
    FROM categories c
    LEFT JOIN inventory i ON c.category_id = i.category_id AND i.is_active = 1
    GROUP BY c.category_id, c.category_name
    ORDER BY total_qty DESC");
    
    $analytics_data = $stmt->fetchAll();

    if ($action === 'edit' && $report_id) {
        $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ?");
        $stmt->execute([$report_id]);
        $edit_report = $stmt->fetch();
    }
}

require_once 'includes/header.php';
?>

<style>
    /* Reports Specific Styles */
    .reports-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .report-card {
        background: var(--card-color);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        margin-bottom: 20px;
        overflow: hidden;
        transition: 0.3s;
    }

    .report-card:hover {
        border-color: var(--accent-pink);
    }

    .report-card-header {
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 1px solid var(--border-color);
    }

    .report-title {
        font-weight: 800;
        font-size: 1.1rem;
        margin-bottom: 5px;
    }

    .report-meta {
        display: flex;
        gap: 20px;
        color: var(--text-muted);
        font-size: 0.85rem;
    }

    .report-meta i {
        margin-right: 5px;
        color: var(--accent-pink);
    }

    .report-card-body {
        padding: 20px 25px;
    }

    .report-content {
        color: var(--text-muted);
        line-height: 1.7;
    }

    .report-card-footer {
        padding: 15px 25px;
        background: rgba(255, 255, 255, 0.02);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .action-btns {
        display: flex;
        gap: 8px;
    }

    .btn-action {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: 0.3s;
    }

    .btn-view {
        background: rgba(99, 102, 241, 0.1);
        color: #818cf8;
    }

    .btn-view:hover {
        background: #818cf8;
        color: white;
    }

    .btn-edit {
        background: rgba(255, 193, 7, 0.1);
        color: var(--janet-yellow);
    }

    .btn-edit:hover {
        background: var(--janet-yellow);
        color: #000;
    }

    .btn-delete {
        background: rgba(255, 46, 99, 0.1);
        color: var(--accent-pink);
    }

    .btn-delete:hover {
        background: var(--accent-pink);
        color: white;
    }

    .report-form {
        background: var(--card-color);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 30px;
    }

    .empty-reports {
        text-align: center;
        padding: 60px 20px;
    }

    .empty-reports i {
        font-size: 4rem;
        color: var(--text-muted);
        opacity: 0.3;
        margin-bottom: 20px;
    }

    .role-badge {
        font-size: 0.7rem;
        font-weight: 800;
        padding: 5px 12px;
        border-radius: 20px;
        text-transform: uppercase;
    }

    .role-owner {
        background: var(--success-green);
        color: #000;
    }

    .role-admin {
        background: var(--janet-yellow);
        color: #000;
    }

    .read-only-notice {
        background: rgba(255, 193, 7, 0.1);
        border: 1px solid rgba(255, 193, 7, 0.2);
        color: var(--janet-yellow);
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .status-badge {
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .status-generated {
        background: rgba(0, 255, 136, 0.1);
        color: var(--success-green);
    }

    .status-draft {
        background: rgba(255, 193, 7, 0.1);
        color: var(--janet-yellow);
    }

    .status-archived {
        background: rgba(128, 128, 128, 0.1);
        color: var(--text-muted);
    }

    /* Analytics Styles */
    .analytics-section {
        background: var(--card-color);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 30px;
    }

    .analytics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .analytics-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 20px;
        transition: 0.3s;
    }

    .analytics-card:hover {
        border-color: var(--accent-pink);
        background: rgba(255, 46, 99, 0.05);
    }

    .analytics-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 15px;
    }

    .analytics-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    .analytics-title {
        font-weight: 700;
        font-size: 1rem;
    }

    .analytics-body {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .analytics-stat {
        display: flex;
        flex-direction: column;
    }

    .alert-badge {
        display: inline-block;
        width: 100%;
        text-align: center;
    }

    .summary-stat {
        text-align: center;
    }

</style>

<?php if ($action === 'add' || $action === 'edit'): ?>

<?php if ($current_user['role'] !== 'OWNER'): ?>
<div class="alert flash-message flash-danger">
    <i class="fas fa-lock me-2"></i>
    You don't have permission to create or edit reports. Only the Owner can perform this action.
</div>
<?php redirect('reports.php'); ?>
<?php endif; ?>

<!-- Report Form -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title mb-0"><?php echo $action === 'edit' ? 'Edit Report' : 'Create New Report'; ?></h2>
    <a href="reports.php" class="btn btn-outline-light">
        <i class="fas fa-arrow-left me-2"></i>Back to Reports
    </a>
</div>

<div class="report-form">
    <form method="POST">
        <input type="hidden" name="form_action" value="<?php echo $action === 'edit' ? 'edit_report' : 'add_report'; ?>">
        <?php if ($edit_report): ?>
        <input type="hidden" name="report_id" value="<?php echo $edit_report['id']; ?>">
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label">Report Title</label>
            <input type="text" name="title" class="form-control" required 
                   value="<?php echo htmlspecialchars($edit_report['title'] ?? ''); ?>" 
                   placeholder="e.g., Monthly Inventory Audit - April">
        </div>

        <div class="mb-3">
            <label class="form-label">Report Content</label>
            <textarea name="content" class="form-control" rows="8" required 
                      placeholder="Enter report details..."><?php echo htmlspecialchars($edit_report['content'] ?? ''); ?></textarea>
        </div>

        <div class="mb-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="Generated" <?php echo ($edit_report['status'] ?? '') === 'Generated' ? 'selected' : ''; ?>>Generated</option>
                <option value="Draft" <?php echo ($edit_report['status'] ?? '') === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="Archived" <?php echo ($edit_report['status'] ?? '') === 'Archived' ? 'selected' : ''; ?>>Archived</option>
            </select>
        </div>

        <div class="d-flex gap-3">
            <button type="submit" class="btn btn-primary">
                <i class="bx bx-save me-2"></i><?php echo $action === 'edit' ? 'Update Report' : 'Save Report'; ?>
            </button>
            <a href="reports.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php else: ?>

<!-- Reports List View -->
<div class="reports-header">
    <div>
        <h2 class="page-title mb-1">Reports Management</h2>
        <p class="text-muted mb-0">
            Logged in as: 
            <span class="role-badge role-<?php echo strtolower($current_user['role']); ?>">
                <?php echo $current_user['role']; ?>
            </span>
        </p>
    </div>
    
    <?php if ($current_user['role'] === 'OWNER'): ?>
    <a href="reports.php?action=add" class="btn btn-primary">
        <i class="bx bx-plus me-2"></i>Create Report
    </a>
    <?php endif; ?>
</div>

<!-- Predictive Analytics Section -->
<?php if (!empty($analytics_data)): ?>
<div class="analytics-section" style="margin-bottom: 30px;">
    <h3 style="font-weight: 800; margin-bottom: 20px; color: var(--text-main);">
        <i class="fas fa-chart-line me-2" style="color: var(--accent-pink);"></i>
        Inventory Predictive Analytics
    </h3>
    
    <div class="analytics-grid">
        <?php 
        $total_items = 0;
        $total_qty = 0;
        $low_stock_count = 0;
        $high_stock_count = 0;
        
        foreach ($analytics_data as $cat): 
            if ($cat['total_items'] > 0):
                $total_items += $cat['total_items'];
                $total_qty += $cat['total_qty'] ?? 0;
                if ($cat['min_qty'] <= 10) $low_stock_count++;
                if ($cat['max_qty'] >= 50) $high_stock_count++;
        ?>
        <div class="analytics-card">
            <div class="analytics-header">
                <div class="analytics-icon" style="background: rgba(255, 46, 99, 0.1); color: var(--accent-pink);">
                    <i class="fas fa-box"></i>
                </div>
                <div class="analytics-title" style="color: var(--text-main);">
                    <?php echo htmlspecialchars($cat['category_name']); ?>
                </div>
            </div>
            
            <div class="analytics-body">
                <div class="analytics-stat">
                    <span style="color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Total Items</span>
                    <div style="font-size: 1.8rem; font-weight: 800; color: var(--text-main);">
                        <?php echo $cat['total_items']; ?>
                    </div>
                </div>
                
                <div class="analytics-stat">
                    <span style="color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Total Qty</span>
                    <div style="font-size: 1.8rem; font-weight: 800; color: var(--success-green);">
                        <?php echo $cat['total_qty'] ?? 0; ?>
                    </div>
                </div>
                
                <div class="analytics-stat">
                    <span style="color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Avg Qty</span>
                    <div style="font-size: 1.8rem; font-weight: 800; color: var(--janet-yellow);">
                        <?php echo round($cat['avg_qty'] ?? 0, 1); ?>
                    </div>
                </div>
                
                <div class="analytics-stat">
                    <span style="color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Min/Max</span>
                    <div style="font-size: 0.95rem; color: var(--text-main);">
                        <span style="color: <?php echo ($cat['min_qty'] <= 10) ? 'var(--accent-pink)' : 'var(--success-green)'; ?>">
                            <?php echo $cat['min_qty']; ?>
                        </span> / 
                        <span style="color: var(--success-green);">
                            <?php echo $cat['max_qty']; ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($cat['min_qty'] <= 10): ?>
                <div class="alert-badge" style="background: rgba(255, 46, 99, 0.1); color: var(--accent-pink); padding: 8px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; margin-top: 10px;">
                    <i class="fas fa-exclamation-triangle me-1"></i> LOW STOCK WARNING
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php 
            endif;
        endforeach; 
        ?>
    </div>
    
    <!-- Summary Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
        <div class="summary-stat" style="background: rgba(0, 255, 136, 0.1); border: 1px solid rgba(0, 255, 136, 0.2); border-radius: 12px; padding: 20px;">
            <div style="color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; margin-bottom: 8px;">
                <i class="fas fa-boxes me-2"></i>Total Inventory Items
            </div>
            <div style="font-size: 2rem; font-weight: 800; color: var(--success-green);">
                <?php echo $total_items; ?>
            </div>
        </div>
        
        <div class="summary-stat" style="background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.2); border-radius: 12px; padding: 20px;">
            <div style="color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; margin-bottom: 8px;">
                <i class="fas fa-cubes me-2"></i>Total Quantity
            </div>
            <div style="font-size: 2rem; font-weight: 800; color: var(--janet-yellow);">
                <?php echo $total_qty; ?>
            </div>
        </div>
        
        <div class="summary-stat" style="background: rgba(255, 46, 99, 0.1); border: 1px solid rgba(255, 46, 99, 0.2); border-radius: 12px; padding: 20px;">
            <div style="color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; margin-bottom: 8px;">
                <i class="fas fa-exclamation-circle me-2"></i>Low Stock Categories
            </div>
            <div style="font-size: 2rem; font-weight: 800; color: var(--accent-pink);">
                <?php echo $low_stock_count; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($current_user['role'] === 'ADMIN'): ?>
<div class="read-only-notice">
    <i class="fas fa-info-circle"></i>
    <span>You have <strong>read-only</strong> access. Only the Owner can create, edit, or delete reports.</span>
</div>
<?php endif; ?>

<?php if (empty($reports)): ?>
<div class="card">
    <div class="empty-reports">
        <i class="fas fa-file-invoice d-block"></i>
        <h4>No Reports Found</h4>
        <p class="text-muted">
            <?php if ($current_user['role'] === 'OWNER'): ?>
            Start by creating your first report.
            <?php else: ?>
            No reports have been created yet.
            <?php endif; ?>
        </p>
        <?php if ($current_user['role'] === 'OWNER'): ?>
        <a href="reports.php?action=add" class="btn btn-primary mt-3">
            <i class="bx bx-plus me-2"></i>Create First Report
        </a>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>

<?php foreach ($reports as $report): ?>
<div class="report-card">
    <div class="report-card-header">
        <div>
            <div class="report-title"><?php echo htmlspecialchars($report['title']); ?></div>
            <div class="report-meta">
                <span><i class="fas fa-calendar"></i><?php echo date('F d, Y', strtotime($report['created_at'])); ?></span>
                <span><i class="fas fa-user"></i><?php echo htmlspecialchars($report['created_by'] ?? 'System'); ?></span>
            </div>
        </div>
        <span class="status-badge status-<?php echo strtolower($report['status']); ?>">
            <?php echo $report['status']; ?>
        </span>
    </div>
    
    <div class="report-card-body">
        <p class="report-content">
            <?php 
            $content = htmlspecialchars($report['content']);
            echo strlen($content) > 300 ? substr($content, 0, 300) . '...' : $content;
            ?>
        </p>
    </div>
    
    <div class="report-card-footer">
        <small class="text-muted">
            Report ID: #<?php echo $report['id']; ?>
        </small>
        
        <div class="action-btns">
            <button class="btn-action btn-view" onclick="viewReport(<?php echo htmlspecialchars(json_encode($report)); ?>)" title="View Full">
                <i class="fas fa-eye"></i>
            </button>
            
            <?php if ($current_user['role'] === 'OWNER'): ?>
            <a href="reports.php?action=edit&id=<?php echo $report['id']; ?>" class="btn-action btn-edit" title="Edit">
                <i class="fas fa-edit"></i>
            </a>
            <button class="btn-action btn-delete" onclick="deleteReport(<?php echo $report['id']; ?>, '<?php echo htmlspecialchars($report['title']); ?>')" title="Delete">
                <i class="fas fa-trash"></i>
            </button>
            <?php else: ?>
            <span class="text-muted" style="font-size: 0.8rem;">Read-only</span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<!-- View Report Modal -->
<div class="modal fade" id="viewReportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewReportTitle">Report Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <small class="text-muted d-block mb-1">Created By</small>
                    <strong id="viewReportAuthor"></strong>
                    <span class="ms-2 text-muted" id="viewReportDate"></span>
                </div>
                <hr style="border-color: var(--border-color);">
                <div id="viewReportContent" style="white-space: pre-wrap; line-height: 1.8;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-light" onclick="printReport()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Report Modal -->
<div class="modal fade" id="deleteReportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="form_action" value="delete_report">
                <input type="hidden" name="report_id" id="delete_report_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="delete_report_name"></strong>?</p>
                    <p class="text-muted small">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let currentReport = null;

    // View report details
    function viewReport(report) {
        currentReport = report;
        document.getElementById('viewReportTitle').textContent = report.title;
        document.getElementById('viewReportAuthor').textContent = report.created_by || 'System';
        document.getElementById('viewReportDate').textContent = new Date(report.created_at).toLocaleDateString('en-US', {
            year: 'numeric', month: 'long', day: 'numeric'
        });
        document.getElementById('viewReportContent').textContent = report.content;
        
        new bootstrap.Modal(document.getElementById('viewReportModal')).show();
    }

    // Delete report
    function deleteReport(reportId, reportTitle) {
        document.getElementById('delete_report_id').value = reportId;
        document.getElementById('delete_report_name').textContent = reportTitle;
        new bootstrap.Modal(document.getElementById('deleteReportModal')).show();
    }

    // Print report
    function printReport() {
        if (!currentReport) return;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>${currentReport.title}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 40px; }
                    h1 { color: #ff2e63; }
                    .meta { color: #666; margin-bottom: 20px; }
                    .content { line-height: 1.8; white-space: pre-wrap; }
                    hr { margin: 20px 0; }
                </style>
            </head>
            <body>
                <h1>${currentReport.title}</h1>
                <div class="meta">
                    By: ${currentReport.created_by || 'System'} | 
                    Date: ${new Date(currentReport.created_at).toLocaleDateString()}
                </div>
                <hr>
                <div class="content">${currentReport.content}</div>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
</script>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
