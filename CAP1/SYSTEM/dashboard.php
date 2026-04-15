<?php
/**
 * Dashboard Page - Janet's Quality Catering System
 * With Functional Predictive Analytics
 */
$page_title = "Dashboard | Janet's Quality Catering";
$current_page = 'dashboard';

require_once 'includes/auth_check.php';

$pdo = getDBConnection();

$low_stock = 0;
$total_varieties = 0;
$total_events = 0;
$pending_events = 0;
$recent_events = [];
$total_categories = 0;
$confirmed_events = 0;
$monthly_data = [];
$low_stock_items = [];
$inventory_predictions = [];
$event_predictions = [];

if ($pdo) {
    // Low stock items (ending_qty <= 10)
    $stmt = $pdo->query("SELECT COUNT(*) FROM inventory WHERE ending_qty <= 10 AND is_active = 1");
    $low_stock = $stmt->fetchColumn();

    // Total inventory varieties
    $stmt = $pdo->query("SELECT COUNT(*) FROM inventory WHERE is_active = 1");
    $total_varieties = $stmt->fetchColumn();

    // Total categories
    $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
    $total_categories = $stmt->fetchColumn();

    // Total events
    $stmt = $pdo->query("SELECT COUNT(*) FROM events");
    $total_events = $stmt->fetchColumn();

    // Pending events
    $stmt = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'Pending'");
    $pending_events = $stmt->fetchColumn();

    // Confirmed events
    $stmt = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'Confirmed'");
    $confirmed_events = $stmt->fetchColumn();

    // Recent events
    $stmt = $pdo->query("SELECT * FROM events ORDER BY event_date DESC LIMIT 5");
    $recent_events = $stmt->fetchAll();

    // Get monthly event data for the past 12 months
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(event_date, '%Y-%m') as month,
            COUNT(*) as event_count,
            SUM(pax) as total_pax
        FROM events 
        WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(event_date, '%Y-%m')
        ORDER BY month ASC
    ");
    $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get low stock items
    $stmt = $pdo->query("SELECT i.*, c.category_name FROM inventory i LEFT JOIN categories c ON i.category_id = c.category_id WHERE i.ending_qty <= 10 AND i.is_active = 1 ORDER BY i.ending_qty ASC LIMIT 5");
    $low_stock_items = $stmt->fetchAll();

    // ============ PREDICTIVE ANALYTICS ============
    
    // 1. Inventory Usage Prediction based on upcoming events
    $stmt = $pdo->query("
        SELECT e.id, e.event_name, e.event_date, e.pax, e.status,
               i.item_id, i.item_name, i.ending_qty, c.category_name
        FROM events e
        CROSS JOIN inventory i
        LEFT JOIN categories c ON i.category_id = c.category_id
        WHERE e.event_date >= CURDATE() 
        AND e.event_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        AND e.status IN ('Pending', 'Confirmed')
        AND i.is_active = 1
        ORDER BY e.event_date ASC
    ");
    $upcoming_events_inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate predicted inventory usage
    $inventory_usage = [];
    $upcoming_pax_total = 0;
    
    $stmt = $pdo->query("
        SELECT SUM(pax) as total_pax, COUNT(*) as event_count
        FROM events 
        WHERE event_date >= CURDATE() 
        AND event_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        AND status IN ('Pending', 'Confirmed')
    ");
    $upcoming_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $upcoming_pax_total = $upcoming_stats['total_pax'] ?? 0;
    $upcoming_event_count = $upcoming_stats['event_count'] ?? 0;
    
    // Get all inventory items and calculate predicted usage
    $stmt = $pdo->query("
        SELECT i.item_id, i.item_name, i.ending_qty, c.category_name,
               CASE 
                   WHEN c.category_name = 'Silverware' THEN 1
                   WHEN c.category_name = 'Dinnerware' THEN 1
                   WHEN c.category_name = 'Glassware' THEN 2
                   WHEN c.category_name = 'Linens' THEN 0.1
                   ELSE 0.5
               END as usage_per_pax
        FROM inventory i
        LEFT JOIN categories c ON i.category_id = c.category_id
        WHERE i.is_active = 1
        ORDER BY i.ending_qty ASC
    ");
    $all_inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_inventory as $item) {
        $predicted_usage = ceil($upcoming_pax_total * $item['usage_per_pax']);
        $remaining_after = $item['ending_qty'] - $predicted_usage;
        $shortage = $remaining_after < 0 ? abs($remaining_after) : 0;
        
        if ($remaining_after < 20 || $shortage > 0) {
            $inventory_predictions[] = [
                'item_name' => $item['item_name'],
                'category' => $item['category_name'],
                'current_qty' => $item['ending_qty'],
                'predicted_usage' => $predicted_usage,
                'remaining_after' => max(0, $remaining_after),
                'shortage' => $shortage,
                'status' => $shortage > 0 ? 'critical' : ($remaining_after < 10 ? 'warning' : 'ok')
            ];
        }
    }
    
    // 2. Event Demand Prediction based on historical data
    $stmt = $pdo->query("
        SELECT 
            MONTH(event_date) as month_num,
            MONTHNAME(event_date) as month_name,
            COUNT(*) as event_count,
            AVG(pax) as avg_pax
        FROM events
        WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 24 MONTH)
        GROUP BY MONTH(event_date), MONTHNAME(event_date)
        ORDER BY month_num
    ");
    $historical_monthly = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate next 3 months predictions
    for ($i = 1; $i <= 3; $i++) {
        $future_month = date('n', strtotime("+$i months"));
        $future_month_name = date('F', strtotime("+$i months"));
        
        $historical_avg = 0;
        $historical_pax = 0;
        foreach ($historical_monthly as $hm) {
            if ($hm['month_num'] == $future_month) {
                $historical_avg = $hm['event_count'];
                $historical_pax = $hm['avg_pax'];
                break;
            }
        }
        
        // Apply growth factor (10% increase)
        $predicted_events = ceil($historical_avg * 1.1);
        $predicted_pax = ceil($historical_pax * 1.1);
        
        $event_predictions[] = [
            'month' => $future_month_name,
            'predicted_events' => max(1, $predicted_events),
            'predicted_avg_pax' => max(50, $predicted_pax),
            'confidence' => $historical_avg > 0 ? 'High' : 'Low'
        ];
    }
}

// Prepare chart data
$chart_labels = [];
$event_counts = [];
$pax_counts = [];

for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $chart_labels[] = date('M', strtotime("-$i months"));
    
    $found_event = false;
    foreach ($monthly_data as $data) {
        if ($data['month'] === $month) {
            $event_counts[] = (int)$data['event_count'];
            $pax_counts[] = (int)$data['total_pax'];
            $found_event = true;
            break;
        }
    }
    if (!$found_event) {
        $event_counts[] = 0;
        $pax_counts[] = 0;
    }
}

require_once 'includes/header.php';
?>

<!-- Welcome Banner -->
<div class="card mb-4" style="background: linear-gradient(135deg, #9370DB 0%, #8B5CF6 100%);">
    <div class="card-body py-4">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h4 class="mb-2" style="color: #fff; font-weight: 600;">
                    Welcome back, <?php echo htmlspecialchars($current_user['first_name'] ?? $current_user['username']); ?>!
                </h4>
                <p class="mb-0" style="color: rgba(255,255,255,0.9);">
                    Here's what's happening with your catering business today. You have <strong><?php echo $pending_events; ?> pending events</strong> that need attention.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <a href="events.php?action=add" class="btn" style="background: rgba(255,255,255,0.2); color: #fff; border: none;">
                    <i class="bx bx-plus me-1"></i> New Event Booking
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="d-block text-muted mb-1" style="font-size: 0.8125rem;">Total Events</span>
                        <h3 class="mb-2" style="color: var(--bs-primary);"><?php echo $total_events; ?></h3>
                        <small style="color: var(--bs-success);"><i class="bx bx-up-arrow-alt"></i> <?php echo $confirmed_events; ?> confirmed</small>
                    </div>
                    <div style="width: 48px; height: 48px; background: rgba(147, 112, 219, 0.15); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="bx bx-calendar-event" style="font-size: 1.5rem; color: var(--bs-primary);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="d-block text-muted mb-1" style="font-size: 0.8125rem;">Pending Events</span>
                        <h3 class="mb-2" style="color: var(--bs-warning);"><?php echo $pending_events; ?></h3>
                        <small class="text-muted">Awaiting confirmation</small>
                    </div>
                    <div style="width: 48px; height: 48px; background: rgba(255, 171, 0, 0.15); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="bx bx-time-five" style="font-size: 1.5rem; color: var(--bs-warning);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="d-block text-muted mb-1" style="font-size: 0.8125rem;">Inventory Items</span>
                        <h3 class="mb-2" style="color: var(--bs-success);"><?php echo $total_varieties; ?></h3>
                        <small class="text-muted"><?php echo $total_categories; ?> categories</small>
                    </div>
                    <div style="width: 48px; height: 48px; background: rgba(113, 221, 55, 0.15); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="bx bx-box" style="font-size: 1.5rem; color: var(--bs-success);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="d-block text-muted mb-1" style="font-size: 0.8125rem;">Low Stock Alert</span>
                        <h3 class="mb-2" style="color: var(--bs-danger);"><?php echo $low_stock; ?></h3>
                        <small style="color: var(--bs-danger);"><i class="bx bx-error-circle"></i> Needs attention</small>
                    </div>
                    <div style="width: 48px; height: 48px; background: rgba(255, 62, 29, 0.15); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="bx bx-error" style="font-size: 1.5rem; color: var(--bs-danger);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Predictive Analytics Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(147, 112, 219, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);">
                <div class="d-flex align-items-center">
                    <div style="width: 42px; height: 42px; background: linear-gradient(135deg, #9370DB 0%, #8B5CF6 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 14px;">
                        <i class="bx bx-brain" style="color: #fff; font-size: 1.25rem;"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0">Predictive Analytics</h5>
                        <small class="text-muted">AI-powered insights for your business</small>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Event Demand Forecast -->
                    <div class="col-lg-6 mb-4">
                        <h6 style="color: var(--heading-color); font-weight: 600; margin-bottom: 16px;">
                            <i class="bx bx-trending-up me-2" style="color: var(--bs-primary);"></i>Event Demand Forecast (Next 3 Months)
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th class="text-center">Predicted Events</th>
                                        <th class="text-center">Avg. Pax</th>
                                        <th class="text-center">Confidence</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($event_predictions)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No prediction data available</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($event_predictions as $pred): ?>
                                    <tr>
                                        <td><strong style="color: var(--heading-color);"><?php echo $pred['month']; ?></strong></td>
                                        <td class="text-center">
                                            <span class="badge badge-primary"><?php echo $pred['predicted_events']; ?> events</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-info"><?php echo $pred['predicted_avg_pax']; ?> pax</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?php echo $pred['confidence'] === 'High' ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo $pred['confidence']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Inventory Predictions -->
                    <div class="col-lg-6 mb-4">
                        <h6 style="color: var(--heading-color); font-weight: 600; margin-bottom: 16px;">
                            <i class="bx bx-package me-2" style="color: var(--bs-warning);"></i>Inventory Forecast (Next 30 Days)
                        </h6>
                        <p class="text-muted mb-3" style="font-size: 0.8125rem;">
                            Based on <?php echo $upcoming_event_count ?? 0; ?> upcoming events with <?php echo number_format($upcoming_pax_total ?? 0); ?> total guests
                        </p>
                        <div style="max-height: 200px; overflow-y: auto;">
                            <?php if (empty($inventory_predictions)): ?>
                            <div class="text-center py-4">
                                <i class="bx bx-check-circle" style="font-size: 2.5rem; color: var(--bs-success);"></i>
                                <p class="text-muted mt-2 mb-0">All inventory levels are healthy!</p>
                            </div>
                            <?php else: ?>
                            <?php foreach (array_slice($inventory_predictions, 0, 5) as $pred): ?>
                            <div class="d-flex align-items-center justify-content-between py-2 border-bottom" style="border-color: var(--border-color) !important;">
                                <div class="d-flex align-items-center">
                                    <div style="width: 36px; height: 36px; background: <?php echo $pred['status'] === 'critical' ? 'rgba(255, 62, 29, 0.15)' : 'rgba(255, 171, 0, 0.15)'; ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                        <i class="bx <?php echo $pred['status'] === 'critical' ? 'bx-error' : 'bx-error-circle'; ?>" style="font-size: 1rem; color: <?php echo $pred['status'] === 'critical' ? 'var(--bs-danger)' : 'var(--bs-warning)'; ?>;"></i>
                                    </div>
                                    <div>
                                        <strong style="color: var(--heading-color); font-size: 0.875rem;"><?php echo htmlspecialchars($pred['item_name']); ?></strong>
                                        <p class="mb-0 text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($pred['category']); ?></p>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <?php if ($pred['shortage'] > 0): ?>
                                    <span class="badge badge-danger">Shortage: <?php echo $pred['shortage']; ?></span>
                                    <?php else: ?>
                                    <span class="badge badge-warning"><?php echo $pred['remaining_after']; ?> left</span>
                                    <?php endif; ?>
                                    <p class="mb-0 text-muted" style="font-size: 0.7rem;">Need: <?php echo $pred['predicted_usage']; ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recommendations -->
                <div class="mt-3 p-3" style="background: rgba(147, 112, 219, 0.08); border-radius: 10px;">
                    <h6 style="color: var(--heading-color); font-weight: 600; margin-bottom: 12px;">
                        <i class="bx bx-bulb me-2" style="color: var(--bs-warning);"></i>Smart Recommendations
                    </h6>
                    <div class="row">
                        <?php
                        $recommendations = [];
                        
                        // Inventory recommendations
                        $critical_items = array_filter($inventory_predictions, fn($p) => $p['status'] === 'critical');
                        if (count($critical_items) > 0) {
                            $recommendations[] = [
                                'icon' => 'bx-error',
                                'color' => 'danger',
                                'text' => 'Order more stock for ' . count($critical_items) . ' critical items before upcoming events'
                            ];
                        }
                        
                        // Event recommendations
                        if ($pending_events > 0) {
                            $recommendations[] = [
                                'icon' => 'bx-calendar-check',
                                'color' => 'warning',
                                'text' => 'Confirm ' . $pending_events . ' pending events to finalize inventory requirements'
                            ];
                        }
                        
                        // Growth recommendation
                        if (!empty($event_predictions) && $event_predictions[0]['predicted_events'] > 2) {
                            $recommendations[] = [
                                'icon' => 'bx-trending-up',
                                'color' => 'success',
                                'text' => 'High demand expected in ' . $event_predictions[0]['month'] . '. Consider hiring additional staff.'
                            ];
                        }
                        
                        if (empty($recommendations)) {
                            $recommendations[] = [
                                'icon' => 'bx-check-circle',
                                'color' => 'success',
                                'text' => 'Everything looks great! Your business is running smoothly.'
                            ];
                        }
                        ?>
                        <?php foreach ($recommendations as $rec): ?>
                        <div class="col-md-4 mb-2">
                            <div class="d-flex align-items-start">
                                <i class="bx <?php echo $rec['icon']; ?> me-2" style="color: var(--bs-<?php echo $rec['color']; ?>); font-size: 1.25rem;"></i>
                                <span style="font-size: 0.8125rem; color: var(--body-color);"><?php echo $rec['text']; ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Events Overview</h5>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-primary active" onclick="updateChart('events')">Events</button>
                        <button type="button" class="btn btn-label-primary" onclick="updateChart('pax')">Guests (Pax)</button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <canvas id="eventsChart" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <a href="events.php?action=add" class="d-flex align-items-center p-3 mb-3" style="background: rgba(147, 112, 219, 0.1); border-radius: 10px; text-decoration: none; transition: all 0.2s;">
                    <div style="width: 42px; height: 42px; background: linear-gradient(135deg, #9370DB 0%, #8B5CF6 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                        <i class="bx bx-plus" style="color: #fff; font-size: 1.25rem;"></i>
                    </div>
                    <div>
                        <strong style="color: var(--heading-color);">New Event Booking</strong>
                        <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Create a new event</p>
                    </div>
                </a>

                <a href="inventory.php" class="d-flex align-items-center p-3 mb-3" style="background: rgba(113, 221, 55, 0.1); border-radius: 10px; text-decoration: none; transition: all 0.2s;">
                    <div style="width: 42px; height: 42px; background: var(--bs-success); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                        <i class="bx bx-box" style="color: #fff; font-size: 1.25rem;"></i>
                    </div>
                    <div>
                        <strong style="color: var(--heading-color);">Manage Inventory</strong>
                        <p class="mb-0 text-muted" style="font-size: 0.8125rem;">View and update items</p>
                    </div>
                </a>

                <a href="categories.php" class="d-flex align-items-center p-3 mb-3" style="background: rgba(3, 195, 236, 0.1); border-radius: 10px; text-decoration: none; transition: all 0.2s;">
                    <div style="width: 42px; height: 42px; background: var(--bs-info); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                        <i class="bx bx-category" style="color: #fff; font-size: 1.25rem;"></i>
                    </div>
                    <div>
                        <strong style="color: var(--heading-color);">Categories</strong>
                        <p class="mb-0 text-muted" style="font-size: 0.8125rem;">Manage item categories</p>
                    </div>
                </a>

                <?php if ($current_user['role'] === 'OWNER'): ?>
                <a href="reports.php" class="d-flex align-items-center p-3" style="background: rgba(255, 171, 0, 0.1); border-radius: 10px; text-decoration: none; transition: all 0.2s;">
                    <div style="width: 42px; height: 42px; background: var(--bs-warning); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                        <i class="bx bx-file" style="color: #fff; font-size: 1.25rem;"></i>
                    </div>
                    <div>
                        <strong style="color: var(--heading-color);">Generate Reports</strong>
                        <p class="mb-0 text-muted" style="font-size: 0.8125rem;">View business reports</p>
                    </div>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Events & Low Stock -->
<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Events</h5>
                    <a href="events.php" class="btn btn-sm btn-label-primary">View All</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Pax</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_events)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <div style="color: var(--bs-secondary);">
                                    <i class="bx bx-calendar" style="font-size: 2.5rem;"></i>
                                    <p class="mb-0 mt-2">No events found</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recent_events as $event): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div style="width: 38px; height: 38px; background: rgba(147, 112, 219, 0.15); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                        <i class="bx bx-calendar-event" style="font-size: 1rem; color: var(--bs-primary);"></i>
                                    </div>
                                    <strong style="color: var(--heading-color);"><?php echo htmlspecialchars($event['event_name']); ?></strong>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($event['fullname']); ?></td>
                            <td>
                                <small><?php echo date('M d, Y', strtotime($event['event_date'])); ?></small>
                            </td>
                            <td>
                                <span class="badge badge-secondary"><?php echo $event['pax']; ?></span>
                            </td>
                            <td>
                                <span class="status-pill status-<?php echo strtolower($event['status']); ?>">
                                    <?php echo $event['status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Low Stock Items</h5>
                    <a href="inventory.php" class="btn btn-sm btn-label-danger">View All</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($low_stock_items)): ?>
                <div class="text-center py-4">
                    <div style="color: var(--bs-secondary);">
                        <i class="bx bx-check-circle" style="font-size: 2.5rem; color: var(--bs-success);"></i>
                        <p class="mb-0 mt-2">All items are well stocked!</p>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($low_stock_items as $item): ?>
                <div class="d-flex align-items-center justify-content-between py-2 border-bottom" style="border-color: var(--border-color) !important;">
                    <div class="d-flex align-items-center">
                        <div style="width: 38px; height: 38px; background: rgba(255, 62, 29, 0.15); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                            <i class="bx bx-package" style="font-size: 1rem; color: var(--bs-danger);"></i>
                        </div>
                        <div>
                            <strong style="color: var(--heading-color); font-size: 0.875rem;"><?php echo htmlspecialchars($item['item_name']); ?></strong>
                            <p class="mb-0 text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($item['category_name']); ?></p>
                        </div>
                    </div>
                    <span class="badge <?php echo $item['ending_qty'] == 0 ? 'badge-danger' : 'badge-warning'; ?>">
                        <?php echo $item['ending_qty']; ?> left
                    </span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- System Info Card -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center mb-3 mb-md-0 border-end" style="border-color: var(--border-color) !important;">
                        <span class="d-block text-muted mb-1" style="font-size: 0.75rem; text-transform: uppercase;">Logged In As</span>
                        <strong style="color: var(--heading-color);"><?php echo htmlspecialchars($current_user['username']); ?></strong>
                        <span class="badge <?php echo $current_user['role'] === 'OWNER' ? 'badge-success' : 'badge-primary'; ?> ms-2"><?php echo $current_user['role']; ?></span>
                    </div>
                    <div class="col-md-3 text-center mb-3 mb-md-0 border-end" style="border-color: var(--border-color) !important;">
                        <span class="d-block text-muted mb-1" style="font-size: 0.75rem; text-transform: uppercase;">Current Date</span>
                        <strong style="color: var(--heading-color);"><?php echo date('F d, Y'); ?></strong>
                    </div>
                    <div class="col-md-3 text-center mb-3 mb-md-0 border-end" style="border-color: var(--border-color) !important;">
                        <span class="d-block text-muted mb-1" style="font-size: 0.75rem; text-transform: uppercase;">System Version</span>
                        <strong style="color: var(--heading-color);">v2.1.0</strong>
                    </div>
                    <div class="col-md-3 text-center">
                        <span class="d-block text-muted mb-1" style="font-size: 0.75rem; text-transform: uppercase;">Database Status</span>
                        <?php if ($pdo): ?>
                        <span class="badge badge-success"><i class="bx bx-check-circle me-1"></i>Connected</span>
                        <?php else: ?>
                        <span class="badge badge-danger"><i class="bx bx-x-circle me-1"></i>Disconnected</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const chartLabels = <?php echo json_encode($chart_labels); ?>;
