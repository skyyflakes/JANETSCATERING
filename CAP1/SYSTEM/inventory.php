<?php
/**
 * Inventory Page - Janet's Quality Catering System
 * Full CRUD operations for inventory items with Sneat design
 */
$page_title = "Inventory Management | Janet's Quality Catering";
$current_page = 'inventory';

require_once 'includes/auth_check.php';

$pdo = getDBConnection();

// Handle form submissions for CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? '';
    
    if ($pdo) {
        try {
            switch ($form_action) {
                case 'add_item':
                    // Only Owner can add items
                    if ($current_user['role'] !== 'OWNER') {
                        setFlash('Unauthorized! Only the Owner can add items.', 'danger');
                        break;
                    }
                    $beginning_qty = (int)$_POST['beginning_qty'];
                    $extra_qty = (int)$_POST['extra_qty'];
                    $ending_qty = $beginning_qty + $extra_qty;
                    
                    $stmt = $pdo->prepare("INSERT INTO inventory (category_id, item_name, beginning_qty, previous_qty, extra_qty, ending_qty, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([
                        (int)$_POST['category_id'],
                        sanitize($_POST['item_name']),
                        $beginning_qty,
                        $beginning_qty,
                        $extra_qty,
                        $ending_qty
                    ]);
                    setFlash('Item added successfully!', 'success');
                    break;

                case 'edit_item':
                    // Only Owner can edit items
                    if ($current_user['role'] !== 'OWNER') {
                        setFlash('Unauthorized! Only the Owner can edit items.', 'danger');
                        break;
                    }
                    $beginning_qty = (int)$_POST['beginning_qty'];
                    $previous_qty = (int)$_POST['previous_qty'];
                    $extra_qty = (int)$_POST['extra_qty'];
                    $ending_qty = $previous_qty + $extra_qty;
                    
                    $stmt = $pdo->prepare("UPDATE inventory SET category_id = ?, item_name = ?, beginning_qty = ?, previous_qty = ?, extra_qty = ?, ending_qty = ? WHERE item_id = ?");
                    $stmt->execute([
                        (int)$_POST['category_id'],
                        sanitize($_POST['item_name']),
                        $beginning_qty,
                        $previous_qty,
                        $extra_qty,
                        $ending_qty,
                        (int)$_POST['item_id']
                    ]);
                    setFlash('Item updated successfully!', 'success');
                    break;

                case 'delete_item':
                    // Only Owner can delete items
                    if ($current_user['role'] !== 'OWNER') {
                        setFlash('Unauthorized! Only the Owner can delete items.', 'danger');
                        break;
                    }
                    $stmt = $pdo->prepare("UPDATE inventory SET is_active = 0 WHERE item_id = ?");
                    $stmt->execute([(int)$_POST['item_id']]);
                    setFlash('Item deleted successfully!', 'success');
                    break;

                case 'adjust_qty':
                    // Only Owner can adjust quantities
                    if ($current_user['role'] !== 'OWNER') {
                        setFlash('Unauthorized! Only the Owner can adjust quantities.', 'danger');
                        break;
                    }
                    $adjustment = (int)$_POST['adjustment'];
                    $item_id = (int)$_POST['item_id'];
                    
                    // Get current item
                    $getStmt = $pdo->prepare("SELECT * FROM inventory WHERE item_id = ?");
                    $getStmt->execute([$item_id]);
                    $item = $getStmt->fetch();
                    
                    if ($item) {
                        $new_extra = $item['extra_qty'] + $adjustment;
                        $new_ending = $item['previous_qty'] + $new_extra;
                        
                        $stmt = $pdo->prepare("UPDATE inventory SET extra_qty = ?, ending_qty = ? WHERE item_id = ?");
                        $stmt->execute([$new_extra, $new_ending, $item_id]);
                        setFlash('Quantity adjusted successfully!', 'success');
                    }
                    break;
            }
        } catch (PDOException $e) {
            setFlash('Error: ' . $e->getMessage(), 'danger');
        }
    }
    
    redirect('inventory.php' . (isset($_GET['category']) ? '?category=' . $_GET['category'] : ''));
}

// Fetch all categories
$categories = [];
$inventory_items = [];
$selected_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

try {
    // Fetch categories
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC");
    $categories = $stmt->fetchAll();

    // Fetch inventory items
    $query = "SELECT i.*, c.category_name FROM inventory i 
              LEFT JOIN categories c ON i.category_id = c.category_id 
              WHERE i.is_active = 1";
    
    if ($selected_category > 0) {
        $query .= " AND i.category_id = $selected_category";
    }
    
    $query .= " ORDER BY c.category_name, i.item_name ASC";
    $stmt = $pdo->query($query);
    $inventory_items = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Inventory Query Error: " . $e->getMessage());
}

