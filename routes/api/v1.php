<?php

use App\Http\Controllers\Api\V1\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\BroadcastController as AdminBroadcastController;
use App\Http\Controllers\Api\V1\Admin\FarmController as AdminFarmController;
use App\Http\Controllers\Api\V1\Admin\FarmerController as AdminFarmerController;
use App\Http\Controllers\Api\V1\Admin\FollowUpController;
use App\Http\Controllers\Api\V1\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\V1\Admin\PractitionerController as AdminPractitionerController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\V1\Admin\SearchController as AdminSearchController;
use App\Http\Controllers\Api\V1\Admin\ServiceRequestController as AdminServiceRequestController;
use App\Http\Controllers\Api\V1\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Api\V1\Admin\VetConsultantRecordController as AdminVetConsultantRecordController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CannedResponseController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ConsultantRecordController;
use App\Http\Controllers\Api\V1\FarmController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LeadInquiryController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\Admin\PosController as AdminPosController;
use App\Http\Controllers\Api\V1\OtpController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
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
Route::get('/weather', [\App\Http\Controllers\Api\V1\WeatherController::class, 'getWeather'])->name('weather.get');

// Phone OTP verification (public or authenticated)
Route::post('/auth/otp/verify', [OtpController::class, 'verify'])->name('auth.otp.verify');
Route::post('/auth/otp/resend', [OtpController::class, 'resend'])->middleware('throttle:10,1')->name('auth.otp.resend');

// Password Reset routes (Phone/Email OTP or Token Link)
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1')->name('auth.forgot-password');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('auth.reset-password');
Route::post('/auth/reset-password-with-token', [AuthController::class, 'resetPasswordWithToken'])->middleware('throttle:6,1')->name('auth.reset-password-with-token');

// Public guest lead inquiries (pre-registration capture)
Route::post('/lead-inquiries', [LeadInquiryController::class, 'store'])->middleware('throttle:15,1')->name('lead-inquiries.store');

// Public Operational Settings & Reference Taxonomies
Route::get('/settings/public', [\App\Http\Controllers\Api\V1\Admin\SettingController::class, 'publicSettings'])->name('settings.public');
Route::get('/taxonomies', [\App\Http\Controllers\Api\V1\TaxonomyController::class, 'index'])->name('taxonomies.index');
Route::get('/payment-methods', [\App\Http\Controllers\Api\V1\PaymentMethodController::class, 'index'])->name('payment-methods.index');

// SSLCommerz Payment Gateway Endpoints
Route::post('/payments/sslcommerz/ipn', [PaymentController::class, 'handleIpn'])->name('payments.sslcommerz.ipn');
Route::match(['get', 'post'], '/payments/sslcommerz/return', [PaymentController::class, 'handleReturn'])->name('payments.sslcommerz.return');
Route::get('/payments/sslcommerz/status/{orderId}', [PaymentController::class, 'checkStatus'])->name('payments.sslcommerz.status');

