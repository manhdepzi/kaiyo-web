<?php

use App\Http\Controllers\AccountOrderController;
use App\Http\Controllers\AccountPortalController;
use App\Http\Controllers\AccountSecurityController;
use App\Http\Controllers\AdminAnalyticsController;
use App\Http\Controllers\AdminAuditController;
use App\Http\Controllers\AdminCatalogController;
use App\Http\Controllers\AdminCmsController;
use App\Http\Controllers\AdminMerchantController;
use App\Http\Controllers\AdminOutboxController;
use App\Http\Controllers\AdminPageController;
use App\Http\Controllers\MarkAccountNotificationReadController;
use App\Http\Controllers\PublicArticleController;
use App\Http\Controllers\PublicBrandController;
use App\Http\Controllers\PublicCartController;
use App\Http\Controllers\PublicCategoryController;
use App\Http\Controllers\PublicCheckoutCompleteController;
use App\Http\Controllers\PublicCheckoutController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\PublicFaqController;
use App\Http\Controllers\PublicHomeController;
use App\Http\Controllers\PublicLegacySlugController;
use App\Http\Controllers\PublicMediaController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\PublicProductController;
use App\Http\Controllers\PublicProjectController;
use App\Http\Controllers\PublicQuotationController;
use App\Http\Controllers\PublicSearchController;
use App\Http\Controllers\ReadinessController;
use App\Http\Controllers\RevokeAccountSessionController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SalesCommercialController;
use App\Http\Controllers\SalesCompanyController;
use App\Http\Controllers\SalesCompanyShowController;
use App\Http\Controllers\SalesCustomerController;
use App\Http\Controllers\SalesCustomerShowController;
use App\Http\Controllers\SalesLeadController;
use App\Http\Controllers\SalesLeadShowController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\EnsureAccountCanAccess;
use App\Http\Middleware\TrackAuthenticatedSession;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', PublicHomeController::class)->name('home');
Route::get('/tim-kiem', PublicSearchController::class)->name('public.search');
Route::get('/du-an', PublicProjectController::class)->name('public.projects');
Route::get('/danh-muc/{slug}', PublicCategoryController::class)->where('slug', '[a-z0-9-]+')->name('public.category');
Route::get('/thuong-hieu/{slug}', PublicBrandController::class)->where('slug', '[a-z0-9-]+')->name('public.brand');
Route::get('/san-pham/{slug}', PublicProductController::class)->where('slug', '[a-z0-9-]+')->name('public.product');
Route::get('/media/{asset}', PublicMediaController::class)->where('asset', '[0-9A-HJKMNP-TV-Z]{26}')->name('public.media');
Route::get('/products/{slug}', PublicLegacySlugController::class)->where('slug', '[a-z0-9-]+')->name('legacy.product');
Route::get('/categories/{slug}', PublicLegacySlugController::class)->where('slug', '[a-z0-9-]+')->name('legacy.category');
Route::get('/noi-dung/{slug}', PublicPageController::class)->where('slug', '[a-z0-9-]+')->name('public.page');
Route::get('/bai-viet/{slug}', PublicArticleController::class)->where('slug', '[a-z0-9-]+')->name('public.article');
Route::get('/cau-hoi-thuong-gap', PublicFaqController::class)->name('public.faq');
Route::get('/robots.txt', RobotsController::class)->name('seo.robots');
Route::get('/sitemap.xml', SitemapController::class)->name('seo.sitemap');
Route::get('/gio-hang', [PublicCartController::class, 'show'])->name('public.cart');
Route::post('/gio-hang/dong', [PublicCartController::class, 'storeLine'])->name('public.cart.lines.store');
Route::delete('/gio-hang/dong/{line}', [PublicCartController::class, 'removeLine'])->whereNumber('line')->name('public.cart.lines.destroy');
Route::post('/gio-hang/lam-moi', [PublicCartController::class, 'refresh'])->name('public.cart.refresh');
Route::get('/bao-gia', [PublicQuotationController::class, 'show'])->name('public.quotation');
Route::post('/bao-gia', [PublicQuotationController::class, 'create'])->name('public.quotation.create');
Route::get('/bao-gia/{quote}', [PublicQuotationController::class, 'view'])
    ->where('quote', '[0-9A-HJKMNP-TV-Z]{26}')
    ->name('public.quotation.view');
