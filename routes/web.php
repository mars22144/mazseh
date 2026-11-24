<?php

use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Admin\CarController as AdminCarController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Kasir\DashboardController as KasirDashboardController;
use App\Http\Controllers\Kasir\PaymentController as KasirPaymentController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SecureFileController;
use App\Http\Controllers\StaffSettingsController;
use Illuminate\Support\Facades\Route;

// Public Pages
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/cars', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/cars/{slug}', [CatalogController::class, 'show'])->name('catalog.show');

// Informative Pages
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'submitContact'])->name('contact.submit');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');

// AI Chatbot API
Route::post('/ai-chat', [AiChatController::class, 'sendMessage'])
    ->middleware('throttle:ai-chat')
    ->name('ai-chat.send');

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login-ip')
        ->name('login.post');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('register.post');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('forgot-password');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])
        ->middleware('throttle:forgot-password')
        ->name('forgot-password.post');
});

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Customer Checkout Flow
    Route::get('/checkout/{slug}', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/checkout/{slug}', [CheckoutController::class, 'store'])->name('checkout.store');

    // Customer Orders & Payment
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{id}/payment', [OrderController::class, 'payment'])->name('orders.payment');
    Route::post('/orders/{id}/payment', [OrderController::class, 'submitPayment'])->name('orders.payment.submit');
    Route::get('/orders/{id}/confirmed', [OrderController::class, 'confirmed'])->name('orders.confirmed');
    Route::get('/orders/{id}/receipt', [OrderController::class, 'receipt'])->name('orders.receipt');
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    // Customer Profile & Document Verification
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Berkas pribadi (KTP/SIM & bukti bayar) hanya untuk pemilik atau staf
    Route::get('/berkas/dokumen/{userId}/{type}', [SecureFileController::class, 'document'])
        ->whereNumber('userId')->whereIn('type', ['ktp', 'sim'])->name('files.document');
    Route::get('/berkas/pembayaran/{paymentId}', [SecureFileController::class, 'paymentProof'])
        ->whereNumber('paymentId')->name('files.payment');
});

// Pengaturan akun staf (kasir & admin)
Route::middleware(['auth', 'role:kasir,admin'])->prefix('pengaturan')->name('staff.settings.')->group(function () {
    Route::get('/', [StaffSettingsController::class, 'edit'])->name('edit');
    Route::put('/profil', [StaffSettingsController::class, 'updateProfile'])->name('profile');
    Route::put('/kata-sandi', [StaffSettingsController::class, 'updatePassword'])->middleware('throttle:6,1')->name('password');
    Route::delete('/sesi-lain', [StaffSettingsController::class, 'logoutOtherSessions'])->middleware('throttle:6,1')->name('sessions');
});

// Admin Panel
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/armada', [AdminCarController::class, 'index'])->name('cars.index');
    Route::get('/armada/baru', [AdminCarController::class, 'create'])->name('cars.create');
    Route::post('/armada', [AdminCarController::class, 'store'])->name('cars.store');
    Route::get('/armada/{id}/ubah', [AdminCarController::class, 'edit'])->whereNumber('id')->name('cars.edit');
    Route::put('/armada/{id}', [AdminCarController::class, 'update'])->whereNumber('id')->name('cars.update');
    Route::get('/pengguna', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('/pengguna/{id}/peran', [AdminUserController::class, 'updateRole'])->whereNumber('id')->name('users.role');
    Route::patch('/pengguna/{id}/dokumen', [AdminUserController::class, 'verifyDocument'])->whereNumber('id')->name('users.document');
});

// Kasir Panel (kasir & admin)
Route::middleware(['auth', 'role:kasir,admin'])->prefix('kasir')->name('kasir.')->group(function () {
    Route::get('/', [KasirDashboardController::class, 'index'])->name('dashboard');
    Route::get('/pembayaran', [KasirPaymentController::class, 'index'])->name('payments.index');
    Route::get('/pembayaran/{id}', [KasirPaymentController::class, 'show'])->whereNumber('id')->name('payments.show');
    Route::post('/pembayaran/{id}/terima', [KasirPaymentController::class, 'approve'])->whereNumber('id')->name('payments.approve');
    Route::post('/pembayaran/{id}/tolak', [KasirPaymentController::class, 'reject'])->whereNumber('id')->name('payments.reject');
});
