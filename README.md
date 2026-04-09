
<p align="center">
  <img 
    src="https://raw.githubusercontent.com/vincentoriccreates/inventory_system/main/images/Barcode%20Inventory%20Management%20System.png" 
    alt="InventoryPro Project Thumbnail" 
    width="100%"
  >
</p>

# 📦 InventoryPro — Barcode Inventory Management System

A complete, production-ready PHP + MySQL Inventory Management System with POS barcode scanning, role-based access control, real-time inventory tracking, and sales analytics.

## 🚀 QUICK START

### Requirements
- PHP 7.4+ (8.x recommended)
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx with mod_rewrite (or PHP built-in server)
- A USB barcode scanner (HID keyboard emulation — no driver needed)

---

## ⚙️ INSTALLATION

### Step 1 — Copy Files
Upload the entire `inventory_system/` folder to your web server root (e.g., `/var/www/html/inventory_system/` or `C:/xampp/htdocs/inventory_system/`).

### Step 2 — Create the Database
Open phpMyAdmin or MySQL CLI and run:
```sql
SOURCE /path/to/inventory_system/config/database.sql;
```
Or paste the contents of `config/database.sql` into phpMyAdmin's SQL tab.

### Step 3 — Configure Database Connection
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');   // Your MySQL host
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');            // Your MySQL password
define('DB_NAME', 'inventory_system');
```

### Step 4 — Launch
Navigate to: `http://localhost/inventory_system/`

---

## 🔐 DEFAULT LOGIN CREDENTIALS

| Role    | Username  | Password   |
|---------|-----------|------------|
| Admin   | `admin`   | `password` |
| Staff   | `staff`   | `password` |
| Cashier | `cashier` | `password` |

> ⚠️ **Change all passwords immediately after first login!**
> Go to **Settings → Change My Password**

---

## 📱 FEATURES

### 🏷️ Item Master
- Full CRUD for products
- Barcode-based product registry (EAN-13 / Code-128 compatible)
- Category, unit, supplier, cost, selling price, reorder level
- Auto profit margin calculation

### 📥 Stock In (Purchase Receiving)
- **Barcode scan → instant auto-fill** (item name, cost, category)
- Auto-generate Reference No: `PO-YYYYMMDD-XXX`
- Batch mode: multiple items per PO
- Running total calculation
- Full history with search

### 🛒 POS / Sales Module
- **POS-style fast scanning interface**
- Barcode scan → auto-add to cart with instant pricing
- Live cart with qty adjustment and removal
- Auto-generate Invoice No: `INV-YYYYMMDD-XXX`
- Auto-compute: total sales, unit cost, profit per line
- **Printable receipt** (optimized for 80mm thermal printer)
- Full sales history

### 📊 Inventory Ledger
- Real-time stock: `current = total_in - total_out`
- Inventory value per item
- Color-coded stock alerts (OK / Low Stock / Out of Stock)
- Filter by category and status

### 📈 Reports (Admin Only)
- Date range filter
- Total Sales, Cost, Profit, Margin
- Daily sales chart (Chart.js)
- Sales by category (doughnut chart)
- Top 10 best sellers
- Daily breakdown table
- Printable reports

### 👥 User Management (Admin Only)
- Add/Edit/Delete users
- Role assignment (Admin / Staff / Cashier)
- Activate/deactivate accounts
- Secure bcrypt password hashing

### 🏪 Suppliers
- Supplier directory with contact details
- Link to items by supplier name

### ⚙️ Settings
- Store name, address, contact
- Currency symbol
- Low stock threshold
- Change own password

---

## 🔒 ROLE-BASED ACCESS CONTROL

| Feature           | Admin | Staff | Cashier |
|-------------------|:-----:|:-----:|:-------:|
| Dashboard         | ✅    | ✅    | ✅      |
| Item Master       | ✅ Full | ✅ View | ❌    |
| Stock In          | ✅    | ✅    | ❌      |
| Inventory         | ✅    | ✅    | ❌      |
| POS / Sales       | ✅    | ❌    | ✅      |
| Reports           | ✅    | ❌    | ❌      |
| User Management   | ✅    | ❌    | ❌      |
| Suppliers         | ✅    | ❌    | ❌      |
| Settings          | ✅    | ❌    | ❌      |

