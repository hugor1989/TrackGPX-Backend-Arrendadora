<p align="center">
  <a href="https://laravel.com" target="_blank">
    <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo">
  </a>
</p>

<p align="center">
  <a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
  <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

---

# 🚛 TrackGPX Flotillas – Backend

Backend desarrollado en **Laravel 11** para la gestión de flotillas GPS, clientes, vehículos, dispositivos y facturación.  
Este proyecto expone una **API RESTful** que sirve como base para aplicaciones móviles y web.

---

## 🧩 Tecnologías principales

- **Framework:** Laravel 11 (PHP ≥ 8.2)
- **Base de datos:** MySQL 8+
- **Colas y jobs:** Database Queue Driver
- **Cache y sesiones:** Database
- **Servidor local:** PHP built-in (`php artisan serve`)

---

## ⚙️ Instalación local

```bash
git clone https://github.com/hugor1989/TrackGPX-Flotillas-Backend.git
cd TrackGPX-Flotillas-Backend
composer install
cp .env.example .env
php artisan key:generate


.env 
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=trackgps_flotillas
DB_USERNAME=root
DB_PASSWORD=

migraciones:

php artisan session:table
php artisan queue:table
php artisan cache:table
php artisan migrate


servidor:
php artisan serve

🗂️ Estructura general del proyecto
trackgps-flotillas-backend/
│
├── app/
│   ├── Console/                          # Comandos Artisan personalizados
│   │   ├── Kernel.php
│   │   └── Commands/
│   │       ├── ProcessGpsQueue.php
│   │       └── GenerateReports.php
│   │
│   ├── Exceptions/
│   │   └── Handler.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── Auth/                # Login, registro, recuperación de contraseñas
│   │   │   │   ├── Admin/               # Controladores del panel del admin principal
│   │   │   │   ├── Customer/            # Controladores del cliente/empresa
│   │   │   │   ├── Vehicle/             # CRUD de vehículos y asignación de drivers
│   │   │   │   ├── Device/              # Alta, baja y configuración de GPS
│   │   │   │   ├── Billing/             # Facturas, pagos, suscripciones
│   │   │   │   ├── Alert/               # Alertas generadas por eventos GPS
│   │   │   │   └── GpsDataController.php# Endpoint que recibe data del servidor TCP
│   │   │   └── Web/
│   │   │       └── DashboardController.php
│   │   │
│   │   ├── Middleware/
│   │   │   ├── Authenticate.php
│   │   │   ├── RoleMiddleware.php
│   │   │   └── VerifyCsrfToken.php
│   │   │
│   │   ├── Requests/                    # Validaciones de entrada
│   │   └── Resources/                   # Transformadores de respuesta (API Resources)
│   │
│   ├── Models/
│   │   ├── AdminUser.php
│   │   ├── Company.php
│   │   ├── Customer.php
│   │   ├── Vehicle.php
│   │   ├── Driver.php
│   │   ├── Device.php
│   │   ├── SimCard.php
│   │   ├── Subscription.php
│   │   ├── Invoice.php
│   │   ├── Payment.php
│   │   ├── Alert.php
│   │   ├── Notification.php
│   │   ├── FineRecord.php
│   │   ├── VehicleDebt.php
│   │   └── GpsPosition.php              # Registro de posiciones GPS recibidas
│   │
│   ├── Services/                        # Lógica desacoplada del core
│   │   ├── GpsDataService.php           # Procesa data entrante desde el servidor TCP
│   │   ├── AlertService.php             # Lógica de alertas automáticas
│   │   ├── BillingService.php           # Facturación y CFDI
│   │   ├── PaymentGatewayService.php    # Integración con Stripe o MercadoPago
│   │   ├── ScraperService.php           # Scraping de multas / adeudos vehiculares
│   │   └── NotificationService.php      # Push, email, SMS
│   │
│   ├── Jobs/                            # Tareas en cola
│   │   ├── ProcessIncomingGps.php       # Guarda data GPS desde la cola
│   │   ├── GenerateInvoice.php
│   │   ├── FetchVehicleDebts.php
│   │   ├── SendAlertNotification.php
│   │   └── ProcessScrapingResult.php
│   │
│   ├── Events/
│   │   ├── NewGpsPositionReceived.php
│   │   ├── AlertTriggered.php
│   │   └── PaymentCompleted.php
│   │
│   ├── Listeners/
│   │   ├── UpdateVehicleLocation.php
│   │   ├── GenerateAlert.php
│   │   ├── SendPushNotification.php
│   │   └── UpdateSubscriptionStatus.php
│   │
│   └── Policies/
│       ├── VehiclePolicy.php
│       ├── DevicePolicy.php
│       └── CompanyPolicy.php
│
├── config/
│   ├── services.php
│   ├── billing.php
│   ├── payment.php
│   └── scraping.php
│
├── routes/
│   ├── api.php                          # Endpoints REST (web y app móvil)
│   ├── web.php
│   └── channels.php                     # Canales para broadcasting (WebSockets)
│
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
│
├── storage/
│   ├── app/
│   ├── logs/
│   └── framework/
│
├── tests/
│   ├── Feature/
│   └── Unit/
│
└── .env.example


Este backend fue desarrollado sobre el framework Laravel (MIT License).
Proyecto privado © TrackGPX Flotillas – Todos los derechos reservados.