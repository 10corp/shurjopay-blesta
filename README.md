# shurjopay-blesta
Here’s the complete guide for integrating the **ShurjoPay Module for Blesta** with all the updated information:

---

## **ShurjoPay Module for Blesta**

### **Versions**
- Tested on Blesta 5.10.3 (or later)
- PHP 8.2 or later

The ShurjoPay module for Blesta is simple to install and fully customizable. It allows businesses to securely accept payments via ShurjoPay, enabling transactions with multiple payment methods.

This tutorial assumes you already have a **ShurjoPay account**. If not, please sign up at [ShurjoPay's website](https://shurjopay.com.bd/?aff=10corp.com).

---
composer require 10corp/shurjopay-blesta

### **Download the Plugin**
The ShurjoPay plugin for Blesta can be downloaded [here](#) (provide the link to your plugin repository).

---

### **Project Configuration in the ShurjoPay System**

1. **Sign in to your ShurjoPay account**:  
   Log in to your ShurjoPay dashboard.

2. **Get API Information**:  
   Obtain the following credentials required for integration:  
   - **API Username**  
   - **API Password**  
   - **Merchant Prefix**

3. **Set Notification/Callback URL**:  
   Configure the **Callback URL** for your project as follows:  
   ```
   http://[your-domain]/[blesta_folder]/callback/gw/[company_id]/shurjopay/
   ```
4. Save your project configuration in the ShurjoPay system.

---

### **Setup ShurjoPay Module on Blesta**

1. **Upload the Plugin**:
   - Unpack all files from `shurjopay-module-blesta.zip`.
   - Upload the content of the `upload` folder to your Blesta root folder using any FTP client.

2. **Install the Gateway**:
   - In your Blesta dashboard, click **Settings** on the top-right navigation menu and choose **Payment Gateways**.
   - From the left sidebar, choose **Available** under the Payment Gateways section to list all available gateways.
   - Locate the ShurjoPay module and click **Install**.

3. **Configure the Gateway**:
   - Enter the required fields, such as:
     - **API Username**  
     - **API Password**  
     - **Merchant Prefix**  
     - **Callback URL**:  
       ```
       http://[your-domain]/[blesta_folder]/callback/gw/[company_id]/shurjopay/
       ```
     - Any additional fields needed by ShurjoPay.
   - Click **Save** to complete the configuration.

---

### **Test Your Integration**

1. Log in as a test user and create an invoice in Blesta.
2. Attempt to pay using ShurjoPay.
3. Ensure the transaction completes successfully and the callback updates the payment status.

---

### **Version Support**
ShurjoPay provides support for the following Blesta versions:

| **Blesta Version** | **Support** |
|---------------------|-------------|
| 4                  | No         |
| 5                  | Yes         |

---

### **Support**
If you encounter any issues or need assistance, contact **cs(at)10corp.com**.