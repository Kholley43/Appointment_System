<?php include VIEW_PATH . '/partials/header.php'; ?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Notifications</h4>
                    <?php if (!empty($notifications)): ?>
                        <button id="markAllRead" class="btn btn-light btn-sm">Mark All as Read</button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!empty($notifications)) : ?>
                        <div class="list-group">
                            <?php foreach ($notifications as $notification) : ?>
                                <?php 
                                    $isRead = $notification['is_read'] ?? false;
                                    $notificationClass = $isRead ? '' : 'list-group-item-primary';
                                    $notificationType = $notification['type'] ?? 'info';
                                    
                                    // Determine icon based on notification type
                                    $icon = 'info-circle';
                                    $iconClass = 'text-info';
                                    
                                    switch($notificationType) {
                                        case 'appointment_reminder':
                                            $icon = 'calendar-check';
                                            $iconClass = 'text-primary';
                                            break;
                                        case 'appointment_confirmed':
                                            $icon = 'check-circle';
                                            $iconClass = 'text-success';
                                            break;
                                        case 'appointment_canceled':
                                            $icon = 'times-circle';
                                            $iconClass = 'text-danger';
                                            break;
                                        case 'system_message':
                                            $icon = 'exclamation-circle';
                                            $iconClass = 'text-warning';
                                            break;
                                    }
                                    
                                    // Format date
                                    $createdAt = new DateTime($notification['created_at']);
                                    $formattedDate = $createdAt->format('M d, Y g:i A');
                                    
                                    // Calculate time ago
                                    $now = new DateTime();
                                    $interval = $createdAt->diff($now);
                                    
                                    if ($interval->d > 0) {
                                        $timeAgo = $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
                                    } elseif ($interval->h > 0) {
                                        $timeAgo = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
                                    } elseif ($interval->i > 0) {
                                        $timeAgo = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
                                    } else {
                                        $timeAgo = 'just now';
                                    }
                                ?>
                                <div class="list-group-item list-group-item-action <?= $notificationClass ?>" 
                                     data-notification-id="<?= $notification['notification_id'] ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1">
                                            <i class="fas fa-<?= $icon ?> <?= $iconClass ?> me-2"></i>
                                            <?= htmlspecialchars($notification['title'] ?? 'Notification') ?>
                                        </h5>
                                        <small class="text-muted" title="<?= $formattedDate ?>"><?= $timeAgo ?></small>
                                    </div>
                                    <p class="mb-1"><?= htmlspecialchars($notification['message']) ?></p>
                                    <?php if (!$isRead): ?>
                                        <button class="btn btn-sm btn-outline-primary mt-2 mark-read" 
                                                data-id="<?= $notification['notification_id'] ?>">
                                            Mark as Read
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div class="alert alert-info">
                            <i class="fas fa-bell-slash me-2"></i>
                            <span>You have no new notifications.</span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($notifications)): ?>
                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">Showing <?= count($notifications) ?> notification(s)</small>
                            <a href="<?= base_url('index.php/notification/settings') ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-cog me-1"></i> Notification Settings
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mark individual notification as read
    const markReadButtons = document.querySelectorAll('.mark-read');
    markReadButtons.forEach(button => {
        button.addEventListener('click', function() {
            const notificationId = this.getAttribute('data-id');
            submitMarkAsReadForm(notificationId);
        });
    });
    
    // Mark all notifications as read
    const markAllButton = document.getElementById('markAllRead');
    if (markAllButton) {
        markAllButton.addEventListener('click', function() {
            submitMarkAllAsReadForm();
        });
    }
    
    // Function to create and submit form for marking a single notification as read
    function submitMarkAsReadForm(notificationId) {
        // Create form element
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= base_url('index.php/notification/markAsRead') ?>';
        form.style.display = 'none';
        
        // Add notification ID to form
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'notification_id';
        idInput.value = notificationId;
        form.appendChild(idInput);
        
        // Add CSRF token if it exists
        const csrfTokenInput = document.querySelector('input[name="csrf_token"]');
        if (csrfTokenInput) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfTokenInput.value;
            form.appendChild(csrfInput);
        }
        
        // Add to body and submit
        document.body.appendChild(form);
        form.submit();
    }
    
    // Function to create and submit form for marking all notifications as read
    function submitMarkAllAsReadForm() {
        // Create form element
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= base_url('index.php/notification/markAllAsRead') ?>';
        form.style.display = 'none';
        
        // Add CSRF token if it exists
        const csrfTokenInput = document.querySelector('input[name="csrf_token"]');
        if (csrfTokenInput) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfTokenInput.value;
            form.appendChild(csrfInput);
        }
        
        // Add to body and submit
        document.body.appendChild(form);
        form.submit();
    }
});
</script>

<?php include VIEW_PATH . '/partials/footer.php'; ?>