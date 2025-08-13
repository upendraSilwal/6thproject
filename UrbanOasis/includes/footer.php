    </main>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-home me-2"></i>Urban Oasis</h5>
                    <p class="mb-0">Your trusted real estate partner in Nepal</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; 2025 Urban Oasis.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
    
    <?php if ($isLoggedIn): ?>
    <script>
        // Activity tracking for logged-in users
        function updateActivity() {
            fetch('update_activity.php', { 
                method: 'GET',
                credentials: 'same-origin'
            }).catch(error => {
                console.log('Activity ping failed:', error);
            });
        }
        
        // Send activity ping every 30 seconds
        setInterval(updateActivity, 30000);
        
        // Send initial ping
        updateActivity();
    </script>
    <?php endif; ?>
    
    <?php if (isset($additionalJS)) echo $additionalJS; ?>
</body>
</html>
