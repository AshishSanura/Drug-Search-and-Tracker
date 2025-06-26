# Drug-Search-and-Tracker (Laravel 12)

## 2. Install Dependencies
composer install

## 3. Environment Setup
cp .env.example .env
php artisan key:generate

## 4. Configure Database
DB_DATABASE=your_database
DB_USERNAME=root
DB_PASSWORD=

## 5. API Authantication Passport Setup
php artisan passport:install
php artisan migrate

## ⚙️ Project Setup Instructions
### 1. Clone the Repository (After Above Step Follw)
```bash
git https://github.com/AshishSanura/Drug-Search-and-Tracker.git
cd Drug-Search-and-Tracker