Route::post('/bao-gia/{quote}/{action}', [PublicQuotationController::class, 'access'])
    ->where('quote', '[0-9A-HJKMNP-TV-Z]{26}')
    ->where('action', 'viewed|accepted|rejected')
    ->name('public.quotation.access');
Route::view('/gioi-thieu', 'public.about')->name('public.about');
Route::get('/lien-he', [PublicContactController::class, 'show'])->name('public.contact');
Route::post('/lien-he', [PublicContactController::class, 'store'])->name('public.contact.store');

Route::get('/ready', ReadinessController::class)
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        EnsureAccountCanAccess::class,
        TrackAuthenticatedSession::class,
    ])
    ->name('health.ready');

Route::middleware(['auth', 'verified', 'private.response'])->group(function (): void {
    Route::get('/thanh-toan', [PublicCheckoutController::class, 'show'])->name('public.checkout');
    Route::post('/thanh-toan', [PublicCheckoutController::class, 'place'])->name('public.checkout.place');
    Route::get('/thanh-toan/hoan-tat/{order}', PublicCheckoutCompleteController::class)
        ->where('order', '[0-9A-HJKMNP-TV-Z]{26}')
        ->name('public.checkout.complete');
    Route::get('/account', [AccountPortalController::class, 'show'])->name('account');
    Route::post('/account/profile', [AccountPortalController::class, 'provision'])->name('account.profile.provision');
    Route::patch('/account/profile', [AccountPortalController::class, 'update'])->name('account.profile.update');
    Route::get('/account/orders/{order}', AccountOrderController::class)
        ->where('order', '[0-9A-HJKMNP-TV-Z]{26}')
        ->name('account.orders.show');
    Route::get('/account/security', AccountSecurityController::class)->name('account.security');
    Route::patch('/account/notifications/{notification}/read', MarkAccountNotificationReadController::class)
        ->where('notification', '[0-9A-HJKMNP-TV-Z]{26}')
        ->name('account.notifications.read');
    Route::delete('/account/security/sessions/{session}', RevokeAccountSessionController::class)
        ->name('account.security.sessions.destroy');
});

Route::middleware(['auth', 'verified', 'private.response', 'staff.2fa', 'staff.navigation', 'permission:crm.customers.read,crm'])
    ->get('/sales/customers', SalesCustomerController::class)
    ->name('sales.customers');
Route::middleware(['auth', 'verified', 'private.response', 'staff.2fa', 'staff.navigation'])
    ->get('/sales/customers/{customer}', SalesCustomerShowController::class)
    ->where('customer', '[0-9A-HJKMNP-TV-Z]{26}')
    ->name('sales.customers.show');
Route::middleware(['auth', 'verified', 'private.response', 'staff.2fa', 'staff.navigation', 'permission:crm.leads.read,crm'])
    ->get('/sales/leads', [SalesLeadController::class, 'index'])
    ->name('sales.leads');
Route::middleware(['auth', 'verified', 'private.response', 'staff.2fa', 'staff.navigation', 'permission:crm.leads.create,crm'])
    ->post('/sales/leads', [SalesLeadController::class, 'store'])
    ->name('sales.leads.store');
Route::middleware(['auth', 'verified', 'private.response', 'staff.2fa', 'staff.navigation'])->group(function (): void {
    Route::get('/sales/leads/{lead}', [SalesLeadShowController::class, 'show'])->where('lead', '[0-9A-HJKMNP-TV-Z]{26}')->name('sales.leads.show');
    Route::patch('/sales/leads/{lead}', [SalesLeadShowController::class, 'update'])->where('lead', '[0-9A-HJKMNP-TV-Z]{26}')->name('sales.leads.update');
    Route::post('/sales/leads/{lead}/convert', [SalesLeadShowController::class, 'convert'])->where('lead', '[0-9A-HJKMNP-TV-Z]{26}')->name('sales.leads.convert');
});
Route::middleware(['auth', 'verified', 'private.response', 'staff.2fa', 'staff.navigation', 'permission:quotes.read,quotes'])
    ->get('/sales/quotes', [SalesCommercialController::class, 'quotes'])->name('sales.quotes');
Route::middleware(['auth', 'verified', 'private.response', 'staff.2fa', 'staff.navigation', 'permission:orders.read,orders'])
    ->get('/sales/orders', [SalesCommercialController::class, 'orders'])->name('sales.orders');