// Calculate statistics
$total_items = count($inventory_items);
$total_qty = array_sum(array_column($inventory_items, 'ending_qty'));
$low_stock = count(array_filter($inventory_items, fn($i) => $i['ending_qty'] <= 10));
$out_of_stock = count(array_filter($inventory_items, fn($i) => $i['ending_qty'] == 0));

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="page-title mb-1">Inventory Management</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Inventory</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="categories.php" class="btn btn-secondary">
            <i class="bx bx-category me-1"></i> Manage Categories
        </a>
        <?php if ($current_user['role'] === 'OWNER'): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
            <i class="bx bx-plus me-1"></i> Add Item
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 col-sm-6 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="d-block text-muted mb-1" style="font-size: 0.8125rem;">Total Items</span>
                        <h3 class="mb-0" style="color: var(--bs-primary);"><?php echo $total_items; ?></h3>
                    </div>
                    <div style="width: 48px; height: 48px; background: rgba(105, 108, 255, 0.16); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="bx bx-box" style="font-size: 1.5rem; color: var(--bs-primary);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 col-sm-6 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="d-block text-muted mb-1" style="font-size: 0.8125rem;">Total Quantity</span>
                        <h3 class="mb-0" style="color: var(--bs-success);"><?php echo number_format($total_qty); ?></h3>
                    </div>
                    <div style="width: 48px; height: 48px; background: rgba(113, 221, 55, 0.16); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="bx bx-cube" style="font-size: 1.5rem; color: var(--bs-success);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 col-sm-6 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="d-block text-muted mb-1" style="font-size: 0.8125rem;">Low Stock</span>
                        <h3 class="mb-0" style="color: var(--bs-warning);"><?php echo $low_stock; ?></h3>
                    </div>
                    <div style="width: 48px; height: 48px; background: rgba(255, 171, 0, 0.16); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="bx bx-error-circle" style="font-size: 1.5rem; color: var(--bs-warning);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 col-sm-6 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="d-block text-muted mb-1" style="font-size: 0.8125rem;">Out of Stock</span>
                        <h3 class="mb-0" style="color: var(--bs-danger);"><?php echo $out_of_stock; ?></h3>
                    </div>
                    <div style="width: 48px; height: 48px; background: rgba(255, 62, 29, 0.16); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="bx bx-x-circle" style="font-size: 1.5rem; color: var(--bs-danger);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter & Search -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
                <label class="form-label mb-2">Filter by Category</label>
                <div class="d-flex flex-wrap gap-2">
                    <a href="inventory.php" class="btn btn-sm <?php echo $selected_category == 0 ? 'btn-primary' : 'btn-label-primary'; ?>">
                        All Items
                    </a>
                    <?php foreach ($categories as $cat): ?>
                    <a href="inventory.php?category=<?php echo $cat['category_id']; ?>" 
                       class="btn btn-sm <?php echo $selected_category == $cat['category_id'] ? 'btn-primary' : 'btn-label-primary'; ?>">
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label mb-2">Search Items</label>
                <div class="input-group">
                    <span class="input-group-text" style="background: var(--card-bg); border-color: var(--border-color);">
                        <i class="bx bx-search"></i>
                    </span>
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by item name..." onkeyup="filterTable()">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Inventory Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <?php echo $selected_category > 0 ? htmlspecialchars($categories[array_search($selected_category, array_column($categories, 'category_id'))]['category_name'] ?? 'Inventory') : 'All Inventory Items'; ?>
            <span class="badge badge-secondary ms-2"><?php echo count($inventory_items); ?> items</span>
        </h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" id="inventoryTable">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th class="text-center">Beginning</th>
                    <th class="text-center">Previous</th>
                    <th class="text-center">Extra (+)</th>
                    <th class="text-center">Ending</th>
                    <th class="text-center">Status</th>
                    <?php if ($current_user['role'] === 'OWNER'): ?>
                    <th class="text-center" style="width: 140px;">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inventory_items)): ?>
                <tr>
                    <td colspan="<?php echo ($current_user['role'] === 'OWNER') ? '9' : '8'; ?>" class="text-center py-5">
                        <div style="color: var(--bs-secondary);">
                            <i class="bx bx-box" style="font-size: 3rem;"></i>
                            <p class="mb-0 mt-2">No items found. Add your first inventory item!</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($inventory_items as $index => $item): ?>
                <tr class="item-row" data-item-name="<?php echo strtolower(htmlspecialchars($item['item_name'])); ?>">
                    <td><strong><?php echo $index + 1; ?></strong></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div style="width: 38px; height: 38px; background: rgba(105, 108, 255, 0.16); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                <i class="bx bx-package" style="font-size: 1.125rem; color: var(--bs-primary);"></i>
                            </div>
                            <strong style="color: var(--heading-color);"><?php echo htmlspecialchars($item['item_name']); ?></strong>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-primary"><?php echo htmlspecialchars($item['category_name']); ?></span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-secondary"><?php echo $item['beginning_qty']; ?></span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-secondary"><?php echo $item['previous_qty']; ?></span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-warning">+<?php echo $item['extra_qty']; ?></span>
                    </td>
                    <td class="text-center">
                        <span class="badge <?php echo $item['ending_qty'] <= 10 ? 'badge-danger' : 'badge-success'; ?>" style="font-weight: 700;">
                            <?php echo $item['ending_qty']; ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <?php if ($item['ending_qty'] == 0): ?>
                            <span class="badge badge-danger">Out of Stock</span>
                        <?php elseif ($item['ending_qty'] <= 10): ?>
                            <span class="badge badge-warning">Low Stock</span>
                        <?php else: ?>
                            <span class="badge badge-success">In Stock</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($current_user['role'] === 'OWNER'): ?>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center">
                            <button type="button" class="btn btn-icon btn-sm" 
                                    style="background: rgba(113, 221, 55, 0.16); color: var(--bs-success);"
                                    onclick="adjustQty(<?php echo $item['item_id']; ?>, '<?php echo htmlspecialchars(addslashes($item['item_name'])); ?>', <?php echo $item['ending_qty']; ?>)" 
                                    title="Adjust Quantity">
                                <i class="bx bx-plus-circle"></i>
                            </button>
                            <button type="button" class="btn btn-icon btn-label-primary btn-sm" 
                                    onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)" 
                                    title="Edit">
                                <i class="bx bx-edit"></i>
                            </button>
                            <button type="button" class="btn btn-icon btn-sm" 
                                    style="background: rgba(255, 62, 29, 0.16); color: var(--bs-danger);"
                                    onclick="deleteItem(<?php echo $item['item_id']; ?>, '<?php echo htmlspecialchars(addslashes($item['item_name'])); ?>')" 
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

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-plus-circle me-2" style="color: var(--bs-primary);"></i>Add New Item
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="form_action" value="add_item">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="item_name" class="form-control" placeholder="Enter item name" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Beginning Qty <span class="text-danger">*</span></label>
                            <input type="number" name="beginning_qty" class="form-control" value="0" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Extra Qty (+)</label>
                            <input type="number" name="extra_qty" class="form-control" value="0" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i> Save Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-edit me-2" style="color: var(--bs-primary);"></i>Edit Item
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="form_action" value="edit_item">
                <input type="hidden" name="item_id" id="edit_item_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category_id" id="edit_category_id" class="form-select" required>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="item_name" id="edit_item_name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Beginning Qty</label>
                            <input type="number" name="beginning_qty" id="edit_beginning_qty" class="form-control" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Previous Qty</label>
                            <input type="number" name="previous_qty" id="edit_previous_qty" class="form-control" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Extra Qty (+)</label>
                            <input type="number" name="extra_qty" id="edit_extra_qty" class="form-control" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i> Update Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Adjust Quantity Modal -->