---

## 🔍 BARCODE SCANNER SETUP

1. **Connect** your USB barcode scanner (plug-and-play, no driver needed)
2. **Open** Stock In or Sales page
3. **Click** the barcode input field (it auto-focuses on page load)
4. **Scan** — the scanner types the barcode and sends ENTER automatically
5. Item details **auto-fill instantly** via AJAX

> Works with any HID-mode USB barcode scanner.  
> Compatible with EAN-13, Code-128, QR codes, and most barcode formats.

---

## 🖨️ RECEIPT PRINTING

1. Complete a sale in the POS module
2. Click **Print Receipt** button
3. Use browser print dialog
4. For thermal printers: set paper width to 80mm, no margins

---

## 📁 FILE STRUCTURE

```
inventory_system/
├── index.php              ← Login page
├── logout.php             ← Logout handler
├── dashboard.php          ← Main dashboard
├── items.php              ← Item Master CRUD
├── stock_in.php           ← Stock In (Purchase)
├── sales.php              ← POS / Sales module
├── inventory.php          ← Inventory ledger
├── reports.php            ← Analytics & reports
├── users.php              ← User management
├── suppliers.php          ← Supplier directory
├── settings.php           ← System settings
│
├── config/
│   ├── database.php       ← DB connection
│   └── database.sql       ← Full DB schema + sample data
│
├── includes/
│   ├── auth.php           ← Authentication & session
│   ├── functions.php      ← Helper functions
│   ├── header.php         ← HTML header + sidebar
│   └── footer.php         ← HTML footer
│
├── ajax/
│   ├── lookup_barcode.php ← Barcode AJAX lookup
│   ├── save_stock_in.php  ← AJAX stock save
│   └── get_ref.php        ← AJAX reference generator
│
└── assets/
    ├── css/style.css      ← Full responsive stylesheet
    └── js/app.js          ← Core JavaScript
```

---

## 🛡️ SECURITY FEATURES

- **bcrypt password hashing** (cost factor 12)
- **PDO prepared statements** (SQL injection prevention)
- **Session-based authentication** with session regeneration on login
- **Role-based page restriction** — server-side enforcement
- **XSS protection** via `htmlspecialchars()` on all output
- CSRF protection via session validation

---

## 💡 TIPS FOR TABLET USE

- Use Chrome or Firefox in **fullscreen / kiosk mode**
- For POS: pair with a **Bluetooth barcode scanner**
- Set browser to remember the URL
- Enable "Add to Home Screen" for app-like experience
- Recommended resolution: 1024×768 or higher

---

## 🔧 TROUBLESHOOTING

**"Database connection failed"**
→ Check `config/database.php` credentials match your MySQL setup

**Barcode not auto-filling**
→ Ensure JavaScript is enabled and the barcode exists in Item Master

**Blank page / errors**
→ Enable PHP error reporting: add `ini_set('display_errors', 1);` to `config/database.php`

**Session issues**
→ Ensure `session_start()` is called before any output

---

## 📋 SAMPLE DATA

The database includes 18 pre-loaded items across 4 categories:
- **Hardware**: PVC pipes, cement, GI wire, paint brushes, electrical tape
- **Grocery**: Rice, sardines, cooking oil, sugar, instant noodles
- **Resort Supplies**: Shampoo sachets, bath soap, mineral water, tissue
- **Beverages**: Soft drinks, beer, energy drinks, bottled water

Plus sample stock-in records and sales transactions for testing.

---

## 📞 SUPPORT

For issues or customization, refer to the inline code comments throughout the PHP files.

---

*Built with PHP, MySQL, Bootstrap-inspired CSS, Chart.js, and vanilla JavaScript.*
*Designed for Filipino small businesses and resort operations.*
