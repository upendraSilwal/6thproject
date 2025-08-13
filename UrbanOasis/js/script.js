/**
 * Urban Oasis - Enhanced Main JavaScript File
 * ES6+ Features with improved performance and maintainability
 */

class UrbanOasisApp {
    constructor() {
        this.init();
    }

    init() {
        this.setupScrollOptimization();
        this.setupPhoneVerification();
        this.setupSmoothScrolling();
        this.setupAnimations();
        this.setupNavbarEffects();
        this.setupPropertySearch();
        this.setupLocationFilters();
        this.setupFormValidation();
        this.setupPropertyCards();
        this.setupMobileMenu();
        this.setupLoadingIndicators();
        this.setupTooltips();
        this.setupLazyLoading();
    }

    // Phone verification with enhanced UX
    setupPhoneVerification() {
        const codeInput = document.getElementById('verification_code');
        if (codeInput) {
            codeInput.focus();
            
            // Auto-format input (add spaces for readability)
            codeInput.addEventListener('input', (e) => {
                const value = e.target.value.replace(/\s/g, '');
                const formatted = value.replace(/(\d{3})(\d{3})/, '$1 $2');
                e.target.value = formatted;
            });

            const form = codeInput.form;
            if (form) {
                form.addEventListener('submit', this.handleVerificationSubmit.bind(this));
            }
        }
    }

    handleVerificationSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const codeInput = form.querySelector('#verification_code');
        const enteredCode = codeInput.value.replace(/\s/g, '');
        
        // Show loading state
        this.showFormLoading(form);
        
