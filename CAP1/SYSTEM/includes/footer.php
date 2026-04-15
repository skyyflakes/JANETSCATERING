            </div><!-- End Content Wrapper -->

            <!-- Footer -->
            <footer class="footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); margin-top: auto;">
                <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 12px;">
                    <div style="font-size: 0.875rem; color: var(--bs-secondary);">
                        &copy; <?php echo date('Y'); ?> <strong>Janet's Quality Catering</strong>. All rights reserved.
                    </div>
                    <div style="font-size: 0.8125rem; color: var(--bs-secondary);">
                        Version 2.0.0 | Powered by Sneat Template
                    </div>
                </div>
            </footer>
        </div><!-- End Layout Page -->
    </div><!-- End Layout Wrapper -->

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle Mobile Menu
        function toggleMenu() {
            const menu = document.querySelector('.layout-menu');
            const overlay = document.querySelector('.layout-overlay');
            menu.classList.toggle('show');
            overlay.classList.toggle('show');
        }

        // Toggle User Dropdown
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const userDropdown = document.getElementById('userDropdown');
            if (userDropdown && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('show');
            }
        });

        // Theme Management
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);
        }

        function setTheme(theme) {
            const html = document.documentElement;
            html.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            updateThemeIcon(theme);
        }

        function updateThemeIcon(theme) {
            const icon = document.getElementById('themeIcon');
            if (icon) {
                icon.className = theme === 'dark' ? 'bx bx-sun' : 'bx bx-moon';
            }
        }

        // Load saved theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            setTheme(savedTheme);

            // Auto-hide flash messages
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html>
