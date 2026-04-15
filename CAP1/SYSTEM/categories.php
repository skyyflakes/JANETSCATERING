<?php
/**
 * Categories Page - Janet's Quality Catering System
 * Full CRUD operations for inventory categories
 */
$page_title = "Categories | Janet's Quality Catering";
$current_page = 'categories';

require_once 'includes/auth_check.php';

$pdo = getDBConnection();

// Handle form submissions for CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? '';
    
    if ($pdo) {
        try {
            switch ($form_action) {
                case 'add_category':
                    // Only Owner can add categories
                    if ($current_user['role'] !== 'OWNER') {
                        setFlash('Unauthorized! Only the Owner can add categories.', 'danger');
                        break;
                    }
                    $stmt = $pdo->prepare("INSERT INTO categories (category_name, description) VALUES (?, ?)");
                    $stmt->execute([
                        sanitize($_POST['category_name']),
                        sanitize($_POST['description'] ?? '')
                    ]);
                    setFlash('Category added successfully!', 'success');
                    break;

                case 'edit_category':
                    // Only Owner can edit categories
                    if ($current_user['role'] !== 'OWNER') {
                        setFlash('Unauthorized! Only the Owner can edit categories.', 'danger');
                        break;
                    }
                    $stmt = $pdo->prepare("UPDATE categories SET category_name = ?, description = ? WHERE category_id = ?");
                    $stmt->execute([
                        sanitize($_POST['category_name']),
                        sanitize($_POST['description'] ?? ''),
                        (int)$_POST['category_id']
                    ]);
                    setFlash('Category updated successfully!', 'success');
                    break;

                case 'delete_category':
                    // Only Owner can delete categories
                    if ($current_user['role'] !== 'OWNER') {
                        setFlash('Unauthorized! Only the Owner can delete categories.', 'danger');
                        break;
                    }
                    // Check if category has items
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE category_id = ? AND is_active = 1");
                    $checkStmt->execute([(int)$_POST['category_id']]);
                    $itemCount = $checkStmt->fetchColumn();
                    
                    if ($itemCount > 0) {
                        setFlash('Cannot delete category with active items! Please move or delete items first.', 'danger');
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
                        $stmt->execute([(int)$_POST['category_id']]);
                        setFlash('Category deleted successfully!', 'success');
                    }
                    break;
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                setFlash('Category name already exists!', 'danger');
            } else {
                setFlash('Error: ' . $e->getMessage(), 'danger');
            }
        }
    }
    
    redirect('categories.php');
}