        // Simulate server validation (replace with actual AJAX call)
        setTimeout(() => {
            this.hideFormLoading(form);
            // Replace with actual server response handling
            form.submit();
        }, 1000);
    }

    // Optimize scroll performance
    setupScrollOptimization() {
        document.body.style.overflowX = 'hidden';
        document.body.style.overflowY = 'auto';
        document.documentElement.style.overflowX = 'hidden';
        document.documentElement.style.overflowY = 'auto';
        
        // Fix for mobile webkit scrolling issues
        if (/iPad|iPhone|iPod/.test(navigator.userAgent) || /Android/.test(navigator.userAgent)) {
            document.body.style.webkitOverflowScrolling = 'touch';
        }
    }

    // Smooth scrolling for navigation links
    setupSmoothScrolling() {
        const navLinks = document.querySelectorAll('a[href^="#"]');
        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = link.getAttribute('href');
                const targetSection = document.querySelector(targetId);
                if (targetSection) {
                    targetSection.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    // Setup intersection observer animations
    setupAnimations() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                    observer.unobserve(entry.target); // Stop observing once animated
                }
            });
        }, observerOptions);

        const animatedElements = document.querySelectorAll('.card, .location-card, .display-5');
        animatedElements.forEach(el => observer.observe(el));
    }

    // Navbar effects on scroll
    setupNavbarEffects() {
        const navbar = document.querySelector('.navbar');
        let ticking = false;

        const updateNavbar = () => {
            if (window.scrollY > 50) {
                navbar.classList.add('bg-dark');
                navbar.classList.remove('bg-transparent');
            } else {
                navbar.classList.remove('bg-dark');
                navbar.classList.add('bg-transparent');
            }
            ticking = false;
        };

        const requestTick = () => {
            if (!ticking) {
                requestAnimationFrame(updateNavbar);
                ticking = true;
            }
        };

        window.addEventListener('scroll', requestTick, { passive: true });
    }

    // Property search functionality
    setupPropertySearch() {
        const searchForm = document.getElementById('propertySearchForm');
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(searchForm);
                const searchParams = new URLSearchParams(formData);
                window.location.href = `properties.php?${searchParams.toString()}`;
            });
        }
    }

    // Location filter functionality
    setupLocationFilters() {
        const locationButtons = document.querySelectorAll('[data-location]');
        locationButtons.forEach(button => {
            button.addEventListener('click', () => {
                const location = button.getAttribute('data-location');
                window.location.href = `properties.php?location=${encodeURIComponent(location)}`;
            });
        });
    }

    // Enhanced form validation
    setupFormValidation() {
        const forms = document.querySelectorAll('.needs-validation');
        forms.forEach(form => {
            form.addEventListener('submit', (event) => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                    this.showValidationErrors(form);
                }
                form.classList.add('was-validated');
            });
        });

        // Real-time validation
        const inputs = document.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('blur', () => {
                if (input.checkValidity()) {
                    input.classList.remove('is-invalid');
                    input.classList.add('is-valid');
                } else {
                    input.classList.remove('is-valid');
                    input.classList.add('is-invalid');
                }
            });
        });
    }

    // Property card interactions
    setupPropertyCards() {
        const propertyCards = document.querySelectorAll('.property-card');
        
        // Use event delegation for better performance
        document.addEventListener('mouseenter', (e) => {
            if (e.target.closest('.property-card')) {
                e.target.closest('.property-card').style.transform = 'translateY(-5px)';
            }
        }, true);

        document.addEventListener('mouseleave', (e) => {
            if (e.target.closest('.property-card')) {
                e.target.closest('.property-card').style.transform = 'translateY(0)';
            }
        }, true);
    }

    // Mobile menu functionality
    setupMobileMenu() {
        const navbarToggler = document.querySelector('.navbar-toggler');
        const navbarCollapse = document.querySelector('.navbar-collapse');
        
        if (navbarToggler && navbarCollapse) {
            navbarToggler.addEventListener('click', () => {
                navbarCollapse.classList.toggle('show');
            });

            // Close mobile menu when clicking on a link
            const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    navbarCollapse.classList.remove('show');
                });
            });

            // Close menu when clicking outside
            document.addEventListener('click', (e) => {
                if (!navbarToggler.contains(e.target) && !navbarCollapse.contains(e.target)) {
                    navbarCollapse.classList.remove('show');
                }
            });
        }
    }

    // Loading indicators
    setupLoadingIndicators() {
        // Global loading functions
        window.showLoading = this.showLoading.bind(this);
        window.hideLoading = this.hideLoading.bind(this);
        
        // Auto-show loading for form submissions
        document.addEventListener('submit', (e) => {
            if (e.target.tagName.toLowerCase() === 'form') {
                setTimeout(() => this.showFormLoading(e.target), 100);
            }
        });
    }

    setupTooltips() {
        // Initialize Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
        
        // Initialize Bootstrap popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));
    }

    setupLazyLoading() {
        const images = document.querySelectorAll('img[data-src]');
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        images.forEach(img => imageObserver.observe(img));
    }

    // Helper methods
    showLoading() {
        if (document.querySelector('.loading-overlay')) return;
        
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'loading-overlay';
        loadingDiv.innerHTML = `
            <div class="d-flex flex-column align-items-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-white">Please wait...</p>
            </div>
        `;
        document.body.appendChild(loadingDiv);
    }

    hideLoading() {
        const loadingDiv = document.querySelector('.loading-overlay');
        if (loadingDiv) {
            loadingDiv.style.opacity = '0';
            setTimeout(() => loadingDiv.remove(), 300);
        }
    }

    showFormLoading(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            const originalText = submitBtn.innerHTML;
            submitBtn.dataset.originalText = originalText;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
        }
    }

    hideFormLoading(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn && submitBtn.dataset.originalText) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = submitBtn.dataset.originalText;
        }
    }

    showValidationErrors(form) {
        const firstInvalidField = form.querySelector('.is-invalid, :invalid');
        if (firstInvalidField) {
            firstInvalidField.focus();
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    // Utility methods
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
}

// Initialize the app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new UrbanOasisApp();

    // Additional legacy support code

    // Ensure proper scrolling on all devices
    document.body.style.overflowX = 'hidden';
    document.body.style.overflowY = 'auto';
    document.documentElement.style.overflowX = 'hidden';
    document.documentElement.style.overflowY = 'auto';
    
    // Fix for mobile webkit scrolling issues
    if (/iPad|iPhone|iPod/.test(navigator.userAgent) || /Android/.test(navigator.userAgent)) {
        document.body.style.webkitOverflowScrolling = 'touch';
    }
    // Smooth scrolling for navigation links
    const navLinks = document.querySelectorAll('a[href^="#"]');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetSection = document.querySelector(targetId);
            if (targetSection) {
                targetSection.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
    

    // Add fade-in animation to elements when they come into view
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
            }
        });
    }, observerOptions);

    // Observe all cards and sections
    const animatedElements = document.querySelectorAll('.card, .location-card, .display-5');
    animatedElements.forEach(el => observer.observe(el));

    // Navbar background change on scroll
    const navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar.classList.add('bg-dark');
            navbar.classList.remove('bg-transparent');
        } else {
            navbar.classList.remove('bg-dark');
            navbar.classList.add('bg-transparent');
        }
    });

    // Property search functionality (for future use)
    function initializePropertySearch() {
        const searchForm = document.getElementById('propertySearchForm');
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const searchParams = new URLSearchParams(formData);
                window.location.href = `properties.php?${searchParams.toString()}`;
            });
        }
    }

    // Initialize property search
    initializePropertySearch();

    // Location filter functionality
    function initializeLocationFilter() {
        const locationButtons = document.querySelectorAll('[data-location]');
        locationButtons.forEach(button => {
            button.addEventListener('click', function() {
                const location = this.getAttribute('data-location');
                window.location.href = `properties.php?location=${location}`;
            });
        });
    }

    // Initialize location filter
    initializeLocationFilter();

    // Form validation
    function initializeFormValidation() {
        const forms = document.querySelectorAll('.needs-validation');
        forms.forEach(form => {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
    }

    // Initialize form validation
    initializeFormValidation();

    // Property card hover effects
    function initializePropertyCards() {
        const propertyCards = document.querySelectorAll('.property-card');
        propertyCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    }

    // Initialize property cards
    initializePropertyCards();

    // Mobile menu toggle
    function initializeMobileMenu() {
        const navbarToggler = document.querySelector('.navbar-toggler');
        const navbarCollapse = document.querySelector('.navbar-collapse');
        
        if (navbarToggler && navbarCollapse) {
            navbarToggler.addEventListener('click', function() {
                navbarCollapse.classList.toggle('show');
            });

            // Close mobile menu when clicking on a link
            const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    navbarCollapse.classList.remove('show');
                });
            });
        }
    }

    // Initialize mobile menu
    initializeMobileMenu();

    // Loading animation
    function showLoading() {
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'loading-overlay';
        loadingDiv.innerHTML = `
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        `;
        document.body.appendChild(loadingDiv);
    }

    function hideLoading() {
        const loadingDiv = document.querySelector('.loading-overlay');
        if (loadingDiv) {
            loadingDiv.remove();
        }
    }

    // Global loading functions
    window.showLoading = showLoading;
    window.hideLoading = hideLoading;

    // Utility functions
    function formatPrice(price) {
        return new Intl.NumberFormat('en-NP', {
            style: 'currency',
            currency: 'NPR'
        }).format(price);
    }

    function formatDate(date) {
        return new Date(date).toLocaleDateString('en-NP');
    }

    // Global utility functions
    window.formatPrice = formatPrice;
    window.formatDate = formatDate;

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    const popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Centralized feature preview logic for property forms
    window.updateFeaturePreview = function({featureInputSelector = 'input[name="features[]"]', previewContainerId = 'preview-categories', previewSectionId = 'feature-preview'} = {}) {
        const featureInputs = document.querySelectorAll(featureInputSelector);
        const features = Array.from(featureInputs).map(input => input.value.trim()).filter(f => f);
        const previewSection = document.getElementById(previewSectionId);
        if (!previewSection) return;
        if (features.length === 0) {
            previewSection.style.display = 'none';
            return;
        }
        // Centralized categories (should match PHP)
        const categories = {
            'Essential Amenities': [],
            'Comfort Features': [],
            'Luxury Amenities': [],
            'Location Benefits': [],
            'Safety Features': [],
            'Other': []
        };
        features.forEach(feature => {
            const lowerFeature = feature.toLowerCase();
            if ([
                'wifi', 'internet', 'security', 'parking', 'water supply', 'electricity', '24/7 security'
            ].some(keyword => lowerFeature.includes(keyword))) {
                categories['Essential Amenities'].push(feature);
            } else if ([
                'air conditioning', 'heating', 'furnished', 'garden', 'balcony', 'terrace', 'fireplace'
            ].some(keyword => lowerFeature.includes(keyword))) {
                categories['Comfort Features'].push(feature);
            } else if ([
                'swimming pool', 'gym', 'spa', 'concierge', 'elevator', 'jacuzzi', 'sauna', 'tennis court'
            ].some(keyword => lowerFeature.includes(keyword))) {
                categories['Luxury Amenities'].push(feature);
            } else if ([
                'near', 'market', 'hospital', 'school', 'transport', 'restaurant', 'bank', 'post office'
            ].some(keyword => lowerFeature.includes(keyword))) {
                categories['Location Benefits'].push(feature);
            } else if ([
                'cctv', 'security guard', 'fire safety', 'emergency', 'alarm system', 'alarm'
            ].some(keyword => lowerFeature.includes(keyword))) {
                categories['Safety Features'].push(feature);
            } else {
                categories['Other'].push(feature);
            }
        });
        const previewContainer = document.getElementById(previewContainerId);
        if (!previewContainer) return;
        previewContainer.innerHTML = '';
        Object.entries(categories).forEach(([category, categoryFeatures]) => {
            if (categoryFeatures.length > 0) {
                const categoryDiv = document.createElement('div');
                categoryDiv.className = 'preview-category';
                categoryDiv.innerHTML = `
                    <h6 class="preview-category-title">${category}</h6>
                    <div class="preview-features">
                        ${categoryFeatures.map(f => `<span class="badge bg-primary me-1 mb-1">${f}</span>`).join('')}
                    </div>
                `;
                previewContainer.appendChild(categoryDiv);
            }
        });
        previewSection.style.display = 'block';
    };

    // Phone number validation for registration form
    const registerForm = document.querySelector('.register-card form');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const phoneInput = document.getElementById('phone');
            if (phoneInput && !/^\d{10}$/.test(phoneInput.value)) {
                phoneInput.setCustomValidity('Phone number must be exactly 10 digits.');
                phoneInput.reportValidity();
                e.preventDefault();
            } else if (phoneInput) {
                phoneInput.setCustomValidity('');
            }
        });
    }

    // Password strength checker
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const strengthDiv = document.getElementById('passwordStrength');
    
    if (passwordInput && strengthDiv) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            
            let strength = 0;
            let feedback = '';
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            if (strength < 2) {
                feedback = '<span class="strength-weak">Weak password</span>';
            } else if (strength < 4) {
                feedback = '<span class="strength-medium">Medium strength password</span>';
            } else {
                feedback = '<span class="strength-strong">Strong password</span>';
            }
            
            strengthDiv.innerHTML = feedback;
        });
    }
    
    // Password confirmation checker
    if (confirmPasswordInput && passwordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    }

    console.log('Urban Oasis website initialized successfully!');
}); 