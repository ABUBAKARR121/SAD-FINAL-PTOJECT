Project Description

This system is a digital platform for managing market stalls and daily dues collection in Freetown, Sierra Leone. It replaces paper based record keeping with a web application that supports trader registration, stall assignment, payment collection, and financial reporting. The system integrates with Orange Money and Africell Money for mobile payments.

Installation

You need a web server with PHP 8.0 or higher and MySQL 5.7 or higher. XAMPP or WAMP is recommended.

Step 1. Place all project files in a folder called market-ops inside your web server document root directory. For XAMPP this is the htdocs folder. For WAMP this is the www folder.

Step 2. Start Apache and MySQL from the control panel.

Step 3. Open a web browser and go to http://localhost/market-ops/install.php

Step 4. The installation page will create the database, tables, and default user accounts automatically.

Step 5. After installation you will see a confirmation message with login details. Click the link to go to the login page.

Default Login Credentials

Administrator account
Username: admin
Password: admin123

Inspector account
Username: inspector
Password: admin123

Usage

After logging in you can access the following features from the sidebar menu.

Dashboard shows summary statistics and quick action buttons for common tasks.
Traders page lists all registered traders with a search function.
Add Trader page allows you to register a new trader and assign a stall.
Collect Dues page is for recording daily payments using cash, mobile money, or bank transfer.
Payments page displays payment history with date and market filters.
Reports page provides financial reports with options to download as Excel or print as PDF.

Mobile Money Setup

The system comes with simulation mode enabled for testing. To use real mobile money payments you need to register as a merchant with Orange Money Sierra Leone and Africell Money Sierra Leone. After receiving your API credentials edit the file named mobile-money-config.php and replace the placeholder values. Set SIMULATION_MODE to false when ready for live transactions.

License

This project is released under the MIT License. You can freely use, modify, and distribute this software. See the LICENSE file for full details.
