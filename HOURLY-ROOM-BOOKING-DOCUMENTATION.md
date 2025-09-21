# Hourly Room Booking System - Complete Documentation

## 📋 Table of Contents
1. [Overview](#overview)
2. [System Architecture](#system-architecture)
3. [Database Schema](#database-schema)
4. [Security Features](#security-features)
5. [Core Features](#core-features)
6. [Admin Interface](#admin-interface)
7. [Payment System](#payment-system)
8. [Notification System](#notification-system)
9. [API & Integrations](#api--integrations)
10. [Installation & Setup](#installation--setup)
11. [Configuration](#configuration)
12. [Troubleshooting](#troubleshooting)

---

## 🎯 Overview

The **Hourly Room Booking System** is a comprehensive WordPress plugin designed for managing hourly room reservations with advanced features including payment processing, stock management, and multi-channel notifications.

### Key Capabilities
- **Hourly Room Booking Management**
- **Multi-Payment Gateway Support** (PayPal + On-site)
- **Real-time Stock Management** for extras
- **Advanced Pricing System** with room-specific rates
- **Multi-Channel Notifications** (Email, SMS, WhatsApp)
- **Comprehensive Admin Dashboard**
- **Analytics & Reporting**
- **Email Template Management**

---

## 🏗️ System Architecture

### Core Classes Structure
```
HourlyRoomBooking/
├── includes/
│   ├── class-booking-manager.php      # Booking operations
│   ├── class-room-manager.php         # Room management
│   ├── class-payment-handler.php      # Payment processing
│   ├── class-notification-manager.php # Notifications
│   ├── class-currency-manager.php     # Currency handling
│   ├── class-input-validator.php      # Input validation
│   ├── class-database.php             # Database operations
│   └── class-admin.php                # Admin interface
├── admin/views/                        # Admin interface views
├── public/                            # Frontend assets
└── templates/                         # Email templates
```

### Design Patterns Used
- **Singleton Pattern** for manager classes
- **Factory Pattern** for payment processing
- **Observer Pattern** for notifications
- **Strategy Pattern** for pricing calculations

---

## 🗄️ Database Schema

### Core Tables

#### 1. **wp_hrb_rooms**
```sql
- id (Primary Key)
- name (Room name)
- description (Room description)
- capacity (Max occupancy)
- hourly_price (Base hourly rate)
- price_2_hours, price_3_hours, price_4_hours (Duration-based pricing)
- price_extra_hour (Additional hour rate)
- images (JSON array of image URLs)
- amenities (JSON array of amenities)
- is_active (Room status)
- sort_order (Display order)
- created_at, updated_at (Timestamps)
```

#### 2. **wp_hrb_customers**
```sql
- id (Primary Key)
- wp_user_id (WordPress user ID - nullable)
- first_name, last_name (Customer details)
- email (Unique, verified)
- phone (Contact number)
- company, address, city, postal_code, country
- date_of_birth
- is_verified (Email/SMS verification status)
- created_at, updated_at (Timestamps)
```

#### 3. **wp_hrb_bookings**
```sql
- id (Primary Key)
- booking_reference (Unique booking ID)
- room_id (Foreign key to rooms)
- customer_id (Foreign key to customers)
- booking_date, start_time, end_time
- total_hours (Calculated duration)
- base_price, extra_people_price, extras_price
- tax_amount, paypal_fee, total_amount
- status (pending/confirmed/cancelled/completed)
- payment_status (pending/paid/failed/refunded)
- payment_method (paypal/onsite)
- special_requests, admin_notes
- created_by_admin, cooldown_override (Admin flags)
- created_at, updated_at (Timestamps)
```

#### 4. **wp_hrb_payments**
```sql
- id (Primary Key)
- booking_id (Foreign key to bookings)
- transaction_id (Payment gateway transaction ID)
- gateway_transaction_id (Gateway-specific ID)
- payment_method (paypal/onsite)
- amount, currency
- status (pending/completed/failed/refunded)
- gateway_response (JSON response data)
- processed_at (Processing timestamp)
- created_at (Creation timestamp)
```

#### 5. **wp_hrb_extras**
```sql
- id (Primary Key)
- name, description
- price (Unit price)
- stock_quantity (Available quantity)
- track_stock (Stock tracking enabled/disabled)
- image_url (Extra item image)
- is_active, sort_order
- created_at, updated_at (Timestamps)
```

#### 6. **wp_hrb_booking_extras**
```sql
- id (Primary Key)
- booking_id (Foreign key to bookings)
- extra_id (Foreign key to extras)
- quantity (Booked quantity)
- unit_price, total_price
- booking_date, start_time, end_time (Time slot)
- created_at (Timestamp)
```

#### 7. **wp_hrb_email_templates**
```sql
- id (Primary Key)
- template_key (booking_confirmation, payment_confirmation, etc.)
- subject, heading, message (Template content)
- html_content (Full HTML template)
- is_active (Template status)
- created_at, updated_at (Timestamps)
```

#### 8. **wp_hrb_otp_verification**
```sql
- id (Primary Key)
- email, phone (Contact details)
- otp_code (6-digit verification code)
- verification_type (email/sms)
- is_verified (Verification status)
- attempts (Failed attempts counter)
- expires_at (Code expiration)
- created_at (Timestamp)
```

---

## 🔒 Security Features

### 1. **Input Validation & Sanitization**
- **Comprehensive Input Validator** (`class-input-validator.php`)
- **Data Type Validation**: Email, phone, dates, times, numbers
- **XSS Prevention**: All user inputs sanitized with `sanitize_text_field()`, `sanitize_email()`
- **SQL Injection Prevention**: All database queries use `$wpdb->prepare()`

### 2. **Authentication & Authorization**
- **Nonce Verification**: All AJAX requests protected with `wp_verify_nonce()`
- **Capability Checks**: Admin functions require `manage_options` capability
- **User Role Management**: Integration with WordPress user system

### 3. **Data Protection**
- **Output Escaping**: All output escaped with `esc_html()`, `esc_attr()`
- **CSRF Protection**: Nonce tokens for all forms
- **Input Length Limits**: Maximum lengths enforced for all text fields

### 4. **Payment Security**
- **PayPal API Security**: Secure token-based authentication
- **Payment Validation**: Amount verification before processing
- **Transaction Logging**: Complete audit trail of all payments

### 5. **File Upload Security**
- **Media Library Integration**: Uses WordPress media uploader
- **File Type Validation**: Only allowed image types
- **Path Traversal Prevention**: Secure file path handling

---

## ⚡ Core Features

### 1. **Room Management**
- **Multi-Room Support**: Unlimited rooms with individual settings
- **Image Management**: Multiple images per room with WordPress media library
- **Amenities System**: JSON-based amenities with icons
- **Pricing Flexibility**: Room-specific pricing with global fallbacks
- **Availability Checking**: Real-time availability validation

### 2. **Booking System**
- **Hourly Booking**: Precise time slot management
- **Duration-Based Pricing**: 2, 3, 4+ hour pricing tiers
- **Extra People Support**: Additional person charges
- **Special Requests**: Custom requirements handling
- **Booking References**: Unique booking IDs (HRB-YYYYMMDD-XXXX)

### 3. **Stock Management**
- **Real-time Stock Tracking**: Live availability for extras
- **Time-based Availability**: Stock checked against booking duration
- **Overlap Prevention**: Prevents double-booking of limited items
- **Automatic Stock Updates**: Real-time inventory management

### 4. **Pricing System**
- **Multi-Currency Support**: USD and EUR with proper formatting
- **Room-Specific Pricing**: Individual room rates
- **Global Default Pricing**: Fallback pricing system
- **PayPal Fee Calculation**: 3% fee for PayPal payments
- **Tax Support**: Configurable tax rates (currently disabled)

### 5. **Customer Management**
- **Guest Booking**: No registration required
- **User Integration**: WordPress user account linking
- **Verification System**: Email/SMS OTP verification
- **Customer History**: Complete booking history tracking

---

## 🎛️ Admin Interface

### 1. **Dashboard**
- **Real-time Statistics**: Today's bookings, monthly revenue
- **Quick Actions**: Create booking, view reports
- **Room Status**: Live room availability overview
- **Recent Bookings**: Latest booking activity

### 2. **Booking Management**
- **Booking List**: Filterable and searchable booking table
- **Bulk Operations**: Mass status updates
- **Booking Details**: Complete booking information view
- **Edit Capabilities**: Modify booking details
- **Status Management**: Pending, confirmed, cancelled, completed

### 3. **Room Management**
- **Room CRUD**: Create, read, update, delete rooms
- **Image Upload**: WordPress media library integration
- **Pricing Configuration**: Individual room pricing
- **Amenities Management**: JSON-based amenities system

### 4. **Reports & Analytics**
- **Revenue Analytics**: Daily, monthly, yearly revenue tracking
- **Booking Statistics**: Booking counts, trends, patterns
- **Peak Hours Analysis**: Most popular booking times
- **Customer Analytics**: Unique customers, repeat bookings
- **Export Capabilities**: Data export functionality

### 5. **Settings Management**
- **General Settings**: Currency, date/time formats
- **Booking Settings**: Advance booking limits, cancellation policies
- **Pricing Settings**: Global pricing configuration
- **Payment Settings**: PayPal configuration
- **Notification Settings**: Email, SMS, WhatsApp settings

### 6. **Email Template Management**
- **Template Editor**: Rich text editor for email templates
- **Variable System**: Dynamic content insertion
- **Preview Functionality**: Template preview before sending
- **Template Types**: Confirmation, modification, payment emails

---

## 💳 Payment System

### 1. **PayPal Integration**
- **PayPal API v2**: Latest PayPal integration
- **Sandbox Support**: Testing environment
- **Order Creation**: Secure order generation
- **Payment Capture**: Automatic payment processing
- **Refund Support**: Full refund capabilities

### 2. **On-site Payments**
- **Manual Payment**: Admin-marked payments
- **Payment Tracking**: Complete payment history
- **Status Management**: Pending to completed workflow

### 3. **Payment Features**
- **Multi-Currency**: USD and EUR support
- **Fee Calculation**: Automatic PayPal fee calculation
- **Payment Logging**: Complete audit trail
- **Refund Management**: Automated refund processing

---

## 📧 Notification System

### 1. **Email Notifications**
- **Template System**: Customizable email templates
- **Variable Replacement**: Dynamic content insertion
- **Multi-language Support**: i18n ready
- **HTML Templates**: Rich email formatting

### 2. **SMS Notifications** (Twilio Integration)
- **Twilio API**: SMS delivery via Twilio
- **OTP Support**: Verification code delivery
- **Status Updates**: Booking status notifications

### 3. **WhatsApp Notifications** (Business API)
- **WhatsApp Business API**: Official WhatsApp integration
- **Rich Messages**: Formatted WhatsApp messages
- **Status Updates**: Real-time booking updates

### 4. **Notification Types**
- **Booking Confirmation**: New booking notifications
- **Payment Confirmation**: Payment success notifications
- **Booking Modified**: Update notifications
- **Booking Cancelled**: Cancellation notifications

---

## 🔌 API & Integrations

### 1. **WordPress Integration**
- **Hooks & Filters**: Extensive WordPress integration
- **User Management**: WordPress user system integration
- **Media Library**: WordPress media uploader
- **Admin Interface**: Native WordPress admin styling

### 2. **External APIs**
- **PayPal API**: Payment processing
- **Twilio API**: SMS notifications
- **WhatsApp Business API**: WhatsApp notifications

### 3. **AJAX Endpoints**
- **Booking Operations**: Create, update, cancel bookings
- **Room Search**: Availability checking
- **Payment Processing**: Payment gateway communication
- **Admin Operations**: Bulk operations, status updates

---

## 🚀 Installation & Setup

### 1. **System Requirements**
- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **Memory**: 128MB minimum
- **Extensions**: cURL, JSON, OpenSSL

### 2. **Installation Steps**
1. Upload plugin files to `/wp-content/plugins/hourly-room-booking/`
2. Activate plugin through WordPress admin
3. Database tables created automatically
4. Configure settings in admin panel
5. Set up payment gateways
6. Configure notification settings

### 3. **Initial Configuration**
1. **General Settings**: Set currency, date formats
2. **Room Setup**: Create rooms with pricing
3. **Payment Setup**: Configure PayPal credentials
4. **Notification Setup**: Configure email/SMS settings
5. **Template Setup**: Customize email templates

---

## ⚙️ Configuration

### 1. **General Settings**
```php
// Currency settings
'hrb_currency' => 'EUR' // USD or EUR
'hrb_date_format' => 'Y-m-d'
'hrb_time_format' => 'H:i'
'hrb_timezone' => 'Europe/Berlin'
```

### 2. **Booking Settings**
```php
// Booking limits
'hrb_booking_advance_days' => 30
'hrb_cancellation_hours' => 24
'hrb_default_booking_duration' => 2
'hrb_cooldown_minutes' => 15
'hrb_max_concurrent_bookings' => 5
```

### 3. **Pricing Settings**
```php
// Global pricing (used as fallback)
'hrb_price_2_hours' => 40.00
'hrb_price_3_hours' => 55.00
'hrb_price_4_hours' => 70.00
'hrb_price_extra_hour' => 15.00
'hrb_extra_person_price' => 15.00
'hrb_max_extra_people' => 10
```

### 4. **PayPal Settings**
```php
// PayPal configuration
'hrb_paypal_sandbox' => 1 // 1 for sandbox, 0 for live
'hrb_paypal_client_id' => 'your_client_id'
'hrb_paypal_client_secret' => 'your_client_secret'
'hrb_paypal_fee_percentage' => 3.0
```

### 5. **Notification Settings**
```php
// Email notifications
'hrb_email_notifications' => 1
'hrb_company_email' => 'admin@example.com'
'hrb_company_name' => 'Your Company'

// SMS notifications (Twilio)
'hrb_sms_notifications' => 0
'hrb_twilio_sid' => 'your_twilio_sid'
'hrb_twilio_token' => 'your_twilio_token'
'hrb_twilio_phone' => '+1234567890'

// WhatsApp notifications
'hrb_whatsapp_notifications' => 0
'hrb_whatsapp_token' => 'your_whatsapp_token'
'hrb_whatsapp_phone_id' => 'your_phone_id'
```

---

## 🔧 Troubleshooting

### 1. **Common Issues**

#### Booking Not Saving
- Check database permissions
- Verify nonce verification
- Check for JavaScript errors

#### Payment Issues
- Verify PayPal credentials
- Check SSL certificate
- Validate currency settings

#### Email Not Sending
- Check SMTP configuration
- Verify email template syntax
- Check spam folder

#### Stock Management Issues
- Verify database constraints
- Check time zone settings
- Validate booking duration

### 2. **Debug Mode**
```php
// Enable debug logging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check logs in /wp-content/debug.log
```

### 3. **Database Issues**
```sql
-- Check table structure
SHOW CREATE TABLE wp_hrb_bookings;

-- Verify data integrity
SELECT COUNT(*) FROM wp_hrb_bookings WHERE status = 'confirmed';
```

---

## 📊 Performance Optimization

### 1. **Database Optimization**
- **Indexes**: Proper indexing on frequently queried fields
- **Query Optimization**: Efficient database queries
- **Connection Pooling**: Reuse database connections

### 2. **Caching Strategy**
- **Object Caching**: Cache frequently accessed data
- **Query Caching**: Cache complex queries
- **Template Caching**: Cache email templates

### 3. **Frontend Optimization**
- **Asset Minification**: Minified CSS/JS
- **Lazy Loading**: Images loaded on demand
- **CDN Support**: Content delivery network integration

---

## 🔄 Maintenance & Updates

### 1. **Regular Maintenance**
- **Database Cleanup**: Remove old OTP records
- **Log Rotation**: Manage log file sizes
- **Backup Strategy**: Regular database backups

### 2. **Update Procedures**
- **Backup First**: Always backup before updates
- **Test Environment**: Test updates in staging
- **Gradual Rollout**: Deploy updates gradually

### 3. **Monitoring**
- **Error Logging**: Monitor error logs
- **Performance Metrics**: Track system performance
- **User Feedback**: Collect user feedback

---

## 📈 Future Enhancements

### Planned Features
1. **Multi-location Support**
2. **Advanced Analytics Dashboard**
3. **Mobile App Integration**
4. **Calendar Synchronization**
5. **Advanced Reporting**
6. **Multi-language Support**
7. **API Documentation**
8. **Webhook Support**

---

## 📞 Support & Contact

### Technical Support
- **Documentation**: This comprehensive guide
- **Code Comments**: Extensive inline documentation
- **Error Logging**: Detailed error tracking
- **Debug Tools**: Built-in debugging capabilities

### System Requirements
- **WordPress**: 5.0+
- **PHP**: 7.4+
- **MySQL**: 5.6+
- **Memory**: 128MB+
- **Extensions**: cURL, JSON, OpenSSL

---

*This documentation covers the complete Hourly Room Booking System as of the current version. For updates and additional features, please refer to the latest version of this document.*