Route::middleware(['auth', 'verified', 'private.response', 'staff.2fa', 'admin.navigation', 'permission:system.audit.read,system'])
    ->get('/admin/audit', AdminAuditController::class)->name('admin.audit');
Route::middleware(['auth', 'verified', 'private.response', 'staff.2fa', 'admin.navigation', 'permission:system.audit.read,system'])
    ->get('/admin/outbox', AdminOutboxController::class)->name('admin.outbox');
Route::middleware(['auth', 'verified', 'private.response', 'staff.2fa', 'admin.navigation', 'permission:analytics.read,analytics'])
    ->get('/admin/analytics', AdminAnalyticsController::class)->name('admin.analytics');
Route::middleware(['auth', 'verified', 'private.response', 'staff.2fa', 'admin.navigation', 'permission:catalog.products.manage,catalog'])->group(function (): void {
    Route::get('/admin/catalog', [AdminCatalogController::class, 'index'])->name('admin.catalog');
    Route::post('/admin/catalog/categories', [AdminCatalogController::class, 'storeCategory'])->name('admin.catalog.categories.store');
    Route::patch('/admin/catalog/categories/{category}', [AdminCatalogController::class, 'updateCategory'])->where('category', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.catalog.categories.update');
    Route::post('/admin/catalog/products', [AdminCatalogController::class, 'storeProduct'])->name('admin.catalog.products.store');
    Route::patch('/admin/catalog/products/{product}', [AdminCatalogController::class, 'updateProduct'])->where('product', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.catalog.products.update');
    Route::post('/admin/catalog/products/{product}/variants', [AdminCatalogController::class, 'storeVariant'])->where('product', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.catalog.variants.store');
    Route::patch('/admin/catalog/variants/{variant}', [AdminCatalogController::class, 'updateVariant'])->where('variant', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.catalog.variants.update');
    Route::post('/admin/catalog/products/{product}/specifications', [AdminCatalogController::class, 'storeSpecification'])->where('product', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.catalog.specifications.store');
    Route::post('/admin/catalog/products/{product}/media', [AdminCatalogController::class, 'uploadMedia'])->where('product', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.catalog.media.store');
    Route::delete('/admin/catalog/products/{product}/media/{asset}/{purpose}', [AdminCatalogController::class, 'detachMedia'])
        ->where('product', '[0-9A-HJKMNP-TV-Z]{26}')->where('asset', '[0-9A-HJKMNP-TV-Z]{26}')->where('purpose', 'primary|gallery|video|document')->name('admin.catalog.media.destroy');
});
Route::middleware(['auth', 'verified', 'private.response', 'staff.2fa', 'admin.navigation', 'permission:merchant.manage,system'])->group(function (): void {
    Route::get('/admin/merchant', [AdminMerchantController::class, 'index'])->name('admin.merchant');
    Route::post('/admin/merchant', [AdminMerchantController::class, 'store'])->name('admin.merchant.store');
    Route::post('/admin/merchant/{batch}/retry', [AdminMerchantController::class, 'retry'])
        ->where('batch', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.merchant.retry');
});
Route::middleware(['auth', 'verified', 'private.response', 'staff.2fa', 'admin.navigation', 'permission:content.manage,content'])->group(function (): void {
    Route::get('/admin/content', [AdminCmsController::class, 'index'])->name('admin.content');
    Route::get('/admin/content/pages', [AdminPageController::class, 'index'])->name('admin.pages');
    Route::post('/admin/content/pages', [AdminPageController::class, 'store'])->name('admin.pages.store');
    Route::post('/admin/content/pages/{page}/revisions', [AdminPageController::class, 'revise'])
        ->where('page', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.pages.revise');
    Route::post('/admin/content/pages/{page}/media', [AdminPageController::class, 'attachMedia'])
        ->where('page', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.pages.media.attach');
    Route::delete('/admin/content/pages/{page}/media/{asset}/{purpose}', [AdminPageController::class, 'detachMedia'])
        ->where('page', '[0-9A-HJKMNP-TV-Z]{26}')->where('asset', '[0-9A-HJKMNP-TV-Z]{26}')
        ->where('purpose', '[a-z][a-z0-9._-]{1,49}')->name('admin.pages.media.detach');
    Route::post('/admin/content/articles', [AdminCmsController::class, 'storeArticle'])->name('admin.content.articles.store');
    Route::post('/admin/content/faqs', [AdminCmsController::class, 'storeFaq'])->name('admin.content.faqs.store');
    Route::post('/admin/content/banners', [AdminCmsController::class, 'storeBanner'])->name('admin.content.banners.store');
    Route::post('/admin/content/email-templates', [AdminCmsController::class, 'storeEmailTemplate'])->name('admin.content.email-templates.store');
    Route::post('/admin/content/articles/{article}/revisions', [AdminCmsController::class, 'reviseArticle'])->where('article', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.content.articles.revise');
    Route::post('/admin/content/faqs/{faq}/revisions', [AdminCmsController::class, 'reviseFaq'])->where('faq', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.content.faqs.revise');
    Route::post('/admin/content/banners/{banner}/revisions', [AdminCmsController::class, 'reviseBanner'])->where('banner', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.content.banners.revise');
    Route::post('/admin/content/email-templates/{template}/revisions', [AdminCmsController::class, 'reviseEmailTemplate'])->where('template', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.content.email-templates.revise');
    Route::post('/admin/content/{type}/{content}/media', [AdminCmsController::class, 'attachMedia'])
        ->where('type', 'articles|faqs|banners')->where('content', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.content.media.attach');
    Route::delete('/admin/content/{type}/{content}/media/{asset}/{purpose}', [AdminCmsController::class, 'detachMedia'])
        ->where('type', 'articles|faqs|banners')->where('content', '[0-9A-HJKMNP-TV-Z]{26}')
        ->where('asset', '[0-9A-HJKMNP-TV-Z]{26}')->where('purpose', '[a-z][a-z0-9._-]{1,49}')->name('admin.content.media.detach');
});
Route::middleware(['auth', 'verified', 'private.response', 'staff.2fa', 'admin.navigation', 'permission:content.publish,content'])->group(function (): void {
    Route::post('/admin/content/pages/{page}/publish', [AdminPageController::class, 'publish'])
        ->where('page', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.pages.publish');
    Route::post('/admin/content/pages/{page}/unpublish', [AdminPageController::class, 'unpublish'])
        ->where('page', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.pages.unpublish');
    Route::post('/admin/content/pages/{page}/schedule', [AdminPageController::class, 'schedule'])
        ->where('page', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.pages.schedule');
    Route::post('/admin/content/articles/{article}/publish', [AdminCmsController::class, 'publishArticle'])->where('article', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.content.articles.publish');
    Route::post('/admin/content/faqs/{faq}/publish', [AdminCmsController::class, 'publishFaq'])->where('faq', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.content.faqs.publish');
    Route::post('/admin/content/banners/{banner}/publish', [AdminCmsController::class, 'publishBanner'])->where('banner', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.content.banners.publish');
    Route::post('/admin/content/email-templates/{template}/publish', [AdminCmsController::class, 'publishEmailTemplate'])->where('template', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.content.email-templates.publish');
    Route::post('/admin/content/{type}/{content}/unpublish', [AdminCmsController::class, 'unpublish'])
        ->where('type', 'articles|faqs|banners|email-templates')->where('content', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.content.unpublish');
    Route::post('/admin/content/{type}/{content}/schedule', [AdminCmsController::class, 'schedule'])
        ->where('type', 'articles|faqs|banners')->where('content', '[0-9A-HJKMNP-TV-Z]{26}')->name('admin.content.schedule');
});
Route::middleware(['auth', 'verified', 'private.response', 'staff.2fa', 'staff.navigation', 'permission:crm.companies.read,crm'])
    ->get('/sales/companies', [SalesCompanyController::class, 'index'])->name('sales.companies');
Route::middleware(['auth', 'verified', 'private.response', 'staff.2fa', 'staff.navigation', 'permission:crm.companies.create,crm'])
    ->post('/sales/companies', [SalesCompanyController::class, 'store'])->name('sales.companies.store');
Route::middleware(['auth', 'verified', 'private.response', 'staff.2fa', 'staff.navigation'])->group(function (): void {
    Route::get('/sales/companies/{company}', [SalesCompanyShowController::class, 'show'])->where('company', '[0-9A-HJKMNP-TV-Z]{26}')->name('sales.companies.show');
    Route::post('/sales/companies/{company}/members', [SalesCompanyShowController::class, 'addMember'])->where('company', '[0-9A-HJKMNP-TV-Z]{26}')->name('sales.companies.members.store');
});
