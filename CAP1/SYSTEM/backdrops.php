<?php
/**
 * Backdrops Gallery Page - Janet's Quality Catering System
 * Sneat Bootstrap Template Design
 */
$page_title = "Backdrops | Janet's Quality Catering";
$current_page = 'backdrops';

require_once 'includes/auth_check.php';

// Check if coming from event booking form
$from_booking = isset($_SESSION['event_form_data']);

// Handle backdrop selection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $photo_name = sanitize($_POST['photo_name'] ?? '');
    if ($photo_name) {
        $_SESSION['selected_backdrop'] = $photo_name;
        
        // If coming from booking form, redirect back to continue booking
        if ($from_booking) {
            setFlash('Backdrop "' . $photo_name . '" selected! Continue with your booking.', 'success');
            redirect('events.php?action=add');
        } else {
            setFlash('Backdrop "' . $photo_name . '" selected! Proceed to booking.', 'success');
            redirect('events.php?action=add');
        }
    }
}

// Generate photo list (1-70)
$photos = [];
for ($i = 1; $i <= 70; $i++) {
    $photos[] = "photo{$i}.jpg";
}

require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="page-title mb-1">Backdrops Gallery</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Backdrops</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <?php if ($from_booking): ?>
        <a href="events.php?action=add" class="btn btn-secondary">
            <i class="bx bx-arrow-back me-1"></i>Back to Booking
        </a>
        <?php endif; ?>
        <div class="input-group" style="width: 250px;">
            <span class="input-group-text" style="background: var(--card-bg); border-color: var(--border-color);">
                <i class="bx bx-search"></i>
            </span>
            <input type="text" id="searchInput" class="form-control" placeholder="Search backdrops..." onkeyup="filterBackdrops()">
        </div>
    </div>
</div>

<?php if ($from_booking): ?>
<div class="alert alert-info mb-4">
    <i class="bx bx-info-circle me-2"></i>
    You are selecting a backdrop for your event booking. Your form data has been saved. Select a backdrop below to continue.
</div>
<?php endif; ?>

<?php if (isset($_SESSION['selected_backdrop'])): ?>
<div class="alert alert-success mb-4">
    <i class="bx bx-check-circle me-2"></i>
    Currently selected: <strong><?php echo htmlspecialchars($_SESSION['selected_backdrop']); ?></strong>
    <a href="events.php?action=add" class="ms-3" style="color: var(--bs-primary);">Proceed to Booking</a>
</div>
<?php endif; ?>

<!-- Gallery Grid -->
<div class="row" id="galleryGrid">
    <?php foreach ($photos as $index => $photo): ?>
    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-4 backdrop-item" data-title="<?php echo $photo; ?>" data-index="<?php echo $index; ?>">
        <div class="card h-100" style="cursor: pointer; transition: all 0.3s;">
            <?php if (isset($_SESSION['selected_backdrop']) && $_SESSION['selected_backdrop'] === $photo): ?>
            <span class="badge badge-success position-absolute" style="top: 10px; right: 10px; z-index: 10;">
                <i class="bx bx-check me-1"></i>Selected
            </span>
            <?php endif; ?>
            
            <div style="height: 150px; overflow: hidden; background: #000;">
                <img src="static/images/<?php echo $photo; ?>" 
                     alt="Backdrop <?php echo $index + 1; ?>" 
                     style="width: 100%; height: 100%; object-fit: cover; transition: 0.4s;"
                     class="backdrop-img"
                     onerror="this.src='https://via.placeholder.com/300x200?text=Backdrop+<?php echo $index + 1; ?>'">
            </div>
            
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-weight: 600; font-size: 0.875rem; color: var(--heading-color);">
                        Backdrop <?php echo $index + 1; ?>
                    </span>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-icon btn-sm btn-label-primary" onclick="viewImage(<?php echo $index; ?>)" title="View">
                            <i class="bx bx-expand"></i>
                        </button>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="photo_name" value="<?php echo $photo; ?>">
                            <button type="submit" class="btn btn-icon btn-sm btn-primary" title="Select">
                                <i class="bx bx-check"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- No Results -->
<div id="noResults" class="text-center py-5" style="display: none;">
    <i class="bx bx-search" style="font-size: 4rem; color: var(--bs-secondary);"></i>
    <h5 class="mt-3" style="color: var(--heading-color);">No backdrops found</h5>
    <p class="text-muted">Try a different search term</p>
</div>

<!-- Lightbox -->
<div class="modal fade" id="lightboxModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content" style="background: transparent; border: none;">
            <button type="button" class="btn-close btn-close-white position-absolute" style="top: -30px; right: 0;" data-bs-dismiss="modal"></button>
            <img src="" alt="Full Size" id="lightboxImg" style="max-width: 100%; max-height: 85vh; border-radius: 8px; margin: 0 auto; display: block;">
            <div class="text-center mt-3">
                <h5 id="lightboxTitle" style="color: #fff;"></h5>
            </div>
        </div>
    </div>
</div>

<style>
.card:hover .backdrop-img {
    transform: scale(1.1);
}
</style>

<script>
const photos = <?php echo json_encode($photos); ?>;
let currentIndex = 0;
let lightboxModal;

document.addEventListener('DOMContentLoaded', function() {
    lightboxModal = new bootstrap.Modal(document.getElementById('lightboxModal'));
});

function viewImage(index) {
    currentIndex = index;
    const img = document.getElementById('lightboxImg');
    const title = document.getElementById('lightboxTitle');
    
    img.src = 'static/images/' + photos[index];
    title.textContent = 'Backdrop ' + (index + 1);
    lightboxModal.show();
}

function filterBackdrops() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const items = document.querySelectorAll('.backdrop-item');
    let visibleCount = 0;

    items.forEach(item => {
        const title = item.dataset.title.toLowerCase();
        const index = item.dataset.index;
        const searchText = `backdrop ${parseInt(index) + 1} ${title}`.toLowerCase();
        
        if (searchText.includes(input)) {
            item.style.display = 'block';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });

    document.getElementById('noResults').style.display = visibleCount === 0 ? 'block' : 'none';
}

// Keyboard navigation
window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        lightboxModal.hide();
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
