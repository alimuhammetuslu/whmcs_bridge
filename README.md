# Perfex CRM to WHMCS Bridge

**Version:** 1.0.0
**Author:** Muhammet Ali USLU
**License:** GPLv3
**Website:** https://teknolojivepsikoloji.com

## Overview
This module bridges the gap between Perfex CRM (Project Management/Sales) and WHMCS (Billing/Hosting Automation). It allows you to sell hosting services directly from Perfex CRM invoices and manage them within the client profile.

## Features
- **Client Sync:** Automatically maps Perfex clients to WHMCS clients via email.
- **Product Sync:** Import WHMCS hosting products into Perfex as "Items".
- **Invoice Integration:** Create invoices in Perfex that trigger WHMCS order & invoice creation.
- **Service Management:** Suspend, Unsuspend, Terminate services from Perfex Client Profile.
- **Payment Gateway:** Redirect Perfex payments to WHMCS payment pages seamlessly.

## Installation

1.  Download the latest release.
2.  Upload the `whmcs_bridge` folder to `modules/` directory in Perfex CRM.
3.  Go to **Setup > Modules** and activate **WHMCS Bridge**.
4.  Go to **Setup > Staff > Permissions** and grant view/edit permissions for the module to Administrator roles.

## Configuration

1.  Go to **WHMCS Bridge > Settings**.
2.  Enter your **WHMCS System URL** (e.g., `https://my.whmcs.com`).
3.  Enter **API Identifier** and **API Secret**.
    *   *To get these:* Go to WHMCS Admin > Setup > General Settings > API Credentials.
    *   Ensure the API Role has permissions for `GetClients`, `AddClient`, `AddOrder`, `GetProducts`, `ModuleCommand`.
    *   **Important:** Add your Perfex server IP to **Setup > General Settings > Security > API IP Access Restriction** in WHMCS.
4.  Set the **Default Payment Gateway** (e.g., `paytr`, `stripe`, `mailin`).

## Usage

### Syncing Products
1.  Go to **WHMCS Bridge > Sync Products**.
2.  Select the products you want to import.
3.  Choose a Perfex Item Group (e.g., "Hosting").
4.  Click **Import**.

### Selling Hosting
1.  Create a new Invoice in Perfex.
2.  Add a synced Hosting Item (ensure it belongs to a group named 'Hosting', 'Server', etc.).
3.  (Optional) Enter the domain name in the **Associated Domain** custom field.
4.  Save the invoice.
5.  The system will automatically create the order in WHMCS.

## License
This project is licensed under the GNU General Public License v3.0 (GPLv3).
Derived works must also be open source.