<div class="modal fade" id="adjustQtyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-plus-circle me-2" style="color: var(--bs-success);"></i>Adjust Quantity
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="form_action" value="adjust_qty">
                <input type="hidden" name="item_id" id="adjust_item_id">
                <div class="modal-body">
                    <p class="mb-2">Item: <strong id="adjust_item_name"></strong></p>
                    <p class="mb-3">Current Qty: <span class="badge badge-primary" id="adjust_current_qty"></span></p>
                    <div class="mb-3">
                        <label class="form-label">Adjustment</label>
                        <input type="number" name="adjustment" class="form-control" placeholder="Enter + or - value" required>
                        <small class="text-muted">Use negative number to decrease</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bx bx-check me-1"></i> Apply
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Item Modal -->
<div class="modal fade" id="deleteItemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-trash me-2" style="color: var(--bs-danger);"></i>Delete Item
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="form_action" value="delete_item">
                <input type="hidden" name="item_id" id="delete_item_id">
                <div class="modal-body">
                    <div class="text-center">
                        <div style="width: 80px; height: 80px; background: rgba(255, 62, 29, 0.16); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                            <i class="bx bx-trash" style="font-size: 2.5rem; color: var(--bs-danger);"></i>
                        </div>
                        <h5 style="color: var(--heading-color);">Are you sure?</h5>
                        <p class="mb-0">You are about to delete item "<strong id="delete_item_name"></strong>".</p>
                        <p class="text-muted">This action cannot be undone.</p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bx bx-trash me-1"></i> Delete Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function filterTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const rows = document.querySelectorAll('.item-row');
    
    rows.forEach(row => {
        const name = row.getAttribute('data-item-name');
        if (name.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function editItem(itemData) {
    const item = typeof itemData === 'string' ? JSON.parse(itemData) : itemData;
    
    document.getElementById('edit_item_id').value = item.item_id;
    document.getElementById('edit_category_id').value = item.category_id;
    document.getElementById('edit_item_name').value = item.item_name;
    document.getElementById('edit_beginning_qty').value = item.beginning_qty;
    document.getElementById('edit_previous_qty').value = item.previous_qty;
    document.getElementById('edit_extra_qty').value = item.extra_qty;
    
    const modal = new bootstrap.Modal(document.getElementById('editItemModal'));
    modal.show();
}

function deleteItem(id, name) {
    document.getElementById('delete_item_id').value = id;
    document.getElementById('delete_item_name').textContent = name;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteItemModal'));
    modal.show();
}

function adjustQty(id, name, currentQty) {
    document.getElementById('adjust_item_id').value = id;
    document.getElementById('adjust_item_name').textContent = name;
    document.getElementById('adjust_current_qty').textContent = currentQty;
    
    const modal = new bootstrap.Modal(document.getElementById('adjustQtyModal'));
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>