// Cascading Location Reference endpoints (Public, Cacheable)
Route::prefix('locations')->as('locations.')->group(function () {
    Route::get('/divisions', [LocationController::class, 'divisions'])->name('divisions');
    Route::get('/districts', [LocationController::class, 'districts'])->name('districts');
    Route::get('/upazilas', [LocationController::class, 'upazilas'])->name('upazilas');
    Route::get('/unions', [LocationController::class, 'unions'])->name('unions');
    Route::get('/pourashavas', [LocationController::class, 'pourashavas'])->name('pourashavas');
});

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
    Route::post('/farms/{farm}/images', [FarmController::class, 'uploadGalleryImages'])->name('farms.images.upload');
    Route::delete('/farms/{farm}/images/{image}', [FarmController::class, 'deleteGalleryImage'])->name('farms.images.delete');
    Route::patch('/farms/{farm}/images/{image}/primary', [FarmController::class, 'setPrimaryImage'])->name('farms.images.primary');
    Route::patch('/farms/{farm}/images/{image}', [FarmController::class, 'updateGalleryImage'])->name('farms.images.update');

    // Orders & Checkout
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders', [OrderController::class, 'store'])->middleware(['phone.verified', 'throttle:15,1'])->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/invoice', [InvoiceController::class, 'farmerInvoice'])->name('orders.invoice');
    Route::post('/coupons/validate', [\App\Http\Controllers\Api\V1\CouponController::class, 'validateCoupon'])->name('coupons.validate');

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

    // Farm Production Cycles
    Route::get('/farms/{farm}/cycles', [\App\Http\Controllers\Api\V1\FarmCycleController::class, 'index'])->name('farms.cycles.index');
    Route::post('/farms/{farm}/cycles', [\App\Http\Controllers\Api\V1\FarmCycleController::class, 'store'])->name('farms.cycles.store');
    Route::put('/farms/{farm}/cycles/{cycle}', [\App\Http\Controllers\Api\V1\FarmCycleController::class, 'update'])->name('farms.cycles.update');

    // Farm Financial Ledger
    Route::get('/farms/{farm}/ledger', [\App\Http\Controllers\Api\V1\FarmLedgerController::class, 'index'])->name('farms.ledger.index');
    Route::post('/farms/{farm}/ledger', [\App\Http\Controllers\Api\V1\FarmLedgerController::class, 'store'])->name('farms.ledger.store');
    Route::get('/farms/{farm}/ledger/{entry}', [\App\Http\Controllers\Api\V1\FarmLedgerController::class, 'show'])->name('farms.ledger.show');
    Route::put('/farms/{farm}/ledger/{entry}', [\App\Http\Controllers\Api\V1\FarmLedgerController::class, 'update'])->name('farms.ledger.update');
    Route::post('/farms/{farm}/ledger/{entry}/void', [\App\Http\Controllers\Api\V1\FarmLedgerController::class, 'void'])->name('farms.ledger.void');

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
    Route::get('/my/follow-ups', [\App\Http\Controllers\Api\V1\Practitioner\PractitionerFollowUpController::class, 'index'])->name('my.follow-ups.index');
    Route::get('/my/follow-ups/count', [\App\Http\Controllers\Api\V1\Practitioner\PractitionerFollowUpController::class, 'count'])->name('my.follow-ups.count');
    Route::post('/my/follow-ups/{type}/{id}/reschedule', [\App\Http\Controllers\Api\V1\Practitioner\PractitionerFollowUpController::class, 'reschedule'])->name('my.follow-ups.reschedule');

    // Notifications (Farmer / Authenticated User)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::get('/notifications/count', [NotificationController::class, 'unreadCount'])->name('notifications.count');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');

    // Chat & Messaging (Farmer, Practitioner, Staff)
    Route::get('/chat/conversations', [ChatController::class, 'index'])->name('chat.conversations.index');
    Route::post('/chat/conversations', [ChatController::class, 'store'])->name('chat.conversations.store');
    Route::get('/chat/conversations/{id}', [ChatController::class, 'show'])->name('chat.conversations.show');
    Route::post('/chat/conversations/{id}/messages', [ChatController::class, 'sendMessage'])->name('chat.conversations.messages.send');
    Route::post('/chat/conversations/{id}/typing', [ChatController::class, 'typing'])->name('chat.conversations.typing');
    Route::patch('/chat/conversations/{id}/read', [ChatController::class, 'markAsRead'])->name('chat.conversations.read');
    Route::patch('/chat/conversations/{id}/close', [ChatController::class, 'close'])->name('chat.conversations.close');
    Route::patch('/chat/conversations/{id}/reopen', [ChatController::class, 'reopen'])->name('chat.conversations.reopen');
    Route::get('/chat/unread-count', [ChatController::class, 'unreadCount'])->name('chat.unread-count');
    Route::get('/chat/canned-responses', [CannedResponseController::class, 'index'])->name('chat.canned-responses.index');

    // WebSocket Broadcasting Auth via Sanctum Bearer token
    \Illuminate\Support\Facades\Broadcast::routes(['middleware' => ['auth:sanctum']]);

    // DEO Portal Dashboard
    Route::middleware('role:admin|data_entry_operator')->get('/deo/dashboard', [\App\Http\Controllers\Api\V1\Deo\DeoPortalController::class, 'dashboard'])->name('deo.dashboard');

    // Admin & Staff routes (Shared between Admin and Data Entry Operator)
    Route::middleware('role:admin|data_entry_operator')->prefix('admin')->as('admin.')->group(function () {
        // Shared Support Inbox
        Route::get('/support-inbox', [ChatController::class, 'index'])->defaults('inbox', true)->name('support-inbox.index');
        // Farms Directory & Management
        Route::get('/farms', [AdminFarmController::class, 'index'])->name('farms.index');
        Route::get('/farms-unmatched-locations', [AdminFarmController::class, 'unmatchedLocations'])->name('farms.unmatched-locations');
        Route::post('/farms', [AdminFarmController::class, 'store'])->name('farms.store');
        Route::get('/farms/{farm}', [AdminFarmController::class, 'show'])->name('farms.show');
        Route::match(['put', 'patch'], '/farms/{farm}', [AdminFarmController::class, 'update'])->name('farms.update');
        Route::patch('/farms/{farm}/location', [AdminFarmController::class, 'updateLocation'])->name('farms.update-location');
        Route::post('/farms/{farm}/image', [AdminFarmController::class, 'uploadImage'])->name('farms.image');
        Route::post('/farms/{farm}/images', [AdminFarmController::class, 'uploadGalleryImages'])->name('farms.images.upload');
        Route::delete('/farms/{farm}/images/{image}', [AdminFarmController::class, 'deleteGalleryImage'])->name('farms.images.delete');
        Route::patch('/farms/{farm}/images/{image}/primary', [AdminFarmController::class, 'setPrimaryImage'])->name('farms.images.primary');
        Route::patch('/farms/{farm}/images/{image}', [AdminFarmController::class, 'updateGalleryImage'])->name('farms.images.update');

        // Farmers Directory & Management
        Route::get('/farmers', [AdminFarmerController::class, 'index'])->name('farmers.index');
        Route::post('/farmers', [AdminFarmerController::class, 'store'])->name('farmers.store');
        Route::get('/farmers/{user}', [AdminFarmerController::class, 'show'])->name('farmers.show');
        Route::match(['put', 'patch'], '/farmers/{user}', [AdminFarmerController::class, 'update'])->name('farmers.update');
        Route::patch('/farmers/{user}/restriction', [AdminFarmerController::class, 'updateRestriction'])->name('farmers.restriction');
        Route::post('/farmers/{user}/avatar', [AdminFarmerController::class, 'uploadAvatar'])->name('farmers.avatar');
        Route::delete('/farmers/{user}/avatar', [AdminFarmerController::class, 'removeAvatar'])->name('farmers.avatar.delete');

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
            Route::patch('/orders/{order}/delivery', [AdminOrderController::class, 'updateDelivery'])->name('orders.update-delivery');
            Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.update-status');
            Route::post('/orders/{order}/refund', [AdminOrderController::class, 'refund'])->name('orders.refund');
            Route::post('/orders/{order}/check-gateway-status', [AdminOrderController::class, 'checkGatewayStatus'])->name('orders.check-gateway-status');
            Route::post('/orders/{order}/mark-as-paid', [AdminOrderController::class, 'markAsPaid'])->name('orders.mark-as-paid');
            Route::post('/orders/{order}/verify-fraud', [AdminOrderController::class, 'verifyFraudStatus'])->name('orders.verify-fraud');

            // Payments & Refunds Management
            Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
            Route::get('/payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
            Route::post('/payments/{payment}/mark-as-paid', [AdminPaymentController::class, 'markAsPaid'])->name('payments.mark-as-paid');
            Route::post('/payments/{payment}/refund', [AdminPaymentController::class, 'refund'])->name('payments.refund');

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
            Route::patch('/vet-consultant-records/{type}/{id}/follow-up', [AdminVetConsultantRecordController::class, 'updateFollowUp'])->name('vet-consultant-records.follow-up');

            // Practitioners (Profiles & Histories)
            Route::get('/practitioners/{id}', [AdminPractitionerController::class, 'show'])->name('practitioners.show');

            // Service Requests
            Route::get('/service-requests', [AdminServiceRequestController::class, 'index'])->name('service-requests.index');
            Route::post('/service-requests', [AdminServiceRequestController::class, 'store'])->name('service-requests.store');
            Route::get('/service-requests/metrics', [AdminServiceRequestController::class, 'metrics'])->name('service-requests.metrics');
            Route::get('/service-requests/practitioners', [AdminServiceRequestController::class, 'practitioners'])->name('service-requests.practitioners');
            Route::get('/service-requests/{serviceRequest}', [AdminServiceRequestController::class, 'show'])->name('service-requests.show');
            Route::patch('/service-requests/{serviceRequest}/assign', [AdminServiceRequestController::class, 'assign'])->name('service-requests.assign');
            Route::patch('/service-requests/{serviceRequest}/follow-up', [AdminServiceRequestController::class, 'updateFollowUp'])->name('service-requests.follow-up');

            // Reports & CSV Exports
            Route::get('/reports/sales', [AdminReportController::class, 'sales'])->name('reports.sales');
            Route::get('/reports/sales/export', [AdminReportController::class, 'exportSales'])->name('reports.sales.export');
            Route::get('/reports/farmers', [AdminReportController::class, 'farmers'])->name('reports.farmers');
            Route::get('/reports/farmers/export', [AdminReportController::class, 'exportFarmers'])->name('reports.farmers.export');
            Route::get('/reports/top-farmers', [AdminReportController::class, 'topFarmers'])->name('reports.top-farmers');
            Route::get('/follow-ups/overdue', [FollowUpController::class, 'overdue'])->name('follow-ups.overdue');


            // Reschedule Requests Approval Queue
            Route::get('/reschedule-requests', [\App\Http\Controllers\Api\V1\Admin\RescheduleRequestController::class, 'index'])->name('reschedule-requests.index');
            Route::get('/reschedule-requests/count', [\App\Http\Controllers\Api\V1\Admin\RescheduleRequestController::class, 'count'])->name('reschedule-requests.count');
            Route::patch('/reschedule-requests/{id}/approve', [\App\Http\Controllers\Api\V1\Admin\RescheduleRequestController::class, 'approve'])->name('reschedule-requests.approve');
            Route::patch('/reschedule-requests/{id}/reject', [\App\Http\Controllers\Api\V1\Admin\RescheduleRequestController::class, 'reject'])->name('reschedule-requests.reject');

            // Broadcast Messages
            Route::get('/broadcasts', [AdminBroadcastController::class, 'index'])->name('broadcasts.index');
            Route::post('/broadcasts', [AdminBroadcastController::class, 'store'])->name('broadcasts.store');

            // Website Lead Inquiries
            Route::get('/lead-inquiries', [LeadInquiryController::class, 'index'])->name('lead-inquiries.index');
            Route::patch('/lead-inquiries/{id}/status', [LeadInquiryController::class, 'updateStatus'])->name('lead-inquiries.update-status');
            Route::delete('/lead-inquiries/{id}', [LeadInquiryController::class, 'destroy'])->name('lead-inquiries.destroy');

            // Canned Responses Management
            Route::post('/canned-responses', [CannedResponseController::class, 'store'])->name('canned-responses.store');
            Route::patch('/canned-responses/{id}', [CannedResponseController::class, 'update'])->name('canned-responses.update');
            Route::delete('/canned-responses/{id}', [CannedResponseController::class, 'destroy'])->name('canned-responses.destroy');

            // ══════════════════════════════════════════════════════════════════
            // SETTINGS, DELIVERY, COUPONS, TAXONOMIES & PAYMENT METHODS
            // ══════════════════════════════════════════════════════════════════
            Route::get('/settings', [\App\Http\Controllers\Api\V1\Admin\SettingController::class, 'index'])->name('settings.index');
            Route::put('/settings', [\App\Http\Controllers\Api\V1\Admin\SettingController::class, 'update'])->name('settings.update');
            Route::post('/settings/logo', [\App\Http\Controllers\Api\V1\Admin\SettingController::class, 'uploadLogo'])->name('settings.logo.upload');
            Route::delete('/settings/logo', [\App\Http\Controllers\Api\V1\Admin\SettingController::class, 'removeLogo'])->name('settings.logo.remove');

            // District Delivery Rates Overrides
            Route::get('/district-delivery-rates', [\App\Http\Controllers\Api\V1\Admin\DistrictDeliveryRateController::class, 'index'])->name('district-delivery-rates.index');
            Route::post('/district-delivery-rates', [\App\Http\Controllers\Api\V1\Admin\DistrictDeliveryRateController::class, 'store'])->name('district-delivery-rates.store');
            Route::put('/district-delivery-rates/{districtDeliveryRate}', [\App\Http\Controllers\Api\V1\Admin\DistrictDeliveryRateController::class, 'update'])->name('district-delivery-rates.update');
            Route::delete('/district-delivery-rates/{districtDeliveryRate}', [\App\Http\Controllers\Api\V1\Admin\DistrictDeliveryRateController::class, 'destroy'])->name('district-delivery-rates.destroy');

            // Coupons & Discounts
            Route::get('/coupons', [\App\Http\Controllers\Api\V1\Admin\CouponController::class, 'index'])->name('coupons.index');
            Route::post('/coupons', [\App\Http\Controllers\Api\V1\Admin\CouponController::class, 'store'])->name('coupons.store');
            Route::get('/coupons/{coupon}', [\App\Http\Controllers\Api\V1\Admin\CouponController::class, 'show'])->name('coupons.show');
            Route::put('/coupons/{coupon}', [\App\Http\Controllers\Api\V1\Admin\CouponController::class, 'update'])->name('coupons.update');
            Route::patch('/coupons/{coupon}/toggle', [\App\Http\Controllers\Api\V1\Admin\CouponController::class, 'toggleActive'])->name('coupons.toggle');

            // Taxonomies Lookup Tables
            Route::get('/taxonomies', [\App\Http\Controllers\Api\V1\Admin\TaxonomyController::class, 'index'])->name('taxonomies.admin-index');
            Route::post('/taxonomies', [\App\Http\Controllers\Api\V1\Admin\TaxonomyController::class, 'store'])->name('taxonomies.store');
            Route::put('/taxonomies/{taxonomy}', [\App\Http\Controllers\Api\V1\Admin\TaxonomyController::class, 'update'])->name('taxonomies.update');
            Route::patch('/taxonomies/{taxonomy}/toggle', [\App\Http\Controllers\Api\V1\Admin\TaxonomyController::class, 'toggleActive'])->name('taxonomies.toggle');
            Route::delete('/taxonomies/{taxonomy}', [\App\Http\Controllers\Api\V1\Admin\TaxonomyController::class, 'destroy'])->name('taxonomies.destroy');

            // Payment Methods Lookup Table
            Route::get('/payment-methods', [\App\Http\Controllers\Api\V1\Admin\PaymentMethodController::class, 'index'])->name('payment-methods.admin-index');
            Route::post('/payment-methods', [\App\Http\Controllers\Api\V1\Admin\PaymentMethodController::class, 'store'])->name('payment-methods.store');
            Route::put('/payment-methods/{paymentMethod}', [\App\Http\Controllers\Api\V1\Admin\PaymentMethodController::class, 'update'])->name('payment-methods.update');
            Route::patch('/payment-methods/{paymentMethod}/toggle', [\App\Http\Controllers\Api\V1\Admin\PaymentMethodController::class, 'toggleActive'])->name('payment-methods.toggle');
        });
    });
});
