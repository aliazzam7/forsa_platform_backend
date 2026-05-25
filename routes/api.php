<?php
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/StudentController.php';
require_once __DIR__ . '/../controllers/JobController.php';
require_once __DIR__ . '/../controllers/PostJobController.php';
require_once __DIR__ . '/../controllers/ApplicationController.php';
require_once __DIR__ . '/../controllers/CompanyController.php';
require_once __DIR__ . '/../controllers/AdminController.php';
require_once __DIR__ . '/../controllers/UploadController.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/');

// Strip base path if hosted in subfolder e.g. /forsa-platform-backend
$base   = '/forsa-platform-backend';
if (str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
}

// ── Helper to extract :id from URI ──────────────────────────────
function seg(string $uri, int $pos): ?int {
    $parts = explode('/', trim($uri, '/'));
    return isset($parts[$pos]) && is_numeric($parts[$pos]) ? (int)$parts[$pos] : null;
}

// ═══════════════════════════════════════════════════════════════
//  AUTH
// ═══════════════════════════════════════════════════════════════
if ($uri === '/api/auth/login'             && $method === 'POST') { AuthController::login();           exit; }
if ($uri === '/api/auth/register/student'  && $method === 'POST') { AuthController::registerStudent(); exit; }
if ($uri === '/api/auth/register/company'  && $method === 'POST') { AuthController::registerCompany(); exit; }
if ($uri === '/api/auth/me'               && $method === 'GET')  { AuthController::me();              exit; }

// ═══════════════════════════════════════════════════════════════
//  PUBLIC STATS
// ═══════════════════════════════════════════════════════════════
if ($uri === '/api/stats' && $method === 'GET') {
    require_once __DIR__ . '/../controllers/StatsController.php';
    StatsController::index();
    exit;
}

// ═══════════════════════════════════════════════════════════════
//  JOBS (public)
// ═══════════════════════════════════════════════════════════════
if ($uri === '/api/jobs'  && $method === 'GET') { JobController::index();             exit; }
if (preg_match('#^/api/jobs/(\d+)$#', $uri, $m) && $method === 'GET') { JobController::show((int)$m[1]); exit; }

// ═══════════════════════════════════════════════════════════════
//  STUDENT
// ═══════════════════════════════════════════════════════════════
if ($uri === '/api/student/dashboard'    && $method === 'GET')  { StudentController::dashboard();     exit; }
if ($uri === '/api/student/profile'      && $method === 'GET')  { StudentController::getProfile();    exit; }
if ($uri === '/api/student/profile'      && $method === 'PUT')  { StudentController::updateProfile(); exit; }
if ($uri === '/api/student/upload-cv'    && $method === 'POST') { StudentController::uploadCV();      exit; }
if ($uri === '/api/student/applications' && $method === 'GET')  { ApplicationController::myApplications(); exit; }
if ($uri === '/api/applications'         && $method === 'POST') { ApplicationController::apply();     exit; }
if (preg_match('#^/api/applications/(\d+)/status$#', $uri, $m) && $method === 'PUT') {
    ApplicationController::updateStatus((int)$m[1]); exit;
}
// new
if ($uri === '/api/student/change-password' && $method === 'PUT') {
    StudentController::changePassword(); exit;
}

//new new
if ($uri === '/api/student/upload-avatar' && $method === 'POST') { StudentController::uploadAvatar(); exit; }

// delete cv from profile student 
if ($uri === '/api/student/cv' && $method === 'DELETE') { StudentController::deleteCV(); exit; }

// ═══════════════════════════════════════════════════════════════
//  COMPANY
// ═══════════════════════════════════════════════════════════════
if ($uri === '/api/company/dashboard'    && $method === 'GET')  { CompanyController::dashboard();     exit; }
if ($uri === '/api/company/profile'      && $method === 'GET')  { CompanyController::getProfile();    exit; }
if ($uri === '/api/company/profile'      && $method === 'PUT')  { CompanyController::updateProfile(); exit; }
if ($uri === '/api/company/upload-logo'  && $method === 'POST') { CompanyController::uploadLogo();    exit; }
if ($uri === '/api/company/jobs'         && $method === 'GET')  { PostJobController::myJobs();        exit; }
if ($uri === '/api/company/jobs'         && $method === 'POST') { PostJobController::create();        exit; }
if (preg_match('#^/api/company/jobs/(\d+)$#', $uri, $m)) {
    if ($method === 'PUT')    { PostJobController::update((int)$m[1]); exit; }
    if ($method === 'DELETE') { PostJobController::delete((int)$m[1]); exit; }
}
if (preg_match('#^/api/company/applications/(\d+)$#', $uri, $m) && $method === 'GET') {
    ApplicationController::jobApplicants((int)$m[1]); exit;
}

