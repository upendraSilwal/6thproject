<?php
session_start();
require_once 'config/database.php';

$pageTitle = 'Pricing';
require_once 'includes/header.php';
?>

<main class="container py-5">
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="text-center mb-5">
        <h1 class="display-4 fw-bold mb-3">Simple Pricing</h1>
        <p class="lead text-muted mb-4">List your properties with ease. No hidden fees, no subscriptions.</p>
        <div class="pricing-info">
            <span class="badge bg-light text-dark fs-6 px-3 py-2">150 NPR = 1 Property Listing</span>
        </div>
    </div>

    <div class="row justify-content-center g-4">
        <!-- Single Listing -->
        <div class="col-lg-4 col-md-6">
            <div class="card pricing-card border-0 shadow-sm">
                <div class="card-body p-4 text-center">
                    <h3 class="fw-bold mb-3">Single Listing</h3>
                    <div class="price mb-3">
                        <span class="display-4 fw-bold text-primary">150</span>
                        <span class="text-muted fs-5">NPR</span>
                    </div>
                    <div class="savings mb-3">
                        <small class="text-muted">&nbsp;</small>
                    </div>
                    <ul class="list-unstyled mb-4">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>1 Property Listing</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>30 Days Active</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Upload Photos</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Edit Anytime</li>
                    </ul>
                    <a href="mock_esewa.php?package=single&amount=150" class="btn btn-outline-primary w-100">List Now</a>
                </div>
            </div>
        </div>

        <!-- 5 Listings Bundle -->
        <div class="col-lg-4 col-md-6">
            <div class="card pricing-card border-0 shadow-sm">
                <div class="card-body p-4 text-center">
                    <h3 class="fw-bold mb-3">5 Listings Bundle</h3>
                    <div class="price mb-3">
                        <span class="display-4 fw-bold text-primary">600</span>
                        <span class="text-muted fs-5">NPR</span>
                    </div>
                    <div class="savings mb-3">
                        <small class="text-success fw-semibold">Save 150 NPR (20% off)</small>
                    </div>
                    <ul class="list-unstyled mb-4">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>5 Property Listings</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>30 Days Active Each</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Upload Photos</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Edit Anytime</li>
                    </ul>
                    <a href="mock_esewa.php?package=bundle5&amount=600" class="btn btn-outline-primary w-100">Get Bundle</a>
                </div>
            </div>
        </div>

        <!-- 10 Listings Bundle -->
        <div class="col-lg-4 col-md-6">
            <div class="card pricing-card border-0 shadow-sm">
                <div class="card-body p-4 text-center">
                    <h3 class="fw-bold mb-3">10 Listings Bundle</h3>
                    <div class="price mb-3">
                        <span class="display-4 fw-bold text-primary">1050</span>
                        <span class="text-muted fs-5">NPR</span>
                    </div>
                    <div class="savings mb-3">
                        <small class="text-success fw-semibold">Save 450 NPR (30% off)</small>
                    </div>
                    <ul class="list-unstyled mb-4">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>10 Property Listings</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>30 Days Active Each</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Upload Photos</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Edit Anytime</li>
                    </ul>
                    <a href="mock_esewa.php?package=bundle10&amount=1050" class="btn btn-outline-primary w-100">Get Bundle</a>
                </div>
            </div>
        </div>
    </div>

    <!-- FAQ Section -->
    <div class="row justify-content-center mt-5">
        <div class="col-lg-8">
            <div class="text-center mb-4">
                <h3 class="fw-bold">Frequently Asked Questions</h3>
            </div>
            <div class="accordion" id="pricingFAQ">
                <div class="accordion-item border-0 mb-3 shadow-sm">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            How long do my listings stay active?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#pricingFAQ">
                        <div class="accordion-body">
                            Each property listing stays active for 30 days from the date of posting. You can renew or repost anytime.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item border-0 mb-3 shadow-sm">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            Can I edit my listings after posting?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#pricingFAQ">
                        <div class="accordion-body">
                            Yes! You can edit your property details, update photos, and modify pricing anytime during the active period.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item border-0 mb-3 shadow-sm">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            What payment methods do you accept?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#pricingFAQ">
                        <div class="accordion-body">
                            We accept payments through eSewa, Khalti, and major credit/debit cards for your convenience.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.pricing-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border-radius: 15px;
    overflow: hidden;
    height: 100%;
}

.pricing-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.pricing-card.popular {
    position: relative;
    border: 2px solid #0d6efd;
    transform: scale(1.05);
}

.pricing-card.popular:hover {
    transform: scale(1.05) translateY(-5px);
}

.popular-badge {
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10;
}

.popular-badge .badge {
    padding: 8px 20px;
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 20px;
}

.pricing-info .badge {
    border-radius: 25px;
    padding: 10px 20px;
    font-weight: 600;
    border: 2px solid #dee2e6;
}

.price {
    margin: 2rem 0;
}

.price .display-4 {
    font-weight: 800;
    line-height: 1;
}

.savings {
    height: 20px;
}

.pricing-card ul {
    text-align: left;
    margin: 0 auto;
    max-width: 250px;
}

.pricing-card ul li {
    padding: 8px 0;
    font-size: 0.95rem;
    color: #495057;
}

.pricing-card .btn {
    border-radius: 10px;
    font-weight: 600;
    padding: 12px 24px;
    transition: all 0.3s ease;
    text-transform: none;
    font-size: 1rem;
}

.btn-primary {
    background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(13, 110, 253, 0.3);
}

.btn-outline-primary {
    border: 2px solid #0d6efd;
    color: #0d6efd;
    font-weight: 600;
}

.btn-outline-primary:hover {
    background: #0d6efd;
    border-color: #0d6efd;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(13, 110, 253, 0.3);
}

/* FAQ Styling */
.accordion-button {
    font-weight: 600;
    color: #495057;
    background: #f8f9fa;
    border: none;
    border-radius: 10px !important;
    padding: 1.25rem 1.5rem;
}

.accordion-button:not(.collapsed) {
    background: #e7f1ff;
    color: #0d6efd;
    border-color: #b6d7ff;
    box-shadow: none;
}

.accordion-button:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
    border-color: #86b7fe;
}

.accordion-body {
    padding: 1.5rem;
    background: #ffffff;
    color: #6c757d;
    border-radius: 0 0 10px 10px;
}

.accordion-item {
    border: none !important;
    border-radius: 10px;
    overflow: hidden;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .pricing-card.popular {
        transform: none;
        margin-top: 1rem;
    }
    
    .pricing-card.popular:hover {
        transform: translateY(-5px);
    }
    
    .display-4 {
        font-size: 2.5rem;
    }
    
    .popular-badge {
        top: -10px;
    }
}

/* Background gradient */
body {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    min-height: 100vh;
}

/* Icon colors */
.fas.fa-check {
    color: #28a745 !important;
}

.fas.fa-thumbs-up {
    color: #28a745 !important;
}

.fas.fa-crown {
    color: #ffc107 !important;
}
</style>

<?php require_once 'includes/footer.php'; ?>