const eventCounts = <?php echo json_encode($event_counts); ?>;
const paxCounts = <?php echo json_encode($pax_counts); ?>;

let currentChart;

function initChart() {
    const ctx = document.getElementById('eventsChart').getContext('2d');
    const primaryColor = '#9370DB';
    
    currentChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Events',
                data: eventCounts,
                backgroundColor: primaryColor + '80',
                borderColor: primaryColor,
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    }
                }
            }
        }
    });
}

function updateChart(type) {
    const buttons = document.querySelectorAll('.btn-group .btn');
    buttons.forEach(btn => {
        btn.classList.remove('active', 'btn-primary');
        btn.classList.add('btn-label-primary');
    });
    
    event.target.classList.remove('btn-label-primary');
    event.target.classList.add('btn-primary', 'active');
    
    const primaryColor = '#9370DB';
    const successColor = '#71dd37';
    
    if (type === 'events') {
        currentChart.data.datasets[0].label = 'Events';
        currentChart.data.datasets[0].data = eventCounts;
        currentChart.data.datasets[0].backgroundColor = primaryColor + '80';
        currentChart.data.datasets[0].borderColor = primaryColor;
    } else {
        currentChart.data.datasets[0].label = 'Guests (Pax)';
        currentChart.data.datasets[0].data = paxCounts;
        currentChart.data.datasets[0].backgroundColor = successColor + '80';
        currentChart.data.datasets[0].borderColor = successColor;
    }
    
    currentChart.update();
}

document.addEventListener('DOMContentLoaded', function() {
    initChart();
});
</script>

<?php include 'includes/footer.php'; ?>
