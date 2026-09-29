<?php

use App\Http\Controllers\Api\V1\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\BroadcastController as AdminBroadcastController;
use App\Http\Controllers\Api\V1\Admin\FarmController as AdminFarmController;
use App\Http\Controllers\Api\V1\Admin\FarmerApprovalController;
use App\Http\Controllers\Api\V1\Admin\FarmerController as AdminFarmerController;
use App\Http\Controllers\Api\V1\Admin\FollowUpController;
use App\Http\Controllers\Api\V1\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\PractitionerController as AdminPractitionerController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\V1\Admin\SearchController as AdminSearchController;
use App\Http\Controllers\Api\V1\Admin\ServiceRequestController as AdminServiceRequestController;
use App\Http\Controllers\Api\V1\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Api\V1\Admin\VetConsultantRecordController as AdminVetConsultantRecordController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConsultantRecordController;
use App\Http\Controllers\Api\V1\FarmController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\Admin\PosController as AdminPosController;
use App\Http\Controllers\Api\V1\OtpController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\Practitioner\ServiceRequestController as PractitionerServiceRequestController;
use App\Http\Controllers\Api\V1\PrescriptionController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ServiceRequestController;
use App\Http\Controllers\Api\V1\StatusController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VetRecordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for version 1 of your
| application. These routes are prefixed with /api/v1 by default.
|
*/

// Public routes
Route::get('/status', StatusController::class)->name('status');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
Route::get('/products/{product}/reviews', [\App\Http\Controllers\Api\V1\ProductReviewController::class, 'index'])->name('products.reviews.index');

// Phone OTP verification (public or authenticated)
Route::post('/auth/otp/verify', [OtpController::class, 'verify'])->name('auth.otp.verify');
Route::post('/auth/otp/resend', [OtpController::class, 'resend'])->middleware('throttle:10,1')->name('auth.otp.resend');

