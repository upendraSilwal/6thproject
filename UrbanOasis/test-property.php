<?php
session_start();
$pageTitle = "Test Property Details";

// Simple HTML without includes to test
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Property Details - Urban Oasis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: white;
            color: black;
            padding-top: 20px;
        }
        .property-carousel {
            position: relative;
        }
        .main-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Property Details</h1>
        <div class="row">
            <div class="col-lg-8">
                <div class="property-images mb-4">
                    <div class="property-carousel">
                        <div class="main-image-container">
                            <img class="main-image" 
                                 src="https://images.unsplash.com/photo-1580587771525-78b9dba3b914?auto=format&fit=crop&w=800&q=80" 
                                 alt="Test Property">
                        </div>
                    </div>
                </div>
                
                <div class="property-info">
                    <h2>Test Property Title</h2>
                    <p>This is a test property description to verify the page is working correctly.</p>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Contact Owner</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-primary w-100">Send Inquiry</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