// Fetch all categories with item counts
$categories = [];
try {
    $stmt = $pdo->query("
        SELECT c.*, 
               COUNT(i.item_id) as item_count,
               SUM(CASE WHEN i.is_active = 1 THEN i.ending_qty ELSE 0 END) as total_qty
        FROM categories c 
        LEFT JOIN inventory i ON c.category_id = i.category_id AND i.is_active = 1
        GROUP BY c.category_id 
        ORDER BY c.category_name ASC
    ");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Categories Query Error: " . $e->getMessage());
}

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1">Categories Management</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Categories</li>
            </ol>
        </nav>
    </div>
    <?php if ($current_user['role'] === 'OWNER'): ?>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
        <i class="bx bx-plus me-1"></i> Add Category
    </button>
    <?php endif; ?>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <span class="d-block text-muted mb-1" style="font-size: 0.8125rem;">Total Categories</span>
                        <h3 class="mb-0" style="color: var(--bs-primary);"><?php echo count($categories); ?></h3>
                    </div>
                    <div class="avatar" style="width: 48px; height: 48px; background: rgba(105, 108, 255, 0.16); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="bx bx-category" style="font-size: 1.5rem; color: var(--bs-primary);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <span class="d-block text-muted mb-1" style="font-size: 0.8125rem;">Total Items</span>
                        <h3 class="mb-0" style="color: var(--bs-success);"><?php echo array_sum(array_column($categories, 'item_count')); ?></h3>
                    </div>
                    <div class="avatar" style="width: 48px; height: 48px; background: rgba(113, 221, 55, 0.16); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="bx bx-box" style="font-size: 1.5rem; color: var(--bs-success);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <span class="d-block text-muted mb-1" style="font-size: 0.8125rem;">Total Quantity</span>
                        <h3 class="mb-0" style="color: var(--bs-info);"><?php echo number_format(array_sum(array_column($categories, 'total_qty'))); ?></h3>
                    </div>
                    <div class="avatar" style="width: 48px; height: 48px; background: rgba(3, 195, 236, 0.16); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="bx bx-cube" style="font-size: 1.5rem; color: var(--bs-info);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <span class="d-block text-muted mb-1" style="font-size: 0.8125rem;">Empty Categories</span>
                        <h3 class="mb-0" style="color: var(--bs-warning);"><?php echo count(array_filter($categories, fn($c) => $c['item_count'] == 0)); ?></h3>
                    </div>
                    <div class="avatar" style="width: 48px; height: 48px; background: rgba(255, 171, 0, 0.16); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="bx bx-folder-open" style="font-size: 1.5rem; color: var(--bs-warning);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Categories Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">All Categories</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Category Name</th>
                    <th>Description</th>
                    <th style="width: 120px;">Items</th>
                    <th style="width: 120px;">Total Qty</th>
                    <th style="width: 160px;">Created</th>
                    <?php if ($current_user['role'] === 'OWNER'): ?>
                    <th style="width: 120px;">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="<?php echo ($current_user['role'] === 'OWNER') ? '7' : '6'; ?>" class="text-center py-4">
                        <div style="color: var(--bs-secondary);">
                            <i class="bx bx-folder-open" style="font-size: 3rem;"></i>
                            <p class="mb-0 mt-2">No categories found. Add your first category!</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($categories as $index => $category): ?>
                <tr>
                    <td>
                        <strong><?php echo $index + 1; ?></strong>
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div style="width: 38px; height: 38px; background: rgba(105, 108, 255, 0.16); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                <i class="bx bx-category" style="font-size: 1.125rem; color: var(--bs-primary);"></i>
                            </div>
                            <div>
                                <strong style="color: var(--heading-color);"><?php echo htmlspecialchars($category['category_name']); ?></strong>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span style="color: var(--bs-secondary);"><?php echo htmlspecialchars($category['description'] ?? '-'); ?></span>
                    </td>
                    <td>
                        <span class="badge badge-primary"><?php echo $category['item_count']; ?> items</span>
                    </td>
                    <td>
                        <span class="badge badge-info"><?php echo number_format($category['total_qty'] ?? 0); ?></span>
                    </td>
                    <td>
                        <small style="color: var(--bs-secondary);"><?php echo date('M d, Y', strtotime($category['created_at'])); ?></small>
                    </td>
                    <?php if ($current_user['role'] === 'OWNER'): ?>
                    <td>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-icon btn-label-primary btn-sm" 
                                    onclick="editCategory(<?php echo $category['category_id']; ?>, '<?php echo htmlspecialchars(addslashes($category['category_name'])); ?>', '<?php echo htmlspecialchars(addslashes($category['description'] ?? '')); ?>')" 
                                    title="Edit">
                                <i class="bx bx-edit"></i>
                            </button>
                            <button type="button" class="btn btn-icon btn-sm" 
                                    style="background: rgba(255, 62, 29, 0.16); color: var(--bs-danger);"
                                    onclick="deleteCategory(<?php echo $category['category_id']; ?>, '<?php echo htmlspecialchars(addslashes($category['category_name'])); ?>', <?php echo $category['item_count']; ?>)" 
                                    title="Delete">
                                <i class="bx bx-trash"></i>
                            </button>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-plus-circle me-2" style="color: var(--bs-primary);"></i>Add New Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="form_action" value="add_category">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="category_name" class="form-control" placeholder="Enter category name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Enter description (optional)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i> Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-edit me-2" style="color: var(--bs-primary);"></i>Edit Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="form_action" value="edit_category">
                <input type="hidden" name="category_id" id="edit_category_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="category_name" id="edit_category_name" class="form-control" placeholder="Enter category name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3" placeholder="Enter description (optional)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i> Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Category Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-trash me-2" style="color: var(--bs-danger);"></i>Delete Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="form_action" value="delete_category">
                <input type="hidden" name="category_id" id="delete_category_id">
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <div style="width: 80px; height: 80px; background: rgba(255, 62, 29, 0.16); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                            <i class="bx bx-trash" style="font-size: 2.5rem; color: var(--bs-danger);"></i>
                        </div>
                        <h5 style="color: var(--heading-color);">Are you sure?</h5>
                        <p class="mb-0">You are about to delete category "<strong id="delete_category_name"></strong>".</p>
                        <p class="text-danger" id="delete_warning" style="display: none;">
                            <i class="bx bx-error-circle me-1"></i>This category has items. Please move or delete items first.
                        </p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="bx bx-trash me-1"></i> Delete Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCategory(id, name, description) {
    document.getElementById('edit_category_id').value = id;
    document.getElementById('edit_category_name').value = name;
    document.getElementById('edit_description').value = description;
    
    const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    modal.show();
}

function deleteCategory(id, name, itemCount) {
    document.getElementById('delete_category_id').value = id;
    document.getElementById('delete_category_name').textContent = name;
    
    const warning = document.getElementById('delete_warning');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    
    if (itemCount > 0) {
        warning.style.display = 'block';
        confirmBtn.disabled = true;
    } else {
        warning.style.display = 'none';
        confirmBtn.disabled = false;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>
