# Remindy - Subscription Management & Bill Reminder System

<div align="center">
  
![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![React](https://img.shields.io/badge/React-18.x-61DAFB?style=for-the-badge&logo=react&logoColor=black)
![TypeScript](https://img.shields.io/badge/TypeScript-5.x-3178C6?style=for-the-badge&logo=typescript&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

**Never miss a payment again. Track, manage, and get reminded about all your subscriptions in one place.**

[Features](#features) • [Installation](#installation)

</div>

## 🌟 Overview

Remindy is a comprehensive subscription management platform that helps individuals track recurring payments, manage subscriptions, and receive automated notifications to avoid missed payments. Built with Laravel and React, it provides a modern, responsive interface for managing your subscription economy participation.

## ✨ Features

### Core Functionality
- 📊 **Dashboard Overview** - At-a-glance view of all subscriptions, upcoming payments, and spending
- 💳 **Subscription Management** - Track unlimited subscriptions with detailed information
- 🔔 **Smart Notifications** - Personalized email/webhook reminders at your preferred time
- ⏰ **User-Scheduled Reminders** - Receive notifications at your chosen time (respects time zones)
- 💰 **Multi-Currency Support** - Support for 150+ currencies with custom currency creation
- 📈 **Financial Forecasting** - Monthly spending projections and budget tracking
- 🏷️ **Categories & Organization** - Organize subscriptions with custom categories
- 📎 **Payment History** - Complete payment records with receipt attachments
- 🎨 **Modern UI** - Clean, responsive interface built with React and Tailwind CSS

### Advanced Features
- **Intelligent Date Calculations** - Handles complex billing cycles including month-end scenarios
- **Time Zone Support** - Notifications delivered at your local time preference
- **Custom SMTP Configuration** - Use your own email server for notifications
- **Webhook Integration** - Connect to external services for automated workflows
- **Flexible Reminder Intervals** - Customize when to receive reminders (30, 15, 7, 3, 1 days before)
- **Payment Method Tracking** - Manage multiple payment methods with usage statistics
- **Bulk Operations** - Manage multiple subscriptions efficiently
- **Data Export** - Export your data in various formats

## 🚀 Quick Start

### Prerequisites

- PHP 8.2 or higher
- Composer 2.x
- Node.js 18.x or higher
- Redis (optional, for caching)

### Installation

1. **Clone the repository**
```bash
git clone https://github.com/ayumbro/remindy.git
cd remindy
```

2. **Install PHP dependencies**
```bash
composer install
```

3. **Install Node dependencies**
```bash
npm install
```

4. **Environment setup**
```bash
cp .env.example .env
php artisan key:generate
```

5. **Configure your database**
Edit `.env` file with your database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=remindy
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

6. **Run migrations and seeders**
```bash
php artisan migrate --seed
```

7. **Build frontend assets**
```bash
npm run build
```

8. **Start the development server**
```bash
php artisan serve
```

Visit `http://localhost:8000` to see the application.

### Development Setup

For development with hot-reload:

```bash
# Terminal 1 - PHP server
php artisan serve

# Terminal 2 - Vite dev server
npm run dev

# Terminal 3 - Queue worker (for notifications)
php artisan queue:work
```

## 🔧 Configuration

### Disabling User Registration

To disable new user registrations (useful for personal deployments):

1. Set `ENABLE_REGISTRATION=false` in your `.env` file
2. Clear the configuration cache: `php artisan config:clear`
3. The sign-up link will be hidden

## 🏗️ Technology Stack

### Backend
- **Laravel 12.x** - PHP web application framework
- **PHP 8.2+** - Server-side programming language
- **MySQL/PostgreSQL** - Relational database
- **Redis** - Caching and queue management
- **Laravel Queue** - Background job processing

### Frontend
- **React 18.x** - UI library
- **TypeScript** - Type-safe JavaScript
- **Inertia.js** - Modern monolith connecting Laravel and React
- **Tailwind CSS** - Utility-first CSS framework
- **Radix UI** - Accessible UI components
- **Vite** - Fast build tool

## 🐛 Bug Reports

Found a bug? Please [open an issue](https://github.com/ayumbro/remindy/issues/new) with a detailed description and steps to reproduce.

## 💡 Feature Requests

Have an idea? We'd love to hear it! [Open a feature request](https://github.com/ayumbro/remindy/issues/new?labels=enhancement) and let's discuss.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Laravel community for the amazing framework
- React team for the powerful UI library
- All contributors who help make Remindy better

## 📊 Project Status

- ✅ Core subscription management
- ✅ Payment tracking
- ✅ Email notifications
- ✅ Multi-currency support
- ✅ Category management
- 🚧 Webhook notifications (planned)

---

<div align="center">
  
**❤️**

</div>