// new 
if ($uri === '/api/company/change-password' && $method === 'PUT') {
    CompanyController::changePassword(); exit;
}

// ═══════════════════════════════════════════════════════════════
//  ADMIN
// ═══════════════════════════════════════════════════════════════
if ($uri === '/api/admin/dashboard'  && $method === 'GET')    { AdminController::dashboard();    exit; }
if ($uri === '/api/admin/users'      && $method === 'GET')    { AdminController::getUsers();     exit; }
if ($uri === '/api/admin/companies'  && $method === 'GET')    { AdminController::getCompanies(); exit; }
if ($uri === '/api/admin/jobs'       && $method === 'GET')    { AdminController::getJobs();      exit; }

if (preg_match('#^/api/admin/users/(\d+)/ban$#', $uri, $m)       && $method === 'PUT')    { AdminController::banUser((int)$m[1]);       exit; }
if (preg_match('#^/api/admin/users/(\d+)$#', $uri, $m)           && $method === 'DELETE') { AdminController::deleteUser((int)$m[1]);    exit; }
if (preg_match('#^/api/admin/companies/(\d+)/approve$#', $uri, $m) && $method === 'PUT')  { AdminController::approveCompany((int)$m[1]); exit; }
if (preg_match('#^/api/admin/companies/(\d+)/ban$#', $uri, $m)   && $method === 'PUT')    { AdminController::banCompany((int)$m[1]);    exit; }
if (preg_match('#^/api/admin/companies/(\d+)$#', $uri, $m)       && $method === 'DELETE') { AdminController::deleteCompany((int)$m[1]); exit; }
if (preg_match('#^/api/admin/jobs/(\d+)$#', $uri, $m)            && $method === 'DELETE') { AdminController::deleteJob((int)$m[1]);     exit; }

// PUT /api/admin/users/:id  — edit user (name/email)
if (preg_match('#^/api/admin/users/(\d+)$#', $uri, $m) && $method === 'PUT') {
    AdminController::updateUser((int)$m[1]); exit;
}
 
// PUT /api/admin/jobs/:id/status  — set status: active/spam/deleted
if (preg_match('#^/api/admin/jobs/(\d+)/status$#', $uri, $m) && $method === 'PUT') {
    AdminController::updateJobStatus((int)$m[1]); exit;
}
 
// PUT /api/admin/companies/:id/status  — set status: pending/approved/banned/rejected
if (preg_match('#^/api/admin/companies/(\d+)/status$#', $uri, $m) && $method === 'PUT') {
    AdminController::updateCompanyStatus((int)$m[1]); exit;
}
 
// GET /api/admin/profile
if ($uri === '/api/admin/profile' && $method === 'GET') {
    AdminController::getProfile(); exit;
}
 
// PUT /api/admin/profile  — update name/email/avatar
if ($uri === '/api/admin/profile' && $method === 'PUT') {
    AdminController::updateProfile(); exit;
}
 
// PUT /api/admin/change-password
if ($uri === '/api/admin/change-password' && $method === 'PUT') {
    AdminController::changePassword(); exit;
}

// ═══════════════════════════════════════════════════════════════
//  CONTACT
// ═══════════════════════════════════════════════════════════════
if ($uri === '/api/contact' && $method === 'POST') {
    require_once __DIR__ . '/../controllers/ContactController.php';
    ContactController::send();
    exit;
}
if ($uri === '/api/admin/messages' && $method === 'GET') {
    require_once __DIR__ . '/../controllers/ContactController.php';
    ContactController::index();
    exit;
}
if (preg_match('#^/api/admin/messages/(\d+)/read$#', $uri, $m) && $method === 'PUT') {
    require_once __DIR__ . '/../controllers/ContactController.php';
    ContactController::markRead((int)$m[1]);
    exit;
}

// ═══════════════════════════════════════════════════════════════
//  UPLOAD
// ═══════════════════════════════════════════════════════════════
if ($uri === '/api/upload/avatar' && $method === 'POST') { UploadController::avatar(); exit; }

// ═══════════════════════════════════════════════════════════════
//  404
// ═══════════════════════════════════════════════════════════════
Response::error('Route not found.', 404);