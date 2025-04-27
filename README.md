# **ShurjoPay Module for Blesta**  
**Version:** 2.0  
**Author:** [10Corp](https://10corp.com)  
**Powered by:** [shurjoMukhi Ltd.](https://shurjomukhi.com.bd/)

---

## **Overview**
The ShurjoPay module for Blesta is simple to install and fully customizable. It allows businesses to securely accept payments via ShurjoPay, enabling transactions with multiple payment methods.

✅ Tested on **Blesta 5.11.2+**  
✅ Compatible with **PHP 8.3+**

If you don't have a ShurjoPay account yet, you can [sign up here](https://shurjopay.com.bd/?aff=10corp.com).

---

## **Installation**

### 1. Install via Composer

```bash
composer require 10corp/shurjopay-blesta
```

### 2. Download the Plugin

You can manually download the ShurjoPay plugin for Blesta [here](#) (replace `#` with your plugin repository link).

---

## **Project Configuration in ShurjoPay System**

1. **Log in** to your [ShurjoPay dashboard](https://merchant.shurjopay.com.bd/login).
2. **Obtain API Credentials**:
   - API Username
   - API Password
   - Merchant Prefix
3. **Set the Callback URL**:
   ```bash
   http://[your-domain]/[blesta_folder]/callback/gw/[company_id]/shurjopay/
   ```
4. Save your project configuration.

---

## **Setup ShurjoPay Module on Blesta**

### 1. Upload the Plugin
- Extract `shurjopay-module-blesta.zip`.
- Upload the `upload/` folder contents to your Blesta installation root via FTP.

### 2. Install the Payment Gateway
- Log into the Blesta admin panel.
- Navigate:  
  **Settings** → **Payment Gateways** → **Available**.
- Find **ShurjoPay** and click **Install**.

### 3. Configure the Gateway
Fill out the required fields:
- API Username
- API Password
- Merchant Prefix
- Callback URL:
  ```bash
  http://[your-domain]/[blesta_folder]/callback/gw/[company_id]/shurjopay/
  ```
- (Optional) Set any extra configuration fields if needed.

Click **Save** to complete.

---

## **Testing the Integration**

1. Create a test invoice as a user in Blesta.
2. Choose ShurjoPay as the payment method.
3. Complete the transaction.
4. Verify that payment status updates automatically via the callback.

---

## **Blesta Version Support**

| **Blesta Version** | **Support Status** |
|---------------------|---------------------|
| 4.x                 | ❌ Not Supported |
| 5.x                 | ✅ Supported |

---

## **Support**

If you encounter any issues or need assistance:  
📧 **Email:** cs@10corp.com
