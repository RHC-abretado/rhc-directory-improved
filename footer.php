</div> <!-- Close container -->
    </div> <!-- Close main-content -->
    
    <!-- Footer -->
    <footer class="bg-dark text-light mt-auto">
        <div class="container py-4">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-users"></i> RHC Department/Staff Directory</h6>
                    
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="documentation.php" class="text-light me-3">
                        <i class="fas fa-question-circle"></i> Documentation
                    </a>
                    <?php if (isLoggedIn()): ?>
                        <a href="display.php" class="text-light" target="_blank">
                            <i class="fas fa-eye"></i> View Directory
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="text-light">
                            <i class="fas fa-sign-in-alt"></i> Staff Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <hr class="my-3 border-secondary">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <small class="text-muted">© <?php echo date('Y'); ?> <a href="https://www.deviant.media/" target="_blank">Deviant Media LLC</a></small>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-muted">
                        Version 3.0 | 
                        <?php if (isLoggedIn()): ?>
                            Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?>
                            <?php if (isAdmin()): ?>
                                <span class="badge bg-danger ms-1">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-primary ms-1">Manager</span>
                            <?php endif; ?>
                        <?php else: ?>
                            Public Access
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </footer>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js" defer></script>
    
    <?php if (isset($additional_scripts)): ?>
        <?php echo $additional_scripts; ?>
    <?php endif; ?>
</body>
</html>