// Protected routes (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/me', [AuthController::class, 'me'])->name('me');
    Route::patch('/me', [AuthController::class, 'updateProfile'])->name('me.update');
    Route::post('/me/password', [AuthController::class, 'updatePassword'])->name('me.password');
    Route::post('/me/photo', [AuthController::class, 'updatePhoto'])->name('me.photo');
    Route::post('/me/avatar', [AuthController::class, 'updateAvatar'])->name('me.avatar');
    Route::get('/user', [UserController::class, 'show'])->name('user');
    Route::apiResource('farms', FarmController::class);
    Route::post('/farms/{farm}/image', [FarmController::class, 'uploadImage'])->name('farms.image');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('phone.verified')->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/invoice', [InvoiceController::class, 'farmerInvoice'])->name('orders.invoice');

    // Product Reviews (Farmers after delivery)
    Route::post('/products/{product}/reviews', [\App\Http\Controllers\Api\V1\ProductReviewController::class, 'store'])->name('products.reviews.store');

    // Prescriptions (PDF generation)
    Route::get('/prescriptions/{prescription}/pdf', [PrescriptionController::class, 'downloadPdf'])->name('prescriptions.pdf');

    // Visit Reports (Unified PDF generation)
    Route::get('/service-requests/{serviceRequest}/visit-report', [\App\Http\Controllers\Api\V1\VisitReportController::class, 'downloadForServiceRequest'])->name('service-requests.visit-report');
    Route::get('/vet-records/{vetRecord}/visit-report', [\App\Http\Controllers\Api\V1\VisitReportController::class, 'downloadForVetRecord'])->name('vet-records.visit-report');
    Route::get('/consultant-records/{consultantRecord}/visit-report', [\App\Http\Controllers\Api\V1\VisitReportController::class, 'downloadForConsultantRecord'])->name('consultant-records.visit-report');
    Route::post('/visit-photos/upload', [\App\Http\Controllers\Api\V1\VisitPhotoController::class, 'upload'])->name('visit-photos.upload');

    // Vet Records
    Route::get('/farms/{farm}/vet-records', [VetRecordController::class, 'index'])->name('farms.vet-records.index');
    Route::post('/farms/{farm}/vet-records', [VetRecordController::class, 'store'])->name('farms.vet-records.store');

    // Consultant Records
    Route::get('/farms/{farm}/consultant-records', [ConsultantRecordController::class, 'index'])->name('farms.consultant-records.index');
    Route::post('/farms/{farm}/consultant-records', [ConsultantRecordController::class, 'store'])->name('farms.consultant-records.store');

    // Service Requests (Farmer & Practitioner)
    Route::post('/farms/{farm}/service-requests', [ServiceRequestController::class, 'store'])->middleware('phone.verified')->name('farms.service-requests.store');
    Route::get('/service-requests', [ServiceRequestController::class, 'index'])->name('service-requests.index');
    Route::get('/service-requests/{serviceRequest}', [ServiceRequestController::class, 'show'])->name('service-requests.farmer.show');
    Route::get('/service-requests/{serviceRequest}/previous-visits', [ServiceRequestController::class, 'previousVisits'])->name('service-requests.previous-visits');
    Route::post('/service-requests/{serviceRequest}/feedback', [ServiceRequestController::class, 'feedback'])->name('service-requests.feedback');

    // Practitioner Portal (Vet / Consultant)
    Route::get('/my/dashboard', [\App\Http\Controllers\Api\V1\Practitioner\PractitionerPortalController::class, 'dashboard'])->name('my.dashboard');
    Route::get('/my/service-requests', [PractitionerServiceRequestController::class, 'myRequests'])->name('my.service-requests');
    Route::get('/my/history', [\App\Http\Controllers\Api\V1\Practitioner\PractitionerPortalController::class, 'history'])->name('my.history');

    // Notifications (Farmer / Authenticated User)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::get('/notifications/count', [NotificationController::class, 'unreadCount'])->name('notifications.count');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');

    // DEO Portal Dashboard
    Route::middleware('role:admin|data_entry_operator')->get('/deo/dashboard', [\App\Http\Controllers\Api\V1\Deo\DeoPortalController::class, 'dashboard'])->name('deo.dashboard');

    // Admin & Staff routes (Shared between Admin and Data Entry Operator)
    Route::middleware('role:admin|data_entry_operator')->prefix('admin')->as('admin.')->group(function () {
        // Farms Directory & Management
        Route::get('/farms', [AdminFarmController::class, 'index'])->name('farms.index');
        Route::post('/farms', [AdminFarmController::class, 'store'])->name('farms.store');
        Route::get('/farms/{farm}', [AdminFarmController::class, 'show'])->name('farms.show');
        Route::post('/farms/{farm}/image', [AdminFarmController::class, 'uploadImage'])->name('farms.image');

        // Farmers Directory & Management
        Route::get('/farmers', [AdminFarmerController::class, 'index'])->name('farmers.index');
        Route::post('/farmers', [AdminFarmerController::class, 'store'])->name('farmers.store');
        Route::get('/farmers/{user}', [AdminFarmerController::class, 'show'])->name('farmers.show');

        // Products Catalog (Read-Only for POS and Browsing)
        Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product}', [AdminProductController::class, 'show'])->name('products.show');

        // Point of Sale / Assisted Sale
        Route::post('/pos/sale', [AdminPosController::class, 'store'])->name('pos.sale');

        // Orders (Read-Only list and invoices for DEO)
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::get('/orders/{order}/invoice', [InvoiceController::class, 'adminInvoice'])->name('orders.invoice');

        // Notifications
        Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/unread-count', [AdminNotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::get('/notifications/count', [AdminNotificationController::class, 'unreadCount'])->name('notifications.count');
        Route::patch('/notifications/{id}/read', [AdminNotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications/mark-all-read', [AdminNotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');

        // Global Search
        Route::get('/search', [AdminSearchController::class, 'search'])->name('search');

        // ══════════════════════════════════════════════════════════════════
        // STRICTLY ADMIN-ONLY ROUTES (DEO Blocked Server-Side via role:admin)
        // ══════════════════════════════════════════════════════════════════
        Route::middleware('role:admin')->group(function () {
            // Product Catalog Mutations & Stock Adjustments
            Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
            Route::post('/products/editor-image', [AdminProductController::class, 'uploadEditorImage'])->name('products.editor-image');
            Route::patch('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
            Route::post('/products/{product}/image', [AdminProductController::class, 'uploadImage'])->name('products.image');
            Route::post('/products/{product}/images', [AdminProductController::class, 'uploadGalleryImage'])->name('products.images.upload');
            Route::patch('/products/{product}/images/reorder', [AdminProductController::class, 'reorderGalleryImages'])->name('products.images.reorder');
            Route::patch('/products/{product}/images/{image}', [AdminProductController::class, 'updateGalleryImage'])->name('products.images.update');
            Route::delete('/products/{product}/images/{image}', [AdminProductController::class, 'deleteGalleryImage'])->name('products.images.delete');
            Route::post('/products/{product}/documents', [AdminProductController::class, 'uploadDocument'])->name('products.documents.upload');
            Route::delete('/products/{product}/documents/{document}', [AdminProductController::class, 'deleteDocument'])->name('products.documents.delete');
            Route::patch('/products/{product}/stock', [AdminProductController::class, 'adjustStock'])->name('products.adjust-stock');
            Route::patch('/products/{product}/status', [AdminProductController::class, 'updateStatus'])->name('products.update-status');
            Route::patch('/products/{product}/featured', [AdminProductController::class, 'toggleFeatured'])->name('products.featured');
            Route::post('/products/{product}/duplicate', [AdminProductController::class, 'duplicate'])->name('products.duplicate');

            // Order Status & Line Items Editing
            Route::put('/orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
            Route::put('/orders/{order}/items', [AdminOrderController::class, 'update'])->name('orders.items.update');
            Route::patch('/orders/{order}/items', [AdminOrderController::class, 'update'])->name('orders.items.patch');
            Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.update-status');

            // Staff Management
            Route::get('/staff', [AdminStaffController::class, 'index'])->name('staff.index');
            Route::post('/staff', [AdminStaffController::class, 'store'])->name('staff.store');
            Route::get('/staff/{staff}', [AdminStaffController::class, 'show'])->name('staff.show');
            Route::patch('/staff/{staff}', [AdminStaffController::class, 'update'])->name('staff.update');
            Route::patch('/staff/{staff}/status', [AdminStaffController::class, 'updateStatus'])->name('staff.update-status');
            Route::delete('/staff/{staff}', [AdminStaffController::class, 'destroy'])->name('staff.destroy');
            Route::post('/users/{user}/avatar', [AdminStaffController::class, 'updateAvatar'])->name('users.avatar');

            // Audit Log
            Route::get('/audit-log', [AdminAuditLogController::class, 'index'])->name('audit-log.index');
            Route::get('/audit-log/recent', [AdminAuditLogController::class, 'recent'])->name('audit-log.recent');

            // Vet & Consultant Records
            Route::get('/vet-consultant-records', [AdminVetConsultantRecordController::class, 'index'])->name('vet-consultant-records.index');

            // Practitioners (Profiles & Histories)
            Route::get('/practitioners/{id}', [AdminPractitionerController::class, 'show'])->name('practitioners.show');

            // Service Requests
            Route::get('/service-requests', [AdminServiceRequestController::class, 'index'])->name('service-requests.index');
            Route::post('/service-requests', [AdminServiceRequestController::class, 'store'])->name('service-requests.store');
            Route::get('/service-requests/metrics', [AdminServiceRequestController::class, 'metrics'])->name('service-requests.metrics');
            Route::get('/service-requests/practitioners', [AdminServiceRequestController::class, 'practitioners'])->name('service-requests.practitioners');
            Route::get('/service-requests/{serviceRequest}', [AdminServiceRequestController::class, 'show'])->name('service-requests.show');
            Route::patch('/service-requests/{serviceRequest}/assign', [AdminServiceRequestController::class, 'assign'])->name('service-requests.assign');

            // Reports & CSV Exports
            Route::get('/reports/sales', [AdminReportController::class, 'sales'])->name('reports.sales');
            Route::get('/reports/sales/export', [AdminReportController::class, 'exportSales'])->name('reports.sales.export');
            Route::get('/reports/farmers', [AdminReportController::class, 'farmers'])->name('reports.farmers');
            Route::get('/reports/farmers/export', [AdminReportController::class, 'exportFarmers'])->name('reports.farmers.export');
            Route::get('/reports/top-farmers', [AdminReportController::class, 'topFarmers'])->name('reports.top-farmers');
            Route::get('/follow-ups/overdue', [FollowUpController::class, 'overdue'])->name('follow-ups.overdue');

            // Farmer Approvals Queue
            Route::get('/farmer-approvals', [FarmerApprovalController::class, 'index'])->name('farmer-approvals.index');
            Route::get('/farmer-approvals/count', [FarmerApprovalController::class, 'count'])->name('farmer-approvals.count');
            Route::patch('/farmer-approvals/{id}/approve', [FarmerApprovalController::class, 'approve'])->name('farmer-approvals.approve');
            Route::patch('/farmer-approvals/{id}/reject', [FarmerApprovalController::class, 'reject'])->name('farmer-approvals.reject');

            // Broadcast Messages
            Route::get('/broadcasts', [AdminBroadcastController::class, 'index'])->name('broadcasts.index');
            Route::post('/broadcasts', [AdminBroadcastController::class, 'store'])->name('broadcasts.store');
        });
    });
});
